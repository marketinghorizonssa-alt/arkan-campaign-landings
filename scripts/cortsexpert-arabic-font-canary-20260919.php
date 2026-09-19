<?php
$root='/home/u878466595/domains/hositee.com/public_html/corts-expert';
$font=$root.'/assets/fonts/beiruti-arabic-v5.woff2';
if(!file_exists($font) || filesize($font)<10000){
  $data=@file_get_contents('https://fonts.gstatic.com/s/beiruti/v5/JTUXjIU69Cmr9FGcSA1t4FZA.woff2');
  if($data===false){fwrite(STDERR,"font_download_failed\n");exit(1);}
  file_put_contents($font,$data);
}
$src=$root.'/riyadh-lawyer/index.html';
$dst=$root.'/_arabic-font-canary';
@mkdir($dst,0755,true);
$html=file_get_contents($src);
$html=preg_replace('~<link rel="canonical" href="[^"]+">~','<meta name="robots" content="noindex,nofollow"><link rel="canonical" href="https://cortsexpert.hositee.com/riyadh-lawyer/">',$html,1);
$html=str_replace('/assets/fonts/beiruti-local-v1.woff2','/assets/fonts/beiruti-arabic-v5.woff2',$html);
file_put_contents($dst.'/index.html',$html);
echo 'created font='.(int)filesize($font)."\n";
?>