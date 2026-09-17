<?php
$root='/home/u878466595/domains/hositee.com/public_html/corts-expert';
$jpg="$root/assets/hero-mobile.jpg";
$webp="$root/assets/hero-mobile-v2.webp";
$src=imagecreatefromjpeg($jpg);
$w=imagesx($src);$h=imagesy($src);
$maxW=720;
if($w>$maxW){$nw=$maxW;$nh=(int)round($h*$nw/$w);$dst=imagecreatetruecolor($nw,$nh);imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);imagewebp($dst,$webp,76);imagedestroy($dst);}else{imagewebp($src,$webp,76);}
imagedestroy($src);
$srcHtml="$root/riyadh-lawyer/index.html";$dir="$root/_hero-webp-canary";@mkdir($dir,0755,true);$html=file_get_contents($srcHtml);
$html=preg_replace('/<link rel="canonical" href="[^"]+">/','<meta name="robots" content="noindex,nofollow"><link rel="canonical" href="https://cortsexpert.hositee.com/riyadh-lawyer/">',$html,1);
$html=str_replace('<link rel="preload" href="/assets/hero-mobile.jpg" as="image" media="(max-width:760px)" fetchpriority="high">','<link rel="preload" href="/assets/hero-mobile-v2.webp" as="image" type="image/webp" media="(max-width:760px)" fetchpriority="high">',$html);
$html=str_replace('<source media="(max-width:760px)" srcset="/assets/hero-mobile.jpg">','<source media="(max-width:760px)" type="image/webp" srcset="/assets/hero-mobile-v2.webp">',$html);
$html=str_replace('fetchpriority="high" decoding="async"','fetchpriority="high" decoding="sync"',$html);
file_put_contents("$dir/index.html",$html);
$g=getimagesize($webp);echo "original={$w}x{$h} webp={$g[0]}x{$g[1]} bytes=".filesize($webp)."\n";
?>