<?php
require_once __DIR__ . '/oyunchu_sual_config.php';
$admin = requireAdmin();

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
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
    header('Location: /telesmartadmin/oyunchu/oyunchu_sual_ads.php' . ($msg ? '?msg=' . urlencode($msg) : ''));
    exit;
}

if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

$adsProvider = getSetting('oh_ads_provider', 'none');
$adsenseClient = getSetting('oh_adsense_client', '');
$adsenseSlotBanner = getSetting('oh_adsense_slot_banner', '');
$adsenseSlotRewarded = getSetting('oh_adsense_slot_rewarded', '');
$admobRewardedUnit = getSetting('oh_admob_rewarded_unit', '');
$adRewardCoins = (int)getSetting('oh_ad_reward_coins', 5);
$adCooldownMin = (int)getSetting('oh_ad_cooldown_min', 3);

function h2($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html><html lang="az" data-theme="dark"><head>
<meta charset="UTF-8"><?= admin_panel_favicon_tag(); ?><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reklam Ayarları – Oyunların Sualları Admin</title>
<script>const t=localStorage.getItem('esevgili_theme')||'light';document.documentElement.setAttribute('data-theme',t);</script>
<link rel="stylesheet" href="/telesmartadmin/oyunchu/oyunchu_admin.css">
<style>
.form-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:22px;margin-bottom:22px}
.form-card h3{font-size:.95rem;font-weight:800;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.form-row{display:grid;gap:12px;margin-bottom:12px}
.form-row.cols2{grid-template-columns:1fr 1fr}
label{font-size:.78rem;font-weight:700;color:var(--text2);display:block;margin-bottom:4px}
input[type=text],input[type=number],select{width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;background:var(--input-bg);color:var(--text);font-size:.86rem;outline:none}
input:focus,select:focus{border-color:var(--rose)}
.btn-save{padding:10px 24px;background:var(--rose);color:#fff;border:none;border-radius:10px;font-size:.87rem;font-weight:700;cursor:pointer}
.btn-save:hover{opacity:.85}
.msg-bar{padding:12px 18px;border-radius:12px;margin-bottom:18px;font-size:.85rem;font-weight:700;background:#d4f5e2;color:#145a32;border:1px solid #a9dfbf}
.field-hint{font-size:.72rem;color:var(--text2);margin-top:4px}
@media(max-width:700px){.form-row.cols2{grid-template-columns:1fr}}
</style>
</head><body>
<?php include __DIR__ . '/oyunchu_sual_sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <div class="topbar-title">📢 Reklam Ayarları</div>
    <div class="topbar-right">
      <button class="theme-btn" onclick="toggleTheme()">🌙</button>
      <span class="admin-chip">⚙ <?=h2($admin['name'])?></span>
    </div>
  </div>
  <div class="content">
    <?php if ($msg): ?><div class="msg-bar"><?=$msg?></div><?php endif; ?>
    <div class="form-card" style="border:1.5px solid var(--gold)">
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
          <select name="oh_ads_provider">
            <option value="none" <?=$adsProvider==='none'?'selected':''?>>Yoxdur (reklam bölməsi gizli qalır)</option>
            <option value="adsense" <?=$adsProvider==='adsense'?'selected':''?>>Google AdSense (veb H5 reklamlar)</option>
            <option value="twa_admob" <?=$adsProvider==='twa_admob'?'selected':''?>>AdMob (yalnız Android TWA tətbiqi daxilində)</option>
          </select>
        </div>
        <div class="form-row cols2">
          <div><label>AdSense Client ID</label><input type="text" name="oh_adsense_client" value="<?=h2($adsenseClient)?>" placeholder="ca-pub-xxxxxxxxxxxxxxxx"></div>
          <div><label>AdSense Rewarded Slot ID</label><input type="text" name="oh_adsense_slot_rewarded" value="<?=h2($adsenseSlotRewarded)?>" placeholder="1234567890"></div>
        </div>
        <div class="form-row cols2">
          <div><label>AdSense Banner Slot ID</label><input type="text" name="oh_adsense_slot_banner" value="<?=h2($adsenseSlotBanner)?>" placeholder="0987654321"></div>
          <div><label>AdMob Rewarded Unit ID (TWA)</label><input type="text" name="oh_admob_rewarded_unit" value="<?=h2($admobRewardedUnit)?>" placeholder="ca-app-pub-xxx/yyy"></div>
        </div>
        <div class="form-row cols2">
          <div><label>Reklam mükafatı (coin)</label><input type="number" name="oh_ad_reward_coins" value="<?=$adRewardCoins?>" min="1"></div>
          <div><label>Növbəti reklama qədər gözləmə (dəqiqə)</label><input type="number" name="oh_ad_cooldown_min" value="<?=$adCooldownMin?>" min="1"></div>
        </div>
        <button type="submit" class="btn-save">💾 Reklam ayarlarını yadda saxla</button>
      </form>
    </div>
  </div>
</div>
<script>function toggleTheme(){const d=document.documentElement,n=d.getAttribute('data-theme')==='dark'?'light':'dark';d.setAttribute('data-theme',n);localStorage.setItem('esevgili_theme',n);}</script>
</body></html>
