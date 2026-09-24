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
$contactSourcesFile=$base.'/contact_sources.json';
$chatlinkClicksFile=$base.'/chatlink_clicks.json';
$googleConversionQueueFile=$base.'/google_conversion_events.jsonl';

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
function decode_chatlink_tracking(string $text):array{
    $empty=['click_id'=>'','decoded'=>'','token'=>'','decoded_token'=>'','format'=>''];
    $map=["\u{200B}"=>0,"\u{200C}"=>1,"\u{200D}"=>2,"\u{FEFF}"=>3];
    $candidates=[];
    if(preg_match_all('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]{4,}/u',$text,$runs)){
        foreach(($runs[0]??[]) as $run)$candidates[]=$run;
    }
    if(preg_match_all('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]/u',$text,$all)){
        $joined=implode('',(array)($all[0]??[]));
        if(mb_strlen($joined,'UTF-8')>=16)$candidates[]=$joined;
    }
    if(!$candidates)return $empty;

    foreach($candidates as $hidden){
        $chars=preg_split('//u',$hidden,-1,PREG_SPLIT_NO_EMPTY);
        if(!is_array($chars)||count($chars)<4)continue;
        $bytes='';$n=count($chars)-count($chars)%4;
        for($i=0;$i<$n;$i+=4){
            if(!isset($map[$chars[$i]],$map[$chars[$i+1]],$map[$chars[$i+2]],$map[$chars[$i+3]])){ $bytes=''; break; }
            $bytes.=chr(($map[$chars[$i]]<<6)|($map[$chars[$i+1]]<<4)|($map[$chars[$i+2]]<<2)|$map[$chars[$i+3]]);
        }
        if($bytes==='')continue;
        if(preg_match('/(hzn1\.(clk_[A-Za-z0-9_-]{4,120})\.[A-Za-z0-9_-]{10,64})/',$bytes,$m)){
            return ['click_id'=>$m[2],'decoded'=>$bytes,'token'=>$hidden,'decoded_token'=>$m[1],'format'=>'hzn1'];
        }
        if(preg_match('/(hzn\.attr\.(clk_[A-Za-z0-9_-]{4,120}))/',$bytes,$m)){
            return ['click_id'=>$m[2],'decoded'=>$bytes,'token'=>$hidden,'decoded_token'=>$m[1],'format'=>'hzn_legacy'];
        }
        if(preg_match('/(ycloud\.chatlink\.(clk_[A-Za-z0-9_-]{4,120})(?:\.[A-Za-z0-9_-]{4,160})?)/',$bytes,$m)){
            return ['click_id'=>$m[2],'decoded'=>$bytes,'token'=>$hidden,'decoded_token'=>$m[1],'format'=>'ycloud'];
        }
    }
    return $empty;
}
function strip_chatlink_tracking(string $text):string{
    $x=preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]{16,}/u','',$text)??$text;
    $x=preg_replace('/^[،,]\s*/u','',$x)??$x;
    return trim($x);
}
function msg_text(array $m):string{
    $type=(string)($m['type']??'unknown');
    if($type==='text') return strip_chatlink_tracking(trim((string)($m['text']['body']??'')));
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
function extract_inbound_source_url(array $m):string{
    $candidates=[
        $m['sourceUrl']??null,$m['source_url']??null,$m['trafficSourceUrl']??null,$m['traffic_source_url']??null,
        $m['referral']['source_url']??null,$m['referral']['sourceUrl']??null,
        $m['tracking']['sourceUrl']??null,$m['tracking']['source_url']??null,
        $m['trafficSource']['url']??null,$m['trafficSource']['sourceUrl']??null
    ];
    foreach($candidates as $v){if(is_string($v)&&trim($v)!=='')return trim($v);}
    return '';
}
function message_source_meta(array $m):array{
    $candidates=[
        $m,
        is_array($m['trafficSource']??null)?$m['trafficSource']:[],
        is_array($m['source']??null)?$m['source']:[],
        is_array($m['growthTool']??null)?$m['growthTool']:[],
        is_array($m['customerProfile']??null)?$m['customerProfile']:[]
    ];
    $out=['source_type'=>'','source_id'=>'','source_url'=>''];
    foreach($candidates as $x){
        if(!is_array($x))continue;
        if($out['source_type']==='')$out['source_type']=trim((string)($x['sourceType']??$x['source_type']??''));
        if($out['source_id']==='')$out['source_id']=trim((string)($x['sourceId']??$x['source_id']??''));
        if($out['source_url']==='')$out['source_url']=trim((string)($x['sourceUrl']??$x['source_url']??$x['url']??''));
    }
    return $out;
}
function infer_traffic_source(string $text,array $referral,array $prev=[],array $messageSource=[]):array{
    $low=function_exists('mb_strtolower')?mb_strtolower(trim($text),'UTF-8'):strtolower(trim($text));
    $refText=function_exists('mb_strtolower')
        ? mb_strtolower(trim((string)($referral['source_url']??'').' '.(string)($referral['headline']??'').' '.(string)($referral['source_type']??'')),'UTF-8')
        : strtolower(trim((string)($referral['source_url']??'').' '.(string)($referral['headline']??'').' '.(string)($referral['source_type']??'')));
    $clid=trim((string)($referral['ctwa_clid']??''));

    if(str_contains($refText,'tiktok'))
        return ['key'=>'tiktok','label'=>'TikTok Ads','confidence'=>'high','reason'=>'referral'];
    if($clid!==''||str_contains($refText,'facebook')||str_contains($refText,'instagram'))
        return ['key'=>'meta','label'=>'Meta Ads','confidence'=>'high','reason'=>$clid!==''?'ctwa_clid':'referral'];
    if(str_contains($refText,'snap')||contains_any($text,['المصدر: Snapchat Ads','المصدر:Snapchat Ads','سناب شات']))
        return ['key'=>'snapchat','label'=>'Snapchat Ads','confidence'=>'high','reason'=>'platform_evidence'];


    if(str_contains($refText,'gclid=')||str_contains($refText,'gbraid=')||str_contains($refText,'wbraid=')||str_contains($refText,'utm_source=google'))
        return ['key'=>'google','label'=>'Google Ads','confidence'=>'high','reason'=>'ycloud_chatlink_source_url'];
    if(str_contains($refText,'google')||contains_any($text,['المصدر: Google Ads','المصدر:Google Ads','source: google ads','جوجل ادز','google ads']))
        return ['key'=>'google','label'=>'Google Ads','confidence'=>'high','reason'=>'platform_evidence'];

    $msType=strtoupper(trim((string)($messageSource['source_type']??'')));
    $msUrl=trim((string)($messageSource['source_url']??''));
    $msLow=strtolower($msUrl);
    if($msUrl!==''||$msType!==''){
        if(str_contains($msLow,'gclid=')||str_contains($msLow,'gbraid=')||str_contains($msLow,'wbraid=')||str_contains($msLow,'utm_source=google'))
            return ['key'=>'google','label'=>'Google Ads','confidence'=>'high','reason'=>'ycloud_inbound_source_url'];
        if(str_contains($msLow,'tiktok')||$msType==='TIKTOK_AD')
            return ['key'=>'tiktok','label'=>'TikTok Ads','confidence'=>'high','reason'=>'ycloud_inbound_source'];
        if(str_contains($msLow,'facebook')||str_contains($msLow,'instagram')||$msType==='AD')
            return ['key'=>'meta','label'=>'Meta Ads','confidence'=>'high','reason'=>'ycloud_inbound_source'];
        if(str_contains($msLow,'snapchat'))
            return ['key'=>'snapchat','label'=>'Snapchat Ads','confidence'=>'high','reason'=>'ycloud_inbound_source'];
        if($msType==='GROWTH_TOOL')
            return ['key'=>'website','label'=>'Website / YCloud Chat Link','confidence'=>'high','reason'=>'ycloud_growth_tool'];
    }

    if(!empty($prev['traffic_source_key']) && ($prev['traffic_source_key']??'')!=='organic')
        return ['key'=>(string)$prev['traffic_source_key'],'label'=>(string)($prev['traffic_source_label']??$prev['traffic_source_key']),'confidence'=>(string)($prev['traffic_source_confidence']??'medium'),'reason'=>(string)($prev['traffic_source_reason']??'previous_attribution')];

    return ['key'=>'organic','label'=>'Organic / Direct','confidence'=>'medium','reason'=>'direct_whatsapp_no_ad_or_site_signal'];
}
function attribution_is_specific_platform(string $key):bool{
    return in_array(strtolower(trim($key)),['google','tiktok','meta','snapchat','microsoft_ads','bing','linkedin','x'],true);
}
function attribution_is_confirmed_native(array $traffic):bool{
    $key=strtolower(trim((string)($traffic['key']??'')));
    $reason=trim((string)($traffic['reason']??''));
    if(!attribution_is_specific_platform($key))return false;
    return in_array($reason,['referral','ctwa_clid','ycloud_chatlink_source_url','platform_evidence','ycloud_inbound_source_url','ycloud_inbound_source','ycloud_contact_source'],true);
}

function funnel_target_client(string $clientId):bool{
    // One canonical WhatsApp funnel for every client resolved by the Hub.
    return trim($clientId)!=='';
}
function google_conversion_target_client(string $clientId):bool{
    return in_array($clientId,['cl_0e6efd258397db','cl_3ea5ae96e05c6b','cl_cbb797950cc8d4'],true);
}
function valid_customer_message_type(string $type):bool{
    return !in_array(strtolower(trim($type)),['','unsupported','reaction','system','unknown','revoke','revoked'],true);
}
function google_queue_event(string $file,array $conv,string $stage,string $occurredAt,string $sourceEventId):void{
    $clientId=(string)($conv['client_id']??'');
    if(!google_conversion_target_client($clientId))return;
    $convId=(string)($conv['id']??'');
    if($convId==='')return;
    $stage=strtolower(trim($stage));
    if(!in_array($stage,['message_sent','interested','qualified','converted'],true))return;
    $eventId='gcv_'.substr(hash('sha256',$clientId.'|'.$convId.'|'.$stage),0,32);
    append_jsonl($file,[
        'event_id'=>$eventId,'client_id'=>$clientId,'conversation_id'=>$convId,'stage'=>$stage,
        'occurred_at'=>$occurredAt!==''?$occurredAt:gmdate('c'),'source_event_id'=>$sourceEventId,'created_at'=>gmdate('c')
    ]);
}
function conversation_has_reply_after_staff(array $conv):bool{
    $seenStaff=false;
    foreach((array)($conv['recent_messages']??[]) as $m){
        if(!is_array($m))continue;
        $d=(string)($m['direction']??'');$type=(string)($m['type']??'');
        if(str_contains($d,'outbound')){$seenStaff=true;continue;}
        if($seenStaff&&str_contains($d,'inbound')&&valid_customer_message_type($type))return true;
    }
    return false;
}
function conversation_has_serious_signal(array $conv):bool{
    $needles=[
        'السعر','التكلفة','موعد','احجز','حجز','التوفر','متاح','المدة','المتطلبات','الأوراق','المستندات',
        'نبدأ','ابدأ','أبدأ','التقسيط','الدفع','تحويل','مساند','زيارة','موقعكم','العنوان',
        'استشارة','قضية','عقد','أتعاب','الاتعاب','شركة','تركة','تنفيذ','دعوى','توكيل','price','cost','appointment','book','booking','available','availability','requirements','documents','payment'
    ];
    foreach((array)($conv['recent_messages']??[]) as $m){
        if(!is_array($m)||!str_contains((string)($m['direction']??''),'inbound'))continue;
        if(!valid_customer_message_type((string)($m['type']??'')))continue;
        if(contains_any((string)($m['text']??''),$needles))return true;
        if(in_array((string)($m['type']??''),['document','image','location','contacts'],true))return true;
    }
    return false;
}
function conversation_has_explicit_conversion(array $conv):bool{
    $needles=[
        'تم الدفع','دفعت','تم التحويل','حولت','حوّلت','تم الحجز','حجزت الموعد','تم تأكيد الحجز',
        'تم التعاقد','وقعت العقد','وقّعت العقد','تم توقيع العقد','تم إصدار العقد','تم اصدار العقد','تم قبول الطلب',
        'paid','payment done','payment completed','booking confirmed','contract signed','order confirmed'
    ];
    foreach((array)($conv['recent_messages']??[]) as $m){
        if(!is_array($m)||!str_contains((string)($m['direction']??''),'inbound'))continue;
        if(!valid_customer_message_type((string)($m['type']??'')))continue;
        if(contains_any((string)($m['text']??''),$needles))return true;
    }
    return false;
}

function is_substantive(string $text,string $type):bool{
    if(!valid_customer_message_type($type))return false;
    if(in_array($type,['location','contacts','document','image','video','audio','interactive','button','order','sticker'],true))return true;
    $t=trim(strtolower($text));if($t==='')return false;
    $plain=preg_replace('/[\p{P}\p{S}\s]+/u','',$t)??$t;if($plain==='')return false;
    $ack=['شكرا','شكراً','شكرًا','تمام','اوكي','أوكي','اوك','ok','okay','thanks','thank you','ماشي','حسنا','حسنًا','👍','👌','🙏'];
    foreach($ack as $a)if($t===$a)return false;
    return text_len($plain)>=2;
}
function recent_push(array $recent,array $item,int $limit=100):array{
    $id=(string)($item['wamid']??$item['message_id']??$item['source_event_id']??'');
    if($id!==''){
        foreach($recent as $old){
            if(!is_array($old))continue;
            $oid=(string)($old['wamid']??$old['message_id']??$old['source_event_id']??'');
            if($oid!==''&&$oid===$id)return array_values($recent);
        }
    }
    $recent[]=$item;if(count($recent)>$limit)$recent=array_slice($recent,-$limit);return array_values($recent);
}
function recent_valid_inbound_count(array $recent):int{
    $n=0;$seen=[];
    foreach($recent as $m){
        if(!is_array($m)||!str_contains((string)($m['direction']??''),'inbound'))continue;
        $t=strtolower(trim((string)($m['type']??'')));
        if(!valid_customer_message_type($t))continue;
        $id=(string)($m['wamid']??$m['message_id']??$m['source_event_id']??'');
        if($id!==''&&isset($seen[$id]))continue;
        if($id!=='')$seen[$id]=true;$n++;
    }
    return $n;
}
function contact_source_key(string $connectionId,string $phone):string{
    return $connectionId.'|'.preg_replace('/\D+/','',$phone);
}
function parse_contact_source(array $contact):array{
    $sourceType=strtoupper(trim((string)($contact['sourceType']??'')));
    $sourceId=trim((string)($contact['sourceId']??''));
    $sourceUrl=trim((string)($contact['sourceUrl']??''));
    $lastConnected=normalize_phone((string)($contact['lastConnectedNumber']??''));
    $key='unknown';$label='Unknown';$confidence='high';$reason='ycloud_contact_source';
    $u=strtolower($sourceUrl);
    if(str_contains($u,'gclid=')||str_contains($u,'gbraid=')||str_contains($u,'wbraid=')||str_contains($u,'utm_source=google')){
        $key='google';$label='Google Ads';
    }elseif($sourceType==='TIKTOK_AD'||str_contains($u,'utm_source=tiktok')||str_contains($u,'tiktok')){
        $key='tiktok';$label='TikTok Ads';
    }elseif(str_contains($u,'utm_source=facebook')||str_contains($u,'utm_source=instagram')||str_contains($u,'facebook.com')||str_contains($u,'instagram.com')){
        $key='meta';$label='Meta Ads';
    }elseif(str_contains($u,'utm_source=snapchat')||str_contains($u,'snapchat')){
        $key='snapchat';$label='Snapchat Ads';
    }elseif($sourceType==='TIKTOK_AD'){
        $key='tiktok';$label='TikTok Ads';
    }elseif($sourceType==='GROWTH_TOOL'){
        $key='website';$label='Website / YCloud Chat Link';
    }elseif($sourceType==='AD'){
        $key='ad';$label='Ad';
    }elseif($sourceType==='WHATSAPP'||$sourceType==='SMB'){
        $key='organic';$label='Organic / Direct';
    }
    return [
        'source_type'=>$sourceType,'source_id'=>$sourceId,'source_url'=>$sourceUrl,
        'last_connected_number'=>$lastConnected,'traffic_source_key'=>$key,
        'traffic_source_label'=>$label,'traffic_source_confidence'=>$confidence,'traffic_source_reason'=>$reason
    ];
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
function stage_rank(?string $tag):int{return match($tag){'message_received'=>0,'interested'=>1,'qualified'=>2,'purchased','converted'=>3,'lost'=>90,default=>0};}
function classify_auto(array $conv,bool $repliedToStaff,string $text,string $type,array $rules):?array{
    if(!($rules['enabled']??true))return null;
    if(!valid_customer_message_type($type))return null;
    $current=(string)($conv['current_tag']??'');
    if(in_array($current,['purchased','converted'],true))return null;
    $clientId=(string)($conv['client_id']??'');
    $valid=max((int)($conv['valid_inbound_count']??0),(int)($conv['inbound_count']??0),recent_valid_inbound_count((array)($conv['recent_messages']??[])));
    $out=(int)($conv['outbound_count']??0);
    $sub=is_substantive($text,$type);

    $lostKw=(array)($rules['lost']['keywords']??[]);
    if($sub&&contains_any($text,$lostKw))return ['tag'=>'lost','reason'=>'explicit_negative_intent','confidence'=>0.98];

    if(funnel_target_client($clientId)){
        // Converted is intentionally strict: explicit completed business outcome + established conversation.
        if($valid>=3&&$out>=1&&conversation_has_explicit_conversion($conv)&&stage_rank($current)<3)
            return ['tag'=>'converted','reason'=>'explicit_completed_business_outcome','confidence'=>0.99];

        // Qualified requires real two-way conversation, >=3 valid customer messages, and a serious next-step signal.
        if($valid>=3&&$out>=1&&conversation_has_reply_after_staff($conv)&&conversation_has_serious_signal($conv)&&stage_rank($current)<2)
            return ['tag'=>'qualified','reason'=>'three_plus_messages_two_way_serious_intent','confidence'=>0.93];

        // User-defined rule: 2+ valid customer messages from the same lead = Interested.
        if($valid>=2&&stage_rank($current)<1)
            return ['tag'=>'interested','reason'=>'two_valid_customer_messages','confidence'=>0.96];

        if($current==='lost'&&$valid>=2)return ['tag'=>'interested','reason'=>'reengaged_two_valid_messages','confidence'=>0.88];
        return null;
    }

    // Legacy/default behavior for other clients.
    $in=(int)($conv['inbound_count']??0);$sub=is_substantive($text,$type);
    $purchaseKw=(array)($rules['purchased']['keywords']??[]);$qualKw=(array)($rules['qualified']['keywords']??[]);
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
    if(in_array($current,['purchased','converted'],true))return false;
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

$handleInbound=function(array $m,bool $history=false)use(&$conversations,&$conversationUpdated,&$conversionCreated,&$autoLabel,$convFile,$conversionFile,$deliveryFile,$event,$eventId,$connectionId,$clientId,$numbersFile,$automationRulesFile,$ycloudSecureDir,$legacyKeyFile,$chatlinkClicksFile,$googleConversionQueueFile){
    $waba=(string)($m['wabaId']??'');$customer=(string)($m['from']??'');$business=(string)($m['to']??'');$id=substr(hash('sha256',$waba.'|'.$customer),0,24);$isNew=!isset($conversations[$id]);
    $profile=is_array($m['customerProfile']??null)?$m['customerProfile']:[];$referral=is_array($m['referral']??null)?$m['referral']:[];$prev=$conversations[$id]??[];$numberId=upsert_number($numbersFile,$business,$waba,$connectionId,$clientId);
    $text=msg_text($m);$msgType=(string)($m['type']??'unknown');$sentAt=(string)($m['sendTime']??$event['createTime']??gmdate('c'));$repliedToStaff=(string)($prev['last_direction']??'')==='outbound_app';
    $inboundSourceUrl=extract_inbound_source_url($m);
    if($inboundSourceUrl!==''&&empty($referral['source_url'])){$referral['source_url']=$inboundSourceUrl;if(empty($referral['source_type']))$referral['source_type']='growth_tool';}
    $messageSource=message_source_meta($m);
    $rawBody=(string)($m['text']['body']??'');
    $chatlink=decode_chatlink_tracking($rawBody);
    $chatClick=[];$chatClickMatchMethod='';
    $clicks=load_json($chatlinkClicksFile,[]);
    if($chatlink['click_id']!==''){
        $candidate=is_array($clicks[$chatlink['click_id']]??null)?$clicks[$chatlink['click_id']]:[];
        if($candidate){
            $format=(string)($chatlink['format']??'');
            $valid=true;
            if($format==='hzn1'){
                $storedToken=(string)($candidate['click_token']??'');
                $decodedToken=(string)($chatlink['decoded_token']??'');
                $sameClient=(string)($candidate['client_id']??'')===$clientId;
                $storedBusiness=normalize_phone((string)($candidate['business_number']??''));
                $sameBusiness=$storedBusiness===''||normalize_phone($business)===$storedBusiness;
                $valid=$storedToken!==''&&$decodedToken!==''&&hash_equals($storedToken,$decodedToken)&&$sameClient&&$sameBusiness;
                if($valid)$chatClickMatchMethod='horizons_hidden_token';
            }elseif($format==='hzn_legacy'){
                $chatClickMatchMethod='horizons_legacy_hidden_token';
            }else{
                $chatClickMatchMethod='ycloud_hidden_token';
            }
            if($valid)$chatClick=$candidate;
        }
    }

    // Strict order: confirmed native platform > hidden encoded click > deferred 5-minute fallback > unknown.
    $traffic=infer_traffic_source($text,$referral,$prev,$messageSource);
    $nativeConfirmed=attribution_is_confirmed_native($traffic);

    if($nativeConfirmed){
        $chatClickMatchMethod='native_confirmed';
        // A hidden token on the same message is consumed, but never allowed to override native evidence.
        if(($chatlink['click_id']??'')!==''&&isset($clicks[$chatlink['click_id']])&&is_array($clicks[$chatlink['click_id']])){
            $cid=(string)$chatlink['click_id'];
            $clicks[$cid]['matched_at']=gmdate('c');
            $clicks[$cid]['matched_customer']=$customer;
            $clicks[$cid]['matched_business']=$business;
            $clicks[$cid]['match_method']='native_superseded_'.(($chatlink['format']??'')==='hzn1'?'horizons_token':'hidden_token');
            save_json($chatlinkClicksFile,$clicks);
        }
    }elseif($chatClick){
        $isHzn=(string)($chatlink['format']??'')==='hzn1';
        $traffic=[
            'key'=>(string)($chatClick['traffic_source_key']??'website'),
            'label'=>(string)($chatClick['traffic_source_label']??'Website / HORIZONS Attribution'),
            'confidence'=>'high',
            'reason'=>$isHzn?'horizons_signed_hidden_token':(string)($chatClick['traffic_source_reason']??'hidden_click_token')
        ];
        if($chatClickMatchMethod==='')$chatClickMatchMethod=$isHzn?'horizons_hidden_token':'hidden_token';
        if(($chatlink['click_id']??'')!==''){
            $cid=(string)$chatlink['click_id'];
            if(isset($clicks[$cid])&&is_array($clicks[$cid])){
                $clicks[$cid]['matched_at']=gmdate('c');
                $clicks[$cid]['matched_customer']=$customer;
                $clicks[$cid]['matched_business']=$business;
                $clicks[$cid]['match_method']=$chatClickMatchMethod;
                save_json($chatlinkClicksFile,$clicks);
            }
        }
    }elseif($isNew&&!$history&&$clientId!==''){
        // Any configured client: defer timestamp attribution until the complete click window is closed.
        $traffic=[
            'key'=>'unknown',
            'label'=>'Unknown',
            'confidence'=>'pending',
            'reason'=>'awaiting_5m_timestamp_resolution'
        ];
        $chatClickMatchMethod='timestamp_pending';
    }

    $recent=is_array($prev['recent_messages']??null)?$prev['recent_messages']:[];
    $recent=recent_push($recent,['direction'=>$history?'history_inbound':'inbound','text'=>$text,'type'=>$msgType,'at'=>$sentAt,'source_event_id'=>$eventId,'message_id'=>(string)($m['id']??''),'wamid'=>(string)($m['wamid']??'')]);
    $validMessage=valid_customer_message_type($msgType);
    $recentValid=recent_valid_inbound_count($recent);
    $inbound=max((int)($prev['inbound_count']??0)+($history?0:1),$recentValid);
    $outbound=(int)($prev['outbound_count']??0);
    $validInbound=max((int)($prev['valid_inbound_count']??0)+((!$history&&$validMessage)?1:0),$recentValid);
    $conv=array_merge($prev,[
        'id'=>$id,'client_id'=>$clientId!==''?$clientId:(string)($prev['client_id']??''),'number_id'=>$numberId,'ycloud_connection_id'=>$connectionId!==''?$connectionId:(string)($prev['ycloud_connection_id']??''),
        'waba_id'=>$waba,'business_number'=>$business,'customer_number'=>$customer,'contact_name'=>(string)($profile['name']??($prev['contact_name']??$customer)),'contact_username'=>(string)($profile['username']??($prev['contact_username']??'')),
        'first_seen_at'=>$prev['first_seen_at']??$sentAt,'last_message_at'=>$sentAt,'last_message_text'=>$text,'last_message_type'=>$msgType,'last_direction'=>$history?'history_inbound':'inbound','last_source_event_id'=>$eventId,
        'current_tag'=>$prev['current_tag']??null,'ctwa_clid'=>(string)($referral['ctwa_clid']??($prev['ctwa_clid']??'')),'ad_source_id'=>(string)($referral['source_id']??($prev['ad_source_id']??'')),'ad_source_type'=>(string)($referral['source_type']??($prev['ad_source_type']??'')),'ad_headline'=>(string)($referral['headline']??($prev['ad_headline']??'')),
        'ycloud_inbound_source_url'=>$inboundSourceUrl!==''?$inboundSourceUrl:(string)($prev['ycloud_inbound_source_url']??''),
        'traffic_source_key'=>$traffic['key'],'traffic_source_label'=>$traffic['label'],'traffic_source_confidence'=>$traffic['confidence'],'traffic_source_reason'=>$traffic['reason'],
        'ycloud_message_source_type'=>$messageSource['source_type']??'','ycloud_message_source_id'=>$messageSource['source_id']??'','ycloud_message_source_url'=>$messageSource['source_url']??'',
        'ycloud_chatlink_click_id'=>$chatlink['click_id']??'',
        'ycloud_chatlink_decoded'=>(string)($chatlink['decoded']??''),
        'horizons_wa_click_id'=>(string)($chatlink['click_id']??($prev['horizons_wa_click_id']??'')),
        'horizons_wa_token_format'=>(string)($chatlink['format']??($prev['horizons_wa_token_format']??'')),
        'horizons_wa_token_decoded'=>(string)($chatlink['decoded_token']??($prev['horizons_wa_token_decoded']??'')),
        'attribution_match_method'=>$chatClickMatchMethod!==''?$chatClickMatchMethod:(string)($prev['attribution_match_method']??''),
        'chatlink_source_url'=>(string)($chatClick['source_url']??($prev['chatlink_source_url']??'')),
        'attribution_landing_url'=>(string)($chatClick['landing_url']??($prev['attribution_landing_url']??'')),
        'attribution_referrer'=>(string)($chatClick['referrer']??($prev['attribution_referrer']??'')),
        'attribution_params'=>is_array($chatClick['query_params']??null)?$chatClick['query_params']:($prev['attribution_params']??[]),
        'attribution_utm'=>is_array($chatClick['utm']??null)?$chatClick['utm']:($prev['attribution_utm']??[]),
        'attribution_first_touch'=>is_array($chatClick['first_touch']??null)?$chatClick['first_touch']:($prev['attribution_first_touch']??[]),
        'attribution_last_touch'=>is_array($chatClick['last_touch']??null)?$chatClick['last_touch']:($prev['attribution_last_touch']??[]),
        'attribution_current_touch'=>is_array($chatClick['current_touch']??null)?$chatClick['current_touch']:($prev['attribution_current_touch']??[]),
        'attribution_touch_history'=>is_array($chatClick['touch_history']??null)?$chatClick['touch_history']:($prev['attribution_touch_history']??[]),
        'google_gclid'=>(string)($chatClick['gclid']??($prev['google_gclid']??'')),
        'google_gbraid'=>(string)($chatClick['gbraid']??($prev['google_gbraid']??'')),
        'google_wbraid'=>(string)($chatClick['wbraid']??($prev['google_wbraid']??'')),
        'google_dclid'=>(string)($chatClick['dclid']??($prev['google_dclid']??'')),
        'meta_fbclid'=>(string)($chatClick['fbclid']??($prev['meta_fbclid']??'')),
        'tiktok_ttclid'=>(string)($chatClick['ttclid']??($prev['tiktok_ttclid']??'')),
        'snapchat_scclid'=>(string)($chatClick['scclid']??$chatClick['ScCid']??($prev['snapchat_scclid']??'')),
        'microsoft_msclkid'=>(string)($chatClick['msclkid']??($prev['microsoft_msclkid']??'')),
        'linkedin_li_fat_id'=>(string)($chatClick['li_fat_id']??($prev['linkedin_li_fat_id']??'')),
        'x_twclid'=>(string)($chatClick['twclid']??($prev['x_twclid']??'')),
        'utm_source'=>(string)($chatClick['utm_source']??($prev['utm_source']??'')),
        'utm_medium'=>(string)($chatClick['utm_medium']??($prev['utm_medium']??'')),
        'utm_campaign'=>(string)($chatClick['utm_campaign']??($prev['utm_campaign']??'')),
        'utm_id'=>(string)($chatClick['utm_id']??($prev['utm_id']??'')),
        'utm_term'=>(string)($chatClick['utm_term']??($prev['utm_term']??'')),
        'utm_content'=>(string)($chatClick['utm_content']??($prev['utm_content']??'')),
        'utm_source_platform'=>(string)($chatClick['utm_source_platform']??($prev['utm_source_platform']??'')),
        'utm_creative_format'=>(string)($chatClick['utm_creative_format']??($prev['utm_creative_format']??'')),
        'utm_marketing_tactic'=>(string)($chatClick['utm_marketing_tactic']??($prev['utm_marketing_tactic']??'')),
        'inbound_count'=>$inbound,'valid_inbound_count'=>$validInbound,'outbound_count'=>$outbound,'recent_messages'=>$recent,'last_customer_reply_to_staff'=>$repliedToStaff,'updated_at'=>gmdate('c')
    ]);
    if($isNew&&!$history){
        add_conversion_event($conversionFile,$conv,'message_received',$eventId,['origin'=>'whatsapp_inbound','source'=>'automation']);$conversionCreated++;
    }
    if(!$history&&$validMessage&&funnel_target_client((string)($conv['client_id']??''))&&empty($conv['current_tag'])){
        $nowTag=gmdate('c');
        $conv['current_tag']='message_received';
        $conv['tagged_at']=$nowTag;
        $conv['tag_source']='automation';
        $conv['auto_label_reason']='first_valid_customer_message';
        $conv['auto_label_confidence']=1.0;
        $lh=is_array($conv['label_history']??null)?$conv['label_history']:[];
        $lh[]=['tag'=>'message_received','source'=>'automation','reason'=>'first_valid_customer_message','confidence'=>1.0,'at'=>$nowTag];
        if(count($lh)>20)$lh=array_slice($lh,-20);
        $conv['label_history']=$lh;
    }
    if(!$history&&$validMessage&&google_conversion_target_client((string)($conv['client_id']??''))&&empty($prev['google_message_sent_queued_at'])){
        google_queue_event($googleConversionQueueFile,$conv,'message_sent',$sentAt,$eventId);
        $conv['google_message_sent_queued_at']=gmdate('c');
    }
    if(!$history){
        $rules=automation_rules($automationRulesFile,(string)($conv['client_id']??''));if(!$rules)$rules=default_rules();
        $decision=classify_auto($conv,$repliedToStaff,$text,$msgType,$rules);
        if($decision&&apply_auto_label($conv,$decision,$conversionFile,$deliveryFile,$eventId,$ycloudSecureDir,$legacyKeyFile)){
            $conversionCreated++;$autoLabel=$decision;
            $stageMap=['interested'=>'interested','qualified'=>'qualified','purchased'=>'converted','converted'=>'converted'];
            $queueStage=$stageMap[(string)($decision['tag']??'')]??'';
            if($queueStage!=='')google_queue_event($googleConversionQueueFile,$conv,$queueStage,$sentAt,$eventId);
        }
    }
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

if($type==='contact.created'&&isset($event['contactCreated'])&&is_array($event['contactCreated'])){
    $contact=$event['contactCreated'];
    $phone=normalize_phone((string)($contact['phoneNumber']??''));
    if($phone!==''){
        $sources=load_json($contactSourcesFile,[]);
        $parsed=parse_contact_source($contact);
        $k=contact_source_key($connectionId,$phone);
        $sources[$k]=array_merge([
            'id'=>(string)($contact['id']??''),'ycloud_connection_id'=>$connectionId,'client_id'=>$clientId,
            'phone_number'=>$phone,'created_at'=>(string)($contact['createTime']??$event['createTime']??gmdate('c')),
            'updated_at'=>gmdate('c')
        ],$parsed);
        save_json($contactSourcesFile,$sources);

        foreach($conversations as $cid=>$conv){
            if(!is_array($conv))continue;
            if(normalize_phone((string)($conv['customer_number']??''))!==$phone)continue;
            if($connectionId!==''&&(string)($conv['ycloud_connection_id']??'')!==$connectionId)continue;
            $conv['ycloud_contact_source_type']=$parsed['source_type'];
            $conv['ycloud_contact_source_id']=$parsed['source_id'];
            $conv['ycloud_contact_source_url']=$parsed['source_url'];
            if(attribution_is_specific_platform((string)$parsed['traffic_source_key'])){
                $conv['traffic_source_key']=$parsed['traffic_source_key'];
                $conv['traffic_source_label']=$parsed['traffic_source_label'];
                $conv['traffic_source_confidence']=$parsed['traffic_source_confidence'];
                $conv['traffic_source_reason']=$parsed['traffic_source_reason'];
                $conv['attribution_match_method']='native_contact';
            }
            $conv['updated_at']=gmdate('c');
            $conversations[$cid]=$conv;$conversationUpdated=true;
        }
        if($conversationUpdated)save_json($convFile,$conversations);
    }
}
elseif($type==='whatsapp.inbound_message.received'&&isset($event['whatsappInboundMessage'])&&is_array($event['whatsappInboundMessage']))$handleInbound($event['whatsappInboundMessage'],false);
elseif($type==='whatsapp.smb.message.echoes'&&isset($event['whatsappMessage'])&&is_array($event['whatsappMessage']))$handleOutbound($event['whatsappMessage'],false);
elseif($type==='whatsapp.smb.history'){
    if(isset($event['whatsappInboundMessage'])&&is_array($event['whatsappInboundMessage']))$handleInbound($event['whatsappInboundMessage'],true);
    elseif(isset($event['whatsappMessage'])&&is_array($event['whatsappMessage']))$handleOutbound($event['whatsappMessage'],true);
    else append_jsonl($systemFile,['id'=>$eventId,'type'=>$type,'ycloud_connection_id'=>$connectionId,'createTime'=>$event['createTime']??gmdate('c'),'data'=>$event]);
}else append_jsonl($systemFile,['id'=>$eventId,'type'=>$type,'ycloud_connection_id'=>$connectionId,'createTime'=>$event['createTime']??gmdate('c'),'data'=>$event]);

echo json_encode(['ok'=>true,'event_type'=>$type,'event_id'=>$eventId,'ycloud_connection_id'=>$connectionId,'stored'=>true,'conversation_updated'=>$conversationUpdated,'events_created'=>$conversionCreated,'auto_label'=>$autoLabel],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
