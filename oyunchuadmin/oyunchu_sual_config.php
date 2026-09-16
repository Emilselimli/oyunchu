<?php
/**
 * ============================================================
 *  Oyunların Sualları — Admin Panel Konfiqurasiyası
 *  Birdən çox oyunun (Sual Duellosu, Tele Time və s.) ORTAQ sual bankını
 *  (es_quiz_questions) idarə edir. eSevgili-dən TAM ayrılıb, öz sessiyası
 *  giriş səhifəsi (auth/oyunchu_login.php) var. Admin
 *  girişi TELE-SMART-ın tək, ortaq `admin_users` cədvəlindən
 *  keçir (bax: esevgili_config.php-dəki eyni prinsip) — ona görə
 *  mövcud adminlər (məs. `emilse`) əlavə heç nə etmədən bu panelə
 *  də daxil ola bilir.
 * ============================================================
 */

// TELE-SMART əsas admin panelinin ortaq funksiyaları
// (admin_panel_favicon_tag(), admin_page_header() və s.) üçün.
require_once __DIR__ . '/../config.php';

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

date_default_timezone_set('Asia/Baku');

$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
         || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_lifetime', 86400);
    ini_set('session.cookie_secure', $is_https ? 1 : 0);
    ini_set('session.cookie_samesite', $is_https ? 'Strict' : 'Lax');
    session_start();
}

// ── .env yüklə (TeleSmart-ın ortaq .env-i, digər panellərlə EYNİ) ─────
$envPath = (function () {
    if (($__p = getenv('CENTRAL_ENV_PATH')) && is_file($__p)) return $__p;
    $__dir = __DIR__;
    for ($__i = 0; $__i < 10; $__i++) {
        if (is_file($__dir . '/db.env/.env')) return $__dir . '/db.env/.env';
        $__parent = dirname($__dir);
        if ($__parent === $__dir) break;
        $__dir = $__parent;
    }
    return __DIR__ . '/../.env';
})();
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k); $v = trim($v);
            if ($k !== '' && !isset($_ENV[$k])) { $_ENV[$k] = $v; putenv("$k=$v"); }
        }
    }
}

$DB_HOST = $_ENV['DB_HOST'] ?? 'localhost';
$DB_USER = $_ENV['DB_USER'] ?? 'root';
$DB_PASS = $_ENV['DB_PASSWORD'] ?? '';
$DB_NAME = $_ENV['DB_NAME'] ?? 'telesmart';

function db(): PDO {
    static $pdo = null;
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    if ($pdo === null) {
        $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('Oyunların Sualları Admin DB error: ' . $e->getMessage());
            http_response_code(500);
            die('Verilənlər bazasına (telesmart) bağlanmaq mümkün olmadı.');
        }
    }
    return $pdo;
}

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ── Settings (açar/dəyər) — TÜM EKOSİSTEMLƏ ORTAQ es_settings cədvəli ──
// Bu, games/sualduellosu/config.php-dəki getSetting()/setSetting() ilə
// EYNİ cədvəldir (skey/svalue) — burada dəyişən (quiz_duello_name,
// quiz_duello_logo) oyunda ANINDA görünür.
function sdEnsureSettingsTable(): void {
    static $done = false;
    if ($done) return;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS es_settings (
            skey   VARCHAR(100) NOT NULL PRIMARY KEY,
            svalue TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $done = true;
    } catch (Exception $e) {}
}

function getSetting(string $key, $default = '') {
    sdEnsureSettingsTable();
    try {
        $st = db()->prepare("SELECT svalue FROM es_settings WHERE skey=? LIMIT 1");
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return ($v === false || $v === null || $v === '') ? $default : $v;
    } catch (Exception $e) { return $default; }
}

function setSetting(string $key, $value): void {
    sdEnsureSettingsTable();
    try {
        db()->prepare("INSERT INTO es_settings (skey, svalue) VALUES (?, ?)
                       ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)")
            ->execute([$key, $value]);
    } catch (Exception $e) {}
}

// ── CSRF ───────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function sd_csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h($_SESSION['csrf_token']) . '">';
}
function sd_csrf_check(): bool {
    $t = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    return !empty($t) && hash_equals($_SESSION['csrf_token'], $t);
}

// ── Autentifikasiya (Sual Duellosu-nun ÖZ sessiyası, ortaq admin_users) ─
const SD_SESSION_TIMEOUT = 14400; // 4 saat
const SD_LOGIN_URL       = '/telesmartadmin/auth/oyunchu_login.php';

