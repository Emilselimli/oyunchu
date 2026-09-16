<?php
require __DIR__.'/oyunchu_config.php';
$baseFs=dirname(__DIR__,2).'/games/oyunchu';
$romDir=$docRoot.'/games/oyunchu/roms/sega'; $coverDir=$docRoot.'/games/oyunchu/retro_covers/sega';
$romUrl='/games/oyunchu/roms/sega/'; $coverUrl='/games/oyunchu/retro_covers/sega/';
@mkdir($romDir,0775,true); @mkdir($coverDir,0775,true);
$romExt=['bin','md','gen','smd','zip']; $coverExt=['png','jpg','jpeg','webp'];
$msg='';$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 ga_verify(); $action=$_POST['action']??'';
 if($action==='save'){
  $id=(int)($_POST['id']??0);$title=trim($_POST['title']??'');$core=trim($_POST['core']??'genesis_plus_gx');$sort=(int)($_POST['sort_order']??0);$active=!empty($_POST['is_active'])?1:0;
  if($title===''){$err='Oyun adı boş ola bilməz.';}else{
   try{
    $slug='sega-'.preg_replace('/[^a-z0-9]+/','-',strtolower(iconv('UTF-8','ASCII//TRANSLIT',$title)?:$title)).'-'.substr(bin2hex(random_bytes(3)),0,6);
    if($id){$q=$conn->prepare('UPDATE oh_sega_games SET title=?,core=?,sort_order=?,is_active=? WHERE id=?');$q->bind_param('ssiii',$title,$core,$sort,$active,$id);$q->execute();$msg='SEGA oyunu yadda saxlanıldı.';ga_audit('oyunchu_sega_save','oh_sega_games',$id,$title);}
    else{
      if(empty($_FILES['rom_file'])||$_FILES['rom_file']['error']!==UPLOAD_ERR_OK){throw new RuntimeException('ROM faylı seçilməyib.');}
      $rf=$_FILES['rom_file'];$re=strtolower(pathinfo($rf['name'],PATHINFO_EXTENSION));
      if(!in_array($re,$romExt,true))throw new RuntimeException('ROM formatı dəstəklənmir. BIN, MD, GEN, SMD və ZIP istifadə edin.');
      if((int)$rf['size']>52428800)throw new RuntimeException('ROM maksimum 50 MB ola bilər.');
      $romName=$slug.'.'.$re;$romTarget=$romDir.'/'.$romName;if(!is_dir($romDir)&&!@mkdir($romDir,0775,true))throw new RuntimeException('ROM qovluğu yaradıla bilmədi: /games/oyunchu/roms/sega/');if(!is_writable($romDir))throw new RuntimeException('ROM qovluğuna yazma icazəsi yoxdur: /games/oyunchu/roms/sega/');if(!move_uploaded_file($rf['tmp_name'],$romTarget))throw new RuntimeException('ROM /games/oyunchu/roms/sega/ qovluğunda saxlanılmadı. PHP upload_tmp_dir və yazma icazəsini yoxlayın.');
      $coverPath=null;
      if(!empty($_FILES['cover_file'])&&$_FILES['cover_file']['error']===UPLOAD_ERR_OK){$cf=$_FILES['cover_file'];$ce=strtolower(pathinfo($cf['name'],PATHINFO_EXTENSION));if(in_array($ce,$coverExt,true)&&$cf['size']<=2097152){$coverName=$slug.'.'.$ce;$coverTarget=$coverDir.'/'.$coverName;if(move_uploaded_file($cf['tmp_name'],$coverTarget))$coverPath=$coverUrl.$coverName;}}
      $romPath=$romUrl.$romName;$q=$conn->prepare('INSERT INTO oh_sega_games(title,slug,cover_path,rom_path,core,sort_order,is_active) VALUES(?,?,?,?,?,?,?)');$q->bind_param('ssssiii',$title,$slug,$coverPath,$romPath,$core,$sort,$active);$q->execute();$newId=$conn->insert_id;$msg='SEGA oyunu yükləndi.';ga_audit('oyunchu_sega_upload','oh_sega_games',$newId,$title);
    }
   }catch(Throwable $e){$err=$e->getMessage();}
  }
 }elseif($action==='delete'){
  $id=(int)($_POST['id']??0);$q=$conn->prepare('SELECT rom_path,cover_path,title FROM oh_sega_games WHERE id=?');$q->bind_param('i',$id);$q->execute();$g=$q->get_result()->fetch_assoc();
  if($g){$q=$conn->prepare('DELETE FROM oh_sega_games WHERE id=?');$q->bind_param('i',$id);$q->execute();foreach([$g['rom_path'],$g['cover_path']] as $url){if($url){$path=''; if(str_starts_with($url,'/games/oyunchu/')) $path=$baseFs.'/'.ltrim(substr($url,strlen('/games/oyunchu/')),'/'); if($path&&is_file($path))@unlink($path);}}$msg='SEGA oyunu silindi.';ga_audit('oyunchu_sega_delete','oh_sega_games',$id,$g['title']);}
 }
}
$rows=$conn->query('SELECT * FROM oh_sega_games ORDER BY sort_order,id')->fetch_all(MYSQLI_ASSOC);
?><!doctype html><html lang="az" data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SEGA oyunları</title><script>document.documentElement.setAttribute('data-theme',localStorage.getItem('esevgili_theme')||'dark');</script></head><body><?php include __DIR__.'/oyunchu_sidebar.php';?><main class="ga-main"><div class="ga-top"><b>🕹️ SEGA / Retro Oyunlar</b><a class="ga-btn ga-mutedbtn" href="/games/oyunchu/sega.php" target="_blank">İstifadəçi səhifəsi</a></div><div class="ga-content">
<?php if($msg):?><div class="ga-alert">✅ <?=ga_h($msg)?></div><?php endif;?><?php if($err):?><div class="ga-alert" style="border-color:#e74c3c">❌ <?=ga_h($err)?></div><?php endif;?>
<div class="ga-card"><h3>➕ Yeni SEGA oyunu yüklə</h3><p class="ga-muted">ROM yalnız hüququnuz olan və ya qanuni şəkildə yaydığınız fayl olmalıdır. İstifadəçi gündə 10 pulsuz cəhd edir; 10-dan sonra hər əlavə cəhd 500 coin-dir.</p><form method="post" enctype="multipart/form-data" class="ga-form"><?=csrf_field()?><input type="hidden" name="action" value="save"><div><label class="ga-label">Oyun adı</label><input class="ga-input" name="title" placeholder="Məs: Sonic The Hedgehog" required></div><div><label class="ga-label">SEGA sistemi</label><select class="ga-input" name="core"><option value="genesis_plus_gx">Mega Drive / Genesis</option><option value="smsplus">Master System</option></select></div><div><label class="ga-label">ROM</label><input class="ga-input" type="file" name="rom_file" accept=".bin,.md,.gen,.smd,.zip" required></div><div><label class="ga-label">Örtük şəkli</label><input class="ga-input" type="file" name="cover_file" accept=".png,.jpg,.jpeg,.webp"></div><div><label class="ga-label">Sıra</label><input class="ga-input" type="number" name="sort_order" value="0"></div><div style="padding-top:24px"><label><input type="checkbox" name="is_active" checked> Aktiv</label></div><div class="full"><button class="ga-btn ga-primary">SEGA oyununu yüklə</button></div></form></div><br>
<?php foreach($rows as $r):?><div class="ga-card" style="margin-bottom:14px"><form method="post" class="ga-form"><?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$r['id']?>"><div><label class="ga-label">Oyun</label><div style="font-size:18px;font-weight:800">🕹️ <?=ga_h($r['title'])?></div><div class="ga-muted">ROM: <?=ga_h($r['rom_path'])?></div></div><div><label class="ga-label">Ad</label><input class="ga-input" name="title" value="<?=ga_h($r['title'])?>"></div><div><label class="ga-label">Core</label><select class="ga-input" name="core"><option value="genesis_plus_gx" <?=$r['core']==='genesis_plus_gx'?'selected':''?>>Mega Drive / Genesis</option><option value="smsplus" <?=$r['core']==='smsplus'?'selected':''?>>Master System</option></select></div><div><label class="ga-label">Sıra</label><input class="ga-input" type="number" name="sort_order" value="<?=$r['sort_order']?>"></div><div style="padding-top:24px"><label><input type="checkbox" name="is_active" <?=$r['is_active']?'checked':''?>> Aktiv</label></div><div class="full"><button class="ga-btn ga-primary">Yadda saxla</button> <a class="ga-btn ga-mutedbtn" href="/games/oyunchu/sega.php?game=<?=$r['id']?>" target="_blank">Oyunu aç</a></div></form><form method="post" style="margin-top:10px" onsubmit="return confirm('Bu SEGA oyununu silmək istəyirsiniz?')"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="ga-btn" style="background:#7f1d1d;color:#fff">Sil</button></form></div><?php endforeach;?>
</div></main></body></html>
