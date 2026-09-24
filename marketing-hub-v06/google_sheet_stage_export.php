<?php
declare(strict_types=1);
$base=__DIR__.'/data';
$secure=dirname(__DIR__,4).'/.marketing';
$tokenFile=$secure.'/qualified_pool_tokens.json';

$convFile=$base.'/conversations.json';
$clickFile=$base.'/chatlink_clicks.json';

function sx_json(string $f,array $d=[]):array{
  if(!is_file($f))return$d;
  $v=json_decode((string)@file_get_contents($f),true);
  return is_array($v)?$v:$d;
}
function sx_s(mixed $v):string{return is_scalar($v)?trim((string)$v):'';}
function sx_recent_valid(array $c):int{
  $n=0;$seen=[];
  foreach((array)($c['recent_messages']??[]) as $m){
    if(!is_array($m)||!str_contains((string)($m['direction']??''),'inbound'))continue;
    $t=strtolower(sx_s($m['type']??''));
    if(in_array($t,['','unsupported','reaction','system','unknown','revoke','revoked'],true))continue;
    $id=sx_s($m['wamid']??$m['message_id']??$m['source_event_id']??'');
    if($id!==''&&isset($seen[$id]))continue;
    if($id!=='')$seen[$id]=true;$n++;
  }
  return $n;
}
function sx_cfg(string $cid):?array{
  $all=[
    'cl_0e6efd258397db'=>[
      'client_name'=>'BCARE','customer_id'=>'4482394160',
      'actions'=>[
        'message_started'=>['id'=>'7789160285','name'=>'BCARE | WhatsApp Message Started | Offline v1','value'=>1],
        'interested'=>['id'=>'7789160288','name'=>'BCARE | WhatsApp Interested | Offline v1','value'=>2],
        'qualified'=>['id'=>'7789160291','name'=>'BCARE | WhatsApp Qualified | Offline v1','value'=>5],
        'converted'=>['id'=>'7789160294','name'=>'BCARE | WhatsApp Converted | Offline v1','value'=>10],
      ]
    ],
    'cl_3ea5ae96e05c6b'=>[
      'client_name'=>'ALMOWAHID','customer_id'=>'4577472256',
      'actions'=>[
        'message_started'=>['id'=>'7789308219','name'=>'ALMOWAHID | WhatsApp Message Started | Offline v1','value'=>1],
        'interested'=>['id'=>'7789308222','name'=>'ALMOWAHID | WhatsApp Interested | Offline v1','value'=>2],
        'qualified'=>['id'=>'7789308225','name'=>'ALMOWAHID | WhatsApp Qualified | Offline v1','value'=>5],
        'converted'=>['id'=>'7789308228','name'=>'ALMOWAHID | WhatsApp Converted | Offline v1','value'=>10],
      ]
    ],
    'cl_cbb797950cc8d4'=>[
      'client_name'=>'ETIZAN','customer_id'=>'8433542366',
      'actions'=>[
        'message_started'=>['id'=>'7790177464','name'=>'ETIZAN | WhatsApp Message Started | Offline v1','value'=>1],
        'interested'=>['id'=>'7790177467','name'=>'ETIZAN | WhatsApp Interested | Offline v1','value'=>2],
        'qualified'=>['id'=>'7790177470','name'=>'ETIZAN | WhatsApp Qualified | Offline v1','value'=>5],
        'converted'=>['id'=>'7790177473','name'=>'ETIZAN | WhatsApp Converted | Offline v1','value'=>10],
      ]
    ],
  ];
  return $all[$cid]??null;
}
function sx_rank(string $tag):int{
  return match(strtolower(trim($tag))){'message_received'=>0,'interested'=>1,'qualified'=>2,'purchased','converted'=>3,default=>-1};
}
function sx_stage_rank(string $stage):int{
  return match($stage){'message_started'=>0,'interested'=>1,'qualified'=>2,'converted'=>3,default=>99};
}
function sx_click(array $c,array $clicks):array{
  foreach([['google_gclid','gclid'],['google_gbraid','gbraid'],['google_wbraid','wbraid']] as [$k,$t]){
    $v=sx_s($c[$k]??'');if($v!=='')return['type'=>$t,'value'=>$v];
  }
  $p=is_array($c['attribution_params']??null)?$c['attribution_params']:[];
  foreach([['gclid','gclid'],['gbraid','gbraid'],['wbraid','wbraid']] as [$k,$t]){
    $v=sx_s($p[$k]??'');if($v!=='')return['type'=>$t,'value'=>$v];
  }
  $clickId=sx_s($c['horizons_wa_click_id']??$c['ycloud_chatlink_click_id']??'');
  $cl=is_array($clicks[$clickId]??null)?$clicks[$clickId]:[];
  foreach([['gclid','gclid'],['gbraid','gbraid'],['wbraid','wbraid']] as [$k,$t]){
    $v=sx_s($cl[$k]??'');if($v!=='')return['type'=>$t,'value'=>$v];
  }
  return['type'=>'','value'=>''];
}
function sx_time(string $raw):string{
  try{$d=new DateTimeImmutable($raw!==''?$raw:'now');}
  catch(Throwable){$d=new DateTimeImmutable('now',new DateTimeZone('UTC'));}
  return $d->setTimezone(new DateTimeZone('Asia/Riyadh'))->format('Y-m-d H:i:sP');
}
function sx_stage_time(array $c,string $stage):string{
  $st=is_array($c['stage_times']??null)?$c['stage_times']:[];
  $key=$stage==='message_started'?'message_started':$stage;
  $v=sx_s($st[$key]??'');
  if($v!=='')return sx_time($v);
  if($stage==='message_started')return sx_time(sx_s($c['first_seen_at']??$c['created_at']??''));
  return sx_time(sx_s($c['tagged_at']??$c['updated_at']??$c['last_message_at']??''));
}

