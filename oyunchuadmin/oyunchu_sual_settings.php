<?php
require_once __DIR__ . '/oyunchu_sual_config.php';
$admin = requireAdmin();

// ── Şəkil yükləmə infrastrukturu (loqo + giriş fonu üçün ortaq) ────────
$brandImgDir = rtrim((defined('IMG_DIR') ? IMG_DIR : (getenv('IMG_DIR') ?: '')) ?: (($_SERVER['DOCUMENT_ROOT'] ?? '') . '/telesmart-img'), '/');
if ($brandImgDir === '/telesmart-img' || $brandImgDir === '') $brandImgDir = '/var/www/html/telesmart-img';
$brandImgUrl = rtrim((defined('IMG_URL') ? IMG_URL : (getenv('IMG_URL') ?: '/telesmart-img')), '/');
$quizBrandDir = $brandImgDir . '/icons/branding';
$quizBrandUrl = $brandImgUrl . '/icons/branding';
const SD_UPLOAD_MAX_BYTES = 2 * 1024 * 1024; // 2MB
$SD_UPLOAD_ALLOWED_EXT = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'svg' => 'image/svg+xml', 'webp' => 'image/webp'];
$sdUploadError = '';

/** Loqonu real server fayl sisteminə yazır və public server-nisbi URL qaytarır. */
function sdqHandleUpload(string $field, string $prefix, string $dir, string $url, array $allowedExt): ?string {
    global $sdUploadError;
    $sdUploadError = '';
    if (empty($_FILES[$field]) || !isset($_FILES[$field]['error'])) return null;
    $f = $_FILES[$field];
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'Fayl serverin upload limitini keçir.',
        UPLOAD_ERR_FORM_SIZE => 'Fayl icazə verilən ölçüdən böyükdür.',
        UPLOAD_ERR_PARTIAL => 'Fayl tam yüklənmədi.',
        UPLOAD_ERR_NO_FILE => '',
        UPLOAD_ERR_NO_TMP_DIR => 'Serverdə müvəqqəti upload qovluğu yoxdur.',
        UPLOAD_ERR_CANT_WRITE => 'Server faylı diskə yaza bilmir.',
        UPLOAD_ERR_EXTENSION => 'PHP upload əlavəsi faylı dayandırdı.',
    ];
    $code = (int)$f['error'];
    if ($code !== UPLOAD_ERR_OK) { $sdUploadError = $errors[$code] ?? ('Fayl yükləmə xətası: ' . $code); return null; }
    if (!is_uploaded_file($f['tmp_name'])) { $sdUploadError = 'Yüklənmiş fayl server tərəfindən təsdiqlənmədi.'; return null; }
    if ((int)$f['size'] <= 0 || (int)$f['size'] > SD_UPLOAD_MAX_BYTES) { $sdUploadError = 'Fayl boşdur və ya 2MB limitini keçir.'; return null; }

    $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
    if (!isset($allowedExt[$ext])) { $sdUploadError = 'Bu fayl formatına icazə verilmir. PNG, JPG, SVG və ya WebP istifadə edin.'; return null; }

    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? finfo_file($finfo, $f['tmp_name']) : null;
    if ($finfo) finfo_close($finfo);
    if ($ext !== 'svg' && $mime && !in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
        $sdUploadError = 'Faylın MIME tipi şəkil formatına uyğun deyil.'; return null;
    }
    if ($ext !== 'svg' && @getimagesize($f['tmp_name']) === false) {
        $sdUploadError = 'Fayl etibarlı şəkil deyil.'; return null;
    }

    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_dir($dir)) { $sdUploadError = 'Loqo qovluğu yaradıla bilmədi: ' . $dir; return null; }
    if (!is_writable($dir)) { @chmod($dir, 0775); }
    if (!is_writable($dir)) { $sdUploadError = 'Loqo qovluğuna yazma icazəsi yoxdur: ' . $dir; return null; }

    $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix) ?: 'game';
    $filename = $safePrefix . '_logo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = rtrim($dir, '/') . '/' . $filename;
    if (!move_uploaded_file($f['tmp_name'], $target)) { $sdUploadError = 'Fayl serverdə saxlanıla bilmədi.'; return null; }
    @chmod($target, 0644);
    return rtrim($url, '/') . '/' . $filename;
}

/** Server-nisbi olmayan (tam domen/IP-li) /telesmart-img/ URL-ini avtomatik server-nisbi yola çevirir. */
function sdqNormalizeAssetUrl(string $val): string {
    if (preg_match('#^https?://[^/]+(/telesmart-img/.*)$#i', $val, $m)) return $m[1];
    return $val;
}

