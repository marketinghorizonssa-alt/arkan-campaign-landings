<?php
declare(strict_types=1);

$base=__DIR__.'/data';
$secure=dirname(__DIR__,4).'/.marketing';
$tokenFile=$secure.'/qualified_pool_tokens.json';
$clients=[
  'cl_0e6efd258397db'=>['name'=>'BCARE'],
  'cl_3ea5ae96e05c6b'=>['name'=>'ALMOWAHID']
];

function qlp_json(string $f,array $d=[]):array{
  if(!is_file($f))return$d;
  $v=json_decode((string)@file_get_contents($f),true);
  return is_array($v)?$v:$d;
}
function qlp_s(mixed $v):string{return is_scalar($v)?trim((string)$v):'';}
function qlp_phone(string $v):string{return preg_replace('/\\D+/','',$v)??'';}
function qlp_stage(array $c):array{
  $qualifiedAt='';$ever=false;
  foreach((array)($c['label_history']??[]) as $h){
    if(!is_array($h))continue;
    $tag=strtolower(qlp_s($h['tag']??''));
    if(in_array($tag,['qualified','purchased','converted'],true)){
      $ever=true;
      if($qualifiedAt==='')$qualifiedAt=qlp_s($h['at']??'');
    }
  }
  $current=strtolower(qlp_s($c['current_tag']??''));
  if(in_array($current,['qualified','purchased','converted'],true)){
    $ever=true;
    if($qualifiedAt==='')$qualifiedAt=qlp_s($c['tagged_at']??$c['updated_at']??'');
  }
  return [$ever,$qualifiedAt,$current==='purchased'?'converted':$current];
}
function qlp_param(array $c,string $k):string{
  $p=is_array($c['attribution_params']??null)?$c['attribution_params']:[];
  return qlp_s($p[$k]??'');
}
function qlp_csv(array $row):void{
  $fh=fopen('php://output','wb');fputcsv($fh,$row);fclose($fh);
}

if(PHP_SAPI==='cli'){
  if(($argv[1]??'')==='init'){
    if(!is_dir($secure))@mkdir($secure,0700,true);
    $t=qlp_json($tokenFile,[]);
    foreach(array_keys($clients) as $cid)if(empty($t[$cid]))$t[$cid]=bin2hex(random_bytes(24));
    @file_put_contents($tokenFile,json_encode($t,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n",LOCK_EX);@chmod($tokenFile,0600);
    echo json_encode($t,JSON_UNESCAPED_SLASHES)."\n";exit;
  }
  http_response_code(404);exit;
}

$cid=qlp_s($_GET['client']??'');$token=qlp_s($_GET['token']??'');
$tokens=qlp_json($tokenFile,[]);
if(!isset($clients[$cid])||$token===''||empty($tokens[$cid])||!hash_equals((string)$tokens[$cid],$token)){
  http_response_code(403);header('Content-Type: text/plain; charset=utf-8');echo "forbidden\n";exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');

$headers=[
  'Lead ID','Qualified At','Current Stage','Source Platform','Source Confidence',
  'Campaign ID','Campaign Name','Ad Group ID','Ad ID','Keyword',
  'GCLID','GBRAID','WBRAID','TTCLID','FBCLID','CTWA CLID','SCCID',
  'Phone SHA256','Email SHA256',
  'Google Native Ready','TikTok Native Ready','Meta Native Ready','Snap Native Ready',
  'Audience Ready','Recommended Use','Conversation ID','Last Seen At'
];
qlp_csv($headers);

$convs=qlp_json($base.'/conversations.json',[]);
foreach($convs as $convId=>$c){
  if(!is_array($c)||qlp_s($c['client_id']??'')!==$cid)continue;
  [$ever,$qualifiedAt,$stage]=qlp_stage($c);if(!$ever)continue;
  $src=strtolower(qlp_s($c['traffic_source_key']??'unknown'));
  $phone=qlp_phone(qlp_s($c['customer_number']??''));
  $phoneHash=$phone!==''?hash('sha256',$phone):'';
  $gclid=qlp_s($c['google_gclid']??qlp_param($c,'gclid'));
  $gbraid=qlp_s($c['google_gbraid']??qlp_param($c,'gbraid'));
  $wbraid=qlp_s($c['google_wbraid']??qlp_param($c,'wbraid'));
  $ttclid=qlp_s($c['tiktok_ttclid']??qlp_param($c,'ttclid'));
  $fbclid=qlp_s($c['meta_fbclid']??qlp_param($c,'fbclid'));
  $ctwa=qlp_s($c['ctwa_clid']??'');
  $sccid=qlp_s($c['snapchat_scclid']??qlp_param($c,'scclid'));
  $googleReady=$src==='google'&&($gclid!==''||$gbraid!==''||$wbraid!=='');
  $tiktokReady=$src==='tiktok'&&$ttclid!=='';
  $metaReady=$src==='meta'&&($ctwa!==''||$fbclid!=='');
  $snapReady=$src==='snapchat'&&$sccid!=='';
  $audienceReady=$phoneHash!=='';
  $recommended='source-native conversion';
  if($audienceReady)$recommended.=' + cross-platform audience';
  $row=[
    (string)$convId,$qualifiedAt,$stage,$src,qlp_s($c['traffic_source_confidence']??''),
    qlp_param($c,'campaign_id'),qlp_param($c,'utm_campaign'),qlp_param($c,'adgroup_id'),qlp_param($c,'ad_id'),qlp_param($c,'utm_term'),
    $gclid,$gbraid,$wbraid,$ttclid,$fbclid,$ctwa,$sccid,
    $phoneHash,'',
    $googleReady?'TRUE':'FALSE',$tiktokReady?'TRUE':'FALSE',$metaReady?'TRUE':'FALSE',$snapReady?'TRUE':'FALSE',
    $audienceReady?'TRUE':'FALSE',$recommended,(string)$convId,qlp_s($c['updated_at']??$c['last_seen_at']??'')
  ];
  qlp_csv($row);
}
