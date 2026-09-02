<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, YCloud-Signature, X-Webhook-Endpoint-ID');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$base=__DIR__.'/data'; if(!is_dir($base)) @mkdir($base,0775,true);
$secureDir=dirname(__DIR__,4).'/.marketing';
$ycloudSecureDir=$secureDir.'/ycloud';
$legacyKeyFile=$secureDir.'/ycloud_api_key';
$connectionsFile=$base.'/ycloud_connections.json';
$numbersFile=$base.'/whatsapp_numbers.json';
$automationRulesFile=$base.'/automation_rules.json';
$deliveryFile=$base.'/platform_delivery.jsonl';

function load_json(string $file,array $default=[]):array{
    if(!is_file($file)) return $default;
    $raw=@file_get_contents($file);
    if($raw===false||trim($raw)==='') return $default;
    $v=json_decode($raw,true);
    return is_array($v)?$v:$default;
}
function save_json(string $file,array $data):bool{
    $tmp=$file.'.tmp';
    $ok=@file_put_contents($tmp,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);
    if($ok===false) return false;
    return @rename($tmp,$file);
}
function append_jsonl(string $file,array $data):bool{
    $fh=@fopen($file,'ab'); if(!$fh) return false;
    @flock($fh,LOCK_EX);
    $ok=fwrite($fh,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n")!==false;
    @flock($fh,LOCK_UN); fclose($fh); return $ok;
}
function normalize_phone(string $phone):string{
    $phone=trim($phone); $plus=str_starts_with($phone,'+')?'+':'';
    $digits=preg_replace('/\D+/','',$phone)??'';
    return $digits===''?'':$plus.$digits;
}
function safe_id(string $id):bool{return(bool)preg_match('/^yc_[a-z0-9_]{4,80}$/',$id);}
function msg_text(array $m):string{
    $type=(string)($m['type']??'unknown');
    if($type==='text') return trim((string)($m['text']['body']??''));
    foreach(['image','video','document','audio','sticker'] as $k){
        if($type===$k&&isset($m[$k])){
            $caption=trim((string)($m[$k]['caption']??'')); if($caption!=='') return $caption;
            $filename=trim((string)($m[$k]['filename']??'')); return $filename!==''?'['.$k.'] '.$filename:'['.$k.']';
        }
    }
    if($type==='location'){$loc=$m['location']??[];return'[location] '.trim((string)($loc['name']??$loc['address']??''));}
    if($type==='contacts') return '[contacts]';
    if($type==='reaction') return '[reaction] '.(string)($m['reaction']['emoji']??'');
    if($type==='interactive') return '[interactive]';
    return '['.$type.']';
}
function event_seen(string $file,string $id):bool{
    if($id===''||!is_file($file)) return false;
    $fh=fopen($file,'rb'); if(!$fh) return false;
    while(($line=fgets($fh))!==false){$row=json_decode($line,true);if(is_array($row)&&($row['id']??'')===$id){fclose($fh);return true;}}
    fclose($fh); return false;
}
function add_conversion_event(string $file,array $conv,string $eventName,string $sourceEventId,array $extra=[]):string{
    $id='mev_'.bin2hex(random_bytes(8));
    append_jsonl($file,array_merge([
        'id'=>$id,'event'=>$eventName,'conversation_id'=>$conv['id'],'client_id'=>$conv['client_id']??'',
        'number_id'=>$conv['number_id']??'','ycloud_connection_id'=>$conv['ycloud_connection_id']??'',
        'waba_id'=>$conv['waba_id']??'','business_number'=>$conv['business_number']??'',
        'customer_number'=>$conv['customer_number']??'','contact_name'=>$conv['contact_name']??'',
        'ctwa_clid'=>$conv['ctwa_clid']??null,'source_event_id'=>$sourceEventId,'created_at'=>gmdate('c')
    ],$extra));
    return $id;
}
function verify_signature(string $raw,string $header,string $secret):bool{
    if($secret===''||$header==='') return false; $t='';$s='';
    foreach(explode(',',$header) as $part){$part=trim($part);if(str_starts_with($part,'t='))$t=substr($part,2);elseif(str_starts_with($part,'s='))$s=substr($part,2);}
    if($t===''||$s===''||!ctype_digit($t)) return false;
    return hash_equals(hash_hmac('sha256',$t.'.'.$raw,$secret),$s);
}
function upsert_number(string $numbersFile,string $business,string $waba,string $connectionId,string $clientId):string{
    $numbers=load_json($numbersFile,[]);$phone=normalize_phone($business);$id='';
    foreach($numbers as $nid=>$n){if(normalize_phone((string)($n['phone_number']??''))===$phone&&(string)($n['waba_id']??'')===$waba){$id=(string)$nid;break;}}
    if($id==='')$id='num_'.substr(sha1($phone.'|'.$waba),0,12);
    $now=gmdate('c');$prev=$numbers[$id]??[];
    $numbers[$id]=[
        'id'=>$id,'client_id'=>$clientId!==''?$clientId:(string)($prev['client_id']??''),
        'ycloud_connection_id'=>$connectionId!==''?$connectionId:(string)($prev['ycloud_connection_id']??''),
        'label'=>(string)($prev['label']??'Detected WhatsApp'),'phone_number'=>$phone,'waba_id'=>$waba,'provider'=>'ycloud',
        'status'=>($clientId!==''||(string)($prev['client_id']??'')!=='')?'assigned':'detected','detected'=>true,
        'created_at'=>(string)($prev['created_at']??$now),'updated_at'=>$now
    ];
    save_json($numbersFile,$numbers);return$id;
}
function ycloud_key_file(string $secureBase,string $connectionId,string $legacyKeyFile):string{
    return $connectionId==='yc_legacy'?$legacyKeyFile:$secureBase.'/'.$connectionId.'/api_key';
}
function ycloud_request(string $apiKey,string $method,string $path,?array $payload=null):array{
    if($apiKey==='') return ['ok'=>false,'status'=>0,'error'=>'missing_api_key'];
    $ch=curl_init('https://api.ycloud.com'.$path);
    $headers=['X-API-Key: '.$apiKey,'Accept: application/json']; if($payload!==null)$headers[]='Content-Type: application/json';
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>18,CURLOPT_FOLLOWLOCATION=>false]);
    if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $body=curl_exec($ch);$errno=curl_errno($ch);$error=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    return ['ok'=>$errno===0&&$status>=200&&$status<300,'status'=>$status,'error'=>$errno?$error:null,'body'=>$body];
}
function text_len(string $s):int{return function_exists('mb_strlen')?mb_strlen($s,'UTF-8'):strlen($s);}
function contains_any(string $text,array $needles):bool{
    $text=strtolower(trim($text)); if($text==='')return false;
    foreach($needles as $needle){$needle=strtolower(trim((string)$needle));if($needle!==''&&str_contains($text,$needle))return true;}
    return false;
}
function is_substantive(string $text,string $type):bool{
    if(in_array($type,['location','contacts','document','image','video','audio','interactive'],true))return true;
    $t=trim(strtolower($text));if($t==='')return false;
    $plain=preg_replace('/[\p{P}\p{S}\s]+/u','',$t)??$t;if($plain==='')return false;
    $ack=['شكرا','شكراً','شكرًا','تمام','اوكي','أوكي','اوك','ok','okay','thanks','thank you','ماشي','حسنا','حسنًا','👍','👌','🙏'];
    foreach($ack as $a)if($t===$a)return false;
    return text_len($plain)>=2;
}
function recent_push(array $recent,array $item,int $limit=12):array{
    $recent[]=$item;if(count($recent)>$limit)$recent=array_slice($recent,-$limit);return array_values($recent);
}
function automation_rules(string $file,string $clientId):array{
    $cfg=load_json($file,[]);$default=is_array($cfg['default']??null)?$cfg['default']:[];$client=is_array($cfg['clients'][$clientId]??null)?$cfg['clients'][$clientId]:[];
    return array_replace_recursive($default,$client);
}
function default_rules():array{
    return [
        'enabled'=>true,
        'interested'=>['min_inbound'=>2,'min_outbound'=>1,'require_reply_after_staff'=>true],
        'qualified'=>[
            'min_inbound'=>2,'min_outbound'=>1,'fallback_min_inbound'=>4,'fallback_min_outbound'=>2,
            'keywords'=>['السعر','سعر','التكلفة','تكلفة','كم','الميزانية','ميزانية','موعد','احجز','حجز','ابغى','أبغى','ابي','أبي','اريد','أريد','جاهز','نبدأ','ابدأ','ابدأوا','المتطلبات','الأوراق','المستندات','الموقع','العنوان','التفاصيل','العرض','الخدمة','تمويل','عقار','استشارة','price','cost','budget','appointment','book','booking','ready','proceed','requirements','documents','location','address','details']
        ],
        'purchased'=>['keywords'=>['تم الدفع','دفعت','تم التحويل','حولت','حوّلت','ارسلت العربون','أرسلت العربون','تم الحجز','حجزت','تم التعاقد','وقعت العقد','وقّعت العقد','اتفقنا','اشتريت','paid','payment done','transferred','booked','contract signed','purchased']],
        'lost'=>['keywords'=>['مش مهتم','غير مهتم','لست مهتم','ما ابغى','ما أبغى','لا اريد','لا أريد','الغاء','إلغاء','الغي','ألغي','وقف التواصل','لا تتواصل','لا تواصلوا','not interested','do not contact','stop messaging','unsubscribe','cancel']]
    ];
}
function stage_rank(?string $tag):int{return match($tag){'interested'=>1,'qualified'=>2,'purchased'=>3,'lost'=>90,default=>0};}
function classify_auto(array $conv,bool $repliedToStaff,string $text,string $type,array $rules):?array{
    if(!($rules['enabled']??true))return null;
    $current=(string)($conv['current_tag']??'');
    if($current==='purchased')return null;
    $in=(int)($conv['inbound_count']??0);$out=(int)($conv['outbound_count']??0);$sub=is_substantive($text,$type);
    $lostKw=(array)($rules['lost']['keywords']??[]);$purchaseKw=(array)($rules['purchased']['keywords']??[]);$qualKw=(array)($rules['qualified']['keywords']??[]);
    if($sub&&contains_any($text,$lostKw))return ['tag'=>'lost','reason'=>'explicit_negative_intent','confidence'=>0.98];
    if($sub&&contains_any($text,$purchaseKw))return ['tag'=>'purchased','reason'=>'explicit_purchase_intent','confidence'=>0.98];
    $qualMinIn=(int)($rules['qualified']['min_inbound']??2);$qualMinOut=(int)($rules['qualified']['min_outbound']??1);
    $qualEligible=$in>=$qualMinIn&&$out>=$qualMinOut;
    if($sub&&$qualEligible&&contains_any($text,$qualKw)&&stage_rank($current)<2)return ['tag'=>'qualified','reason'=>'service_intent_signal','confidence'=>0.88];
    $fbIn=(int)($rules['qualified']['fallback_min_inbound']??4);$fbOut=(int)($rules['qualified']['fallback_min_outbound']??2);
    if($sub&&$in>=$fbIn&&$out>=$fbOut&&$repliedToStaff&&stage_rank($current)<2)return ['tag'=>'qualified','reason'=>'sustained_two_way_engagement','confidence'=>0.78];
    $intMinIn=(int)($rules['interested']['min_inbound']??2);$intMinOut=(int)($rules['interested']['min_outbound']??1);$needReply=(bool)($rules['interested']['require_reply_after_staff']??true);
    if($sub&&$in>=$intMinIn&&$out>=$intMinOut&&(!$needReply||$repliedToStaff)&&stage_rank($current)<1)return ['tag'=>'interested','reason'=>'customer_replied_after_staff','confidence'=>0.90];
    if($current==='lost'&&$sub&&!contains_any($text,$lostKw)&&$repliedToStaff)return ['tag'=>'interested','reason'=>'reengaged_after_lost','confidence'=>0.80];
    return null;
}
function apply_auto_label(array &$conv,array $decision,string $conversionFile,string $deliveryFile,string $sourceEventId,string $ycloudSecureDir,string $legacyKeyFile):bool{
    $tag=(string)($decision['tag']??'');if($tag==='')return false;$current=(string)($conv['current_tag']??'');
    if($current===$tag)return false;
    if($current!=='lost'&&$tag!=='lost'&&stage_rank($tag)<=stage_rank($current))return false;
    if($current==='purchased')return false;
    $now=gmdate('c');$conv['current_tag']=$tag;$conv['tagged_at']=$now;$conv['tag_source']='automation';$conv['auto_label_reason']=$decision['reason']??'';$conv['auto_label_confidence']=$decision['confidence']??null;
    $history=is_array($conv['label_history']??null)?$conv['label_history']:[];$history[]=['tag'=>$tag,'source'=>'automation','reason'=>$decision['reason']??'','confidence'=>$decision['confidence']??null,'at'=>$now];if(count($history)>20)$history=array_slice($history,-20);$conv['label_history']=$history;
    $eventId=add_conversion_event($conversionFile,$conv,$tag,$sourceEventId,['source'=>'auto_automation','automation_reason'=>$decision['reason']??'','confidence'=>$decision['confidence']??null]);
    if($tag==='lost'){
        append_jsonl($deliveryFile,['id'=>'del_'.bin2hex(random_bytes(7)),'event_id'=>$eventId,'provider'=>'internal','event'=>$tag,'success'=>true,'http_status'=>0,'error'=>null,'created_at'=>$now]);
        return true;
    }
    $connId=(string)($conv['ycloud_connection_id']??'');$keyFile=ycloud_key_file($ycloudSecureDir,$connId,$legacyKeyFile);$key=is_file($keyFile)?trim((string)@file_get_contents($keyFile)):'';
    if($key!==''&&!empty($conv['customer_number'])){
        $resp=ycloud_request($key,'POST','/v2/event/events',['eventName'=>$tag,'occurTime'=>$now,'contactPhoneNumber'=>$conv['customer_number']]);
        append_jsonl($deliveryFile,['id'=>'del_'.bin2hex(random_bytes(7)),'event_id'=>$eventId,'provider'=>'ycloud','ycloud_connection_id'=>$connId,'event'=>$tag,'success'=>(bool)$resp['ok'],'http_status'=>(int)$resp['status'],'error'=>$resp['error'],'source'=>'automation','created_at'=>gmdate('c')]);
    } else {
        append_jsonl($deliveryFile,['id'=>'del_'.bin2hex(random_bytes(7)),'event_id'=>$eventId,'provider'=>'ycloud','ycloud_connection_id'=>$connId,'event'=>$tag,'success'=>false,'http_status'=>0,'error'=>$connId===''?'ycloud_connection_not_resolved':($key===''?'ycloud_connection_not_connected':'missing_customer_number'),'source'=>'automation','created_at'=>gmdate('c')]);
    }
    return true;
}

