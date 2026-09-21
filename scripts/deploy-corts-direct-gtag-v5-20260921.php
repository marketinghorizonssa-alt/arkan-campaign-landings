<?php
$root='/home/u878466595/domains/hositee.com/public_html/corts-expert';
$app=$root.'/assets/app.js';
$bak=$root.'/assets/app.js.bak-direct-gtag-v5-20260921';
if(!file_exists($bak) && file_exists($app)) copy($app,$bak);

$src='https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/main/scripts/corts-direct-gtag-v5-20260921.js';
$ch=curl_init($src);
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20,CURLOPT_USERAGENT=>'CORTS-Deploy/1.0']);
$js=curl_exec($ch);
$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
curl_close($ch);
if($code!==200 || !is_string($js) || strlen($js)<3000){fwrite(STDERR,"download_failed code=$code bytes=".strlen((string)$js)."\n");exit(1);}
file_put_contents($app,$js);

$paths=[
 $root.'/index.html',
 $root.'/riyadh-lawyer/index.html',
 $root.'/legal-consultation/index.html',
 $root.'/labor-law/index.html',
 $root.'/debt-collection-execution/index.html',
 $root.'/family-inheritance/index.html',
 $root.'/inheritance-estates/index.html',
 $root.'/business-commercial-law/index.html',
 $root.'/company-law/index.html',
 $root.'/trademark-intellectual-property/index.html',
 $root.'/real-estate-law/index.html',
 $root.'/criminal-specialized/index.html',
 $root.'/privacy/index.html'
];
$changed=0;
foreach($paths as $p){
  if(!file_exists($p))continue;
  $s=file_get_contents($p);
  $orig=$s;
  $s=preg_replace('~<script src="/assets/app\.js\?v=[^"]+" defer></script>~','<script src="/assets/app.js?v=20260921-direct-gtag-v5" defer></script>',$s);
  if($s!==$orig){
    $b=$p.'.bak-direct-gtag-v5-20260921';
    if(!file_exists($b)) copy($p,$b);
    file_put_contents($p,$s);
    $changed++;
  }
}
echo "deployed app_bytes=".filesize($app)." html_changed=$changed\n";
?>