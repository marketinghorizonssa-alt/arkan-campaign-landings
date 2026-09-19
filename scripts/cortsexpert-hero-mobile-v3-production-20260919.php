<?php
$root='/home/u878466595/domains/hositee.com/public_html/corts-expert';
$src=$root.'/assets/hero-mobile.jpg';
$dst=$root.'/assets/hero-mobile-v3.jpg';
if(!file_exists($dst)){
  $im=@imagecreatefromjpeg($src);
  if(!$im){fwrite(STDERR,"image_read_failed\n");exit(1);}
  imageinterlace($im,true);
  imagejpeg($im,$dst,58);
  imagedestroy($im);
}
$changed=0;
foreach(glob($root.'/*/index.html') as $p){
  $dir=basename(dirname($p));
  if(str_starts_with($dir,'_')) continue;
  $s=file_get_contents($p);
  if(strpos($s,'/assets/hero-mobile.jpg')===false) continue;
  @copy($p,$p.'.bak-hero-v3-20260919');
  $s=str_replace('/assets/hero-mobile.jpg','/assets/hero-mobile-v3.jpg',$s);
  file_put_contents($p,$s);
  $changed++;
}
$p=$root.'/index.html';
if(file_exists($p)){
  $s=file_get_contents($p);
  if(strpos($s,'/assets/hero-mobile.jpg')!==false){
    @copy($p,$p.'.bak-hero-v3-20260919');
    $s=str_replace('/assets/hero-mobile.jpg','/assets/hero-mobile-v3.jpg',$s);
    file_put_contents($p,$s);
    $changed++;
  }
}
echo 'changed='.$changed.' hero_bytes='.(int)filesize($dst)."\n";
?>