if(!is_file($automationRulesFile))save_json($automationRulesFile,['default'=>default_rules(),'clients'=>[]]);

$raw=file_get_contents('php://input');if($raw===false||trim($raw)===''){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'empty_body']);exit;}
$connections=load_json($connectionsFile,[]);$connectionId=trim((string)($_GET['connection']??''));
if($connectionId===''){if(isset($connections['yc_legacy']))$connectionId='yc_legacy';}
if($connectionId!==''&&(!safe_id($connectionId)||!isset($connections[$connectionId]))){http_response_code(404);echo json_encode(['ok'=>false,'error'=>'ycloud_connection_not_found']);exit;}
if($connectionId!==''&&$connectionId!=='yc_legacy'){$secretFile=$ycloudSecureDir.'/'.$connectionId.'/webhook_secret';$secret=is_file($secretFile)?trim((string)@file_get_contents($secretFile)):'';$sig=(string)($_SERVER['HTTP_YCLOUD_SIGNATURE']??'');if($secret!==''&&!verify_signature($raw,$sig,$secret)){http_response_code(401);echo json_encode(['ok'=>false,'error'=>'invalid_signature']);exit;}}
$event=json_decode($raw,true);if(!is_array($event)){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'invalid_json']);exit;}
$type=(string)($event['type']??'unknown');$eventId=(string)($event['id']??'');$rawFile=$base.'/raw_events.jsonl';$convFile=$base.'/conversations.json';$conversionFile=$base.'/conversion_events.jsonl';$systemFile=$base.'/system_events.jsonl';
if($eventId!==''&&event_seen($rawFile,$eventId)){echo json_encode(['ok'=>true,'duplicate'=>true,'event_type'=>$type],JSON_UNESCAPED_SLASHES);exit;}
$endpointId=(string)($_SERVER['HTTP_X_WEBHOOK_ENDPOINT_ID']??'');append_jsonl($rawFile,['id'=>$eventId,'type'=>$type,'ycloud_connection_id'=>$connectionId,'endpoint_id'=>$endpointId,'createTime'=>$event['createTime']??gmdate('c'),'payload'=>$event]);
if($connectionId!==''&&isset($connections[$connectionId])){$connections[$connectionId]['last_webhook_at']=gmdate('c');$connections[$connectionId]['last_event_type']=$type;if($endpointId!=='')$connections[$connectionId]['last_endpoint_id']=$endpointId;$connections[$connectionId]['updated_at']=gmdate('c');save_json($connectionsFile,$connections);}
$clientId=$connectionId!==''?(string)($connections[$connectionId]['client_id']??''):'';$conversations=load_json($convFile,[]);$conversationUpdated=false;$conversionCreated=0;$autoLabel=null;

