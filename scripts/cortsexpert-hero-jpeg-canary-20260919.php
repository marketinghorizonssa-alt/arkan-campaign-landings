<?php
$root='/home/u878466595/domains/hositee.com/public_html/corts-expert';
$src=$root.'/riyadh-lawyer/index.html';
$dst=$root.'/_hero-jpeg-canary';
@mkdir($dst,0755,true);
$html=file_get_contents($src);
$html=preg_replace('~<link rel="canonical" href="[^"]+">~','<meta name="robots" content="noindex,nofollow"><link rel="canonical" href="https://cortsexpert.hositee.com/riyadh-lawyer/">',$html,1);
$srcImg=$root.'/assets/hero-mobile.jpg';
$dstImg=$root.'/assets/hero-mobile-q58.jpg';
$im=@imagecreatefromjpeg($srcImg);
if(!$im){fwrite(STDERR,"image_read_failed\n");exit(1);}
imageinterlace($im,true);
imagejpeg($im,$dstImg,58);
imagedestroy($im);
$html=str_replace('/assets/hero-mobile.jpg','/assets/hero-mobile-q58.jpg',$html);
file_put_contents($dst.'/index.html',$html);
echo 'created hero='.(int)filesize($dstImg)."\n";
?>