$convs=sx_json($convFile,[]);
$clicks=sx_json($clickFile,[]);
$out=['generated_at'=>gmdate('c'),'clients'=>[]];

foreach($convs as $convId=>$conv){
  if(!is_array($conv))continue;
  $cid=sx_s($conv['client_id']??'');$cfg=sx_cfg($cid);if(!$cfg)continue;
  $in=max((int)($conv['valid_inbound_count']??0),(int)($conv['inbound_count']??0),sx_recent_valid($conv));if($in<1)continue;

  // Google Ads stage tabs are click-conversion feeds only.
  // Organic/other-platform leads belong in Lead Pool, not these tabs.
  if(strtolower(sx_s($conv['traffic_source_key']??''))!=='google')continue;
  $click=sx_click($conv,$clicks);

  $rank=sx_rank(sx_s($conv['current_tag']??''));
  if($rank<0)$rank=0;
  if($in>=2)$rank=max($rank,1);
  foreach(['message_started','interested','qualified','converted'] as $stage){
    if($rank<sx_stage_rank($stage))continue;
    $a=$cfg['actions'][$stage];
    $key=$cid.'|'.$convId.'|'.$stage;
    $eventId='gcv_'.substr(hash('sha256',$key),0,32);
    $row=[
      'gclid'=>$click['type']==='gclid'?$click['value']:'',
      'gbraid'=>$click['type']==='gbraid'?$click['value']:'',
      'wbraid'=>$click['type']==='wbraid'?$click['value']:'',
      'conversion_time'=>sx_stage_time($conv,$stage),
      'conversion_value'=>$a['value'],'currency'=>'SAR','order_id'=>$eventId,
      'conversion_action'=>$a['name'],'import_ready'=>true,
      'source'=>'google','valid_inbound_count'=>$in,
      'conversation_id'=>(string)$convId,'event_id'=>$eventId,'google_ads_customer_id'=>$cfg['customer_id'],
      'conversion_action_id'=>$a['id'],
      'audit_reason'=>'Google Ads source confirmed + click ID present'
    ];
    $out['clients'][$cid]['client_name']=$cfg['client_name'];
    $out['clients'][$cid]['stages'][$stage][]=$row;
  }
}

