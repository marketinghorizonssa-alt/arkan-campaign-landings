<?php
$root='/home/u878466595/domains/hositee.com/public_html/corts-expert';
$files=['index.html','riyadh-lawyer/index.html','legal-consultation/index.html','labor-law/index.html','debt-collection-execution/index.html','family-inheritance/index.html','inheritance-estates/index.html','business-commercial-law/index.html','company-law/index.html','trademark-intellectual-property/index.html','real-estate-law/index.html','criminal-specialized/index.html'];
$changed=0;
foreach($files as $rel){
  $p="$root/$rel";
  if(!is_file($p)) continue;
  $s=file_get_contents($p);
  if(strpos($s,'MOBILE_HERO_LITE_V1')!==false) continue;
  if(strpos($s,'hero-media')===false) continue;
  $s=str_replace('<link rel="preload" href="/assets/hero-mobile.jpg" as="image" media="(max-width:760px)" fetchpriority="high">','',$s);
  $s=str_replace('<source media="(max-width:760px)" srcset="/assets/hero-mobile.jpg">','<source media="(max-width:760px)" srcset="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=">',$s);
  $css='/* MOBILE_HERO_LITE_V1 */@media(max-width:760px){.hero-media{display:none!important}.hero:before{background:linear-gradient(160deg,#102445 0%,#1b3461 58%,#294978 100%)!important}}';
  $s=str_replace('</style>',$css.'</style>',$s);
  file_put_contents($p,$s);
  $changed++;
}
file_put_contents("$root/.mobile-hero-version","MOBILE_HERO_LITE_V1_20260917\n");
echo "changed=$changed\n";
?>