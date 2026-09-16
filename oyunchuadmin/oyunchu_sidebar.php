<?php
/**
 * Oyunçu Admin — Ortaq Sidebar (Oyun sistemi + Sual bankı bölmələri üçün TEK naviqasiya)
 * Bu fayl oyunchu_sual_sidebar.php ilə EYNİ dizayn sistemini
 * (oyunchu_admin.css → TELE-SMART qara/bənövşəyi tema) istifadə edir,
 * belə ki hər iki alt-panel arasında keçid edərkən dizayn və menyu dəyişmir.
 */
$base = basename($_SERVER['PHP_SELF'] ?? '');
$adminName = $admin['name'] ?? ($_SESSION['admin_full_name'] ?? $_SESSION['admin_username'] ?? $_SESSION['sd_admin_name'] ?? 'Admin');
// Sidebar-ın yuxarısındakı loqo əvvəllər hardcoded 🎮 emoji idi. İndi digər modullarla (TeleTube, TMAIL)
// eyni mərkəzi mexanizmdən (Telesmart Admin → Panel & Sayt Favicon/Loqo, page_key='oyunchu_login')
// oxunur, ona görə loqo kod dəyişmədən admin_favicon_settings.php-dən dəyişdirilə bilər.
$ohSbBrand = admin_page_header('oyunchu_login', ['logo_type' => 'none', 'logo_image_url' => '']);
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap">
<link rel="stylesheet" href="/telesmartadmin/oyunchu/oyunchu_admin.css">
<div class="sidebar" id="adminSidebar">
  <div class="sb-logo">
    <?php if ($ohSbBrand['logo_type'] === 'image' && $ohSbBrand['logo_image_url'] !== ''): ?>
      <img src="<?=h($ohSbBrand['logo_image_url'])?>" alt="Oyunçu" style="height:2rem;width:auto;object-fit:contain;filter:drop-shadow(0 0 12px rgba(200,80,106,.45))" onerror="this.onerror=null;this.replaceWith(Object.assign(document.createElement('span'),{textContent:'🎮',style:'font-size:2rem;filter:drop-shadow(0 0 12px rgba(200,80,106,.45))'}))">
    <?php else: ?>
      <span style="font-size:2rem;filter:drop-shadow(0 0 12px rgba(200,80,106,.45))">🎮</span>
    <?php endif; ?>
    <div class="sb-label">OYUNÇU · İDARƏETMƏ</div>
  </div>

  <div class="sb-profile">
    <div class="sb-profile-av"><?=strtoupper(substr($adminName,0,1))?></div>
    <div>
      <div class="sb-profile-name"><?=h($adminName)?></div>
      <div class="sb-profile-role">Admin</div>
    </div>
  </div>

  <nav class="sb-nav">
    <a href="/telesmartadmin/oyunchu/oyunchu_index.php" class="nav-item <?=$base==='oyunchu_index.php'?'active':''?>"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
    <div class="sep">Oyun sistemi</div>
    <a href="/telesmartadmin/oyunchu/oyunchu_all_games.php" class="nav-item <?=$base==='oyunchu_all_games.php'?'active':''?>"><i class="fa-solid fa-gamepad"></i> Bütün oyunlar</a>
    <a href="/telesmartadmin/oyunchu/oyunchu_retro.php" class="nav-item <?=$base==='oyunchu_retro.php'?'active':''?>"><i class="fa-solid fa-compact-disc"></i> Retro konsollar</a>
    <a href="/telesmartadmin/oyunchu/oyunchu_games.php" class="nav-item <?=$base==='oyunchu_games.php'?'active':''?>"><i class="fa-solid fa-pen-to-square"></i> Oyun qeydiyyatı</a>
    <a href="/telesmartadmin/oyunchu/oyunchu_shop.php" class="nav-item <?=$base==='oyunchu_shop.php'?'active':''?>"><i class="fa-solid fa-store"></i> Mağaza</a>
    <a href="/telesmartadmin/oyunchu/oyunchu_users.php" class="nav-item <?=$base==='oyunchu_users.php'?'active':''?>"><i class="fa-solid fa-users"></i> Oyunçu ekosistemi</a>
    <a href="/telesmartadmin/oyunchu/oyunchu_economy.php" class="nav-item <?=$base==='oyunchu_economy.php'?'active':''?>"><i class="fa-solid fa-coins"></i> İqtisadiyyat / Quest</a>
    <a href="/telesmartadmin/oyunchu/oyunchu_game_settings.php" class="nav-item <?=$base==='oyunchu_game_settings.php'?'active':''?>"><i class="fa-solid fa-sliders"></i> Oyun ayarları</a>
    <a href="/telesmartadmin/oyunchu/oyunchu_header_settings.php" class="nav-item <?=$base==='oyunchu_header_settings.php'?'active':''?>"><i class="fa-solid fa-window-maximize"></i> Üst panel</a>
    <a href="/telesmartadmin/oyunchu/oyunchu_xo_settings.php" class="nav-item <?=$base==='oyunchu_xo_settings.php'?'active':''?>"><i class="fa-solid fa-gamepad"></i> X-O ayarları</a>
    <a href="/telesmartadmin/oyunchu/oyunchu_scores.php" class="nav-item <?=$base==='oyunchu_scores.php'?'active':''?>"><i class="fa-solid fa-ranking-star"></i> Nəticələr</a>
    <a href="/telesmartadmin/oyunchu/oyunchu_game_questions.php" class="nav-item <?=$base==='oyunchu_game_questions.php'?'active':''?>"><i class="fa-solid fa-list-check"></i> Oyun sualları</a>
    <div class="sep">Sual bankı</div>
    <a href="/telesmartadmin/oyunchu/oyunchu_sual_index.php" class="nav-item <?=$base==='oyunchu_sual_index.php'?'active':''?>"><i class="fas fa-chart-line"></i> Xülasə</a>
    <a href="/telesmartadmin/oyunchu/oyunchu_sual_questions.php" class="nav-item <?=$base==='oyunchu_sual_questions.php'?'active':''?>"><i class="fas fa-circle-question"></i> Suallar</a>
    <a href="/telesmartadmin/oyunchu/oyunchu_sual_sozutap.php" class="nav-item <?=$base==='oyunchu_sual_sozutap.php'?'active':''?>"><i class="fas fa-font"></i> Sözü Tap / Söz Yağışı</a>
    <a href="/telesmartadmin/oyunchu/oyunchu_sual_ads.php" class="nav-item <?=$base==='oyunchu_sual_ads.php'?'active':''?>"><i class="fa-solid fa-rectangle-ad"></i> Reklamlar</a>
    <a href="/telesmartadmin/oyunchu/oyunchu_sual_settings.php" class="nav-item <?=$base==='oyunchu_sual_settings.php'?'active':''?>"><i class="fas fa-gear"></i> Tənzimləmələr</a>
    <div class="sep">Sistem</div>
    <a href="/telesmartadmin/admin_panel.php" class="nav-item"><i class="fa-solid fa-arrow-left"></i> Əsas Admin Panel</a>
  </nav>

  <div class="sb-bottom">
    <?php if (!empty($_SESSION['sd_admin_id']) && empty($_SESSION['admin_logged_in'])): ?>
    <a href="/telesmartadmin/auth/oyunchu_logout.php" class="nav-item" style="color:rgba(255,100,100,.85)"><i class="fas fa-right-from-bracket"></i> Çıxış</a>
    <?php else: ?>
    <a href="/telesmartadmin/auth/admin_logout.php" class="nav-item" style="color:rgba(255,100,100,.85)"><i class="fas fa-right-from-bracket"></i> Çıxış</a>
    <?php endif; ?>
  </div>
</div>

<link rel="stylesheet" href="/telesmartadmin/assets/mobile_nav.css">
<script src="/telesmartadmin/assets/mobile_nav.js"></script>
