<?php
$root='/home/u878466595/domains/hositee.com/public_html/corts-expert';
$jpg="$root/assets/hero-mobile.jpg";$webp="$root/assets/hero-mobile-v2.webp";
if(!is_file($webp)){
  $src=imagecreatefromjpeg($jpg);$w=imagesx($src);$h=imagesy($src);$nw=min(720,$w);$nh=(int)round($h*$nw/$w);$dst=imagecreatetruecolor($nw,$nh);imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);imagewebp($dst,$webp,76);imagedestroy($dst);imagedestroy($src);
}
$files=['index.html','riyadh-lawyer/index.html','legal-consultation/index.html','labor-law/index.html','debt-collection-execution/index.html','family-inheritance/index.html','inheritance-estates/index.html','business-commercial-law/index.html','company-law/index.html','trademark-intellectual-property/index.html','real-estate-law/index.html','criminal-specialized/index.html'];
$changed=0;
foreach($files as $rel){
  $p="$root/$rel";if(!is_file($p))continue;$s=file_get_contents($p);if(strpos($s,'hero-mobile-v2.webp')!==false)continue;
  $bak=$p.'.pre-hero-webp-20260917';if(!is_file($bak))copy($p,$bak);
  $s=str_replace('<link rel="preload" href="/assets/hero-mobile.jpg" as="image" media="(max-width:760px)" fetchpriority="high">','<link rel="preload" href="/assets/hero-mobile-v2.webp" as="image" type="image/webp" media="(max-width:760px)" fetchpriority="high">',$s);
  $s=str_replace('<source media="(max-width:760px)" srcset="/assets/hero-mobile.jpg">','<source media="(max-width:760px)" type="image/webp" srcset="/assets/hero-mobile-v2.webp">',$s);
  $s=str_replace('fetchpriority="high" decoding="async"','fetchpriority="high" decoding="sync"',$s);
  file_put_contents($p,$s);$changed++;
}
file_put_contents("$root/.hero-image-version","HERO_MOBILE_WEBP_V2_20260917\n");
echo "changed=$changed webp_bytes=".filesize($webp)."\n";
?>