function sdqLogoPreviewHtml(string $logo): string {
    if (preg_match('#^(https?://|/)#i', $logo)) {
        return '<img src="' . htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') . '" style="height:2.2rem;width:auto;object-fit:contain">';
    }
    return '<span style="font-size:2.2rem">' . htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') . '</span>';
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_branding') {        $gameMap = [
            'sualduellosu' => ['name_key' => 'quiz_duello_name', 'logo_key' => 'quiz_duello_logo', 'default' => 'Sual Duellosu'],
            'teletime'     => ['name_key' => 'teletime_name',    'logo_key' => 'teletime_logo',    'default' => 'Tele Time'],
            'zencir'       => ['name_key' => 'zencir_name',      'logo_key' => 'zencir_logo',      'default' => 'Zəncir'],
            'milyoncu'     => ['name_key' => 'milyoncu_name',    'logo_key' => 'milyoncu_logo',    'default' => 'Milyonçu'],
            'suretturu'    => ['name_key' => 'suretturu_name',   'logo_key' => 'suretturu_logo',   'default' => 'Sürət Turu'],
            'oyunchu'      => ['name_key' => 'oyunchu_name',     'logo_key' => 'oyunchu_logo',     'default' => 'Oyunçu'],
            'sozutap'      => ['name_key' => 'sozutap_name',     'logo_key' => 'sozutap_logo',     'default' => 'Sözü Tap'],
            'kelmezencir'  => ['name_key' => 'kelmezencir_name', 'logo_key' => 'kelmezencir_logo', 'default' => 'Kəlmə Zənciri'],
            'sudoku'       => ['name_key' => 'sudoku_name',      'logo_key' => 'sudoku_logo',      'default' => 'Sudoku'],
            'sozyagishi'   => ['name_key' => 'sozyagishi_name',  'logo_key' => 'sozyagishi_logo',  'default' => 'Sürətli Söz Yağışı'],
            'sahmat'       => ['name_key' => 'sahmat_name',      'logo_key' => 'sahmat_logo',      'default' => 'Şahmat'],
        ];
        $game = $gameMap[$_POST['game'] ?? ''] ?? null;
        if ($game === null) {
            // Statik siyahıda yoxdursa, oh_games cədvəlindən avtomatik aşkarlanan
            // (dinamik) oyun ola bilər — həmin oyunların loqo/ad formaları da
            // eyni "save_branding" action-ına POST edir, sadəcə $gameMap-də yoxdur.
            $slug = (string)($_POST['game'] ?? '');
            if ($slug !== '') {
                try {
                    $stmt = db()->prepare("SELECT name_key, logo_key, default_name FROM oh_games WHERE slug = ? AND is_active = 1 LIMIT 1");
                    $stmt->execute([$slug]);
                    $dgRow = $stmt->fetch();
                } catch (Throwable $e) { $dgRow = false; }
                if ($dgRow) {
                    $game = ['name_key' => $dgRow['name_key'], 'logo_key' => $dgRow['logo_key'], 'default' => $dgRow['default_name']];
                }
            }
        }
        if ($game === null) { $msg = '❌ Naməlum oyun.'; }
        else {
            $nameKey = $game['name_key']; $logoKey = $game['logo_key'];

            $name = trim($_POST[$nameKey] ?? '');
            if ($name === '') $name = $game['default'];
            setSetting($nameKey, $name);

            $uploadedLogo = sdqHandleUpload($logoKey . '_file', $_POST['game'], $quizBrandDir, $quizBrandUrl, $SD_UPLOAD_ALLOWED_EXT);
            if ($uploadedLogo) {
                setSetting($logoKey, $uploadedLogo);
            } elseif ($sdUploadError !== '') {
                $msg = '❌ Loqo yüklənmədi: ' . $sdUploadError;
            } elseif (trim($_POST[$logoKey . '_value'] ?? '') !== '') {
                setSetting($logoKey, sdqNormalizeAssetUrl(trim($_POST[$logoKey . '_value'])));
            }

            // "Oyunçu" hub üçün əlavə olaraq favicon da idarə olunur (digər oyunlarda favicon yoxdur).
            // sdqHandleUpload() daxilindəki MIME yoxlaması yalnız png/jpg/webp/svg üçün nəzərdə tutulub,
            // ona görə favicon üçün də loqo ilə EYNİ $SD_UPLOAD_ALLOWED_EXT istifadə olunur (.ico verilmir).
            if ($_POST['game'] === 'oyunchu') {
                $uploadedFavicon = sdqHandleUpload('oyunchu_favicon_file', 'oyunchu_favicon', $quizBrandDir, $quizBrandUrl, $SD_UPLOAD_ALLOWED_EXT);
                if ($uploadedFavicon) {
                    setSetting('oyunchu_favicon', $uploadedFavicon);
                } elseif ($sdUploadError !== '') {
                    $msg = '❌ Favicon yüklənmədi: ' . $sdUploadError;
                } elseif (trim($_POST['oyunchu_favicon_value'] ?? '') !== '') {
                    setSetting('oyunchu_favicon', sdqNormalizeAssetUrl(trim($_POST['oyunchu_favicon_value'])));
                }
            }

            sdAuditLog('game_branding_update', 'es_settings', $_POST['game'], 'Ad: ' . $name);
            $msg = '✅ "' . $name . '" görünüşü yeniləndi.';
        }
    }

    if ($action === 'save_login_bg') {
        $bgType = $_POST['login_bg_type'] ?? 'default';
        if (!in_array($bgType, ['default', 'color', 'image'], true)) $bgType = 'default';
        setSetting('quiz_duello_login_bg_type', $bgType);

        if ($bgType === 'color') {
            $color = trim($_POST['login_bg_color'] ?? '');
            if ($color !== '') setSetting('quiz_duello_login_bg_value', $color);
        } elseif ($bgType === 'image') {
            $uploadedBg = sdqHandleUpload('login_bg_image_file', 'loginbg', $quizBrandDir, $quizBrandUrl, $SD_UPLOAD_ALLOWED_EXT);
            if ($uploadedBg) {
                setSetting('quiz_duello_login_bg_value', $uploadedBg);
            } elseif (trim($_POST['login_bg_image_value'] ?? '') !== '') {
                setSetting('quiz_duello_login_bg_value', sdqNormalizeAssetUrl(trim($_POST['login_bg_image_value'])));
            }
        } else {
            setSetting('quiz_duello_login_bg_value', '');
        }
        sdAuditLog('quiz_login_bg_update', 'es_settings', 'quiz_duello_login_bg', 'Tip: ' . $bgType);
        $msg = '✅ Giriş səhifəsinin arxa fonu yeniləndi.';
    }

    if ($action === 'save_attempts') {
        $attemptsGameMap = [
            'zencir'       => 'zencir',
            'milyoncu'     => 'milyoncu',
            'suretturu'    => 'suretturu',
            'sualduellosu' => 'sualduellosu',
            'sudoku'       => 'sudoku',
            'sozyagishi'   => 'sozyagishi',
        ];
        $gameKey = $attemptsGameMap[$_POST['game'] ?? ''] ?? null;
        if ($gameKey === null) { $msg = '❌ Naməlum oyun.'; }
        else {
            $freeAttempts = max(1, (int)($_POST['free_attempts'] ?? 5));
            $cost1 = max(0, (int)($_POST['extra_cost_1'] ?? 60));
            $cost2 = max(0, (int)($_POST['extra_cost_2'] ?? 200));
            setSetting($gameKey . '_free_attempts', $freeAttempts);
            setSetting($gameKey . '_extra_cost_1', $cost1);
            setSetting($gameKey . '_extra_cost_2', $cost2);
            sdAuditLog('game_attempts_update', 'es_settings', $gameKey,
                "Pulsuz cəhd: $freeAttempts, 1-ci əlavə: $cost1 coin, 2-ci+ əlavə: $cost2 coin");
            $msg = "✅ \"$gameKey\" cəhd/qiymət qaydaları yeniləndi: $freeAttempts pulsuz cəhd, sonra $cost1 coin, sonra $cost2 coin.";
        }
    }

    if ($action === 'save_ads_config') {
        $provider = $_POST['oh_ads_provider'] ?? 'none';
        if (!in_array($provider, ['none', 'adsense', 'twa_admob'], true)) $provider = 'none';
        setSetting('oh_ads_provider', $provider);
        setSetting('oh_adsense_client', trim($_POST['oh_adsense_client'] ?? ''));
        setSetting('oh_adsense_slot_banner', trim($_POST['oh_adsense_slot_banner'] ?? ''));
        setSetting('oh_adsense_slot_rewarded', trim($_POST['oh_adsense_slot_rewarded'] ?? ''));
        setSetting('oh_admob_rewarded_unit', trim($_POST['oh_admob_rewarded_unit'] ?? ''));
        setSetting('oh_ad_reward_coins', max(1, (int)($_POST['oh_ad_reward_coins'] ?? 5)));
        setSetting('oh_ad_cooldown_min', max(1, (int)($_POST['oh_ad_cooldown_min'] ?? 3)));
        sdAuditLog('oh_ads_config_update', 'es_settings', 'oh_ads', 'Provider: ' . $provider);
        $msg = '✅ Reklam ayarları yeniləndi.';
    }

    header('Location: /telesmartadmin/oyunchu/oyunchu_sual_settings.php' . ($msg ? '?msg=' . urlencode($msg) : ''));
    exit;
}

if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

$quizBrandName = getSetting('quiz_duello_name', 'Sual Duellosu');
$quizBrandLogo = getSetting('quiz_duello_logo', '⚔️');
$ttBrandName   = getSetting('teletime_name', 'Tele Time');
$ttBrandLogo   = getSetting('teletime_logo', '📺');
$zcBrandName   = getSetting('zencir_name', 'Zəncir');
$zcBrandLogo   = getSetting('zencir_logo', '⛓️');
$mcBrandName   = getSetting('milyoncu_name', 'Milyonçu');
$mcBrandLogo   = getSetting('milyoncu_logo', '💰');
$srBrandName   = getSetting('suretturu_name', 'Sürət Turu');
$srBrandLogo   = getSetting('suretturu_logo', '⚡');
$ohBrandName   = getSetting('oyunchu_name', 'Oyunçu');
$ohBrandLogo   = getSetting('oyunchu_logo', '🎮');
$ohBrandFavicon = getSetting('oyunchu_favicon', '');
$szBrandName   = getSetting('sozutap_name', 'Sözü Tap');
$szBrandLogo   = getSetting('sozutap_logo', '🔤');
$kzBrandName   = getSetting('kelmezencir_name', 'Kəlmə Zənciri');
$kzBrandLogo   = getSetting('kelmezencir_logo', '🔗');
$suBrandName   = getSetting('sudoku_name', 'Sudoku');
$suBrandLogo   = getSetting('sudoku_logo', '🔢');
$syBrandName   = getSetting('sozyagishi_name', 'Sürətli Söz Yağışı');
$syBrandLogo   = getSetting('sozyagishi_logo', '🌧️');
$sahBrandName  = getSetting('sahmat_name', 'Şahmat');
$sahBrandLogo  = getSetting('sahmat_logo', '♞');
$adsProvider   = getSetting('oh_ads_provider', 'none');
$adsenseClient = getSetting('oh_adsense_client', '');
$adsenseSlotBanner = getSetting('oh_adsense_slot_banner', '');
$adsenseSlotRewarded = getSetting('oh_adsense_slot_rewarded', '');
$admobRewardedUnit = getSetting('oh_admob_rewarded_unit', '');
$adRewardCoins = (int)getSetting('oh_ad_reward_coins', 5);
$adCooldownMin = (int)getSetting('oh_ad_cooldown_min', 3);
$loginBgType   = getSetting('quiz_duello_login_bg_type', 'default');
$loginBgValue  = getSetting('quiz_duello_login_bg_value', '');

