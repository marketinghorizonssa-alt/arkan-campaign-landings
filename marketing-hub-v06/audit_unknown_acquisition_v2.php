<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

$base=__DIR__.'/data';
$rawFile=$base.'/raw_events.jsonl';
$connectionsFile=$base.'/ycloud_connections.json';
$clientId=(string)(getenv('CLIENT_ID')?:'cl_76c4e019afc588');
$from=(string)(getenv('FROM')?:'2026-08-01');
$to=(string)(getenv('TO')?:gmdate('Y-m-d'));

function v_s(mixed $v):string{return is_scalar($v)?trim((string)$v):'';}
function v_l(string $v):string{return function_exists('mb_strtolower')?mb_strtolower($v,'UTF-8'):strtolower($v);}
function v_clean(string $v):string{$v=preg_replace('/[\p{Cf}\p{Cc}\p{Cs}]+/u','',$v)??$v;return preg_replace('/\s+/u',' ',trim($v))??trim($v);}
function v_text(array $m):string{$t=v_s($m['type']??'');if($t==='text')return v_clean(v_s($m['text']['body']??''));foreach(['image','video','document','audio'] as $k)if($t===$k&&isset($m[$k])&&is_array($m[$k]))return v_clean(v_s($m[$k]['caption']??''));return'';}
function v_contains(string $t,array $xs):bool{$t=v_l(v_clean($t));foreach($xs as $x){$x=v_l(v_clean((string)$x));if($x!==''&&str_contains($t,$x))return true;}return false;}
function v_tiktok(string $text):bool{$t=v_l(v_clean($text));return str_contains($t,'tiktok')&&(str_contains($t,'صادفت')||str_contains($t,'came across your ad')||str_contains($t,'أود معرفة المزيد')||str_contains($t,'would like to find out more'));}
function v_source(array $m,string $text):string{
  $r=is_array($m['referral']??null)?$m['referral']:[];
  $clid=v_s($r['ctwa_clid']??$r['ctwaClid']??'');
  $src=v_l(v_s($r['source_url']??$r['sourceUrl']??'').' '.v_s($r['headline']??''));
  if($clid!==''||str_contains($src,'facebook')||str_contains($src,'instagram'))return'meta';
  if(v_tiktok($text)||str_contains($src,'tiktok'))return'tiktok';
  if(v_contains($text,['المصدر: google ads','source: google ads']))return'google';
  if(v_contains($text,['المصدر: snapchat ads','source: snapchat ads']))return'snapchat';
  return'unknown';
}
function v_ts(string $s):int{try{return (new DateTimeImmutable($s))->getTimestamp();}catch(Throwable){return 0;}}
function v_classify(array $msgs):array{
  usort($msgs,fn($a,$b)=>strcmp((string)$a['at'],(string)$b['at']));
  $first=null;foreach($msgs as $m)if($m['direction']==='inbound'){$first=$m;break;}
  if(!$first)return['bucket'=>'ambiguous','reason'=>'no_inbound','window'=>[]];
  $t0=v_ts((string)$first['at']);$window=[];
  foreach($msgs as $m){
    if($m['direction']!=='inbound')continue;
    $ts=v_ts((string)$m['at']);if($ts===0||$ts-$t0>900)break;
    $window[]=$m;if(count($window)>=4)break;
  }
  $joined=' ';foreach($window as $m)$joined.=' '.(string)$m['text'];
  $firstText=(string)($window[0]['text']??'');
  $firstEvent=(string)($window[0]['event_type']??'');

  $referralWords=['اعطتني رقمك','أعطتني رقمك','عطاني رقمك','عطتني رقمك','اعطاني رقمك','أعطاني رقمك','من طرف','حولني عليك','حوّلني عليك','رقمك من','دز لي رقمك','ارسلي رقم','أرسلي رقم','ارسل رقم','أرسل رقم'];
  $strongPrior=['كما اتفقنا','زي ما اتفقنا','حسب اتفاقنا','حسب الاتفاق','باقي المبلغ','باقي الحساب','المرة اللي فاتت','المرة السابقة','سبق وكلمتك','سبق وتواصلت','كلمتك قبل','تكلمنا قبل','ارسلت لك قبل','أرسلت لك قبل','نفس الطلب','نفس الجهاز','نفس الكمية','باقي الطلب','الدفعة الثانية','الدفعة الاخيرة','الدفعة الأخيرة'];
  $nonLeadCommerce=['ايفون','آيفون','iphone','16 برو','16pro','برو ماكس','pro max','سلفر','جهاز','اجهزة','أجهزة','كمية','الكميه','الكميات','سعره أعلى','سعر اعلى'];
  $leadIntent=['عقار','شقة','شقه','فيلا','تمويل','رهن','مديونية','قرض','تملك','التملك','شراء بيت','شراء عقار','استشارة','استشاره','سمة','الراتب','البنك','الدفعة','ميزانية','وحدة سكنية','وحده سكنيه','اركـان','أركان'];
  $callRequest=['اتصل علي','تتصل علي','اتصلوا علي','تتصلون علي','كلمني','كلّموني','دق علي','دقوا علي','تواصل معي','تواصلوا معي','ابي احد يتصل','أبي أحد يتصل'];
  $webMarker=['ark-web-','تم إرسال طلبي من الموقع','تم ارسال طلبي من الموقع'];

  if(v_contains($joined,$referralWords))return['bucket'=>'explicit_non_ad_referral','reason'=>'explicit_referral_language','window'=>$window];
  if(v_contains($joined,$strongPrior))return['bucket'=>'clear_prior_or_existing','reason'=>'explicit_prior_relationship_language','window'=>$window];
  if(v_contains($joined,$nonLeadCommerce))return['bucket'=>'clear_prior_or_existing','reason'=>'non_arkan_commerce_context','window'=>$window];

  if(v_contains($joined,$webMarker))return['bucket'=>'likely_new_lead','reason'=>'website_lead_marker','window'=>$window];
  if(v_contains($firstText,$callRequest))return['bucket'=>'likely_new_lead','reason'=>'first_message_call_request_no_prior_evidence','window'=>$window];

  if($firstEvent==='whatsapp.smb.history'){
    return['bucket'=>'historical_cannot_prove_new','reason'=>'first_visible_message_from_history_sync','window'=>$window];
  }

  if(v_contains($joined,$leadIntent))return['bucket'=>'likely_new_lead','reason'=>'live_service_intent_no_prior_evidence','window'=>$window];

  $genericGreetings=['السلام عليكم','سلام عليكم','مرحبا','مرحباً','هلا','هاي','hello','hi'];
  if(v_contains($firstText,$genericGreetings))return['bucket'=>'ambiguous_live','reason'=>'generic_live_opening','window'=>$window];
  if(v_clean($firstText)==='')return['bucket'=>'ambiguous_live','reason'=>'empty_or_media_live_opening','window'=>$window];
  return['bucket'=>'ambiguous_live','reason'=>'insufficient_live_evidence','window'=>$window];
}