$handleInbound=function(array $m,bool $history=false)use(&$conversations,&$conversationUpdated,&$conversionCreated,&$autoLabel,$convFile,$conversionFile,$deliveryFile,$event,$eventId,$connectionId,$clientId,$numbersFile,$automationRulesFile,$ycloudSecureDir,$legacyKeyFile){
    $waba=(string)($m['wabaId']??'');$customer=(string)($m['from']??'');$business=(string)($m['to']??'');$id=substr(hash('sha256',$waba.'|'.$customer),0,24);$isNew=!isset($conversations[$id]);
    $profile=is_array($m['customerProfile']??null)?$m['customerProfile']:[];$referral=is_array($m['referral']??null)?$m['referral']:[];$prev=$conversations[$id]??[];$numberId=upsert_number($numbersFile,$business,$waba,$connectionId,$clientId);
    $text=msg_text($m);$msgType=(string)($m['type']??'unknown');$sentAt=(string)($m['sendTime']??$event['createTime']??gmdate('c'));$repliedToStaff=(string)($prev['last_direction']??'')==='outbound_app';
    $recent=is_array($prev['recent_messages']??null)?$prev['recent_messages']:[];$recent=recent_push($recent,['direction'=>$history?'history_inbound':'inbound','text'=>$text,'type'=>$msgType,'at'=>$sentAt,'source_event_id'=>$eventId]);
    $inbound=(int)($prev['inbound_count']??0)+($history?0:1);$outbound=(int)($prev['outbound_count']??0);
    $conv=array_merge($prev,[
        'id'=>$id,'client_id'=>$clientId!==''?$clientId:(string)($prev['client_id']??''),'number_id'=>$numberId,'ycloud_connection_id'=>$connectionId!==''?$connectionId:(string)($prev['ycloud_connection_id']??''),
        'waba_id'=>$waba,'business_number'=>$business,'customer_number'=>$customer,'contact_name'=>(string)($profile['name']??($prev['contact_name']??$customer)),'contact_username'=>(string)($profile['username']??($prev['contact_username']??'')),
        'first_seen_at'=>$prev['first_seen_at']??$sentAt,'last_message_at'=>$sentAt,'last_message_text'=>$text,'last_message_type'=>$msgType,'last_direction'=>$history?'history_inbound':'inbound','last_source_event_id'=>$eventId,
        'current_tag'=>$prev['current_tag']??null,'ctwa_clid'=>(string)($referral['ctwa_clid']??($prev['ctwa_clid']??'')),'ad_source_id'=>(string)($referral['source_id']??($prev['ad_source_id']??'')),'ad_source_type'=>(string)($referral['source_type']??($prev['ad_source_type']??'')),'ad_headline'=>(string)($referral['headline']??($prev['ad_headline']??'')),
        'inbound_count'=>$inbound,'outbound_count'=>$outbound,'recent_messages'=>$recent,'last_customer_reply_to_staff'=>$repliedToStaff,'updated_at'=>gmdate('c')
    ]);
    if($isNew&&!$history){add_conversion_event($conversionFile,$conv,'conversation_started',$eventId,['origin'=>'whatsapp_inbound','source'=>'automation']);$conversionCreated++;}
    if(!$history){$rules=automation_rules($automationRulesFile,(string)($conv['client_id']??''));if(!$rules)$rules=default_rules();$decision=classify_auto($conv,$repliedToStaff,$text,$msgType,$rules);if($decision&&apply_auto_label($conv,$decision,$conversionFile,$deliveryFile,$eventId,$ycloudSecureDir,$legacyKeyFile)){$conversionCreated++;$autoLabel=$decision;}}
    $conversations[$id]=$conv;save_json($convFile,$conversations);$conversationUpdated=true;
};
$handleOutbound=function(array $m,bool $history=false)use(&$conversations,&$conversationUpdated,$convFile,$event,$eventId,$connectionId,$clientId,$numbersFile){
    $waba=(string)($m['wabaId']??'');$business=(string)($m['from']??'');$customer=(string)($m['to']??'');$id=substr(hash('sha256',$waba.'|'.$customer),0,24);$prev=$conversations[$id]??[];$profile=is_array($m['customerProfile']??null)?$m['customerProfile']:[];$numberId=upsert_number($numbersFile,$business,$waba,$connectionId,$clientId);
    $text=msg_text($m);$msgType=(string)($m['type']??'unknown');$sentAt=(string)($m['sendTime']??$event['createTime']??gmdate('c'));$recent=is_array($prev['recent_messages']??null)?$prev['recent_messages']:[];$recent=recent_push($recent,['direction'=>$history?'history_outbound':'outbound_app','text'=>$text,'type'=>$msgType,'at'=>$sentAt,'source_event_id'=>$eventId]);
    $inbound=(int)($prev['inbound_count']??0);$outbound=(int)($prev['outbound_count']??0)+($history?0:1);
    $conv=array_merge($prev,[
        'id'=>$id,'client_id'=>$clientId!==''?$clientId:(string)($prev['client_id']??''),'number_id'=>$numberId,'ycloud_connection_id'=>$connectionId!==''?$connectionId:(string)($prev['ycloud_connection_id']??''),
        'waba_id'=>$waba,'business_number'=>$business,'customer_number'=>$customer,'contact_name'=>$prev['contact_name']??$customer,'contact_username'=>(string)($profile['username']??($prev['contact_username']??'')),
        'first_seen_at'=>$prev['first_seen_at']??$sentAt,'last_message_at'=>$sentAt,'last_message_text'=>$text,'last_message_type'=>$msgType,'last_direction'=>$history?'history_outbound':'outbound_app','last_source_event_id'=>$eventId,
        'current_tag'=>$prev['current_tag']??null,'ctwa_clid'=>$prev['ctwa_clid']??'','inbound_count'=>$inbound,'outbound_count'=>$outbound,'recent_messages'=>$recent,'updated_at'=>gmdate('c')
    ]);
    $conversations[$id]=$conv;save_json($convFile,$conversations);$conversationUpdated=true;
};

