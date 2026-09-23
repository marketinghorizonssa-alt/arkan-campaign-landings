<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$base=__DIR__.'/data';
function jl(string $f):array{
  $v=is_file($f)?json_decode((string)file_get_contents($f),true):[];
  return is_array($v)?$v:[];
}
function s(mixed $v):string{return is_scalar($v)?trim((string)$v):'';}
function first_inbound(array $c):array{
  foreach((array)($c['recent_messages']??[]) as $m){
    if(!is_array($m))continue;
    $d=s($m['direction']??'');
    if(str_contains($d,'inbound'))return $m;
  }
  return [];
}
function is_pinned(string $t):bool{
  $n=preg_replace('/\s+/u',' ',trim($t))??trim($t);
  return str_contains($n,'مرحباً بي كير') && str_contains($n,'أريد الاستفسار');
}
$convs=jl($base.'/conversations.json');
$clicks=jl($base.'/chatlink_clicks.json');
$tz=new DateTimeZone('Asia/Riyadh');
$day=(new DateTimeImmutable('now',$tz))->format('Y-m-d');
$out=[];
foreach($convs as $id=>$c){
  if(!is_array($c)||s($c['client_id']??'')!=='cl_0e6efd258397db')continue;
  $first=s($c['first_seen_at']??''); if($first==='')continue;
  try{$d=(new DateTimeImmutable($first))->setTimezone($tz);}catch(Throwable){continue;}
  if($d->format('Y-m-d')!==$day)continue;
  $m=first_inbound($c); $text=s($m['text']??'');
  if(!is_pinned($text))continue;
  $clickId=s($c['ycloud_chatlink_click_id']??'');
  $click=is_array($clicks[$clickId]??null)?$clicks[$clickId]:[];
  $params=is_array($c['attribution_params']??null)?$c['attribution_params']:[];
  $utm=is_array($c['attribution_utm']??null)?$c['attribution_utm']:[];
  $gclid=s($c['google_gclid']??($params['gclid']??($click['gclid']??'')));
  $gbraid=s($c['google_gbraid']??($params['gbraid']??($click['gbraid']??'')));
  $wbraid=s($c['google_wbraid']??($params['wbraid']??($click['wbraid']??'')));
  $source=s($c['traffic_source_key']??'');
  $eventId='gcv_'.substr(hash('sha256','cl_0e6efd258397db|'.$id.'|message_sent'),0,32);
  $decoded=s($c['ycloud_chatlink_decoded']??'');
  $out[]=[
    'conversation_id'=>(string)$id,
    'phone'=>s($c['customer_number']??''),
    'first_seen_riyadh'=>$d->format('Y-m-d H:i:sP'),
    'first_message'=>$text,
    'token_decoded'=>$decoded!=='',
    'decoded_value'=>$decoded,
    'click_id'=>$clickId,
    'click_record_found'=>(bool)$click,
    'match_method'=>s($c['attribution_match_method']??''),
    'source'=>$source,
    'source_label'=>s($c['traffic_source_label']??''),
    'confidence'=>s($c['traffic_source_confidence']??''),
    'reason'=>s($c['traffic_source_reason']??''),
    'utm_source'=>s($utm['utm_source']??($params['utm_source']??($click['utm_source']??''))),
    'utm_medium'=>s($utm['utm_medium']??($params['utm_medium']??($click['utm_medium']??''))),
    'utm_campaign'=>s($utm['utm_campaign']??($params['utm_campaign']??($click['utm_campaign']??''))),
    'gclid'=>$gclid,'gbraid'=>$gbraid,'wbraid'=>$wbraid,
    'click_browser_time'=>s($click['browser_time']??''),
    'click_source_url'=>s($click['source_url']??''),
    'import_ready'=>$source==='google'&&($gclid!==''||$gbraid!==''||$wbraid!==''),
    'google_event_id'=>$eventId
  ];
}
usort($out,fn($a,$b)=>strcmp($a['first_seen_riyadh'],$b['first_seen_riyadh']));
$summary=[
  'date_riyadh'=>$day,
  'pinned_messages'=>count($out),
  'token_decoded'=>count(array_filter($out,fn($x)=>$x['token_decoded'])),
  'hidden_token_matches'=>count(array_filter($out,fn($x)=>$x['match_method']==='hidden_token')),
  'timestamp_fallback_matches'=>count(array_filter($out,fn($x)=>str_contains($x['match_method'],'5m_window'))),
  'google_high'=>count(array_filter($out,fn($x)=>$x['source']==='google'&&$x['confidence']==='high')),
  'import_ready'=>count(array_filter($out,fn($x)=>$x['import_ready']))
];
echo json_encode(['summary'=>$summary,'items'=>$out],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";