$connections=json_decode((string)@file_get_contents($connectionsFile),true);if(!is_array($connections))$connections=[];
$connClient=[];foreach($connections as $k=>$v)if(is_array($v)){$id=v_s($v['id']??(is_string($k)?$k:''));if($id!=='')$connClient[$id]=v_s($v['client_id']??'');}
$convs=[];
if(is_file($rawFile)&&($fh=fopen($rawFile,'rb'))){
 while(($line=fgets($fh))!==false){
  $r=json_decode($line,true);if(!is_array($r))continue;
  $p=is_array($r['payload']??null)?$r['payload']:[];
  $eventType=v_s($r['type']??'');$conn=v_s($r['ycloud_connection_id']??'');$cid=$connClient[$conn]??'';
  if($cid!==$clientId)continue;
  $m=null;$dir='';
  if($eventType==='whatsapp.inbound_message.received'&&isset($p['whatsappInboundMessage'])){$m=$p['whatsappInboundMessage'];$dir='inbound';}
  elseif($eventType==='whatsapp.smb.message.echoes'&&isset($p['whatsappMessage'])){$m=$p['whatsappMessage'];$dir='outbound';}
  elseif($eventType==='whatsapp.smb.history'&&isset($p['whatsappInboundMessage'])){$m=$p['whatsappInboundMessage'];$dir='inbound';}
  elseif($eventType==='whatsapp.smb.history'&&isset($p['whatsappMessage'])){$m=$p['whatsappMessage'];$dir='outbound';}
  if(!is_array($m))continue;
  $w=v_s($m['wabaId']??'');$cust=v_s($dir==='inbound'?($m['from']??''):($m['to']??''));if($w===''||$cust==='')continue;
  $id=substr(hash('sha256',$w.'|'.$cust),0,24);$at=v_s($m['sendTime']??$r['createTime']??'');$txt=v_text($m);
  if(!isset($convs[$id]))$convs[$id]=['messages'=>[],'first_inbound_at'=>'','first_source'=>'unknown'];
  $convs[$id]['messages'][]=['direction'=>$dir,'at'=>$at,'type'=>v_s($m['type']??''),'text'=>$txt,'raw'=>$m,'event_type'=>$eventType];
  if($dir==='inbound'&&$at!==''&&($convs[$id]['first_inbound_at']===''||strcmp($at,$convs[$id]['first_inbound_at'])<0)){
    $convs[$id]['first_inbound_at']=$at;$convs[$id]['first_source']=v_source($m,$txt);
  }
 }
 fclose($fh);
}
$tz=new DateTimeZone('Asia/Riyadh');$start=new DateTimeImmutable($from.' 00:00:00',$tz);$end=new DateTimeImmutable($to.' 23:59:59',$tz);
$counts=['likely_new_lead'=>0,'explicit_non_ad_referral'=>0,'clear_prior_or_existing'=>0,'historical_cannot_prove_new'=>0,'ambiguous_live'=>0];
$reasons=[];$ids=[];$total=0;
foreach($convs as $id=>$c){
 $fi=(string)$c['first_inbound_at'];if($fi==='')continue;
 try{$dt=(new DateTimeImmutable($fi))->setTimezone($tz);}catch(Throwable){continue;}
 if($dt<$start||$dt>$end)continue;
 if(($c['first_source']??'unknown')!=='unknown')continue;
 $total++;$x=v_classify($c['messages']);$b=$x['bucket'];$counts[$b]++;$reasons[$x['reason']]=($reasons[$x['reason']]??0)+1;
 if(!isset($ids[$b]))$ids[$b]=[];$ids[$b][]=$id;
}
echo json_encode(['ok'=>true,'client_id'=>$clientId,'from'=>$from,'to'=>$to,'unknown_first_visible'=>$total,'classification'=>$counts,'reasons'=>$reasons,'lead_ids_by_bucket'=>$ids],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";
