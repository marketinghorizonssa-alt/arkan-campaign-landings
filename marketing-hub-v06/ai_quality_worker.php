<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$base = __DIR__ . '/data';
$secure = dirname(__DIR__, 4) . '/.marketing';
$convFile = $base . '/conversations.json';
$conversionFile = $base . '/conversion_events.jsonl';
$deliveryFile = $base . '/platform_delivery.jsonl';
$connectionsFile = $base . '/ycloud_connections.json';
$keyFile = $secure . '/openai_api_key';
$modelFile = $secure . '/openai_quality_model';
$openaiKey = is_file($keyFile) ? trim((string)file_get_contents($keyFile)) : '';
$model = is_file($modelFile) ? trim((string)file_get_contents($modelFile)) : 'gpt-5.6-luna';

function aq_load(string $file, array $default=[]): array {
    if (!is_file($file)) return $default;
    $v = json_decode((string)file_get_contents($file), true);
    return is_array($v) ? $v : $default;
}
function aq_save(string $file, array $data): bool {
    $tmp=$file.'.tmp';
    if (file_put_contents($tmp,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX)===false) return false;
    return rename($tmp,$file);
}
function aq_append(string $file,array $row): bool {
    $fh=fopen($file,'ab'); if(!$fh)return false; flock($fh,LOCK_EX);
    $ok=fwrite($fh,json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n")!==false;
    flock($fh,LOCK_UN); fclose($fh); return $ok;
}
function aq_stage_rank(string $s): int { return match($s){'new'=>0,'interested'=>1,'qualified'=>2,'converted'=>3,default=>-1}; }
function aq_key_file(string $secure,string $id): string { return $id==='yc_legacy'?$secure.'/ycloud_api_key':$secure.'/ycloud/'.$id.'/api_key'; }
function aq_ycloud_send(string $key,string $event,string $phone,string $when): array {
    $ch=curl_init('https://api.ycloud.com/v2/event/events');
    $payload=['eventName'=>$event,'occurTime'=>$when,'contactPhoneNumber'=>$phone];
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['X-API-Key: '.$key,'Accept: application/json','Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false]);
    curl_exec($ch);$errno=curl_errno($ch);$error=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    return ['ok'=>$errno===0&&$status>=200&&$status<300,'status'=>$status,'error'=>$errno?$error:null];
}
function aq_extract_output(array $r): string {
    foreach(($r['output']??[]) as $item){ if(!is_array($item)||($item['type']??'')!=='message')continue; foreach(($item['content']??[]) as $c){if(is_array($c)&&($c['type']??'')==='output_text')return (string)($c['text']??'');}}
    return '';
}
function aq_call_openai(string $key,string $model,array $conv): array {
    $messages=[];
    foreach(array_slice((array)($conv['recent_messages']??[]),-12) as $m){
        if(!is_array($m))continue;
        $dir=(string)($m['direction']??'');
        if(str_starts_with($dir,'history_')) $dir=substr($dir,8);
        $messages[]=['direction'=>$dir,'type'=>(string)($m['type']??''),'text'=>mb_substr((string)($m['text']??''),0,1200,'UTF-8'),'at'=>(string)($m['at']??'')];
    }
    $context=[
        'inbound_count'=>(int)($conv['inbound_count']??0),
        'outbound_count'=>(int)($conv['outbound_count']??0),
        'previous_ai_stage'=>(string)($conv['ai_stage']??'new'),
        'messages'=>$messages,
    ];
    $instructions = 'You classify WhatsApp advertising leads for campaign optimization, not customer-service performance. Read the conversation context semantically. Do not classify based on a single keyword. Stages: new = too little evidence or only greeting; interested = relevant prospect showing genuine engagement/need but qualification is incomplete; qualified = relevant prospect with credible buying/service intent plus meaningful fit or next-step evidence such as requirements, budget/affordability, timing, booking, documents, location, financing, or a concrete request to proceed; converted = explicit completed business outcome such as confirmed booking/order/payment/contract/deposit when the conversation supports it; unqualified = spam, wrong audience, clearly out of scope, explicit rejection/no intent, or clearly unsuitable. A lead may improve after an earlier unqualified state. Be conservative: when evidence is weak choose new or interested rather than qualified. Never judge the staff. Return only the required JSON.';
    $schema=[
        'type'=>'object','additionalProperties'=>false,
        'properties'=>[
            'stage'=>['type'=>'string','enum'=>['new','interested','qualified','converted','unqualified']],
            'quality_score'=>['type'=>'integer','minimum'=>0,'maximum'=>100],
            'confidence'=>['type'=>'number','minimum'=>0,'maximum'=>1],
            'intent_level'=>['type'=>'string','enum'=>['none','low','medium','high','very_high']],
            'fit_level'=>['type'=>'string','enum'=>['unknown','poor','possible','good','strong']],
            'urgency'=>['type'=>'string','enum'=>['unknown','low','medium','high']],
            'reason'=>['type'=>'string'],
            'summary'=>['type'=>'string'],
            'buying_signals'=>['type'=>'array','items'=>['type'=>'string']],
            'negative_signals'=>['type'=>'array','items'=>['type'=>'string']],
        ],
        'required'=>['stage','quality_score','confidence','intent_level','fit_level','urgency','reason','summary','buying_signals','negative_signals']
    ];
    $payload=[
        'model'=>$model,
        'store'=>false,
        'reasoning'=>['effort'=>'low'],
        'instructions'=>$instructions,
        'input'=>[['role'=>'user','content'=>[['type'=>'input_text','text'=>json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]]]],
        'text'=>['format'=>['type'=>'json_schema','name'=>'lead_quality','strict'=>true,'schema'=>$schema]],
        'max_output_tokens'=>1200,
    ];
    $ch=curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>35,CURLOPT_FOLLOWLOCATION=>false]);
    $body=curl_exec($ch);$errno=curl_errno($ch);$error=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    $decoded=is_string($body)?json_decode($body,true):null;
    if($errno!==0||$status<200||$status>=300||!is_array($decoded))return ['ok'=>false,'status'=>$status,'error'=>$errno?$error:'openai_http_error'];
    $text=aq_extract_output($decoded);$result=json_decode($text,true);
    if(!is_array($result))return ['ok'=>false,'status'=>$status,'error'=>'invalid_structured_output'];
    return ['ok'=>true,'status'=>$status,'result'=>$result,'response_id'=>(string)($decoded['id']??'')];
}

