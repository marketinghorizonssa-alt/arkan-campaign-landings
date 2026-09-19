<?php
$root='/home/u878466595/domains/hositee.com/public_html/corts-expert';
$src=$root.'/riyadh-lawyer/index.html';
$dstDir=$root.'/_mobile-perf-canary';
@mkdir($dstDir,0755,true);
$html=file_get_contents($src);
if($html===false){fwrite(STDERR,"read_failed\n");exit(1);}
$html=preg_replace('~<link rel="canonical" href="[^"]+">~','<meta name="robots" content="noindex,nofollow"><link rel="canonical" href="https://cortsexpert.hositee.com/riyadh-lawyer/">',$html,1);
$html=str_replace('<link rel="preload" href="/assets/fonts/beiruti-local-v1.woff2" as="font" type="font/woff2" crossorigin>','<link rel="preload" href="/assets/fonts/beiruti-local-v1.woff2" as="font" type="font/woff2" crossorigin media="(min-width:761px)">',$html);
$html=str_replace('</style><script type="application/ld+json">','@media(max-width:760px){body{font-family:system-ui,-apple-system,"Segoe UI",Tahoma,Arial,sans-serif!important}}</style><script type="application/ld+json">',$html);
$srcImg=$root.'/assets/hero-mobile.jpg';
$dstImg=$root.'/assets/hero-mobile-q68.jpg';
$im=@imagecreatefromjpeg($srcImg);
if($im){imageinterlace($im,true);imagejpeg($im,$dstImg,68);imagedestroy($im);}
$html=str_replace('/assets/hero-mobile.jpg','/assets/hero-mobile-q68.jpg',$html);
file_put_contents($dstDir.'/index.html',$html);
echo 'created html='.filesize($dstDir.'/index.html').' hero='.(file_exists($dstImg)?filesize($dstImg):0)."\n";
?>