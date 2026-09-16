<?php
require_once __DIR__ . '/oyunchu_sual_config.php';
$admin = requireAdmin();

function ohs_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$defaults = [
    'oyunchu_header_logo'       => '🎮',
    'oyunchu_header_show_text'   => '0',
    'oyunchu_header_title'       => '',
    'oyunchu_header_nav_games'   => 'Oyunlar',
    'oyunchu_header_nav_leaders' => 'Liderlər',
    'oyunchu_header_nav_tour'    => 'Turnir',
    'oyunchu_header_nav_shop'    => 'Mağaza',
    'oyunchu_header_bg'          => '#100b12',
    'oyunchu_header_text'        => '#f2edf5',
    'oyunchu_header_muted'       => '#8f7e95',
    'oyunchu_header_accent'      => '#c8506a',
    'oyunchu_header_border'      => '#2b1d32',
    'oyunchu_header_height'      => '58',
    'oyunchu_header_mobile_text' => '0',
];

foreach ($defaults as $k => $v) { $defaults[$k] = getSetting($k, $v); }
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF səhvini 500 kimi göstərməmək üçün əvvəlcə tokeni yoxla.
    // Bu səhifə oyunchu_sual_config.php istifadə edir; burada ga_verify() yoxdur.
    // Ortaq CSRF yoxlamasını birbaşa əsas config.php-dəki csrf_verify() ilə et.
    if (function_exists('csrf_verify')) {
        csrf_verify(false);
    } elseif (function_exists('sd_csrf_check')) {
        if (!sd_csrf_check()) { http_response_code(403); exit('CSRF yoxlaması uğursuz oldu.'); }
    } else {
        http_response_code(500); exit('CSRF yoxlama funksiyası tapılmadı.');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'save_header' || $action === 'save_header_logo') {
        try {
            // es_settings cədvəlini bu sorğunun özündə də təmin et.
            // setSetting() daxilindəki səssiz catch səbəbindən DB xətaları gizlənməsin.
            $pdo = db();
            $pdo->exec("CREATE TABLE IF NOT EXISTS es_settings (
                skey VARCHAR(100) NOT NULL PRIMARY KEY,
                svalue TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $bools = ['oyunchu_header_show_text','oyunchu_header_mobile_text'];
            $colorKeys = ['oyunchu_header_bg','oyunchu_header_text','oyunchu_header_muted','oyunchu_header_accent','oyunchu_header_border'];
            $save = $pdo->prepare("INSERT INTO es_settings (skey, svalue) VALUES (?, ?)
                                   ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)");

            $keysToSave = $action === 'save_header_logo' ? ['oyunchu_header_logo'] : array_keys($defaults);
            foreach ($keysToSave as $key) {
                $old = $defaults[$key] ?? '';
                if (in_array($key, $bools, true)) {
                    $v = isset($_POST[$key]) ? '1' : '0';
                } else {
                    $v = trim((string)($_POST[$key] ?? ''));
                    if ($key === 'oyunchu_header_logo') {
                        // Emoji, absolute path və ya tam URL qəbul edilir.
                        if ($v === '') $v = $old;
                        if (strlen($v) > 500) $v = $old;
                    }
                    if ($key === 'oyunchu_header_height') {
                        $v = (string)max(44, min(90, (int)$v));
                    }
                    if (in_array($key, $colorKeys, true) && !preg_match('/^#[0-9a-fA-F]{6}$/', $v)) {
                        $v = $old;
                    }
                }
                $save->execute([$key, $v]);
            }

            // Audit uğursuz olsa belə əsas save əməliyyatını pozmasın.
            if (function_exists('ga_audit')) ga_audit('oyunchu_header_update', 'es_settings', null, 'Oyunçu üst paneli yeniləndi');
            $msg = 'Üst panel ayarları yadda saxlanıldı.';
            foreach ($defaults as $k => $v) $defaults[$k] = getSetting($k, $v);
        } catch (Throwable $e) {
            error_log('oyunchu_header_settings save failed: ' . $e->getMessage());
            $msg = 'Yadda saxlama xətası: ' . $e->getMessage();
        }
    }
}

