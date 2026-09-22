<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
$allowed=[
  'https://pcare.sa',
  'https://www.pcare.sa',
  'https://marketing.hositee.com'
];
if($origin!=='' && in_array($origin,$allowed,true)) header('Access-Control-Allow-Origin: '.$origin);
else header('Access-Control-Allow-Origin: *');
header('Vary: Origin');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if(($_SERVER['REQUEST_METHOD']??'')==='OPTIONS'){http_response_code(204);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);exit;}

$base=__DIR__.'/data';
if(!is_dir($base)) @mkdir($base,0775,true);
$mapFile=$base.'/chatlink_clicks.json';
$logFile=$base.'/chatlink_click_events.jsonl';

function load_json_file(string $f):array{
  if(!is_file($f))return[];
  $v=json_decode((string)@file_get_contents($f),true);
  return is_array($v)?$v:[];
}
function save_json_file(string $f,array $v):bool{
  $tmp=$f.'.tmp';
  if(@file_put_contents($tmp,json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX)===false)return false;
  return @rename($tmp,$f);
}
function append_jsonl_file(string $f,array $v):bool{
  return @file_put_contents($f,json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n",FILE_APPEND|LOCK_EX)!==false;
}
function clean_scalar(mixed $v,int $max=4096):string{
  if(is_bool($v))return $v?'1':'0';
  if(is_int($v)||is_float($v))return (string)$v;
  if(!is_string($v))return'';
  $v=trim($v);
  return strlen($v)>$max?substr($v,0,$max):$v;
}
function clean_map(mixed $v,int $depth=0):array{
  if(!is_array($v)||$depth>3)return[];
  $out=[];$n=0;
  foreach($v as $k=>$x){
    if($n++>=150)break;
    $key=preg_replace('/[^a-zA-Z0-9_.:-]+/','_',clean_scalar((string)$k,120))??'';
    if($key==='')continue;
    if(is_array($x))$out[$key]=clean_map($x,$depth+1);
    else $out[$key]=clean_scalar($x,4096);
  }
  return $out;
}
function flatten_params(array $touch):array{
  $p=[];
  if(is_array($touch['params']??null))$p=$touch['params'];
  elseif(is_array($touch['query']??null))$p=$touch['query'];
  return $p;
}
function first_nonempty(array $maps,string $key):string{
  foreach($maps as $m){
    if(!is_array($m))continue;
    $v=clean_scalar($m[$key]??'');
    if($v!=='')return$v;
  }
  return'';
}
function detect_source(array $maps,string $sourceUrl,string $referrer):array{
  $merged=[];
  foreach(array_reverse($maps) as $m)if(is_array($m))$merged=array_replace($merged,$m);
  $u=strtolower($sourceUrl.' '.$referrer.' '.json_encode($merged,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
  $utm=strtolower((string)($merged['utm_source']??''));
  if(!empty($merged['gclid'])||!empty($merged['gbraid'])||!empty($merged['wbraid'])||!empty($merged['dclid'])||str_contains($utm,'google')||str_contains($u,'gclid=')||str_contains($u,'gbraid=')||str_contains($u,'wbraid=')||str_contains($u,'dclid=')||str_contains($u,'utm_source=google')||str_contains($u,'googleads'))
    return ['key'=>'google','label'=>'Google Ads','reason'=>'click_id_or_utm','confidence'=>'high'];
  if(!empty($merged['ttclid'])||str_contains($utm,'tiktok')||str_contains($u,'ttclid=')||str_contains($u,'utm_source=tiktok')||str_contains($u,'tiktok'))
    return ['key'=>'tiktok','label'=>'TikTok Ads','reason'=>'click_id_or_utm','confidence'=>'high'];
  if(!empty($merged['fbclid'])||str_contains($utm,'facebook')||str_contains($utm,'instagram')||str_contains($utm,'meta')||str_contains($u,'fbclid=')||str_contains($u,'utm_source=facebook')||str_contains($u,'utm_source=instagram')||str_contains($u,'facebook.com')||str_contains($u,'instagram.com'))
    return ['key'=>'meta','label'=>'Meta Ads','reason'=>'click_id_or_utm','confidence'=>'high'];
  if(!empty($merged['scclid'])||!empty($merged['ScCid'])||str_contains($utm,'snap')||str_contains($u,'scclid=')||str_contains($u,'sccid=')||str_contains($u,'utm_source=snap')||str_contains($u,'snapchat'))
    return ['key'=>'snapchat','label'=>'Snapchat Ads','reason'=>'click_id_or_utm','confidence'=>'high'];
  if(!empty($merged['msclkid'])||str_contains($utm,'bing')||str_contains($utm,'microsoft'))
    return ['key'=>'microsoft_ads','label'=>'Microsoft Ads','reason'=>'click_id_or_utm','confidence'=>'high'];
  if(!empty($merged['li_fat_id'])||str_contains($utm,'linkedin')||str_contains($u,'linkedin.com'))
    return ['key'=>'linkedin','label'=>'LinkedIn Ads','reason'=>'click_id_or_utm','confidence'=>'high'];
  if(!empty($merged['twclid'])||str_contains($utm,'twitter')||$utm==='x'||str_contains($u,'x.com'))
    return ['key'=>'x','label'=>'X Ads','reason'=>'click_id_or_utm','confidence'=>'high'];
  if($utm!=='')return ['key'=>$utm,'label'=>'Campaign / '.strtoupper($utm),'reason'=>'utm_source','confidence'=>'high'];
  if($referrer!=='')return ['key'=>'referral','label'=>'Referral','reason'=>'referrer','confidence'=>'medium'];
  return ['key'=>'organic','label'=>'Organic / Direct','reason'=>'no_campaign_signal','confidence'=>'medium'];
}

$raw=(string)file_get_contents('php://input');
if($raw===''||strlen($raw)>131072){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'invalid_body']);exit;}
$j=json_decode($raw,true);
if(!is_array($j)){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'invalid_json']);exit;}

