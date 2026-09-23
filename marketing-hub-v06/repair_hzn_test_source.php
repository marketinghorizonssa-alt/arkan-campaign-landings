<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$base=__DIR__.'/data';
$clickId='clk_ac2a1dd16c9fcb73d05698b8';
$clickFile=$base.'/chatlink_clicks.json';
$convFile=$base.'/conversations.json';
function jl(string $f):array{$v=is_file($f)?json_decode((string)file_get_contents($f),true):[];return is_array($v)?$v:[];}
function js(string $f,array $v):void{$t=$f.'.tmp';file_put_contents($t,json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);rename($t,$f);}
$clicks=jl($clickFile);$convs=jl($convFile);
$r=is_array($clicks[$clickId]??null)?$clicks[$clickId]:null;
if(!$r){echo json_encode(['ok'=>false,'error'=>'click_not_found'])."\n";exit;}
$p=is_array($r['query_params']??null)?$r['query_params']:[];
$plat=strtolower((string)($p['utm_source_platform']??$r['utm_source_platform']??''));
if(!str_contains($plat,'google')){echo json_encode(['ok'=>false,'error'=>'not_google_platform'])."\n";exit;}
$r['traffic_source_key']='google';
$r['traffic_source_label']='Google Ads';
$r['traffic_source_reason']='utm_source_platform';
$r['traffic_source_confidence']='high';
$clicks[$clickId]=$r;js($clickFile,$clicks);
$updated=[];
foreach($convs as $id=>$c){
  if(!is_array($c)||(string)($c['ycloud_chatlink_click_id']??'')!==$clickId)continue;
  $c['traffic_source_key']='google';
  $c['traffic_source_label']='Google Ads';
  $c['traffic_source_reason']='horizons_signed_hidden_token';
  $c['traffic_source_confidence']='high';
  $c['attribution_match_method']='horizons_hidden_token';
  $c['updated_at']=gmdate('c');
  $convs[$id]=$c;$updated[]=$id;
}
js($convFile,$convs);
echo json_encode(['ok'=>true,'click_id'=>$clickId,'updated_conversations'=>$updated],JSON_UNESCAPED_SLASHES)."\n";
