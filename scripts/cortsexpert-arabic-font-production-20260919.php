<?php
$root='/home/u878466595/domains/hositee.com/public_html/corts-expert';
$font=$root.'/assets/fonts/beiruti-arabic-v5.woff2';
if(!file_exists($font) || filesize($font)<10000){fwrite(STDERR,"font_missing\n");exit(1);}
$changed=0;
$files=glob($root.'/*/index.html');
$files[]=$root.'/index.html';
foreach($files as $p){
  if(!file_exists($p)) continue;
  $dir=basename(dirname($p));
  if(str_starts_with($dir,'_')) continue;
  $s=file_get_contents($p);
  if(strpos($s,'/assets/fonts/beiruti-local-v1.woff2')===false) continue;
  @copy($p,$p.'.bak-font-v5-20260919');
  $s=str_replace('/assets/fonts/beiruti-local-v1.woff2','/assets/fonts/beiruti-arabic-v5.woff2',$s);
  file_put_contents($p,$s);
  $changed++;
}
echo 'changed='.$changed.' font_bytes='.(int)filesize($font)."\n";
?>