if($openaiKey==='') { echo json_encode(['ok'=>false,'error'=>'missing_openai_api_key','expected'=>$keyFile],JSON_UNESCAPED_SLASHES)."\n"; exit(2); }
$conversations=aq_load($convFile,[]);$connections=aq_load($connectionsFile,[]);$processed=0;$changed=0;$sent=0;$failed=0;$skipped=0;
foreach($conversations as $id=>&$conv){
    if(!is_array($conv)||(int)($conv['inbound_count']??0)<1){$skipped++;continue;}
    $last=(string)($conv['last_message_at']??'');$evaluated=(string)($conv['ai_evaluated_message_at']??'');
    if($last!==''&&$evaluated===$last){$skipped++;continue;}
    $res=aq_call_openai($openaiKey,$model,$conv);$processed++;
    if(!$res['ok']){ $conv['ai_last_error']=$res['error'];$conv['ai_last_error_at']=gmdate('c');$failed++;continue; }
    $r=$res['result'];$old=(string)($conv['ai_stage']??'new');$new=(string)$r['stage'];$now=gmdate('c');
    $conv['ai_stage']=$new;$conv['ai_quality_score']=(int)$r['quality_score'];$conv['ai_confidence']=(float)$r['confidence'];$conv['ai_intent_level']=$r['intent_level'];$conv['ai_fit_level']=$r['fit_level'];$conv['ai_urgency']=$r['urgency'];$conv['ai_reason']=$r['reason'];$conv['ai_summary']=$r['summary'];$conv['ai_buying_signals']=$r['buying_signals'];$conv['ai_negative_signals']=$r['negative_signals'];$conv['ai_evaluated_at']=$now;$conv['ai_evaluated_message_at']=$last;$conv['ai_model']=$model;$conv['ai_response_id']=$res['response_id'];unset($conv['ai_last_error'],$conv['ai_last_error_at']);
    $hist=is_array($conv['ai_history']??null)?$conv['ai_history']:[];$hist[]=['stage'=>$new,'score'=>(int)$r['quality_score'],'confidence'=>(float)$r['confidence'],'reason'=>$r['reason'],'at'=>$now,'message_at'=>$last];if(count($hist)>30)$hist=array_slice($hist,-30);$conv['ai_history']=$hist;
    if($new===$old)continue;
    $changed++;
    $eligible=in_array($new,['interested','qualified','converted','unqualified'],true)&&(float)$r['confidence']>=0.75;
    if(!$eligible)continue;
    $sentStages=is_array($conv['ai_sent_stages']??null)?$conv['ai_sent_stages']:[];
    if(!empty($sentStages[$new]))continue;
    $eventId='mev_ai_'.bin2hex(random_bytes(8));
    $event=['id'=>$eventId,'event'=>$new,'conversation_id'=>(string)($conv['id']??$id),'client_id'=>(string)($conv['client_id']??''),'number_id'=>(string)($conv['number_id']??''),'ycloud_connection_id'=>(string)($conv['ycloud_connection_id']??''),'waba_id'=>(string)($conv['waba_id']??''),'business_number'=>(string)($conv['business_number']??''),'customer_number'=>(string)($conv['customer_number']??''),'ctwa_clid'=>$conv['ctwa_clid']??null,'source'=>'ai_quality','ai_confidence'=>(float)$r['confidence'],'ai_score'=>(int)$r['quality_score'],'created_at'=>$now];
    aq_append($conversionFile,$event);
    $connId=(string)($conv['ycloud_connection_id']??'');if($connId===''&&isset($connections['yc_legacy']))$connId='yc_legacy';$key=$connId!==''&&is_file(aq_key_file($secure,$connId))?trim((string)file_get_contents(aq_key_file($secure,$connId))):'';
    if($key!==''&&!empty($conv['customer_number'])){$yr=aq_ycloud_send($key,$new,(string)$conv['customer_number'],$now);aq_append($deliveryFile,['id'=>'del_ai_'.bin2hex(random_bytes(7)),'event_id'=>$eventId,'provider'=>'ycloud','ycloud_connection_id'=>$connId,'event'=>$new,'success'=>$yr['ok'],'http_status'=>$yr['status'],'error'=>$yr['error'],'source'=>'ai_quality','created_at'=>gmdate('c')]);if($yr['ok']){$sentStages[$new]=$now;$sent++;}else$failed++;}else{aq_append($deliveryFile,['id'=>'del_ai_'.bin2hex(random_bytes(7)),'event_id'=>$eventId,'provider'=>'ycloud','ycloud_connection_id'=>$connId,'event'=>$new,'success'=>false,'http_status'=>0,'error'=>$key===''?'ycloud_connection_not_connected':'missing_customer_number','source'=>'ai_quality','created_at'=>gmdate('c')]);$failed++;}
    $conv['ai_sent_stages']=$sentStages;
}
unset($conv);aq_save($convFile,$conversations);
echo json_encode(['ok'=>$failed===0,'model'=>$model,'processed'=>$processed,'stage_changes'=>$changed,'postbacks_sent'=>$sent,'failed'=>$failed,'skipped'=>$skipped],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