$clientId=clean_scalar($j['client_id']??'');
if(!preg_match('/^cl_[a-z0-9]{8,40}$/',$clientId)){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'invalid_client']);exit;}

$token=clean_scalar($j['click_token']??$j['token']??'',4096);
$clickId=clean_scalar($j['click_id']??'',160);
if($clickId==='' && preg_match('/(clk_[A-Za-z0-9_-]{4,120})/',$token,$m))$clickId=$m[1];
if($clickId===''||!preg_match('/^clk_[A-Za-z0-9_-]{4,120}$/',$clickId)){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'invalid_click_id']);exit;}

$first=clean_map($j['first_touch']??[]);
$last=clean_map($j['last_touch']??[]);
$current=clean_map($j['current_touch']??[]);
$query=clean_map($j['query_params']??$j['params']??[]);
if(!$current)$current=['params'=>$query];
$currentParams=flatten_params($current);
$lastParams=flatten_params($last);
$firstParams=flatten_params($first);
$maps=[$currentParams,$lastParams,$firstParams,$query];

$sourceUrl=clean_scalar($j['source_url']??$current['url']??$j['page_url']??'',8192);
$pageUrl=clean_scalar($j['page_url']??$current['url']??$sourceUrl,8192);
$referrer=clean_scalar($j['referrer']??$current['referrer']??$first['referrer']??'',8192);
$src=detect_source($maps,$sourceUrl,$referrer);

$known=[
 'gclid','gbraid','wbraid','dclid','gad_source','gad_campaignid',
 'fbclid','ttclid','scclid','ScCid','msclkid','li_fat_id','twclid','srsltid',
 'campaign_id','campaignid','campaign_name','adgroup_id','adgroupid','adgroup_name',
 'ad_id','creative','ad_name','keyword','matchtype','network','device','placement',
 'targetid','loc_physical_ms','loc_interest_ms','feeditemid','extensionid','adposition'
];
$flat=[];
foreach($known as $k){$v=first_nonempty($maps,$k);if($v!=='')$flat[$k]=$v;}

$utm=[];
$allMaps=array_merge($firstParams,$lastParams,$currentParams,$query);
foreach($allMaps as $k=>$v){
  if(str_starts_with(strtolower((string)$k),'utm_'))$utm[(string)$k]=clean_scalar($v,2048);
}
ksort($utm);