if($type==='whatsapp.inbound_message.received'&&isset($event['whatsappInboundMessage'])&&is_array($event['whatsappInboundMessage']))$handleInbound($event['whatsappInboundMessage'],false);
elseif($type==='whatsapp.smb.message.echoes'&&isset($event['whatsappMessage'])&&is_array($event['whatsappMessage']))$handleOutbound($event['whatsappMessage'],false);
elseif($type==='whatsapp.smb.history'){
    if(isset($event['whatsappInboundMessage'])&&is_array($event['whatsappInboundMessage']))$handleInbound($event['whatsappInboundMessage'],true);
    elseif(isset($event['whatsappMessage'])&&is_array($event['whatsappMessage']))$handleOutbound($event['whatsappMessage'],true);
    else append_jsonl($systemFile,['id'=>$eventId,'type'=>$type,'ycloud_connection_id'=>$connectionId,'createTime'=>$event['createTime']??gmdate('c'),'data'=>$event]);
}else append_jsonl($systemFile,['id'=>$eventId,'type'=>$type,'ycloud_connection_id'=>$connectionId,'createTime'=>$event['createTime']??gmdate('c'),'data'=>$event]);

echo json_encode(['ok'=>true,'event_type'=>$type,'event_id'=>$eventId,'ycloud_connection_id'=>$connectionId,'stored'=>true,'conversation_updated'=>$conversationUpdated,'events_created'=>$conversionCreated,'auto_label'=>$autoLabel],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
