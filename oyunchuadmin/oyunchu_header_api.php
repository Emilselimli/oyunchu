<?php
require_once __DIR__ . '/oyunchu_sual_config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$keys = [
 'oyunchu_logo'=>'🎮', 'oyunchu_header_show_text'=>'0', 'oyunchu_header_title'=>'',
 'oyunchu_header_nav_games'=>'Oyunlar', 'oyunchu_header_nav_leaders'=>'Liderlər',
 'oyunchu_header_nav_tour'=>'Turnir', 'oyunchu_header_nav_shop'=>'Mağaza',
 'oyunchu_header_bg'=>'#100b12', 'oyunchu_header_text'=>'#f2edf5',
 'oyunchu_header_muted'=>'#8f7e95', 'oyunchu_header_accent'=>'#c8506a',
 'oyunchu_header_border'=>'#2b1d32', 'oyunchu_header_height'=>'58',
 'oyunchu_header_mobile_text'=>'0'
];
$out=[]; foreach($keys as $k=>$d){$out[$k]=getSetting($k,$d);}
$out['show_text']=(bool)((int)$out['oyunchu_header_show_text']);
$out['mobile_show_text']=(bool)((int)$out['oyunchu_header_mobile_text']);
$out['height']=(int)$out['oyunchu_header_height'];
$out['ok']=true;
echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