$logo = getSetting('oyunchu_header_logo', '🎮');
$pageActive = 'header_settings';
?>
<!doctype html><html lang="az" data-theme="dark"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Oyunçu üst paneli</title>
<link rel="stylesheet" href="/telesmartadmin/oyunchu/oyunchu_admin.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
.header-preview{border:1px solid var(--border);border-radius:16px;overflow:hidden;background:#0b080c;margin-bottom:20px}
.preview-top{height:58px;display:flex;align-items:center;justify-content:space-between;padding:0 18px;border-bottom:1px solid #2b1d32}
.preview-brand{display:flex;align-items:center;gap:12px;min-width:150px}.preview-brand img{height:32px;width:auto;object-fit:contain}.preview-brand b{font-size:15px}.preview-nav{display:flex;gap:8px}.preview-nav span{padding:8px 12px;border-radius:8px;font-size:12px}.preview-nav .active{background:rgba(200,80,106,.16);color:#c8506a}.preview-right{font-size:12px;opacity:.85}
.form-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:18px}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.full{grid-column:1/-1}.hint{font-size:.74rem;color:var(--text3);line-height:1.5;margin-top:5px}.save{padding:11px 20px;border:0;border-radius:10px;background:#c8506a;color:#fff;font-weight:700;cursor:pointer}.msg{padding:12px 15px;border-radius:10px;background:#d9f6e5;color:#145a32;margin-bottom:16px;font-weight:700}.check{display:flex;align-items:center;gap:9px;padding:10px 0}.check input{width:18px;height:18px}
@media(max-width:760px){.form-grid{grid-template-columns:1fr}.preview-nav{display:none}.preview-top{padding:0 12px}.preview-right{display:none}}
</style></head><body>
<?php include __DIR__ . '/oyunchu_sidebar.php'; ?>
<main class="ga-main">
<div class="ga-top"><b>🎛️ Oyunçu üst paneli</b></div>
<div class="ga-content">
<?php if ($msg): ?><div class="msg">✅ <?=ohs_h($msg)?></div><?php endif; ?>
<div class="header-preview">
  <div class="preview-top" id="previewTop">
    <div class="preview-brand">
      <?php if (preg_match('#^(https?://|/)#i', $logo)): ?><img src="<?=ohs_h($logo)?>" alt="">
      <?php else: ?><span style="font-size:28px"><?=ohs_h($logo)?></span><?php endif; ?>
      <b id="previewTitle" style="<?= $defaults['oyunchu_header_show_text'] === '1' ? '' : 'display:none' ?>"><?=ohs_h($defaults['oyunchu_header_title'])?></b>
    </div>
    <div class="preview-nav"><span class="active"><?=ohs_h($defaults['oyunchu_header_nav_games'])?></span><span><?=ohs_h($defaults['oyunchu_header_nav_leaders'])?></span><span><?=ohs_h($defaults['oyunchu_header_nav_tour'])?></span><span><?=ohs_h($defaults['oyunchu_header_nav_shop'])?></span></div>
    <div class="preview-right">🪙 12994&nbsp;&nbsp; 👑</div>
  </div>
</div>

<div class="form-card">
<h3 style="margin-bottom:14px">Üst panel loqosu</h3>
<p class="hint" style="margin-bottom:16px">Bu loqo yalnız <b>Oyunçu üst paneli</b> üçün ayrıdır. Buraya emoji, server yolu (məs. <code>/telesmart-img/oyunchu/logo.png</code>) və ya tam şəkil URL-si yaza bilərsən.</p>
<div style="display:flex;align-items:center;gap:16px;margin-bottom:14px">
  <div style="width:72px;height:52px;border:1px solid var(--border);border-radius:10px;display:flex;align-items:center;justify-content:center;background:#0b080c;overflow:hidden">
    <?php if (preg_match('#^(https?://|/)#i', $logo)): ?><img src="<?=ohs_h($logo)?>" alt="" style="max-width:64px;max-height:44px;object-fit:contain"><?php else: ?><span style="font-size:30px"><?=ohs_h($logo)?></span><?php endif; ?>
  </div>
  <div class="hint" style="margin:0">Cari üst panel loqosu</div>
</div>
<form method="post"><input type="hidden" name="action" value="save_header_logo"><?=csrf_field()?>
<label>Oyunçu üst panel loqosu</label>
<input class="ga-input" type="text" name="oyunchu_header_logo" value="<?=ohs_h($logo)?>" placeholder="🎮 və ya /telesmart-img/oyunchu/logo.png">
<div class="hint">Bu sahə digər oyunların loqosunu və Oyunçu səhifəsinin əsas loqosunu dəyişmir.</div>
<div style="margin-top:14px"><button class="save" type="submit">💾 Loqonu yadda saxla</button></div>
</form>
</div>

<div class="form-card">
<h3 style="margin-bottom:14px">Üst panel görünüşü</h3>
<p class="hint" style="margin-bottom:16px">Bu ayarlar Oyunçu veb başlığının desktop və mobil görünüşü üçün mərkəzi ayarlardır. <b>"Oyunçu" yazısı default olaraq söndürülüb</b>; loqo tək göstəriləcək.</p>
<form method="post"><input type="hidden" name="action" value="save_header"><?=csrf_field()?>
<div class="form-grid">
<div class="full check"><input type="checkbox" id="showText" name="oyunchu_header_show_text" <?= $defaults['oyunchu_header_show_text']==='1'?'checked':'' ?>><label for="showText" style="margin:0">Loqonun yanında yazı göstərilsin</label></div>
<div><label>Başlıq mətni</label><input class="ga-input" id="headerTitle" type="text" name="oyunchu_header_title" value="<?=ohs_h($defaults['oyunchu_header_title'])?>" placeholder="Boş saxla — yalnız loqo"></div>
<div><label>Panel hündürlüyü (px)</label><input class="ga-input" type="number" min="44" max="90" name="oyunchu_header_height" value="<?=ohs_h($defaults['oyunchu_header_height'])?>"></div>
<div><label>Oyunlar menyusu</label><input class="ga-input" type="text" name="oyunchu_header_nav_games" value="<?=ohs_h($defaults['oyunchu_header_nav_games'])?>"></div>
<div><label>Liderlər menyusu</label><input class="ga-input" type="text" name="oyunchu_header_nav_leaders" value="<?=ohs_h($defaults['oyunchu_header_nav_leaders'])?>"></div>
<div><label>Turnir menyusu</label><input class="ga-input" type="text" name="oyunchu_header_nav_tour" value="<?=ohs_h($defaults['oyunchu_header_nav_tour'])?>"></div>
<div><label>Mağaza menyusu</label><input class="ga-input" type="text" name="oyunchu_header_nav_shop" value="<?=ohs_h($defaults['oyunchu_header_nav_shop'])?>"></div>
<div><label>Panel fon rəngi</label><input class="ga-input" type="color" name="oyunchu_header_bg" value="<?=ohs_h($defaults['oyunchu_header_bg'])?>"></div>
<div><label>Əsas yazı rəngi</label><input class="ga-input" type="color" name="oyunchu_header_text" value="<?=ohs_h($defaults['oyunchu_header_text'])?>"></div>
<div><label>Səssiz yazı rəngi</label><input class="ga-input" type="color" name="oyunchu_header_muted" value="<?=ohs_h($defaults['oyunchu_header_muted'])?>"></div>
<div><label>Vurğu rəngi</label><input class="ga-input" type="color" name="oyunchu_header_accent" value="<?=ohs_h($defaults['oyunchu_header_accent'])?>"></div>
<div><label>Alt xətt / border rəngi</label><input class="ga-input" type="color" name="oyunchu_header_border" value="<?=ohs_h($defaults['oyunchu_header_border'])?>"></div>
<div class="full check"><input type="checkbox" id="mobileText" name="oyunchu_header_mobile_text" <?= $defaults['oyunchu_header_mobile_text']==='1'?'checked':'' ?>><label for="mobileText" style="margin:0">Mobil versiyada da başlıq yazısını göstər</label></div>
<div class="full"><button class="save" type="submit">💾 Üst paneli yadda saxla</button></div>
</div></form>
</div>

</div></main>
<script>
const showText=document.getElementById('showText'), title=document.getElementById('headerTitle'), preview=document.getElementById('previewTitle');
function sync(){ preview.textContent=title.value; preview.style.display=showText.checked?'':'none'; }
showText.addEventListener('change',sync); title.addEventListener('input',sync);
</script></body></html>
