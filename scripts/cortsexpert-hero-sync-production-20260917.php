<?php
$root='/home/u878466595/domains/hositee.com/public_html/corts-expert';
$files=['index.html','riyadh-lawyer/index.html','legal-consultation/index.html','labor-law/index.html','debt-collection-execution/index.html','family-inheritance/index.html','inheritance-estates/index.html','business-commercial-law/index.html','company-law/index.html','trademark-intellectual-property/index.html','real-estate-law/index.html','criminal-specialized/index.html'];
$changed=0;
foreach($files as $rel){
  $p="$root/$rel";
  if(!is_file($p)) continue;
  $s=file_get_contents($p);
  if(strpos($s,'fetchpriority="high" decoding="async"')===false) continue;
  $bak=$p.'.pre-hero-sync-20260917';
  if(!is_file($bak)) copy($p,$bak);
  $s=str_replace('fetchpriority="high" decoding="async"','fetchpriority="high" decoding="sync"',$s);
  file_put_contents($p,$s);
  $changed++;
}
file_put_contents("$root/.hero-decoding-version","HERO_DECODING_SYNC_V1_20260917\n");
echo "changed=$changed\n";
?>