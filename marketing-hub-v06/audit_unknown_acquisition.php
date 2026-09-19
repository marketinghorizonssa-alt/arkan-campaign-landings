<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

$base=__DIR__.'/data';
$rawFile=$base.'/raw_events.jsonl';
$connectionsFile=$base.'/ycloud_connections.json';
$clientsFile=$base.'/clients.json';
$clientId=(string)(getenv('CLIENT_ID')?:'cl_76c4e019afc588');
$from=(string)(getenv('FROM')?:'2026-08-01');
$to=(string)(getenv('TO')?:gmdate('Y-m-d'));

function a_s(mixed $v):string{return is_scalar($v)?trim((string)$v):'';}
function a_l(string $v):string{return function_exists('mb_strtolower')?mb_strtolower($v,'UTF-8'):strtolower($v);}
function a_clean(string $v):string{$v=preg_replace('/[\p{Cf}\p{Cc}\p{Cs}]+/u','',$v)??$v;return preg_replace('/\s+/u',' ',trim($v))??trim($v);}
function a_text(array $m):string{$t=a_s($m['type']??'');if($t==='text')return a_clean(a_s($m['text']['body']??''));foreach(['image','video','document','audio'] as $k)if($t===$k&&isset($m[$k])&&is_array($m[$k]))return a_clean(a_s($m[$k]['caption']??''));return'';}
function a_contains(string $t,array $xs):bool{$t=a_l(a_clean($t));foreach($xs as $x){$x=a_l(a_clean((string)$x));if($x!==''&&str_contains($t,$x))return true;}return false;}
function a_tiktok(string $text):bool{$t=a_l(a_clean($text));return str_contains($t,'tiktok')&&(str_contains($t,'صادفت')||str_contains($t,'came across your ad')||str_contains($t,'أود معرفة المزيد')||str_contains($t,'would like to find out more'));}
function a_source(array $m,string $text):string{
  $r=is_array($m['referral']??null)?$m['referral']:[];
  $clid=a_s($r['ctwa_clid']??$r['ctwaClid']??'');
  $src=a_l(a_s($r['source_url']??$r['sourceUrl']??'').' '.a_s($r['headline']??''));
  if($clid!==''||str_contains($src,'facebook')||str_contains($src,'instagram'))return'meta';
  if(a_tiktok($text)||str_contains($src,'tiktok'))return'tiktok';
  if(a_contains($text,['المصدر: google ads','source: google ads']))return'google';
  if(a_contains($text,['المصدر: snapchat ads','source: snapchat ads']))return'snapchat';
  return'unknown';
}
function a_ts(string $s):int{try{return (new DateTimeImmutable($s))->getTimestamp();}catch(Throwable){return 0;}}
function a_classify(array $msgs):array{
  usort($msgs,fn($a,$b)=>strcmp((string)$a['at'],(string)$b['at']));
  $first=null;foreach($msgs as $m)if($m['direction']==='inbound'){$first=$m;break;}
  if(!$first)return['bucket'=>'ambiguous','reason'=>'no_inbound','window'=>[]];
  $t0=a_ts((string)$first['at']);$window=[];
  foreach($msgs as $m){
    if($m['direction']!=='inbound')continue;
    $ts=a_ts((string)$m['at']); if($ts===0||$ts-$t0>900)break;
    $window[]=$m; if(count($window)>=4)break;
  }
  $joined=' ';foreach($window as $m)$joined.=' '.(string)$m['text'];
  $firstText=(string)($window[0]['text']??'');

  $strongPrior=[
    'كما اتفقنا','زي ما اتفقنا','حسب اتفاقنا','حسب الاتفاق','باقي المبلغ','باقي الحساب',
    'المرة اللي فاتت','المرة السابقة','سبق وكلمتك','سبق وتواصلت','كلمتك قبل','تكلمنا قبل',
    'ارسلت لك قبل','أرسلت لك قبل','نفس الطلب','نفس الجهاز','نفس الكمية','باقي الطلب',
    'الدفعة الثانية','الدفعة الاخيرة','الدفعة الأخيرة','ارجع لك مثل ما اتفقنا','راجع لك مثل ما اتفقنا'
  ];
  $nonLeadCommerce=['ايفون','آيفون','iphone','16 برو','16pro','برو ماكس','pro max','سلفر','جهاز','اجهزة','أجهزة','كمية','الكميه','الكميات','سعره أعلى','سعر اعلى'];
  $leadIntent=['عقار','شقة','شقه','فيلا','تمويل','رهن','مديونية','قرض','تملك','التملك','شراء بيت','شراء عقار','استشارة','استشاره','سمة','الراتب','البنك','الدفعة','ميزانية','وحدة سكنية','وحده سكنيه'];
  $callRequest=['اتصل علي','اتصلوا علي','كلمني','كلّموني','دق علي','دقوا علي','تواصل معي','تواصلوا معي','ابي احد يتصل','أبي أحد يتصل'];

  if(a_contains($joined,$strongPrior))return['bucket'=>'clear_prior_or_existing','reason'=>'explicit_prior_relationship_language','window'=>$window];
  if(a_contains($joined,$nonLeadCommerce))return['bucket'=>'clear_prior_or_existing','reason'=>'non_arkan_commerce_context','window'=>$window];
  if(a_contains($firstText,$callRequest))return['bucket'=>'likely_new_lead','reason'=>'first_message_call_request_no_prior_evidence','window'=>$window];
  if(a_contains($joined,$leadIntent))return['bucket'=>'likely_new_lead','reason'=>'service_intent_no_prior_evidence','window'=>$window];

  $genericGreetings=['السلام عليكم','سلام عليكم','مرحبا','مرحباً','هلا','هاي','hello','hi'];
  if(a_contains($firstText,$genericGreetings))return['bucket'=>'ambiguous','reason'=>'generic_opening_no_source_or_prior_evidence','window'=>$window];

  if(a_clean($firstText)==='')return['bucket'=>'ambiguous','reason'=>'unsupported_or_empty_first_message','window'=>$window];
  return['bucket'=>'ambiguous','reason'=>'insufficient_evidence','window'=>$window];
}

