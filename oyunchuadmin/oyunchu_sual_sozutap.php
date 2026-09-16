<?php
require_once __DIR__ . '/oyunchu_sual_config.php';
$admin = requireAdmin();

// DB cədvəlini yarat (yoxdursa)
try {
    db()->exec("CREATE TABLE IF NOT EXISTS sz_words (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        word VARCHAR(20) UNIQUE NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

const SZ_EPOCH = '2026-01-01';
function sz_todayDayIndex(): int {
    $epoch = new DateTime(SZ_EPOCH);
    $today = new DateTime(date('Y-m-d'));
    return (int)$epoch->diff($today)->days;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_word') {
        $word = mb_strtolower(trim($_POST['word'] ?? ''), 'UTF-8');
        $word = preg_replace('/[^\p{L}]/u', '', $word); // yalnız hərflər
        if (mb_strlen($word, 'UTF-8') < 3 || mb_strlen($word, 'UTF-8') > 12) {
            $msg = '❌ Söz 3-12 hərf arasında olmalıdır.';
        } else {
            try {
                db()->prepare("INSERT INTO sz_words (word) VALUES (?)")->execute([$word]);
                sdAuditLog('sozutap_word_add', 'sz_words', null, "Söz: $word");
                $msg = "✅ \"$word\" söz bankına əlavə edildi.";
            } catch (Exception $e) {
                $msg = '❌ Bu söz artıq bankdadır.';
            }
        }
    }

    if ($action === 'delete_word') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            db()->prepare("DELETE FROM sz_words WHERE id=?")->execute([$id]);
            sdAuditLog('sozutap_word_delete', 'sz_words', $id, '');
            $msg = '🗑 Söz silindi.';
        } catch (Exception $e) { $msg = '❌ Xəta baş verdi.'; }
    }

    if ($action === 'save_limits') {
        $freeAttempts = max(1, (int)($_POST['free_attempts'] ?? 5));
        $cost1 = max(0, (int)($_POST['extra_cost_1'] ?? 60));
        $cost2 = max(0, (int)($_POST['extra_cost_2'] ?? 200));
        setSetting('sozutap_free_attempts', $freeAttempts);
        setSetting('sozutap_extra_cost_1', $cost1);
        setSetting('sozutap_extra_cost_2', $cost2);
        sdAuditLog('sozutap_limits_update', 'es_settings', null,
            "Pulsuz cəhd: $freeAttempts, 1-ci əlavə: $cost1 coin, 2-ci+ əlavə: $cost2 coin");
        $msg = "✅ Cəhd/qiymət qaydaları yeniləndi: $freeAttempts pulsuz cəhd, sonra $cost1 coin, sonra $cost2 coin.";
    }

    if ($action === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            db()->prepare("UPDATE sz_words SET is_active = 1 - is_active WHERE id=?")->execute([$id]);
        } catch (Exception $e) {}
    }

    if ($action === 'import_csv') {
        if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $msg = '❌ Fayl yüklənmədi. Yenidən cəhd edin.';
        } else {
            $tmpPath = $_FILES['csv_file']['tmp_name'];
            $fh = fopen($tmpPath, 'r');
            if (!$fh) {
                $msg = '❌ Fayl açıla bilmədi.';
            } else {
                $bom = fread($fh, 3);
                if ($bom !== "\xEF\xBB\xBF") rewind($fh);
                $ok = 0; $failed = 0;
                $ins = db()->prepare("INSERT IGNORE INTO sz_words (word) VALUES (?)");
                while (($row = fgetcsv($fh, 0, ',')) !== false) {
                    if (count($row) === 0) continue;
                    $word = mb_strtolower(trim($row[0] ?? ''), 'UTF-8');
                    $word = preg_replace('/[^\p{L}]/u', '', $word);
                    $len = mb_strlen($word, 'UTF-8');
                    if ($len < 3 || $len > 12) { $failed++; continue; }
                    try {
                        $r = $ins->execute([$word]);
                        if ($ins->rowCount() > 0) $ok++; else $failed++;
                    } catch (Exception $e) { $failed++; }
                }
                fclose($fh);
                sdAuditLog('sozutap_csv_import', 'sz_words', null, "$ok uğurlu, $failed uğursuz");
                $msg = "✅ $ok söz əlavə edildi" . ($failed > 0 ? ", $failed sətir keçildi (təkrar/uzunluq)." : '.');
            }
        }
    }

    header('Location: /telesmartadmin/oyunchu/oyunchu_sual_sozutap.php' . ($msg ? '?msg=' . urlencode($msg) : '')); exit;
}

$msg = $msg ?: ($_GET['msg'] ?? '');

