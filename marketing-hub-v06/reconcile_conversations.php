<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

$base=__DIR__.'/data';
$rawFile=$base.'/raw_events.jsonl';
$convFile=$base.'/conversations.json';
$connFile=$base.'/ycloud_connections.json';
$clickFile=$base.'/chatlink_clicks.json';
$contactFile=$base.'/contact_sources.json';
$eventsFile=$base.'/conversion_events.jsonl';

function r_json(string $f,array $d=[]):array{
  if(!is_file($f))return$d;
  $v=json_decode((string)@file_get_contents($f),true);
  return is_array($v)?$v:$d;
}
function r_save(string $f,array $v):void{
  $tmp=$f.'.tmp';
  if(file_put_contents($tmp,json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX)===false)throw new RuntimeException('write_failed');
  if(!rename($tmp,$f))throw new RuntimeException('rename_failed');
}
function r_s(mixed $v):string{return is_scalar($v)?trim((string)$v):'';}
function r_digits(string $v):string{return preg_replace('/\D+/','',$v)??'';}
function r_valid_type(string $t):bool{
  return !in_array(strtolower(trim($t)),['','unsupported','reaction','system','unknown','revoke','revoked'],true);
}
function r_text(array $m):string{
  $type=strtolower(r_s($m['type']??'unknown'));
  if($type==='text')return r_s($m['text']['body']??'');
  if($type==='interactive')return '[interactive]';
  foreach(['image','video','document','audio','sticker'] as $k){
    if($type===$k&&isset($m[$k])){
      $cap=r_s($m[$k]['caption']??'');if($cap!=='')return$cap;
      $fn=r_s($m[$k]['filename']??'');return$fn!==''?'['.$k.'] '.$fn:'['.$k.']';
    }
  }
  if($type==='location')return'[location] '.r_s($m['location']['name']??$m['location']['address']??'');
  if($type==='contacts')return'[contacts]';
  if($type==='reaction')return'[reaction]';
  return'['.$type.']';
}
function r_epoch(string $s):int{
  if($s==='')return 0;
  try{return(new DateTimeImmutable($s))->getTimestamp();}catch(Throwable){return 0;}
}
function r_iso(int $t):string{return gmdate('Y-m-d\TH:i:s.000\Z',$t);}
function r_contains(string $text,array $needles):bool{
  $t=mb_strtolower($text,'UTF-8');
  foreach($needles as $n){
    $n=mb_strtolower((string)$n,'UTF-8');
    if($n!==''&&mb_strpos($t,$n)!==false)return true;
  }
  return false;
}
function r_serious(string $text,string $type):bool{
  if(in_array(strtolower($type),['document','image','location','contacts'],true))return true;
  return r_contains($text,[
    'السعر','سعر','التكلفة','تكلفة','موعد','احجز','حجز','التوفر','متاح','المدة','المتطلبات',
    'الأوراق','المستندات','نبدأ','ابدأ','أبدأ','الدفع','تحويل','زيارة','موقعكم','العنوان',
    'استشارة','قضية','عقد','أتعاب','الاتعاب','شركة','تركة','تنفيذ','دعوى','توكيل','عرض','تفاصيل',
    'price','cost','appointment','book','booking','available','requirements','documents','payment','contract','quote'
  ]);
}
function r_converted(string $text):bool{
  return r_contains($text,[
    'تم الدفع','دفعت','تم التحويل','حولت','حوّلت','تم الحجز','حجزت الموعد','تم تأكيد الحجز',
    'تم التعاقد','وقعت العقد','وقّعت العقد','تم توقيع العقد','تم إصدار العقد','تم اصدار العقد',
    'تم قبول الطلب','تم الشراء','اشتريت','paid','payment done','payment completed',
    'booking confirmed','contract signed','order confirmed','purchased'
  ]);
}
function r_decode_token(string $text):array{
  $empty=['click_id'=>'','token'=>''];
  $map=["\u{200B}"=>0,"\u{200C}"=>1,"\u{200D}"=>2,"\u{FEFF}"=>3];
  $candidates=[];
  if(preg_match_all('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]{4,}/u',$text,$m))foreach(($m[0]??[])as$x)$candidates[]=$x;
  if(preg_match_all('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]/u',$text,$m)){
    $joined=implode('',(array)($m[0]??[]));
    if(mb_strlen($joined,'UTF-8')>=16)$candidates[]=$joined;
  }
  foreach($candidates as $hidden){
    $chars=preg_split('//u',$hidden,-1,PREG_SPLIT_NO_EMPTY);if(!is_array($chars))continue;
    $n=count($chars)-count($chars)%4;$bytes='';
    for($i=0;$i<$n;$i+=4){
      if(!isset($map[$chars[$i]],$map[$chars[$i+1]],$map[$chars[$i+2]],$map[$chars[$i+3]])){ $bytes='';break;}
      $bytes.=chr(($map[$chars[$i]]<<6)|($map[$chars[$i+1]]<<4)|($map[$chars[$i+2]]<<2)|$map[$chars[$i+3]]);
    }
    if($bytes!==''&&preg_match('/(hzn1\.(clk_[A-Za-z0-9_-]{4,120})\.[A-Za-z0-9_-]{10,64})/',$bytes,$mm))return['click_id'=>$mm[2],'token'=>$mm[1]];
    if($bytes!==''&&preg_match('/(?:hzn\.attr|ycloud\.chatlink)\.(clk_[A-Za-z0-9_-]{4,120})/',$bytes,$mm))return['click_id'=>$mm[1],'token'=>$bytes];
  }
  return$empty;
}
function r_click_time(array $c):int{
  foreach(['browser_time','created_at','captured_at']as$k){$t=r_epoch(r_s($c[$k]??''));if($t>0)return$t;}
  return 0;
}
function r_apply_click(array &$c,array $click,string $clickId,string $method):void{
  $c['traffic_source_key']=r_s($click['traffic_source_key']??'website');
  $c['traffic_source_label']=r_s($click['traffic_source_label']??'Website');
  $c['traffic_source_confidence']='high';
  $c['traffic_source_reason']=$method;
  $c['attribution_match_method']=$method;
  $c['horizons_wa_click_id']=$clickId;
  $c['ycloud_chatlink_click_id']=$clickId;
  $c['chatlink_source_url']=r_s($click['source_url']??'');
  $c['attribution_landing_url']=r_s($click['landing_url']??$click['source_url']??'');
  $c['attribution_referrer']=r_s($click['referrer']??'');
  $c['attribution_params']=is_array($click['query_params']??null)?$click['query_params']:[];
  $c['attribution_utm']=is_array($click['utm']??null)?$click['utm']:[];
  foreach(['gclid'=>'google_gclid','gbraid'=>'google_gbraid','wbraid'=>'google_wbraid','dclid'=>'google_dclid','fbclid'=>'meta_fbclid','ttclid'=>'tiktok_ttclid','msclkid'=>'microsoft_msclkid','li_fat_id'=>'linkedin_li_fat_id','twclid'=>'x_twclid']as$from=>$to){
    if(r_s($click[$from]??'')!=='')$c[$to]=r_s($click[$from]);
  }
  $sc=r_s($click['scclid']??$click['ScCid']??'');if($sc!=='')$c['snapchat_scclid']=$sc;
  foreach((array)($click['utm']??[])as$k=>$v)if(str_starts_with((string)$k,'utm_'))$c[(string)$k]=r_s($v);
}
function r_rank(string $s):int{return match(strtolower($s)){'message_received'=>0,'interested'=>1,'qualified'=>2,'purchased','converted'=>3,'lost'=>90,default=>-1};}
function r_event_keys(string $f):array{
  $out=[];if(!is_file($f))return$out;$h=fopen($f,'rb');
  while(($line=fgets($h))!==false){$r=json_decode($line,true);if(!is_array($r))continue;$id=r_s($r['conversation_id']??'');$e=r_s($r['event']??'');if($id!==''&&$e!=='')$out[$id.'|'.$e]=true;}
  fclose($h);return$out;
}
function r_append_stage(string $f,array $c,string $stage,string $at,array &$keys):void{
  $key=$c['id'].'|'.$stage;if(isset($keys[$key]))return;
  $row=['id'=>'mev_'.bin2hex(random_bytes(8)),'event'=>$stage,'conversation_id'=>$c['id'],'client_id'=>$c['client_id']??'',
    'number_id'=>$c['number_id']??'','ycloud_connection_id'=>$c['ycloud_connection_id']??'','waba_id'=>$c['waba_id']??'',
    'business_number'=>$c['business_number']??'','customer_number'=>$c['customer_number']??'','contact_name'=>$c['contact_name']??'',
    'source'=>'reconcile','created_at'=>$at];
  file_put_contents($f,json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n",FILE_APPEND|LOCK_EX);$keys[$key]=true;
}

$conns=r_json($connFile,[]);$connClient=[];
foreach($conns as $id=>$row)if(is_array($row))$connClient[(string)($row['id']??$id)]=r_s($row['client_id']??'');
$old=r_json($convFile,[]);$clicks=r_json($clickFile,[]);$contacts=r_json($contactFile,[]);
$contactBy=[];
foreach($contacts as $row){
  if(!is_array($row))continue;$cid=r_s($row['client_id']??'');$p=r_digits(r_s($row['phone_number']??''));
  if($cid!==''&&$p!=='')$contactBy[$cid.'|'.$p]=$row;
}
$clickByClient=[];
foreach($clicks as $id=>$row){
  if(!is_array($row))continue;$cid=r_s($row['client_id']??'');$t=r_click_time($row);if($cid===''||$t<=0)continue;
  $row['_id']=(string)$id;$row['_t']=$t;$clickByClient[$cid][]=$row;
}
foreach($clickByClient as &$arr)usort($arr,fn($a,$b)=>$a['_t']<=>$b['_t']);unset($arr);

$state=[];$seenMsg=[];$eventRows=0;
if(is_file($rawFile)&&($h=fopen($rawFile,'rb'))){
  while(($line=fgets($h))!==false){
    $row=json_decode($line,true);if(!is_array($row))continue;$eventRows++;
    $payload=is_array($row['payload']??null)?$row['payload']:[];
    $type=r_s($row['type']??$payload['type']??'');$dir='';$m=null;$history=false;
    if($type==='whatsapp.inbound_message.received'&&is_array($payload['whatsappInboundMessage']??null)){$dir='inbound';$m=$payload['whatsappInboundMessage'];}
    elseif($type==='whatsapp.smb.message.echoes'&&is_array($payload['whatsappMessage']??null)){$dir='outbound';$m=$payload['whatsappMessage'];}
    elseif($type==='whatsapp.smb.history'&&is_array($payload['whatsappInboundMessage']??null)){$dir='inbound';$m=$payload['whatsappInboundMessage'];$history=true;}
    elseif($type==='whatsapp.smb.history'&&is_array($payload['whatsappMessage']??null)){$dir='outbound';$m=$payload['whatsappMessage'];$history=true;}
    if(!is_array($m)||$dir==='')continue;
    $conn=r_s($row['ycloud_connection_id']??'');$cid=$connClient[$conn]??'';if($cid==='')continue;
    $waba=r_s($m['wabaId']??'');$business=$dir==='inbound'?r_s($m['to']??''):r_s($m['from']??'');
    $customer=$dir==='inbound'?r_s($m['from']??''):r_s($m['to']??'');if(r_digits($customer)===''||r_digits($customer)===r_digits($business))continue;
    $convId=substr(hash('sha256',$waba.'|'.$customer),0,24);
    $msgId=r_s($m['id']??$m['messageId']??$row['id']??'');if($msgId==='')$msgId=sha1($convId.'|'.$dir.'|'.r_s($m['sendTime']??$row['createTime']??'').'|'.r_text($m));
    if(isset($seenMsg[$convId][$msgId]))continue;$seenMsg[$convId][$msgId]=true;
    $at=r_s($m['sendTime']??$payload['createTime']??$row['createTime']??'');$ep=r_epoch($at);if($ep<=0)continue;
    $msgType=r_s($m['type']??'unknown');$text=r_text($m);
    if(!isset($state[$convId]))$state[$convId]=[
      'client_id'=>$cid,'connection'=>$conn,'waba'=>$waba,'business'=>$business,'customer'=>$customer,
      'name'=>r_s($m['customerProfile']['name']??''),'messages'=>[],'first_in'=>0,'last'=>0,
      'inbound'=>0,'valid_inbound'=>0,'outbound'=>0,'token_click'=>''
    ];
    $s=&$state[$convId];
    if($s['name']===''&&r_s($m['customerProfile']['name']??'')!=='')$s['name']=r_s($m['customerProfile']['name']);
    $s['messages'][]=['direction'=>$dir,'type'=>$msgType,'text'=>$text,'at'=>$at,'epoch'=>$ep,'msg_id'=>$msgId,'raw'=>$m];
    if($ep>$s['last'])$s['last']=$ep;
    if($dir==='inbound'){
      $s['inbound']++;
      if(r_valid_type($msgType)){
        $s['valid_inbound']++;
        if($s['first_in']===0||$ep<$s['first_in'])$s['first_in']=$ep;
      }
      if($s['token_click']===''&&$msgType==='text'){
        $dec=r_decode_token((string)($m['text']['body']??''));
        if($dec['click_id']!=='')$s['token_click']=$dec['click_id'];
      }
    }else{$s['outbound']++;}
    unset($s);
  }
  fclose($h);
}

$stageKeys=r_event_keys($eventsFile);$new=$old;$stats=['rebuilt'=>0,'new_conversations'=>0,'token_matches'=>0,'timestamp_matches'=>0,'google'=>0,'interested'=>0,'qualified'=>0,'converted'=>0];
foreach($state as $convId=>$s){
  if($s['valid_inbound']<1)continue;
  usort($s['messages'],fn($a,$b)=>$a['epoch']<=>$b['epoch']);
  $prev=is_array($old[$convId]??null)?$old[$convId]:[];$c=$prev;
  $c['id']=$convId;$c['client_id']=$s['client_id'];$c['ycloud_connection_id']=$s['connection'];$c['waba_id']=$s['waba'];
  $c['business_number']=$s['business'];$c['customer_number']=$s['customer'];$c['contact_name']=$s['name']!==''?$s['name']:r_s($prev['contact_name']??$s['customer']);
  $c['first_seen_at']=r_iso($s['first_in']);$c['last_message_at']=r_iso($s['last']);$last=end($s['messages']);
  $c['last_message_text']=$last['text'];$c['last_message_type']=$last['type'];$c['last_direction']=$last['direction']==='inbound'?'inbound':'outbound_app';
  $c['inbound_count']=$s['inbound'];$c['valid_inbound_count']=$s['valid_inbound'];$c['outbound_count']=$s['outbound'];
  $recent=array_slice($s['messages'],-80);
  $c['recent_messages']=array_map(fn($m)=>['direction'=>$m['direction']==='inbound'?'inbound':'outbound_app','text'=>$m['text'],'type'=>$m['type'],'at'=>$m['at'],'message_id'=>$m['msg_id']],$recent);

  $clickId=$s['token_click']!==''?$s['token_click']:r_s($prev['horizons_wa_click_id']??$prev['ycloud_chatlink_click_id']??'');
  if($clickId!==''&&isset($clicks[$clickId])&&is_array($clicks[$clickId])){
    r_apply_click($c,$clicks[$clickId],$clickId,$s['token_click']!==''?'horizons_hidden_token':'stored_click_id');$stats['token_matches']++;
  }elseif(!in_array(strtolower(r_s($c['traffic_source_key']??'')),['google','tiktok','meta','snapchat','microsoft_ads','linkedin','x'],true)){
    $cand=[];
    foreach($clickByClient[$s['client_id']]??[] as $cl){
      if(r_digits(r_s($cl['business_number']??''))!==''&&r_digits(r_s($cl['business_number']??''))!==r_digits($s['business']))continue;
      $t=(int)$cl['_t'];if($t>$s['first_in']||$s['first_in']>$t+300)continue;$cand[]=$cl;
    }
    if(count($cand)===1){$cl=$cand[0];r_apply_click($c,$cl,r_s($cl['_id']),'single_unique_browser_5m_window');$stats['timestamp_matches']++;}
  }
  $cs=$contactBy[$s['client_id'].'|'.r_digits($s['customer'])]??[];
  if(is_array($cs)&&in_array(strtolower(r_s($cs['traffic_source_key']??'')),['google','tiktok','meta','snapchat','microsoft_ads','linkedin','x'],true)){
    $c['traffic_source_key']=r_s($cs['traffic_source_key']);$c['traffic_source_label']=r_s($cs['traffic_source_label']??$c['traffic_source_key']);
    $c['traffic_source_confidence']='high';$c['traffic_source_reason']='native_contact';$c['attribution_match_method']='native_contact';
  }
  if(r_s($c['traffic_source_key']??'')===''){$c['traffic_source_key']='organic';$c['traffic_source_label']='Organic / Direct';$c['traffic_source_confidence']='medium';$c['traffic_source_reason']='no_specific_attribution';}

  $valid=0;$hadOutbound=false;$replied=false;$serious=false;$converted=false;$stageTimes=[];
  foreach($s['messages'] as $m){
    if($m['direction']==='outbound'){$hadOutbound=true;continue;}
    if(!r_valid_type($m['type']))continue;
    $valid++;
    if($valid===1)$stageTimes['message_started']=r_iso($m['epoch']);
    if($valid===2)$stageTimes['interested']=r_iso($m['epoch']);
    if($hadOutbound)$replied=true;
    if(r_serious($m['text'],$m['type']))$serious=true;
    if(r_converted($m['text'])){$converted=true;if(!isset($stageTimes['converted']))$stageTimes['converted']=r_iso($m['epoch']);}
    if($valid>=3&&$hadOutbound&&$replied&&$serious&&!isset($stageTimes['qualified']))$stageTimes['qualified']=r_iso($m['epoch']);
  }
  $autoStage='message_received';
  if($valid>=2)$autoStage='interested';
  if(isset($stageTimes['qualified']))$autoStage='qualified';
  if($converted)$autoStage='converted';
  $manual=strtolower(r_s($prev['tag_source']??''))==='manual';
  $current=$manual?r_s($prev['current_tag']??$autoStage):$autoStage;
  if($current==='purchased')$current='converted';
  if($manual&&r_rank($current)<r_rank($autoStage))$current=$autoStage;
  $c['current_tag']=$current;$c['tag_source']=$manual?'manual':'automation';$c['stage_times']=$stageTimes;$c['tagged_at']=$stageTimes[$current]??r_iso($s['last']);
  $c['auto_label_reason']=match($current){'message_received'=>'first_valid_customer_message','interested'=>'two_valid_customer_messages','qualified'=>'three_plus_messages_two_way_serious_intent','converted'=>'explicit_completed_business_outcome',default=>r_s($prev['auto_label_reason']??'')};
  $c['auto_label_confidence']=match($current){'message_received'=>1.0,'interested'=>0.96,'qualified'=>0.93,'converted'=>0.99,default=>(float)($prev['auto_label_confidence']??0.8)};
  $c['updated_at']=gmdate('c');
  $new[$convId]=$c;$stats['rebuilt']++;if(!isset($old[$convId]))$stats['new_conversations']++;
  if($c['traffic_source_key']==='google')$stats['google']++;if(r_rank($current)>=1)$stats['interested']++;if(r_rank($current)>=2)$stats['qualified']++;if(r_rank($current)>=3)$stats['converted']++;

  r_append_stage($eventsFile,$c,'message_received',$stageTimes['message_started']??$c['first_seen_at'],$stageKeys);
  if(r_rank($current)>=1)r_append_stage($eventsFile,$c,'interested',$stageTimes['interested']??$c['tagged_at'],$stageKeys);
  if(r_rank($current)>=2)r_append_stage($eventsFile,$c,'qualified',$stageTimes['qualified']??$c['tagged_at'],$stageKeys);
  if(r_rank($current)>=3)r_append_stage($eventsFile,$c,'converted',$stageTimes['converted']??$c['tagged_at'],$stageKeys);
}
r_save($convFile,$new);
echo json_encode(['ok'=>true,'raw_events'=>$eventRows,'conversations'=>count($new),'stats'=>$stats],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";
