<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$base=__DIR__.'/data';
function jl(string $f):array{$v=is_file($f)?json_decode((string)file_get_contents($f),true):[];return is_array($v)?$v:[];}
function s(mixed $v):string{return is_scalar($v)?trim((string)$v):'';}
function epoch(array $r):int{foreach(['browser_time','created_at','captured_at'] as $k){$x=s($r[$k]??'');if($x!==''&&($t=strtotime($x)))return$t;}return 0;}
$convs=jl($base.'/conversations.json');$clicks=jl($base.'/chatlink_clicks.json');
$out=[];
foreach($convs as $id=>$c){
  if(!is_array($c)||s($c['client_id']??'')!=='cl_3ea5ae96e05c6b')continue;
  if(strtolower(s($c['traffic_source_key']??''))!=='google')continue;
  $first=s($c['first_seen_at']??'');$ft=$first!==''?strtotime($first):0;if(!$ft)continue;
  if($ft<strtotime('2026-09-23T00:00:00+03:00')||$ft>strtotime('2026-09-25T00:00:00+03:00'))continue;
  $clickId=s($c['horizons_wa_click_id']??$c['ycloud_chatlink_click_id']??'');
  $direct=is_array($clicks[$clickId]??null)?$clicks[$clickId]:[];
  $candidates=[];
  foreach($clicks as $cid=>$r){
    if(!is_array($r)||s($r['client_id']??'')!=='cl_3ea5ae96e05c6b')continue;
    $t=epoch($r);if(!$t||$t>$ft||$t<$ft-300)continue;
    $src=strtolower(s($r['traffic_source_key']??''));if($src!=='google')continue;
    $candidates[]=[
      'click_id'=>(string)$cid,'delta_seconds'=>$ft-$t,
      'gclid'=>s($r['gclid']??''),'gbraid'=>s($r['gbraid']??''),'wbraid'=>s($r['wbraid']??''),
      'utm_source'=>s(($r['utm']['utm_source']??null)?:($r['query_params']['utm_source']??'')),
      'utm_source_platform'=>s(($r['utm']['utm_source_platform']??null)?:($r['query_params']['utm_source_platform']??'')),
      'match_method'=>s($r['match_method']??'')
    ];
  }
  $out[]=[
    'conversation_id'=>(string)$id,'first_seen'=>$first,
    'valid_inbound_count'=>(int)($c['valid_inbound_count']??$c['inbound_count']??0),
    'current_tag'=>s($c['current_tag']??''),
    'source'=>s($c['traffic_source_key']??''),'confidence'=>s($c['traffic_source_confidence']??''),
    'attribution_match_method'=>s($c['attribution_match_method']??''),
    'click_id'=>$clickId,
    'conv_gclid'=>s($c['google_gclid']??''),'conv_gbraid'=>s($c['google_gbraid']??''),'conv_wbraid'=>s($c['google_wbraid']??''),
    'direct_click'=>[
      'found'=>(bool)$direct,'gclid'=>s($direct['gclid']??''),'gbraid'=>s($direct['gbraid']??''),'wbraid'=>s($direct['wbraid']??'')
    ],
    'window_google_clicks'=>$candidates
  ];
}
usort($out,fn($a,$b)=>strcmp($a['first_seen'],$b['first_seen']));
echo json_encode(['ok'=>true,'count'=>count($out),'items'=>$out],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";