function requireAdmin(): array {
    // Əsas TeleSmart admin panelindən artıq daxil olan admin varsa (admin_logged_in),
    // Oyunçu bölməsinə görə ayrıca giriş tələb etmə — sessiyaları körpüləyirik ki,
    // bütün səhifələr arasında keçid fasiləsiz olsun.
    if (empty($_SESSION['sd_admin_id']) && !empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id'])) {
        $_SESSION['sd_admin_id']   = (int)$_SESSION['admin_id'];
        $_SESSION['sd_admin_name'] = $_SESSION['admin_full_name'] ?? $_SESSION['admin_username'] ?? null;
        $_SESSION['sd_login_time'] = time();
    }

    if (isset($_SESSION['sd_login_time']) &&
        (time() - $_SESSION['sd_login_time']) > SD_SESSION_TIMEOUT) {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . SD_LOGIN_URL . '?timeout=1');
        exit();
    }

    if (empty($_SESSION['sd_admin_id'])) {
        header('Location: ' . SD_LOGIN_URL);
        exit();
    }

    try {
        $st = db()->prepare("SELECT * FROM admin_users WHERE id=? AND is_active=1 LIMIT 1");
        $st->execute([(int)$_SESSION['sd_admin_id']]);
        $u = $st->fetch();
    } catch (Exception $e) {
        $u = false;
    }

    if (!$u) {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . SD_LOGIN_URL);
        exit();
    }

    $_SESSION['sd_login_time'] = time();
    $u['name'] = $u['username'] ?? ($u['email'] ?? 'Admin');
    $u['is_admin'] = 1;
    return $u;
}

/** Login: istifadəçi adı/e-poçt + şifrə — ortaq `admin_users` cədvəlinə qarşı. */
function sdVerifyAdmin(string $login, string $password): ?array {
    try {
        $st = db()->prepare("SELECT * FROM admin_users WHERE (username=? OR email=?) AND is_active=1 LIMIT 1");
        $st->execute([$login, $login]);
        $u = $st->fetch();
    } catch (Exception $e) {
        return null;
    }
    if (!$u) return null;

    $hash = $u['password'] ?? null;
    if ($hash === null || $hash === '') return null;

    $info = password_get_info($hash);
    if (!empty($info['algo'])) {
        $ok = password_verify($password, $hash);
    } elseif (preg_match('/^[a-f0-9]{32}$/i', $hash)) {
        $ok = hash_equals(strtolower($hash), md5($password));
    } elseif (preg_match('/^[a-f0-9]{40}$/i', $hash)) {
        $ok = hash_equals(strtolower($hash), sha1($password));
    } else {
        $ok = hash_equals((string)$hash, $password);
    }
    if (!$ok) return null;

    try { db()->prepare("UPDATE admin_users SET last_login=NOW() WHERE id=?")->execute([$u['id']]); } catch (Exception $e) {}

    $u['name'] = $u['username'] ?? ($u['email'] ?? 'Admin');
    $u['is_admin'] = 1;
    return $u;
}

// ── Admin fəaliyyət loqu — bütün panellərlə ORTAQ cədvəl (es_admin_audit_log) ──
function sdAuditLog(string $action, string $targetType = '', $targetId = null, string $details = ''): void {
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS es_admin_audit_log (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_id    INT UNSIGNED NOT NULL,
            admin_name  VARCHAR(150) DEFAULT NULL,
            action      VARCHAR(60) NOT NULL,
            target_type VARCHAR(40) DEFAULT NULL,
            target_id   VARCHAR(60) DEFAULT NULL,
            details     TEXT,
            ip_address  VARCHAR(45) DEFAULT NULL,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin (admin_id),
            INDEX idx_target (target_type, target_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $adminId   = (int)($_SESSION['sd_admin_id'] ?? 0);
        $adminName = $_SESSION['sd_admin_name'] ?? null;

        db()->prepare("INSERT INTO es_admin_audit_log (admin_id,admin_name,action,target_type,target_id,details,ip_address,created_at)
                        VALUES (?,?,?,?,?,?,?,NOW())")
            ->execute([
                $adminId, $adminName, $action, $targetType,
                $targetId !== null ? (string)$targetId : null,
                $details, $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
    } catch (Exception $e) {}
}
