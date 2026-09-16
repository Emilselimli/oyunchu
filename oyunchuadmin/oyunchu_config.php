<?php
require_once __DIR__ . '/../config.php';
// Əsas admin panel girişi (admin_logged_in) VƏ ya Oyunçu girişi (sd_admin_id) — hər ikisi qəbul olunur,
// belə ki oyunchu_sual_* səhifələrindən gələn admin bura yenidən giriş etmədən keçə bilsin.
if ((empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) && empty($_SESSION['sd_admin_id'])) { header('Location: /telesmartadmin/auth/admin_login.php'); exit; }
$conn = getDBConnection(); $conn->set_charset('utf8mb4');
function ga_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
// Ortaq sidebar (oyunchu_sidebar.php) h() funksiyasını istifadə edir — bu, əvvəllər
// yalnız oyunchu_sual_config.php-də təyin olunurdu, ona görə oyun sistemi səhifələrində
// (bu fayl vasitəsilə yüklənəndə) "Call to undefined function h()" fatal xətası yaranırdı
// və səhifə sidebar-ın ortasında dayanırdı. Burada da təyin edərək hər iki tərəfi uyğunlaşdırırıq.
if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
function ga_verify(): void { csrf_verify(false); }
function ga_audit(string $a,string $t='', $id=null,string $d=''): void { global $conn; if(function_exists('admin_log_action')) admin_log_action($conn,$a,$t,$id,$d); }
function ga_schema(): void { global $conn; $sqls=[
"CREATE TABLE IF NOT EXISTS oh_games (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,slug VARCHAR(30) UNIQUE NOT NULL,path VARCHAR(255) NOT NULL,name_key VARCHAR(60) NOT NULL,logo_key VARCHAR(60) NOT NULL,default_name VARCHAR(100) NOT NULL,default_logo VARCHAR(255) NOT NULL,color VARCHAR(20) NOT NULL DEFAULT '#999999',score_table VARCHAR(60) DEFAULT NULL,score_col VARCHAR(30) DEFAULT 'score',sort_order INT DEFAULT 0,is_active TINYINT(1) DEFAULT 1,created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS oh_shop_items (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,item_key VARCHAR(30) UNIQUE NOT NULL,category VARCHAR(20) NOT NULL DEFAULT 'frame',name VARCHAR(100) NOT NULL,price INT UNSIGNED NOT NULL DEFAULT 0,style VARCHAR(255) NOT NULL,premium_only TINYINT(1) DEFAULT 0,sort_order INT DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS oh_user_items (user_id INT UNSIGNED NOT NULL,item_key VARCHAR(30) NOT NULL,equipped TINYINT(1) DEFAULT 0,purchased_at DATETIME DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(user_id,item_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS oh_push_subscriptions (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,endpoint VARCHAR(500) NOT NULL,p256dh VARCHAR(255) NOT NULL,auth_key VARCHAR(255) NOT NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uniq_endpoint(endpoint(255)),KEY idx_user(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS oh_challenges (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,challenger_id INT UNSIGNED NOT NULL,game_slug VARCHAR(30) NOT NULL,score_to_beat INT NOT NULL DEFAULT 0,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,KEY idx_challenger(challenger_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS oh_quest_claims (user_id INT UNSIGNED NOT NULL,quest_key VARCHAR(40) NOT NULL,claim_date DATE NOT NULL,PRIMARY KEY(user_id,quest_key,claim_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS oh_daily_login (user_id INT UNSIGNED PRIMARY KEY,streak INT UNSIGNED NOT NULL DEFAULT 0,last_claim_date DATE DEFAULT NULL,total_claims INT UNSIGNED NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS oh_referral_codes (user_id INT UNSIGNED PRIMARY KEY,code VARCHAR(12) UNIQUE NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS oh_referral_log (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,referrer_id INT UNSIGNED NOT NULL,referred_id INT UNSIGNED NOT NULL,rewarded TINYINT(1) DEFAULT 0,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uniq_referred(referred_id),KEY idx_referrer(referrer_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS oh_premium_requests (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,status ENUM('pending','approved','rejected') DEFAULT 'pending',created_at DATETIME DEFAULT CURRENT_TIMESTAMP,KEY idx_user(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS oh_ad_rewards (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,coins INT UNSIGNED NOT NULL,watched_at DATETIME DEFAULT CURRENT_TIMESTAMP,KEY idx_user_time(user_id,watched_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" ]; foreach($sqls as $s){try{$conn->query($s);}catch(Throwable $e){error_log($e->getMessage());}} }
ga_schema();
try{
$conn->query("CREATE TABLE IF NOT EXISTS oh_retro_consoles (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,slug VARCHAR(40) UNIQUE NOT NULL,name VARCHAR(100) NOT NULL,logo VARCHAR(255) DEFAULT '🎮',description VARCHAR(255) DEFAULT NULL,core VARCHAR(60) NOT NULL,sort_order INT NOT NULL DEFAULT 0,is_active TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS oh_retro_games (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,console_id INT UNSIGNED NOT NULL,title VARCHAR(160) NOT NULL,slug VARCHAR(180) UNIQUE NOT NULL,cover_path VARCHAR(255) DEFAULT NULL,rom_path VARCHAR(500) NOT NULL,core VARCHAR(60) NOT NULL,sort_order INT NOT NULL DEFAULT 0,is_active TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,KEY idx_console(console_id),KEY idx_active(is_active)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS oh_retro_meta (meta_key VARCHAR(64) PRIMARY KEY, meta_value VARCHAR(255) DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$initialized=$conn->query("SELECT meta_value FROM oh_retro_meta WHERE meta_key='initialized' LIMIT 1")->fetch_assoc();
if(!$initialized){
$seed=[['sega','SEGA','🕹️','Mega Drive / Genesis və Master System','genesis_plus_gx',10],['dendy','Dendy','🎮','NES uyğun klassik oyunlar','nestopia',20],['nes','Nintendo NES','🟥','Nintendo Entertainment System','nestopia',30],['snes','Super Nintendo','🟪','Super Nintendo Entertainment System','snes9x',40],['playstation','Sony PlayStation','🔵','PlayStation 1 klassikləri','pcsx_rearmed',50],['gameboy','Game Boy','🟩','Game Boy / Game Boy Color','gambatte',60],['arcade','Arcade','👾','Klassik arcade oyunları','mame2003_plus',70],['atari2600','Atari 2600','🟫','Atari 2600 klassikləri','stella2014',80]];
if((int)($conn->query("SELECT COUNT(*) AS n FROM oh_retro_consoles")->fetch_assoc()['n']??0)===0){$qi=$conn->prepare("INSERT IGNORE INTO oh_retro_consoles(slug,name,logo,description,core,sort_order) VALUES(?,?,?,?,?,?)");foreach($seed as $r){$qi->bind_param('sssssi',$r[0],$r[1],$r[2],$r[3],$r[4],$r[5]);$qi->execute();}}
$conn->query("INSERT INTO oh_retro_meta(meta_key,meta_value) VALUES ('initialized','1')");
}
}catch(Throwable $e){error_log('Retro schema: '.$e->getMessage());}
try{$conn->query("CREATE TABLE IF NOT EXISTS oh_sega_games (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(120) NOT NULL,slug VARCHAR(140) UNIQUE NOT NULL,cover_path VARCHAR(255) DEFAULT NULL,rom_path VARCHAR(255) NOT NULL,core VARCHAR(40) NOT NULL DEFAULT 'genesis_plus_gx',sort_order INT NOT NULL DEFAULT 0,is_active TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");}catch(Throwable $e){error_log($e->getMessage());}