// ── Gündəlik cəhd limiti + coin qiymətləri (hər oyun üçün) ──────────────
function attemptsSettingsFor(string $key): array {
    return [
        'free' => (int)getSetting($key . '_free_attempts', 5),
        'c1'   => (int)getSetting($key . '_extra_cost_1', 60),
        'c2'   => (int)getSetting($key . '_extra_cost_2', 200),
    ];
}
$zcAttempts = attemptsSettingsFor('zencir');
$mcAttempts = attemptsSettingsFor('milyoncu');
$srAttempts = attemptsSettingsFor('suretturu');
$sdAttempts = attemptsSettingsFor('sualduellosu');
$suAttempts = attemptsSettingsFor('sudoku');
$syAttempts = attemptsSettingsFor('sozyagishi');

// ── Əlavə cəhd alışları (bütün 4 oyun) — son 80, kim/nə vaxt/nə qədər ───
$extraPurchaseTypes = ['zencir_extra' => 'Zəncir', 'milyoncu_extra' => 'Milyonçu', 'suretturu_extra' => 'Sürət Turu', 'duello_extra' => 'Sual Duellosu', 'sudoku_extra' => 'Sudoku'];
$extraPurchaseLog = [];
$extraPurchaseStatsToday = [];
try {
    $inTypes = "'" . implode("','", array_keys($extraPurchaseTypes)) . "'";
    $extraPurchaseLog = db()->query("SELECT ct.created_at, ct.amount, ct.type, ct.note, u.id AS uid, u.name, u.email
                                      FROM es_coin_tx ct LEFT JOIN users u ON u.id = ct.user_id
                                      WHERE ct.type IN ($inTypes)
                                      ORDER BY ct.id DESC LIMIT 80")->fetchAll();
    $rows = db()->query("SELECT type, COUNT(*) c, COALESCE(SUM(-amount),0) s FROM es_coin_tx WHERE type IN ($inTypes) AND DATE(created_at)=CURDATE() GROUP BY type")->fetchAll();
    foreach ($rows as $r) { $extraPurchaseStatsToday[$r['type']] = ['count' => (int)$r['c'], 'coins' => (int)$r['s']]; }
} catch (Exception $e) {}

function h2($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function attemptsCardHtml(string $gameKey, string $title, array $settings, string $note = '', string $accent = ''): void {
?>
    <details class="attempts-drawer" data-accent="<?=h2($accent)?>">
      <summary>🎯 Gündəlik cəhd limiti və coin qiymətləri — <?=(int)$settings['free']?> pulsuz / gün</summary>
      <div class="drawer-body">
        <div class="field-hint" style="margin-bottom:14px">
          Hər istifadəçi gündə <b><?=(int)$settings['free']?></b> pulsuz cəhd oynayır<?= $note ? ' (' . h2($note) . ')' : '' ?>.
          Bitdikdən sonra 1-ci əlavə cəhd <b><?=(int)$settings['c1']?> coin</b>, 2-ci və hər sonrakı əlavə cəhd
          <b><?=(int)$settings['c2']?> coin</b>-dir.
        </div>
        <form method="POST" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
          <input type="hidden" name="action" value="save_attempts">
          <input type="hidden" name="game" value="<?=h2($gameKey)?>">
          <div>
            <label>Gündəlik pulsuz cəhd sayı</label>
            <input type="text" name="free_attempts" value="<?=(int)$settings['free']?>" style="width:120px" required>
          </div>
          <div>
            <label>1-ci əlavə cəhd (coin)</label>
            <input type="text" name="extra_cost_1" value="<?=(int)$settings['c1']?>" style="width:120px" required>
          </div>
          <div>
            <label>2-ci və sonrakı əlavə cəhd (coin)</label>
            <input type="text" name="extra_cost_2" value="<?=(int)$settings['c2']?>" style="width:150px" required>
          </div>
          <button type="submit" class="btn-save" style="margin-bottom:0">Yadda saxla</button>
        </form>
      </div>
    </details>
<?php
}


// ── Dinamik oyun görünüşləri ─────────────────────────────────────────────
// oh_games-də olan və yuxarıdakı klassik bloklarda olmayan oyunlar burada avtomatik görünür.
$knownBrandSlugs = ['sualduellosu','teletime','zencir','milyoncu','suretturu','oyunchu','sozutap','kelmezencir','sudoku','sozyagishi','sahmat'];
$dynamicBrandGames = [];
try {
    $dynamicBrandGames = db()->query("SELECT id,slug,name_key,logo_key,default_name,default_logo,path,is_active FROM oh_games WHERE is_active=1 ORDER BY sort_order,id")->fetchAll();
} catch (Throwable $e) { $dynamicBrandGames = []; }

$pageActive = 'settings';
?>
<!DOCTYPE html><html lang="az" data-theme="dark"><head>
<meta charset="UTF-8"><?= admin_panel_favicon_tag(); ?><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tənzimləmələr – Oyunların Sualları Admin</title>
<script>const t=localStorage.getItem('esevgili_theme')||'light';document.documentElement.setAttribute('data-theme',t);</script>
<link rel="stylesheet" href="/telesmartadmin/oyunchu/oyunchu_admin.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&display=swap">
<style>
/* ── Tənzimləmələr — "Oyun Rəfi" dizaynı ─────────────────────────────────
   Hər oyun kartı, sistemdə (oh_games.color) artıq mövcud olan öz rənginə
   uyğun bir vurğu ilə fərqləndirilir — rəfdəki hər kaset öz rəngi ilə
   dərhal tanınır. Başlıqlar Sora, mətn Inter (əsas panel fontu) ilə yazılır.
   [data-accent] hər hansı elementə həmin oyunun --card-accent dəyərini verir. */
[data-accent="duello"]      { --card-accent:#c8506a; }
[data-accent="teletime"]    { --card-accent:#3498db; }
[data-accent="zencir"]      { --card-accent:#e67e22; }
[data-accent="milyoncu"]    { --card-accent:#c9a84c; }
[data-accent="suretturu"]   { --card-accent:#e94560; }
[data-accent="hub"]         { --card-accent:#c8506a; }
[data-accent="sozutap"]     { --card-accent:#16a085; }
[data-accent="kelmezencir"] { --card-accent:#9b59b6; }
[data-accent="sudoku"]      { --card-accent:#2980b9; }
[data-accent="sozyagishi"]  { --card-accent:#00b4d8; }
[data-accent="sahmat"]      { --card-accent:#b08968; }
[data-accent="dynamic"]     { --card-accent:var(--text3); }
[data-accent="gold"]        { --card-accent:var(--gold); }

/* Hero — səhifənin başındakı tanıtım zolağı */
.settings-hero{
  background:linear-gradient(120deg,#241627,var(--surface) 65%);
  border:1px solid var(--border); border-radius:18px;
  padding:26px 28px; margin-bottom:20px;
}
.settings-hero h1{
  font-family:'Sora',sans-serif; font-size:1.3rem; font-weight:700;
  color:var(--text); margin-bottom:6px; letter-spacing:-.01em;
}
.settings-hero p{ color:var(--text2); font-size:.86rem; max-width:62ch; line-height:1.55; }
.settings-hero .hero-stats{ display:flex; gap:10px; margin-top:16px; flex-wrap:wrap; }
.hero-stat{
  background:rgba(200,80,106,.12); border:1px solid rgba(200,80,106,.28);
  border-radius:10px; padding:8px 14px; font-size:.78rem; color:var(--text2);
}
.hero-stat b{ color:var(--text); font-family:'Sora',sans-serif; font-size:.95rem; }

/* Rəf — çoxsütunlu (newspaper-style) yerləşdirmə: HTML strukturuna toxunmadan
   kartlar avtomatik iki sütuna bölünür, geniş bloklar (full-span) tam eni tutur. */
.content{ column-count:2; column-gap:22px; }
@media (max-width:980px){ .content{ column-count:1; } }
.full-span{ column-span:all; }

.form-card{
  break-inside:avoid; display:inline-block; width:100%;
  background:var(--surface);
  background:linear-gradient(160deg, color-mix(in srgb, var(--card-accent, var(--border)) 7%, var(--surface)), var(--surface) 60%);
  border:1px solid var(--border);
  border:1px solid color-mix(in srgb, var(--card-accent, var(--border)) 32%, var(--border));
  border-left:3px solid var(--card-accent, var(--border));
  border-radius:14px; padding:20px 22px; margin-bottom:20px;
}
.form-card.hero-hub{
  border-left-width:4px; padding:26px 26px 24px;
  background:linear-gradient(150deg, color-mix(in srgb, var(--card-accent) 16%, var(--surface)), var(--surface) 70%);
}
.form-card h3{
  font-family:'Sora',sans-serif; font-size:1rem; font-weight:700;
  letter-spacing:-.005em; margin-bottom:14px; display:flex; align-items:center; gap:9px;
}
.form-row{display:grid;gap:12px;margin-bottom:12px}
.form-row.cols2{grid-template-columns:1fr 1fr}
label{font-size:.74rem;font-weight:600;color:var(--text2);display:block;margin-bottom:5px}
input[type=text],input[type=color],select{width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;background:var(--input-bg);color:var(--text);font-size:.86rem;outline:none;transition:border-color .15s, box-shadow .15s}
input[type=text]:focus,input[type=color]:focus,select:focus{
  border-color:var(--card-accent, var(--rose));
  box-shadow:0 0 0 3px color-mix(in srgb, var(--card-accent, var(--rose)) 22%, transparent);
}
input[type=file]{width:100%;font-size:.8rem;color:var(--text2)}
.btn-save{padding:10px 22px;background:var(--card-accent, var(--rose));color:#fff;border:none;border-radius:10px;font-size:.85rem;font-weight:700;cursor:pointer;transition:opacity .15s, transform .12s}
.btn-save:hover{opacity:.88;transform:translateY(-1px)}
.btn-save:active{transform:translateY(0)}
.msg-bar{padding:12px 18px;border-radius:12px;margin-bottom:18px;font-size:.85rem;font-weight:700;background:#d4f5e2;color:#145a32;border:1px solid #a9dfbf}
.field-hint{font-size:.72rem;color:var(--text3);margin-top:4px;line-height:1.5}
.bg-preview{width:100%;height:90px;border-radius:12px;border:1.5px solid var(--border);background:#0a0a0a;display:flex;align-items:center;justify-content:center;margin-top:10px;background-size:cover;background-position:center}
.bg-type-row{display:flex;gap:8px;margin-bottom:14px}
.bg-type-pill{flex:1;padding:9px 6px;text-align:center;border-radius:10px;border:1.5px solid var(--border);background:var(--surface);font-size:.8rem;font-weight:700;color:var(--text2);cursor:pointer;transition:background .15s, border-color .15s}
.bg-type-pill.sel{background:var(--rose);border-color:var(--rose);color:#fff}
.bg-section{display:none}
.bg-section.show{display:block}

/* Cəhd/qiymət "çekmecəsi" — öz oyun kartının altına bitişik açılıb-bağlanan panel */
.attempts-drawer{
  break-inside:avoid; margin:-14px 0 20px; border-radius:0 0 13px 13px;
  border:1px solid color-mix(in srgb, var(--card-accent, var(--border)) 32%, var(--border));
  border-top:1px dashed color-mix(in srgb, var(--card-accent, var(--border)) 45%, var(--border));
  background:color-mix(in srgb, var(--card-accent, var(--border)) 4%, var(--bg));
}
.attempts-drawer summary{
  list-style:none; cursor:pointer; padding:11px 22px; display:flex; align-items:center; gap:8px;
  font-size:.78rem; font-weight:700; color:var(--card-accent, var(--text2));
}
.attempts-drawer summary::-webkit-details-marker{display:none}
.attempts-drawer summary::before{content:'▸'; transition:transform .15s; font-size:.7rem}
.attempts-drawer[open] summary::before{transform:rotate(90deg)}
.attempts-drawer .drawer-body{padding:2px 22px 18px}
</style>
</head><body>
<?php include __DIR__ . '/oyunchu_sual_sidebar.php'; ?>
<div class="main">

  <div class="topbar">
    <div class="topbar-title">⚙️ Tənzimləmələr</div>
    <div class="topbar-right">
      <button class="theme-btn" onclick="toggleTheme()">🌙</button>
      <span class="admin-chip">⚙ <?=h2($admin['name'])?></span>
    </div>
  </div>

  <div class="content">
    <?php $dynamicExtraCount = count(array_filter($dynamicBrandGames, fn($dg) => !in_array($dg['slug'], $knownBrandSlugs, true))); ?>
    <div class="settings-hero full-span">
      <h1>Oyun rəfi</h1>
      <p>Hər oyunun adı, loqosu və gündəlik cəhd/qiymət qaydaları bu səhifədən, kod dəyişmədən idarə olunur. Hər kartın rəngi həmin oyunun öz identifikasiya rənginə uyğundur.</p>
      <div class="hero-stats">
        <div class="hero-stat"><b><?= count($knownBrandSlugs) ?></b> sabit oyun</div>
        <?php if ($dynamicExtraCount > 0): ?><div class="hero-stat"><b><?= $dynamicExtraCount ?></b> avtomatik aşkarlanan oyun</div><?php endif; ?>
        <div class="hero-stat">Reklam, giriş fonu və alış tarixçəsi aşağıda</div>
      </div>
    </div>
    <?php if ($msg): ?><div class="msg-bar full-span"><?=$msg?></div><?php endif; ?>

    <!-- Oyun Görünüşü: Sual Duellosu -->
    <div class="form-card" data-accent="duello">
      <h3>⚔️ Sual Duellosu Görünüşü — "<?=h2($quizBrandName)?>"</h3>
      <div class="field-hint" style="margin-bottom:14px">Burada dəyişdiyiniz ad və loqo "Sual Duellosu" oyununun (giriş, qeydiyyat, üst panel, bildirişlər) BÜTÜN səhifələrində avtomatik tətbiq olunur.</div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_branding">
        <input type="hidden" name="game" value="sualduellosu">
        <div class="form-row cols2">
          <div>
            <label>Oyunun adı</label>
            <input type="text" name="quiz_duello_name" value="<?=h2($quizBrandName)?>" placeholder="Sual Duellosu">
          </div>
          <div>
            <label>Cari loqo</label>
            <div style="display:flex;align-items:center;gap:10px;height:38px"><?=sdqLogoPreviewHtml($quizBrandLogo)?></div>
          </div>
        </div>
        <div class="form-row cols2">
          <div>
            <label>Yeni loqo şəkli yüklə</label>
            <input type="file" name="quiz_duello_logo_file" accept=".png,.jpg,.jpeg,.svg,.webp">
            <div class="field-hint">PNG/SVG tövsiyə olunur, şəffaf fon. Maks. 2MB.</div>
          </div>
          <div>
            <label>Və ya emoji / şəkil URL-i yazın</label>
            <input type="text" name="quiz_duello_logo_value" placeholder="⚔️ və ya /telesmart-img/icons/logo.png">
            <div class="field-hint" style="color:#c0392b">⚠️ Domen/IP yazmayın — yalnız <b>/telesmart-img/...</b> ilə başlayan server-nisbi yol yazın.</div>
          </div>
        </div>
        <button type="submit" class="btn-save">💾 Sual Duellosu görünüşünü yadda saxla</button>
      </form>
    </div>
    <?php attemptsCardHtml('sualduellosu', 'Sual Duellosu', $sdAttempts, 'yeni otaq yaratmaq', 'duello'); ?>

    <!-- Oyun Görünüşü: Tele Time -->
    <div class="form-card" data-accent="teletime">
      <h3>📺 Tele Time Görünüşü — "<?=h2($ttBrandName)?>"</h3>
      <div class="field-hint" style="margin-bottom:14px">Burada dəyişdiyiniz ad və loqo "Tele Time" oyununun (giriş, qeydiyyat, üst panel) BÜTÜN səhifələrində avtomatik tətbiq olunur. Sual Duellosu-nun görünüşünə təsir etmir.</div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_branding">
        <input type="hidden" name="game" value="teletime">
        <div class="form-row cols2">
          <div>
            <label>Oyunun adı</label>
            <input type="text" name="teletime_name" value="<?=h2($ttBrandName)?>" placeholder="Tele Time">
          </div>
          <div>
            <label>Cari loqo</label>
            <div style="display:flex;align-items:center;gap:10px;height:38px"><?=sdqLogoPreviewHtml($ttBrandLogo)?></div>
          </div>
        </div>
        <div class="form-row cols2">
          <div>
            <label>Yeni loqo şəkli yüklə</label>
            <input type="file" name="teletime_logo_file" accept=".png,.jpg,.jpeg,.svg,.webp">
            <div class="field-hint">PNG/SVG tövsiyə olunur, şəffaf fon. Maks. 2MB.</div>
          </div>
          <div>
            <label>Və ya emoji / şəkil URL-i yazın</label>
            <input type="text" name="teletime_logo_value" placeholder="📺 və ya /telesmart-img/icons/teletime_logo.png">
            <div class="field-hint" style="color:#c0392b">⚠️ Domen/IP yazmayın — yalnız <b>/telesmart-img/...</b> ilə başlayan server-nisbi yol yazın.</div>
          </div>
        </div>
        <button type="submit" class="btn-save">💾 Tele Time görünüşünü yadda saxla</button>
      </form>
    </div>

    <!-- Oyun Görünüşü: Zəncir -->
    <div class="form-card" data-accent="zencir">
      <h3>⛓️ Zəncir Görünüşü — "<?=h2($zcBrandName)?>"</h3>
      <div class="field-hint" style="margin-bottom:14px">Burada dəyişdiyiniz ad və loqo "Zəncir" oyununun (giriş, qeydiyyat, üst panel) BÜTÜN səhifələrində avtomatik tətbiq olunur. Digər oyunların görünüşünə təsir etmir.</div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_branding">
        <input type="hidden" name="game" value="zencir">
        <div class="form-row cols2">
          <div>
            <label>Oyunun adı</label>
            <input type="text" name="zencir_name" value="<?=h2($zcBrandName)?>" placeholder="Zəncir">
          </div>
          <div>
            <label>Cari loqo</label>
            <div style="display:flex;align-items:center;gap:10px;height:38px"><?=sdqLogoPreviewHtml($zcBrandLogo)?></div>
          </div>
        </div>
        <div class="form-row cols2">
          <div>
            <label>Yeni loqo şəkli yüklə</label>
            <input type="file" name="zencir_logo_file" accept=".png,.jpg,.jpeg,.svg,.webp">
            <div class="field-hint">PNG/SVG tövsiyə olunur, şəffaf fon. Maks. 2MB.</div>
          </div>
          <div>
            <label>Və ya emoji / şəkil URL-i yazın</label>
            <input type="text" name="zencir_logo_value" placeholder="⛓️ və ya /telesmart-img/icons/zencir_logo.png">
            <div class="field-hint" style="color:#c0392b">⚠️ Domen/IP yazmayın — yalnız <b>/telesmart-img/...</b> ilə başlayan server-nisbi yol yazın.</div>
          </div>
        </div>
        <button type="submit" class="btn-save">💾 Zəncir görünüşünü yadda saxla</button>
      </form>
    </div>
    <?php attemptsCardHtml('zencir', 'Zəncir', $zcAttempts, 'yeni qaçış', 'zencir'); ?>

    <!-- Oyun Görünüşü: Milyonçu -->
    <div class="form-card" data-accent="milyoncu">
      <h3>💰 Milyonçu Görünüşü — "<?=h2($mcBrandName)?>"</h3>
      <div class="field-hint" style="margin-bottom:14px">Burada dəyişdiyiniz ad və loqo "Milyonçu" oyununun (giriş, qeydiyyat, üst panel) BÜTÜN səhifələrində avtomatik tətbiq olunur. Digər oyunların görünüşünə təsir etmir.</div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_branding">
        <input type="hidden" name="game" value="milyoncu">
        <div class="form-row cols2">
          <div>
            <label>Oyunun adı</label>
            <input type="text" name="milyoncu_name" value="<?=h2($mcBrandName)?>" placeholder="Milyonçu">
          </div>
          <div>
            <label>Cari loqo</label>
            <div style="display:flex;align-items:center;gap:10px;height:38px"><?=sdqLogoPreviewHtml($mcBrandLogo)?></div>
          </div>
        </div>
        <div class="form-row cols2">
          <div>
            <label>Yeni loqo şəkli yüklə</label>
            <input type="file" name="milyoncu_logo_file" accept=".png,.jpg,.jpeg,.svg,.webp">
            <div class="field-hint">PNG/SVG tövsiyə olunur, şəffaf fon. Maks. 2MB.</div>
          </div>
          <div>
            <label>Və ya emoji / şəkil URL-i yazın</label>
            <input type="text" name="milyoncu_logo_value" placeholder="💰 və ya /telesmart-img/icons/milyoncu_logo.png">
            <div class="field-hint" style="color:#c0392b">⚠️ Domen/IP yazmayın — yalnız <b>/telesmart-img/...</b> ilə başlayan server-nisbi yol yazın.</div>
          </div>
        </div>
        <button type="submit" class="btn-save">💾 Milyonçu görünüşünü yadda saxla</button>
      </form>
    </div>
    <?php attemptsCardHtml('milyoncu', 'Milyonçu', $mcAttempts, 'yeni oyun', 'milyoncu'); ?>

    <!-- Oyun Görünüşü: Sürət Turu -->
    <div class="form-card" data-accent="suretturu">
      <h3>⚡ Sürət Turu Görünüşü — "<?=h2($srBrandName)?>"</h3>
      <div class="field-hint" style="margin-bottom:14px">Burada dəyişdiyiniz ad və loqo "Sürət Turu" oyununun (giriş, qeydiyyat, üst panel) BÜTÜN səhifələrində avtomatik tətbiq olunur. Digər oyunların görünüşünə təsir etmir.</div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_branding">
        <input type="hidden" name="game" value="suretturu">
        <div class="form-row cols2">
          <div>
            <label>Oyunun adı</label>
            <input type="text" name="suretturu_name" value="<?=h2($srBrandName)?>" placeholder="Sürət Turu">
          </div>
          <div>
            <label>Cari loqo</label>
            <div style="display:flex;align-items:center;gap:10px;height:38px"><?=sdqLogoPreviewHtml($srBrandLogo)?></div>
          </div>
        </div>
        <div class="form-row cols2">
          <div>
            <label>Yeni loqo şəkli yüklə</label>
            <input type="file" name="suretturu_logo_file" accept=".png,.jpg,.jpeg,.svg,.webp">
            <div class="field-hint">PNG/SVG tövsiyə olunur, şəffaf fon. Maks. 2MB.</div>
          </div>
          <div>
            <label>Və ya emoji / şəkil URL-i yazın</label>
            <input type="text" name="suretturu_logo_value" placeholder="⚡ və ya /telesmart-img/icons/suretturu_logo.png">
            <div class="field-hint" style="color:#c0392b">⚠️ Domen/IP yazmayın — yalnız <b>/telesmart-img/...</b> ilə başlayan server-nisbi yol yazın.</div>
          </div>
        </div>
        <button type="submit" class="btn-save">💾 Sürət Turu görünüşünü yadda saxla</button>
      </form>
    </div>
    <?php attemptsCardHtml('suretturu', 'Sürət Turu', $srAttempts, 'yeni tur', 'suretturu'); ?>

    <!-- Oyun Görünüşü: Oyunçu (Mərkəzi Lobi) -->
    <div class="form-card full-span hero-hub" data-accent="hub">
      <h3>🎮 Oyunçu Görünüşü — "<?=h2($ohBrandName)?>" <span style="font-size:.7rem;font-weight:600;color:var(--text2)">(Mərkəzi giriş/lobi)</span></h3>
      <div class="field-hint" style="margin-bottom:14px">Bu, bütün 5 oyunun ORTAQ girişini/lobisini idarə edən "Oyunçu" hub-unun adı və loqosudur (<code>games/oyunchu/</code>). Digər 5 oyunun öz görünüşünə təsir etmir.</div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_branding">
        <input type="hidden" name="game" value="oyunchu">
        <div class="form-row cols2">
          <div>
            <label>Oyunun adı</label>
            <input type="text" name="oyunchu_name" value="<?=h2($ohBrandName)?>" placeholder="Oyunçu">
          </div>
          <div>
            <label>Cari loqo</label>
            <div style="display:flex;align-items:center;gap:10px;height:38px"><?=sdqLogoPreviewHtml($ohBrandLogo)?></div>
          </div>
        </div>
        <div class="form-row cols2">
          <div>
            <label>Yeni loqo şəkli yüklə</label>
            <input type="file" name="oyunchu_logo_file" accept=".png,.jpg,.jpeg,.svg,.webp">
            <div class="field-hint">PNG/SVG tövsiyə olunur, şəffaf fon. Maks. 2MB.</div>
          </div>
          <div>
            <label>Və ya emoji / şəkil URL-i yazın</label>
            <input type="text" name="oyunchu_logo_value" placeholder="🎮 və ya /telesmart-img/icons/oyunchu_logo.png">
            <div class="field-hint" style="color:#c0392b">⚠️ Domen/IP yazmayın — yalnız <b>/telesmart-img/...</b> ilə başlayan server-nisbi yol yazın.</div>
          </div>
        </div>
        <div class="form-row cols2" style="margin-top:6px;padding-top:14px;border-top:1px dashed var(--border)">
          <div>
            <label>Cari favicon</label>
            <div style="display:flex;align-items:center;gap:10px;height:38px"><?= $ohBrandFavicon !== '' ? sdqLogoPreviewHtml($ohBrandFavicon) : '<span class="field-hint">Təyin edilməyib — sayt ümumi favicondan istifadə edir</span>' ?></div>
          </div>
          <div>
            <label>Yeni favicon yüklə</label>
            <input type="file" name="oyunchu_favicon_file" accept=".png,.jpg,.jpeg,.svg,.webp">
            <div class="field-hint">Kvadrat PNG/SVG tövsiyə olunur (məs. 64×64 və ya 512×512). Maks. 2MB.</div>
          </div>
        </div>
        <div class="form-row cols2">
          <div></div>
          <div>
            <label>Və ya şəkil URL-i yazın</label>
            <input type="text" name="oyunchu_favicon_value" value="<?=h2($ohBrandFavicon)?>" placeholder="/telesmart-img/icons/oyunchu_favicon.png">
            <div class="field-hint" style="color:#c0392b">⚠️ Domen/IP yazmayın — yalnız <b>/telesmart-img/...</b> ilə başlayan server-nisbi yol yazın.</div>
          </div>
        </div>
        <button type="submit" class="btn-save">💾 Oyunçu görünüşünü yadda saxla</button>
      </form>
    </div>

    <!-- Oyun Görünüşü: Sözü Tap -->
    <div class="form-card" data-accent="sozutap">
      <h3>🔤 Sözü Tap Görünüşü — "<?=h2($szBrandName)?>"</h3>
      <div class="field-hint" style="margin-bottom:14px">Söz bankını idarə etmək üçün sol menyudan "Sözü Tap" bölməsinə keçin. Burada yalnız ad/loqo dəyişdirilir.</div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_branding">
        <input type="hidden" name="game" value="sozutap">
        <div class="form-row cols2">
          <div>
            <label>Oyunun adı</label>
            <input type="text" name="sozutap_name" value="<?=h2($szBrandName)?>" placeholder="Sözü Tap">
          </div>
          <div>
            <label>Cari loqo</label>
            <div style="display:flex;align-items:center;gap:10px;height:38px"><?=sdqLogoPreviewHtml($szBrandLogo)?></div>
          </div>
        </div>
        <div class="form-row cols2">
          <div>
            <label>Yeni loqo şəkli yüklə</label>
            <input type="file" name="sozutap_logo_file" accept=".png,.jpg,.jpeg,.svg,.webp">
            <div class="field-hint">PNG/SVG tövsiyə olunur, şəffaf fon. Maks. 2MB.</div>
          </div>
          <div>
            <label>Və ya emoji / şəkil URL-i yazın</label>
            <input type="text" name="sozutap_logo_value" placeholder="🔤 və ya /telesmart-img/icons/sozutap_logo.png">
            <div class="field-hint" style="color:#c0392b">⚠️ Domen/IP yazmayın — yalnız <b>/telesmart-img/...</b> ilə başlayan server-nisbi yol yazın.</div>
          </div>
        </div>
        <button type="submit" class="btn-save">💾 Sözü Tap görünüşünü yadda saxla</button>
      </form>
    </div>

    <!-- Oyun Görünüşü: Kəlmə Zənciri -->
    <div class="form-card" data-accent="kelmezencir">
      <h3>🔗 Kəlmə Zənciri Görünüşü — "<?=h2($kzBrandName)?>"</h3>
      <div class="field-hint" style="margin-bottom:14px">Sual bankını (ortaq) idarə etmək üçün sol menyudan "Suallar" bölməsinə keçin. Burada yalnız ad/loqo dəyişdirilir.</div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_branding">
        <input type="hidden" name="game" value="kelmezencir">
        <div class="form-row cols2">
          <div>
            <label>Oyunun adı</label>
            <input type="text" name="kelmezencir_name" value="<?=h2($kzBrandName)?>" placeholder="Kəlmə Zənciri">
          </div>
          <div>
            <label>Cari loqo</label>
            <div style="display:flex;align-items:center;gap:10px;height:38px"><?=sdqLogoPreviewHtml($kzBrandLogo)?></div>
          </div>
        </div>
        <div class="form-row cols2">
          <div>
            <label>Yeni loqo şəkli yüklə</label>
            <input type="file" name="kelmezencir_logo_file" accept=".png,.jpg,.jpeg,.svg,.webp">
            <div class="field-hint">PNG/SVG tövsiyə olunur, şəffaf fon. Maks. 2MB.</div>
          </div>
          <div>
            <label>Və ya emoji / şəkil URL-i yazın</label>
            <input type="text" name="kelmezencir_logo_value" placeholder="🔗 və ya /telesmart-img/icons/kelmezencir_logo.png">
            <div class="field-hint" style="color:#c0392b">⚠️ Domen/IP yazmayın — yalnız <b>/telesmart-img/...</b> ilə başlayan server-nisbi yol yazın.</div>
          </div>
        </div>
        <button type="submit" class="btn-save">💾 Kəlmə Zənciri görünüşünü yadda saxla</button>
      </form>
    </div>

    <!-- Oyun Görünüşü: Sudoku -->
    <div class="form-card" data-accent="sudoku">
      <h3>🔢 Sudoku Görünüşü — "<?=h2($suBrandName)?>"</h3>
      <div class="field-hint" style="margin-bottom:14px">Burada dəyişdiyiniz ad və loqo "Sudoku" oyununun (giriş, üst panel, Oyun Mərkəzi) BÜTÜN səhifələrində avtomatik tətbiq olunur.</div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_branding">
        <input type="hidden" name="game" value="sudoku">
        <div class="form-row cols2">
          <div>
            <label>Oyunun adı</label>
            <input type="text" name="sudoku_name" value="<?=h2($suBrandName)?>" placeholder="Sudoku">
          </div>
          <div>
            <label>Cari loqo</label>
            <div style="display:flex;align-items:center;gap:10px;height:38px"><?=sdqLogoPreviewHtml($suBrandLogo)?></div>
          </div>
        </div>
        <div class="form-row cols2">
          <div>
            <label>Yeni loqo şəkli yüklə</label>
            <input type="file" name="sudoku_logo_file" accept=".png,.jpg,.jpeg,.svg,.webp">
            <div class="field-hint">PNG/SVG tövsiyə olunur, şəffaf fon. Maks. 2MB.</div>
          </div>
          <div>
            <label>Və ya emoji / şəkil URL-i yazın</label>
            <input type="text" name="sudoku_logo_value" placeholder="🔢 və ya /telesmart-img/icons/sudoku_logo.png">
            <div class="field-hint" style="color:#c0392b">⚠️ Domen/IP yazmayın — yalnız <b>/telesmart-img/...</b> ilə başlayan server-nisbi yol yazın.</div>
          </div>
        </div>
        <button type="submit" class="btn-save">💾 Sudoku görünüşünü yadda saxla</button>
      </form>
    </div>
    <?php attemptsCardHtml('sudoku', 'Sudoku', $suAttempts, 'yeni tapmaca', 'sudoku'); ?>

    <!-- Oyun Görünüşü: Söz Yağışı -->
    <div class="form-card" data-accent="sozyagishi">
      <h3>🌧️ Söz Yağışı Görünüşü — "<?=h2($syBrandName)?>"</h3>
      <div class="field-hint" style="margin-bottom:14px">Burada dəyişdiyiniz ad və loqo "Sürətli Söz Yağışı" oyununun (giriş, üst panel, Oyun Mərkəzi) BÜTÜN səhifələrində avtomatik tətbiq olunur.</div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_branding">
        <input type="hidden" name="game" value="sozyagishi">
        <div class="form-row cols2">
          <div>
            <label>Oyunun adı</label>
            <input type="text" name="sozyagishi_name" value="<?=h2($syBrandName)?>" placeholder="Sürətli Söz Yağışı">
          </div>
          <div>
            <label>Cari loqo</label>
            <div style="display:flex;align-items:center;gap:10px;height:38px"><?=sdqLogoPreviewHtml($syBrandLogo)?></div>
          </div>
        </div>
        <div class="form-row cols2">
          <div>
            <label>Yeni loqo şəkli yüklə</label>
            <input type="file" name="sozyagishi_logo_file" accept=".png,.jpg,.jpeg,.svg,.webp">
            <div class="field-hint">PNG/SVG tövsiyə olunur, şəffaf fon. Maks. 2MB.</div>
          </div>
          <div>
            <label>Və ya emoji / şəkil URL-i yazın</label>
            <input type="text" name="sozyagishi_logo_value" placeholder="🌧️ və ya /telesmart-img/icons/sozyagishi_logo.png">
            <div class="field-hint" style="color:#c0392b">⚠️ Domen/IP yazmayın — yalnız <b>/telesmart-img/...</b> ilə başlayan server-nisbi yol yazın.</div>
          </div>
        </div>
        <button type="submit" class="btn-save">💾 Söz Yağışı görünüşünü yadda saxla</button>
      </form>
    </div>
    <?php attemptsCardHtml('sozyagishi', 'Söz Yağışı', $syAttempts, 'yeni raund', 'sozyagishi'); ?>

    <!-- Oyun Görünüşü: Şahmat -->
    <div class="form-card" data-accent="sahmat">
      <h3>♞ Şahmat Görünüşü — "<?=h2($sahBrandName)?>"</h3>
      <div class="field-hint" style="margin-bottom:14px">Burada dəyişdiyiniz ad və loqo "Şahmat" oyununun (giriş, otaq ekranı, üst panel) BÜTÜN səhifələrində avtomatik tətbiq olunur. Digər oyunların görünüşünə təsir etmir. Şahmatda gündəlik cəhd limiti yoxdur (sərbəst, otaqlı oyundur), ona görə yalnız ad/loqo tənzimlənir.</div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_branding">
        <input type="hidden" name="game" value="sahmat">
        <div class="form-row cols2">
          <div>
            <label>Oyunun adı</label>
            <input type="text" name="sahmat_name" value="<?=h2($sahBrandName)?>" placeholder="Şahmat">
          </div>
          <div>
            <label>Cari loqo</label>
            <div style="display:flex;align-items:center;gap:10px;height:38px"><?=sdqLogoPreviewHtml($sahBrandLogo)?></div>
          </div>
        </div>
        <div class="form-row cols2">
          <div>
            <label>Yeni loqo şəkli yüklə</label>
            <input type="file" name="sahmat_logo_file" accept=".png,.jpg,.jpeg,.svg,.webp">
            <div class="field-hint">PNG/SVG tövsiyə olunur, şəffaf fon. Maks. 2MB.</div>
          </div>
          <div>
            <label>Və ya emoji / şəkil URL-i yazın</label>
            <input type="text" name="sahmat_logo_value" placeholder="♞ və ya /telesmart-img/icons/sahmat_logo.png">
            <div class="field-hint" style="color:#c0392b">⚠️ Domen/IP yazmayın — yalnız <b>/telesmart-img/...</b> ilə başlayan server-nisbi yol yazın.</div>
          </div>
        </div>
        <button type="submit" class="btn-save">💾 Şahmat görünüşünü yadda saxla</button>
      </form>
    </div>


    <!-- Dinamik əlavə oyunlar: oh_games-dən gəlir -->
    <?php foreach ($dynamicBrandGames as $dg):
        if (in_array($dg['slug'], $knownBrandSlugs, true)) continue;
        $dgName = getSetting($dg['name_key'], $dg['default_name']);
        $dgLogo = getSetting($dg['logo_key'], $dg['default_logo']);
    ?>
    <div class="form-card dynamic-game-branding" data-accent="dynamic">
      <h3>🎮 <?=h2($dgName)?> Görünüşü — "<?=h2($dgName)?>"</h3>
      <div class="field-hint" style="margin-bottom:14px">Bu oyun <b>oh_games</b> cədvəlindən avtomatik aşkarlandı. Buradakı ad və loqo yalnız <b><?=h2($dg['slug'])?></b> oyununa aiddir.</div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_branding">
        <input type="hidden" name="game" value="<?=h2($dg['slug'])?>">
        <div class="form-row cols2">
          <div>
            <label>Oyunun adı</label>
            <input type="text" name="<?=h2($dg['name_key'])?>" value="<?=h2($dgName)?>" placeholder="<?=h2($dg['default_name'])?>">
          </div>
          <div>
            <label>Cari loqo</label>
            <div style="display:flex;align-items:center;gap:10px;height:38px"><?=sdqLogoPreviewHtml($dgLogo)?></div>
          </div>
        </div>
        <div class="form-row cols2">
          <div>
            <label>Yeni loqo şəkli yüklə</label>
            <input type="file" name="<?=h2($dg['logo_key'])?>_file" accept=".png,.jpg,.jpeg,.svg,.webp" data-logo-upload>
            <div class="field-hint">PNG/SVG tövsiyə olunur · maksimum 2MB.</div>
          </div>
          <div>
            <label>Və ya emoji / şəkil URL-i</label>
            <input type="text" name="<?=h2($dg['logo_key'])?>_value" placeholder="🎮 və ya /telesmart-img/icons/<?=h2($dg['slug'])?>_logo.png">
            <div class="field-hint">Domen/IP yox — server-nisbi <b>/telesmart-img/...</b> yolu.</div>
          </div>
        </div>
        <button type="submit" class="btn-save">💾 <?=h2($dgName)?> görünüşünü yadda saxla</button>
      </form>
    </div>
    <?php endforeach; ?>

    <!-- Reklam Ayarları (Freemium/Premium) -->
    <div class="form-card full-span" data-accent="gold">
      <h3>📢 Reklam Ayarları (Freemium/Premium)</h3>
      <div class="field-hint" style="margin-bottom:14px">
        Premium (VIP) istifadəçilərə HEÇ VAXT reklam göstərilmir. Freemium
        istifadəçilər isə "Reklama bax, coin qazan" düyməsini görür.
        Aşağıda seçdiyiniz təchizatçının ID-lərini yazmayınca, reklam bölməsi
        avtomatik gizli qalır (saxta reklam göstərilmir).
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="save_ads_config">
        <div class="form-row">
          <label>Reklam təchizatçısı</label>
          <select name="oh_ads_provider" style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid var(--border, #ddd);font-size:.9rem;background:var(--surface, #fff)">
            <option value="none" <?=$adsProvider==='none'?'selected':''?>>Yoxdur (reklam bölməsi gizli qalır)</option>
            <option value="adsense" <?=$adsProvider==='adsense'?'selected':''?>>Google AdSense (veb H5 reklamlar)</option>
            <option value="twa_admob" <?=$adsProvider==='twa_admob'?'selected':''?>>AdMob (yalnız Android TWA tətbiqi daxilində)</option>
          </select>
        </div>
        <div class="form-row cols2">
          <div>
            <label>AdSense Client ID</label>
            <input type="text" name="oh_adsense_client" value="<?=h2($adsenseClient)?>" placeholder="ca-pub-xxxxxxxxxxxxxxxx">
          </div>
          <div>
            <label>AdSense Rewarded Slot ID</label>
            <input type="text" name="oh_adsense_slot_rewarded" value="<?=h2($adsenseSlotRewarded)?>" placeholder="1234567890">
          </div>
        </div>
        <div class="form-row cols2">
          <div>
            <label>AdSense Banner Slot ID</label>
            <input type="text" name="oh_adsense_slot_banner" value="<?=h2($adsenseSlotBanner)?>" placeholder="0987654321">
          </div>
          <div>
            <label>AdMob Rewarded Unit ID (TWA)</label>
            <input type="text" name="oh_admob_rewarded_unit" value="<?=h2($admobRewardedUnit)?>" placeholder="ca-app-pub-xxx/yyy">
          </div>
        </div>
        <div class="form-row cols2">
          <div>
            <label>Reklam mükafatı (coin)</label>
            <input type="number" name="oh_ad_reward_coins" value="<?=$adRewardCoins?>" min="1">
          </div>
          <div>
            <label>Növbəti reklama qədər gözləmə (dəqiqə)</label>
            <input type="number" name="oh_ad_cooldown_min" value="<?=$adCooldownMin?>" min="1">
          </div>
        </div>
        <button type="submit" class="btn-save">💾 Reklam ayarlarını yadda saxla</button>
      </form>
    </div>

    <!-- Giriş Səhifəsinin Arxa Fonu -->
    <div class="form-card full-span">
      <h3>🖼️ Admin Giriş Səhifəsinin Arxa Fonu</h3>
      <div class="field-hint" style="margin-bottom:14px">Bu, yalnız <code>/telesmartadmin/auth/oyunchu_login.php</code> (admin giriş) səhifəsinin arxa fonuna aiddir — oyunun özünə (istifadəçilərin gördüyü giriş/qeydiyyat səhifələrinə) təsir etmir.</div>
      <form method="POST" enctype="multipart/form-data" id="bgForm">
        <input type="hidden" name="action" value="save_login_bg">
        <div class="bg-type-row">
          <div class="bg-type-pill <?=$loginBgType==='default'?'sel':''?>" data-val="default" onclick="selBgType(this)">Defolt</div>
          <div class="bg-type-pill <?=$loginBgType==='color'?'sel':''?>" data-val="color" onclick="selBgType(this)">Rəng</div>
          <div class="bg-type-pill <?=$loginBgType==='image'?'sel':''?>" data-val="image" onclick="selBgType(this)">Şəkil</div>
        </div>
        <input type="hidden" name="login_bg_type" id="loginBgTypeInput" value="<?=h2($loginBgType)?>">

        <div class="bg-section <?=$loginBgType==='color'?'show':''?>" id="bgSectionColor">
          <label>Fon rəngi</label>
          <input type="color" name="login_bg_color" value="<?=h2($loginBgType==='color' && $loginBgValue ? $loginBgValue : '#0a0a0a')?>" style="height:44px;padding:4px">
        </div>

        <div class="bg-section <?=$loginBgType==='image'?'show':''?>" id="bgSectionImage">
          <div class="form-row cols2">
            <div>
              <label>Fon şəkli yüklə</label>
              <input type="file" name="login_bg_image_file" accept=".png,.jpg,.jpeg,.svg,.webp">
              <div class="field-hint">Maks. 2MB. Tam ekran fon kimi istifadə olunacaq.</div>
            </div>
            <div>
              <label>Və ya şəkil yolu yazın</label>
              <input type="text" name="login_bg_image_value" placeholder="/telesmart-img/icons/loginbg.jpg" value="<?=$loginBgType==='image'?h2($loginBgValue):''?>">
              <div class="field-hint" style="color:#c0392b">⚠️ Yalnız <b>/telesmart-img/...</b> server-nisbi yol yazın (domen/IP yazmayın).</div>
            </div>
          </div>
        </div>

        <?php if ($loginBgType !== 'default' && $loginBgValue): ?>
        <div class="bg-preview" style="<?= $loginBgType==='color' ? 'background:'.h2($loginBgValue) : "background-image:url('".h2($loginBgValue)."')" ?>">
          <span style="color:rgba(255,255,255,.5);font-size:.75rem">Cari fon</span>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn-save" style="margin-top:14px">💾 Fonu yadda saxla</button>
      </form>
    </div>

    <!-- Əlavə Cəhd Alışları — bütün oyunlar üçün ortaq log -->
    <div class="form-card full-span">
      <h3>🧾 Əlavə Cəhd Alışları — Bugünkü Xülasə</h3>
      <div class="quiz-stats" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px">
        <?php foreach ($extraPurchaseTypes as $tKey => $tLabel):
            $s = $extraPurchaseStatsToday[$tKey] ?? ['count' => 0, 'coins' => 0]; ?>
        <div class="qs-card" style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 16px">
          <div style="font-size:1.4rem;font-weight:900;color:#e67e22"><?= (int)$s['count'] ?></div>
          <div style="font-size:.72rem;color:var(--text2);margin-top:3px"><?= h2($tLabel) ?> · bugün 🪙<?= (int)$s['coins'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-card full-span" style="padding:0">
      <div style="padding:20px 22px 0"><h3 style="margin-bottom:4px">📋 Son Əlavə Cəhd Alışları — Kim, Nə vaxt, Hansı Oyun, Nə qədər (son 80)</h3></div>
      <div style="max-height:480px;overflow:auto;margin-top:12px">
        <?php if (!$extraPurchaseLog): ?>
        <div style="padding:22px;text-align:center;color:var(--text2)">Hələ heç kim əlavə cəhd almayıb.</div>
        <?php endif; ?>
        <?php foreach ($extraPurchaseLog as $tx): ?>
        <div style="padding:12px 22px;border-top:1px solid var(--border);display:flex;justify-content:space-between;gap:10px;align-items:center">
          <div style="min-width:0">
            <div style="font-weight:700;font-size:.86rem"><?= h2($tx['name'] ?: ('İstifadəçi #' . (int)$tx['uid'])) ?>
              <span style="font-weight:600;color:var(--text2)">— <?= h2($extraPurchaseTypes[$tx['type']] ?? $tx['type']) ?></span>
            </div>
            <div style="font-size:.72rem;color:var(--text2);margin-top:2px"><?= h2($tx['email'] ?? '') ?> · <?= h2($tx['note']) ?> · <?= h2(date('d.m.Y H:i', strtotime($tx['created_at']))) ?></div>
          </div>
          <div style="font-weight:900;color:#e67e22;white-space:nowrap">− <?= (int)abs($tx['amount']) ?> coin</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</div>
<script>
function selBgType(el){
  document.querySelectorAll('.bg-type-pill').forEach(p=>p.classList.remove('sel'));
  el.classList.add('sel');
  const val = el.dataset.val;
  document.getElementById('loginBgTypeInput').value = val;
  document.getElementById('bgSectionColor').classList.toggle('show', val==='color');
  document.getElementById('bgSectionImage').classList.toggle('show', val==='image');
}
function toggleTheme(){
  const html=document.documentElement;
  const isDark=html.getAttribute('data-theme')==='dark';
  html.setAttribute('data-theme',isDark?'light':'dark');
  localStorage.setItem('esevgili_theme',isDark?'light':'dark');
}
document.querySelectorAll('input[data-logo-upload]').forEach(function(el){el.addEventListener('change',function(){if(this.files[0] && this.files[0].size>2097152){alert('Loqo maksimum 2MB olmalıdır.');this.value='';}});});
</script>
</body></html>
