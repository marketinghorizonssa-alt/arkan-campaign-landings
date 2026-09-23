<?php
declare(strict_types=1);
$base=__DIR__.'/data';
$secure=dirname(__DIR__,4).'/.marketing';
if(PHP_SAPI!=='cli'){
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  $tokenFile=$secure.'/google_conversion_feed_token';
  $expected=is_file($tokenFile)?trim((string)@file_get_contents($tokenFile)):'';
  $provided=is_scalar($_GET['token']??null)?trim((string)$_GET['token']):'';
  if($expected===''||$provided===''||!hash_equals($expected,$provided)){
    http_response_code(403);echo json_encode(['ok'=>false,'error'=>'forbidden']);exit;
  }
}

$convFile=$base.'/conversations.json';

function bf_json(string $f,array $d=[]):array{
  if(!is_file($f))return$d;
  $v=json_decode((string)@file_get_contents($f),true);
  return is_array($v)?$v:$d;
}
function bf_s(mixed $v):string{return is_scalar($v)?trim((string)$v):'';}
function bf_client_cfg(string $cid):?array{
  $m=[
    'cl_0e6efd258397db'=>['name'=>'BCARE','customer_id'=>'4482394160','conversion_action_id'=>'7789160285','conversion_action_name'=>'BCARE | WhatsApp Message Started | Offline v1'],
    'cl_3ea5ae96e05c6b'=>['name'=>'ALMOWAHID','customer_id'=>'4577472256','conversion_action_id'=>'7789308219','conversion_action_name'=>'ALMOWAHID | WhatsApp Message Started | Offline v1'],
  ];
  return $m[$cid]??null;
}
function bf_click(array $c):array{
  $g=bf_s($c['google_gclid']??'');
  $gb=bf_s($c['google_gbraid']??'');
  $wb=bf_s($c['google_wbraid']??'');
  if($g!=='')return['type'=>'gclid','value'=>$g];
  if($gb!=='')return['type'=>'gbraid','value'=>$gb];
  if($wb!=='')return['type'=>'wbraid','value'=>$wb];
  $p=is_array($c['attribution_params']??null)?$c['attribution_params']:[];
  $g=bf_s($p['gclid']??'');$gb=bf_s($p['gbraid']??'');$wb=bf_s($p['wbraid']??'');
  if($g!=='')return['type'=>'gclid','value'=>$g];
  if($gb!=='')return['type'=>'gbraid','value'=>$gb];
  if($wb!=='')return['type'=>'wbraid','value'=>$wb];
  return['type'=>'','value'=>''];
}
function bf_google_time(string $raw):string{
  try{$d=new DateTimeImmutable($raw!==''?$raw:'now');}
  catch(Throwable){$d=new DateTimeImmutable('now',new DateTimeZone('UTC'));}
  return $d->setTimezone(new DateTimeZone('Asia/Riyadh'))->format('Y-m-d H:i:sP');
}
function bf_valid_inbound(array $c):int{
  $v=(int)($c['valid_inbound_count']??0);
  if($v>0)return$v;
  $in=(int)($c['inbound_count']??0);
  if($in>0)return$in;
  foreach((array)($c['recent_messages']??[]) as $m){
    if(!is_array($m))continue;
    $d=bf_s($m['direction']??'');$t=strtolower(bf_s($m['type']??''));
    if(!str_contains($d,'inbound'))continue;
    if(in_array($t,['','unsupported','reaction','system','unknown','revoke','revoked'],true))continue;
    return 1;
  }
  return 0;
}

$convs=bf_json($convFile,[]);
$out=['generated_at'=>gmdate('c'),'clients'=>[]];
foreach($convs as $convId=>$c){
  if(!is_array($c))continue;
  $cid=bf_s($c['client_id']??'');$cfg=bf_client_cfg($cid);if(!$cfg)continue;
  $valid=bf_valid_inbound($c);if($valid<1)continue;
  $click=bf_click($c);
  $source=strtolower(bf_s($c['traffic_source_key']??''));
  $first=bf_s($c['first_seen_at']??$c['created_at']??'');
  $eventId='gcv_'.substr(hash('sha256',$cid.'|'.(string)$convId.'|message_sent'),0,32);
  $row=[
    'event_id'=>$eventId,
    'client_id'=>$cid,
    'client_name'=>$cfg['name'],
    'customer_id'=>$cfg['customer_id'],
    'conversion_action_id'=>$cfg['conversion_action_id'],
    'conversion_action_name'=>$cfg['conversion_action_name'],
    'conversion_time'=>bf_google_time($first),
    'conversion_value'=>1,
    'currency'=>'SAR',
    'click_id_type'=>$click['type'],
    'click_id'=>$click['value'],
    'gclid'=>$click['type']==='gclid'?$click['value']:'',
    'gbraid'=>$click['type']==='gbraid'?$click['value']:'',
    'wbraid'=>$click['type']==='wbraid'?$click['value']:'',
    'source'=>$source,
    'valid_inbound_count'=>$valid,
    'conversation_id'=>(string)$convId,
    'order_id'=>$eventId,
    'eligible'=>($click['value']!==''&&($source==='google'||$click['type']!==''))
  ];
  $out['clients'][$cid]['all'][]=$row;
  if($row['eligible'])$out['clients'][$cid]['eligible'][]=$row;
  else $out['clients'][$cid]['audit'][]=$row;
}
foreach(['cl_0e6efd258397db','cl_3ea5ae96e05c6b'] as $cid){
  if(!isset($out['clients'][$cid]))$out['clients'][$cid]=[];
  foreach(['all','eligible','audit'] as $k)if(!isset($out['clients'][$cid][$k]))$out['clients'][$cid][$k]=[];
  $out['clients'][$cid]['summary']=[
    'all'=>count($out['clients'][$cid]['all']),
    'eligible'=>count($out['clients'][$cid]['eligible']),
    'audit'=>count($out['clients'][$cid]['audit'])
  ];
}
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";