$stats = ['total' => 0, 'active' => 0];
try {
    $stats['total'] = (int)db()->query("SELECT COUNT(*) FROM sz_words")->fetchColumn();
    $stats['active'] = (int)db()->query("SELECT COUNT(*) FROM sz_words WHERE is_active=1")->fetchColumn();
} catch (Exception $e) {}

$todayWordRow = null;
try {
    $activeWords = db()->query("SELECT id, word FROM sz_words WHERE is_active=1 ORDER BY id ASC")->fetchAll();
    if ($activeWords) {
        $idx = sz_todayDayIndex() % count($activeWords);
        $todayWordRow = $activeWords[$idx];
    }
} catch (Exception $e) {}

$playedToday = 0; $wonToday = 0;
try {
    $st = db()->query("SELECT COUNT(*) c, COALESCE(SUM(won),0) w FROM sz_results WHERE day_index=" . sz_todayDayIndex());
    $row = $st->fetch();
    $playedToday = (int)($row['c'] ?? 0);
    $wonToday = (int)($row['w'] ?? 0);
} catch (Exception $e) {}

// ── Cəhd limiti + coin qiymətləri (es_settings) ─────────────────────────
$freeAttemptsSetting = (int)getSetting('sozutap_free_attempts', 5);
$extraCost1 = (int)getSetting('sozutap_extra_cost_1', 60);
$extraCost2 = (int)getSetting('sozutap_extra_cost_2', 200);

$extraToday = 0; $extraTodayCoins = 0; $extraTotalCoins = 0;
try {
    $st = db()->query("SELECT COUNT(*) c, COALESCE(SUM(-amount),0) s FROM es_coin_tx WHERE type='sozutap_extra' AND DATE(created_at)=CURDATE()");
    $r = $st->fetch();
    $extraToday = (int)($r['c'] ?? 0);
    $extraTodayCoins = (int)($r['s'] ?? 0);
    $extraTotalCoins = (int)db()->query("SELECT COALESCE(SUM(-amount),0) FROM es_coin_tx WHERE type='sozutap_extra'")->fetchColumn();
} catch (Exception $e) {}