foreach(['cl_0e6efd258397db','cl_3ea5ae96e05c6b','cl_cbb797950cc8d4'] as $cid){
  $cfg=sx_cfg($cid);
  if(!isset($out['clients'][$cid]))$out['clients'][$cid]=['client_name'=>$cfg['client_name'],'stages'=>[]];
  foreach(['message_started','interested','qualified','converted'] as $stage){
    if(!isset($out['clients'][$cid]['stages'][$stage]))$out['clients'][$cid]['stages'][$stage]=[];
    usort($out['clients'][$cid]['stages'][$stage],fn($x,$y)=>strcmp((string)$x['conversion_time'],(string)$y['conversion_time']));
  }
}

function sx_csv(array $row):void{$fh=fopen('php://output','wb');fputcsv($fh,$row);fclose($fh);}
function sx_client_alias(string $v):string{
  $v=strtolower(trim($v));
  return match($v){
    'bcare','pcare','cl_0e6efd258397db'=>'cl_0e6efd258397db',
    'almowahid','mowahid','cl_3ea5ae96e05c6b'=>'cl_3ea5ae96e05c6b',
    'etizan','cl_cbb797950cc8d4'=>'cl_cbb797950cc8d4',
    default=>$v
  };
}
function sx_stage_alias(string $v):string{
  $v=strtolower(trim($v));
  return match($v){
    'message','message_started','started'=>'message_started',
    'interested'=>'interested','qualified'=>'qualified',
    'converted','purchased'=>'converted',
    default=>$v
  };
}

if(PHP_SAPI!=='cli'){
  $cid=sx_client_alias((string)($_GET['client']??''));
  $stage=sx_stage_alias((string)($_GET['stage']??''));
  $token=sx_s($_GET['token']??'');$tokens=sx_json($tokenFile,[]);
  if(!$cid||!isset($tokens[$cid])||$token===''||!hash_equals((string)$tokens[$cid],$token)){
    http_response_code(403);header('Content-Type:text/plain');echo"forbidden\n";exit;
  }
  if(!in_array($stage,['message_started','interested','qualified','converted'],true)){
    http_response_code(422);header('Content-Type:text/plain');echo"bad_stage\n";exit;
  }
  header('Content-Type:text/csv; charset=utf-8');header('Cache-Control:no-store');header('X-Robots-Tag:noindex, nofollow, noarchive');
  sx_csv(['GCLID','GBRAID','WBRAID','Conversion Time','Conversion Value','Conversion Currency','Order ID','Conversion Action','Import Ready','Source','Valid Inbound Messages','Conversation ID','Event ID','Google Ads Customer ID','Conversion Action ID','Audit Reason']);
  foreach($out['clients'][$cid]['stages'][$stage]??[] as $r){
    sx_csv([$r['gclid'],$r['gbraid'],$r['wbraid'],$r['conversion_time'],$r['conversion_value'],$r['currency'],$r['order_id'],$r['conversion_action'],$r['import_ready']?'TRUE':'FALSE',$r['source'],$r['valid_inbound_count'],$r['conversation_id'],$r['event_id'],$r['google_ads_customer_id'],$r['conversion_action_id'],$r['audit_reason']]);
  }
  exit;
}

$argClient=strtolower(trim((string)($argv[1]??'')));
$argStage=strtolower(trim((string)($argv[2]??'')));
if($argClient!==''&&$argStage!==''){
  $cid=sx_client_alias($argClient);$stage=sx_stage_alias($argStage);
  echo json_encode($out['clients'][$cid]['stages'][$stage]??[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";exit;
}
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";