$record=[
 'click_id'=>$clickId,
 'click_token'=>$token,
 'client_id'=>$clientId,
 'interaction_id'=>clean_scalar($j['interaction_id']??'',160),
 'source_url'=>$sourceUrl,
 'page_url'=>$pageUrl,
 'landing_url'=>clean_scalar($j['landing_url']??$first['url']??$pageUrl,8192),
 'referrer'=>$referrer,
 'original_href'=>clean_scalar($j['original_href']??'',8192),
 'button_text'=>clean_scalar($j['button_text']??'',512),
 'button_context'=>clean_scalar($j['button_context']??'',512),
 'first_touch'=>$first,
 'last_touch'=>$last,
 'current_touch'=>$current,
 'touch_history'=>clean_map($j['touch_history']??[]),
 'query_params'=>$query ?: $currentParams,
 'utm'=>$utm,
 'traffic_source_key'=>$src['key'],
 'traffic_source_label'=>$src['label'],
 'traffic_source_reason'=>$src['reason'],
 'traffic_source_confidence'=>$src['confidence'],
 'captured_at'=>gmdate('c'),
 'browser_time'=>clean_scalar($j['browser_time']??$j['ts']??'',64),
 'user_agent'=>clean_scalar($_SERVER['HTTP_USER_AGENT']??'',1024)
];
$record=array_merge($record,$flat);
foreach($utm as $k=>$v)$record[$k]=$v;

$all=load_json_file($mapFile);
$prev=is_array($all[$clickId]??null)?$all[$clickId]:[];
$record['created_at']=$prev['created_at']??$record['captured_at'];
$all[$clickId]=array_replace_recursive($prev,$record);
if(!save_json_file($mapFile,$all)){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'write_failed']);exit;}
append_jsonl_file($logFile,$record);

// Event ordering is not guaranteed: if the WhatsApp webhook arrived milliseconds before
// this browser attribution request, backfill the already-created conversation now.
$backfilled=0;
$convFile=$base.'/conversations.json';
$convs=load_json_file($convFile);
if($convs){
  foreach($convs as $cid=>$conv){
    if(!is_array($conv))continue;
    if(clean_scalar($conv['client_id']??'')!==$clientId)continue;
    if(clean_scalar($conv['ycloud_chatlink_click_id']??'')!==$clickId)continue;
    $conv['traffic_source_key']=$src['key'];
    $conv['traffic_source_label']=$src['label'];
    $conv['traffic_source_reason']='chatlink_click_map';
    $conv['traffic_source_confidence']=$src['confidence'];
    $conv['chatlink_source_url']=$sourceUrl;
    $conv['attribution_landing_url']=$record['landing_url'];
    $conv['attribution_referrer']=$referrer;
    $conv['attribution_params']=$record['query_params'];
    $conv['attribution_utm']=$utm;
    $conv['attribution_first_touch']=$first;
    $conv['attribution_last_touch']=$last;
    $conv['attribution_current_touch']=$current;
    $conv['attribution_touch_history']=$record['touch_history'];
    foreach([
      'gclid'=>'google_gclid','gbraid'=>'google_gbraid','wbraid'=>'google_wbraid','dclid'=>'google_dclid',
      'fbclid'=>'meta_fbclid','ttclid'=>'tiktok_ttclid','msclkid'=>'microsoft_msclkid',
      'li_fat_id'=>'linkedin_li_fat_id','twclid'=>'x_twclid'
    ] as $rk=>$ck){if(!empty($record[$rk]))$conv[$ck]=$record[$rk];}
    if(!empty($record['scclid']))$conv['snapchat_scclid']=$record['scclid'];
    elseif(!empty($record['ScCid']))$conv['snapchat_scclid']=$record['ScCid'];
    foreach($utm as $uk=>$uv)$conv[$uk]=$uv;
    $conv['updated_at']=gmdate('c');
    $convs[$cid]=$conv;$backfilled++;
  }
  if($backfilled)save_json_file($convFile,$convs);
}

echo json_encode([
 'ok'=>true,
 'click_id'=>$clickId,
 'source'=>$src['key'],
 'utm_keys'=>array_keys($utm),
 'captured_params'=>count($record['query_params']),
 'backfilled_conversations'=>$backfilled
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