$extraLog = [];
try {
    $extraLog = db()->query("SELECT ct.created_at, ct.amount, ct.note, u.id AS uid, u.name, u.email
                              FROM es_coin_tx ct LEFT JOIN users u ON u.id = ct.user_id
                              WHERE ct.type='sozutap_extra'
                              ORDER BY ct.id DESC LIMIT 50")->fetchAll();
} catch (Exception $e) {}

$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 40;
$offset = ($page - 1) * $perPage;
$words = [];
try {
    $st = db()->prepare("SELECT * FROM sz_words ORDER BY id DESC LIMIT $perPage OFFSET $offset");
    $st->execute();
    $words = $st->fetchAll();
} catch (Exception $e) {}
$totalPages = $stats['total'] ? (int)ceil($stats['total'] / $perPage) : 1;

function h2($s){return htmlspecialchars($s??'',ENT_QUOTES,'UTF-8');}
?>
<!DOCTYPE html>
<html lang="az">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sözü Tap — Söz Bankı</title>
<script>const t=localStorage.getItem("esevgili_theme")||"light";document.documentElement.setAttribute("data-theme",t);</script>
<link rel="stylesheet" href="/telesmartadmin/oyunchu/oyunchu_admin.css">
<style>
/* Sözü Tap: shared admin layout compatibility */
.sozutap-page{width:100%;max-width:1400px;margin:0 auto;}
.quiz-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:20px;}
.qs-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px 18px;text-align:left;min-width:0;}
.qs-num{font-size:1.7rem;font-weight:900;color:var(--rose);}
.qs-lbl{font-size:.74rem;color:var(--text3);margin-top:4px;}
.form-card,.tbl-wrap{background:var(--surface);border:1px solid var(--border);border-radius:14px;color:var(--text);margin-bottom:20px;}
.form-card{padding:20px;}
.form-card h3,.tbl-top h3{color:var(--text);font-size:.95rem;font-weight:800;margin-bottom:14px;}
.form-card label{display:block;color:var(--text2);font-size:.78rem;font-weight:700;margin-bottom:7px;}
.form-card input[type=text],.form-card input[type=file]{width:100%;background:var(--input-bg);color:var(--text);border:1px solid var(--border);border-radius:9px;padding:10px 12px;outline:none;}
.form-card input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(59,130,246,.10);}
.field-hint{color:var(--text3)!important;font-size:.74rem;line-height:1.5;}
.tbl-wrap{overflow:hidden;}
.tbl-top{padding:15px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.q-list{display:flex;flex-direction:column;}
.q-card{background:var(--surface);border-bottom:1px solid var(--border);padding:13px 18px;display:flex;align-items:center;gap:12px;color:var(--text);}
.q-card:last-child{border-bottom:0;}
.q-main{flex:1;min-width:0;}
.q-question{color:var(--text);font-size:.92rem;font-weight:800;overflow-wrap:anywhere;}
.q-meta{color:var(--text3);font-size:.72rem;margin-top:4px;}
.btn-save,.btn-tog,.page-btn{font-family:inherit;cursor:pointer;}
.btn-save{border:0;background:linear-gradient(135deg,var(--gold),var(--goldd));color:#fff;padding:10px 16px;border-radius:9px;font-weight:800;}
.btn-tog{border:1px solid var(--border);padding:8px 11px;border-radius:8px;font-weight:700;}
.empty-row{padding:25px;text-align:center;color:var(--text3);}
.pagination{display:flex;gap:6px;padding:15px 18px;border-top:1px solid var(--border);flex-wrap:wrap;}
.page-btn{padding:7px 11px;border:1px solid var(--border);border-radius:7px;color:var(--text2);background:var(--surface);}
.page-btn.active{background:var(--rose);color:#fff;border-color:var(--rose);}
.alert{padding:12px 16px;border-radius:10px;margin-bottom:18px;border:1px solid var(--border);}
.alert.ok{background:rgba(16,185,129,.10);color:var(--text);}
@media(max-width:1000px){.quiz-stats{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media(max-width:700px){
  .content{padding:16px 12px;}
  .quiz-stats{grid-template-columns:1fr 1fr;}
  .form-card form{flex-direction:column!important;align-items:stretch!important;}
  .form-card form button{width:100%;}
  .q-card{flex-wrap:wrap;}
}
</style>
</head>
<body>
<?php require __DIR__ . '/oyunchu_sual_sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-title">🔤 Sözü Tap — Söz Bankı</div>
    <div class="topbar-right">
      <button class="theme-btn" onclick="toggleTheme()" aria-label="Temanı dəyiş">🌙</button>
      <span class="admin-chip">⚙ <?=h2($admin['name'])?></span>
      <span class="topbar-date"><?=date('d.m.Y H:i')?></span>
    </div>
  </div>
  <div class="content">
  <div class="sozutap-page">

  <?php if ($msg): ?><div class="alert ok"><?= h2($msg) ?></div><?php endif; ?>

  <div class="quiz-stats">
    <div class="qs-card"><div class="qs-num"><?= $stats['total'] ?></div><div class="qs-lbl">Ümumi Söz</div></div>
    <div class="qs-card"><div class="qs-num" style="color:var(--blue)"><?= $stats['active'] ?></div><div class="qs-lbl">Aktiv Söz</div></div>
    <div class="qs-card"><div class="qs-num" style="color:var(--gold)"><?= $playedToday ?></div><div class="qs-lbl">Bugün Oynanan</div></div>
    <div class="qs-card"><div class="qs-num" style="color:#27ae60"><?= $wonToday ?></div><div class="qs-lbl">Bugün Tapılan</div></div>
  </div>

  <div class="quiz-stats">
    <div class="qs-card"><div class="qs-num" style="color:#e67e22"><?= $extraToday ?></div><div class="qs-lbl">Bugün Alınan Əlavə Cəhd</div></div>
    <div class="qs-card"><div class="qs-num" style="color:#e67e22">🪙<?= $extraTodayCoins ?></div><div class="qs-lbl">Bugün Əlavə Cəhddən Coin</div></div>
    <div class="qs-card"><div class="qs-num" style="color:#e67e22">🪙<?= $extraTotalCoins ?></div><div class="qs-lbl">Ümumi Əlavə Cəhd Coini</div></div>
  </div>

  <div class="form-card">
    <h3>🎯 Gündəlik Cəhd Limiti və Coin Qiymətləri</h3>
    <div class="field-hint" style="margin-bottom:14px">
      Hər istifadəçi gündə <b><?= $freeAttemptsSetting ?></b> pulsuz cəhd (yeni oyun) oynayır.
      Bitdikdən sonra 1-ci əlavə cəhd <b><?= $extraCost1 ?> coin</b>, 2-ci və hər sonrakı əlavə cəhd
      <b><?= $extraCost2 ?> coin</b>-dir. Dəyişiklik dərhal oyunda tətbiq olunur.
    </div>
    <form method="POST" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="action" value="save_limits">
      <div>
        <label>Gündəlik pulsuz cəhd sayı</label>
        <input type="text" name="free_attempts" value="<?= (int)$freeAttemptsSetting ?>" style="width:120px" required>
      </div>
      <div>
        <label>1-ci əlavə cəhd (coin)</label>
        <input type="text" name="extra_cost_1" value="<?= (int)$extraCost1 ?>" style="width:120px" required>
      </div>
      <div>
        <label>2-ci və sonrakı əlavə cəhd (coin)</label>
        <input type="text" name="extra_cost_2" value="<?= (int)$extraCost2 ?>" style="width:150px" required>
      </div>
      <button type="submit" class="btn-save" style="margin-bottom:0">Yadda saxla</button>
    </form>
  </div>

  <div class="tbl-wrap">
    <div class="tbl-top"><h3>🧾 Əlavə Cəhd Alışları — Kim, Nə vaxt, Nə qədər (son 50)</h3></div>
    <div class="q-list">
      <?php if (!$extraLog): ?>
      <div class="empty-row">Hələ heç kim əlavə cəhd almayıb.</div>
      <?php endif; ?>
      <?php foreach ($extraLog as $tx): ?>
      <div class="q-card">
        <div class="q-main">
          <div class="q-question">
            <?= h2($tx['name'] ?: ('İstifadəçi #' . (int)$tx['uid'])) ?>
            <span style="color:#e67e22;font-weight:900">− <?= (int)abs($tx['amount']) ?> coin</span>
          </div>
          <div class="q-meta"><?= h2($tx['email'] ?? '') ?> · <?= h2($tx['note']) ?> · <?= h2(date('d.m.Y H:i', strtotime($tx['created_at']))) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($todayWordRow): ?>
  <div class="form-card" style="border:1.5px solid var(--gold)">
    <h3>🎯 Bugünkü Söz (yalnız admin görür)</h3>
    <p style="font-size:1.3rem;font-weight:900;letter-spacing:3px;text-transform:uppercase;color:var(--gold)"><?= h2($todayWordRow['word']) ?></p>
    <div class="field-hint">Gün indeksi: <?= sz_todayDayIndex() ?> · <?= count($activeWords) ?> aktiv sözdən seçilib. Hər gün avtomatik dəyişir, əl ilə idarə tələb etmir.</div>
  </div>
  <?php endif; ?>

  <div class="form-card">
    <h3>➕ Tək Söz Əlavə Et</h3>
    <form method="POST" style="display:flex;gap:10px;align-items:flex-end">
      <input type="hidden" name="action" value="add_word">
      <div style="flex:1">
        <label>Söz (3-12 hərf, Azərbaycan əlifbası)</label>
        <input type="text" name="word" placeholder="məsələn: kitab" required>
      </div>
      <button type="submit" class="btn-save" style="margin-bottom:0">Əlavə et</button>
    </form>
  </div>

  <div class="form-card">
    <h3>📥 CSV ilə Toplu İdxal</h3>
    <div class="field-hint" style="margin-bottom:14px">Hər sətirdə 1 söz olan sadə CSV/mətn faylı yükləyin (başlıq sətri lazım deyil). Təkrar sözlər avtomatik keçilir.</div>
    <form method="POST" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:flex-end">
      <input type="hidden" name="action" value="import_csv">
      <div style="flex:1">
        <input type="file" name="csv_file" accept=".csv,.txt" required>
      </div>
      <button type="submit" class="btn-save" style="margin-bottom:0">İdxal et</button>
    </form>
  </div>

  <div class="tbl-wrap">
    <div class="tbl-top"><h3>📋 Söz Siyahısı (<?= $stats['total'] ?>)</h3></div>
    <div class="q-list">
      <?php if (!$words): ?>
      <div class="empty-row">Hələ söz əlavə edilməyib.</div>
      <?php endif; ?>
      <?php foreach ($words as $w): ?>
      <div class="q-card">
        <div class="q-main">
          <div class="q-question" style="text-transform:uppercase;letter-spacing:1px"><?= h2($w['word']) ?></div>
          <div class="q-meta"><?= mb_strlen($w['word'], 'UTF-8') ?> hərf · <?= h2(date('d.m.Y', strtotime($w['created_at']))) ?></div>
        </div>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="toggle_active">
          <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
          <button type="submit" class="btn-tog" style="<?= $w['is_active'] ? 'background:rgba(39,174,96,.15);color:#27ae60' : 'background:rgba(150,150,150,.15);color:#888' ?>">
            <?= $w['is_active'] ? '✅ Aktiv' : '⏸ Deaktiv' ?>
          </button>
        </form>
        <form method="POST" style="display:inline" onsubmit="return confirm('Bu söz silinsin?')">
          <input type="hidden" name="action" value="delete_word">
          <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
          <button type="submit" class="btn-tog" style="background:rgba(231,76,60,.15);color:#e74c3c">🗑</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?p=<?= $i ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>

  </div>
  </div>
</div>
<script>
function toggleTheme(){
  const html=document.documentElement;
  const isDark=html.getAttribute("data-theme")==="dark";
  html.setAttribute("data-theme",isDark?"light":"dark");
  localStorage.setItem("esevgili_theme",isDark?"light":"dark");
}
</script>
</body>
</html>