$connections=json_decode((string)@file_get_contents($connectionsFile),true);if(!is_array($connections))$connections=[];
$connClient=[];foreach($connections as $k=>$v)if(is_array($v)){$id=a_s($v['id']??(is_string($k)?$k:''));if($id!=='')$connClient[$id]=a_s($v['client_id']??'');}

$convs=[];
if(is_file($rawFile)&&($fh=fopen($rawFile,'rb'))){
 while(($line=fgets($fh))!==false){
  $r=json_decode($line,true);if(!is_array($r))continue;
  $p=is_array($r['payload']??null)?$r['payload']:[];
  $type=a_s($r['type']??'');$conn=a_s($r['ycloud_connection_id']??'');$cid=$connClient[$conn]??'';
  if($cid!==$clientId)continue;
  $m=null;$dir='';
  if($type==='whatsapp.inbound_message.received'&&isset($p['whatsappInboundMessage'])){$m=$p['whatsappInboundMessage'];$dir='inbound';}
  elseif($type==='whatsapp.smb.message.echoes'&&isset($p['whatsappMessage'])){$m=$p['whatsappMessage'];$dir='outbound';}
  elseif($type==='whatsapp.smb.history'&&isset($p['whatsappInboundMessage'])){$m=$p['whatsappInboundMessage'];$dir='inbound';}
  elseif($type==='whatsapp.smb.history'&&isset($p['whatsappMessage'])){$m=$p['whatsappMessage'];$dir='outbound';}
  if(!is_array($m))continue;
  $w=a_s($m['wabaId']??'');$cust=a_s($dir==='inbound'?($m['from']??''):($m['to']??''));if($w===''||$cust==='')continue;
  $id=substr(hash('sha256',$w.'|'.$cust),0,24);$at=a_s($m['sendTime']??$r['createTime']??'');$txt=a_text($m);
  if(!isset($convs[$id]))$convs[$id]=['messages'=>[],'first_inbound_at'=>'','first_source'=>'unknown'];
  $convs[$id]['messages'][]=['direction'=>$dir,'at'=>$at,'type'=>a_s($m['type']??''),'text'=>$txt,'raw'=>$m];
  if($dir==='inbound'&&$at!==''&&($convs[$id]['first_inbound_at']===''||strcmp($at,$convs[$id]['first_inbound_at'])<0)){
    $convs[$id]['first_inbound_at']=$at;$convs[$id]['first_source']=a_source($m,$txt);
  }
 }
 fclose($fh);
}

$tz=new DateTimeZone('Asia/Riyadh');$start=new DateTimeImmutable($from.' 00:00:00',$tz);$end=new DateTimeImmutable($to.' 23:59:59',$tz);
$counts=['likely_new_lead'=>0,'clear_prior_or_existing'=>0,'ambiguous'=>0];
$reasons=[];$examples=['likely_new_lead'=>[],'clear_prior_or_existing'=>[],'ambiguous'=>[]];$totalUnknown=0;
foreach($convs as $id=>$c){
 $fi=(string)$c['first_inbound_at'];if($fi==='')continue;
 try{$dt=(new DateTimeImmutable($fi))->setTimezone($tz);}catch(Throwable){continue;}
 if($dt<$start||$dt>$end)continue;
 if(($c['first_source']??'unknown')!=='unknown')continue;
 $totalUnknown++;
 $x=a_classify($c['messages']);$b=$x['bucket'];$counts[$b]++;$reasons[$x['reason']]=($reasons[$x['reason']]??0)+1;
 if(count($examples[$b])<12){
   $sn=[];foreach($x['window'] as $m)$sn[]=['at'=>$m['at'],'text'=>$m['text'],'type'=>$m['type']];
   $examples[$b][]=['lead_id'=>$id,'first_inbound_at'=>$fi,'reason'=>$x['reason'],'opening_window'=>$sn];
 }
}
echo json_encode(['ok'=>true,'client_id'=>$clientId,'from'=>$from,'to'=>$to,'unknown_first_time_whatsapp'=>$totalUnknown,'classification'=>$counts,'reasons'=>$reasons,'examples'=>$examples],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";
