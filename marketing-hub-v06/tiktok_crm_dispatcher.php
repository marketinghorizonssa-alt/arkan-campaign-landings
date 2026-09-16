<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$secure = dirname(__DIR__, 4) . '/.marketing';
$poolRoot = $secure . '/lead_pools';
$configFile = __DIR__ . '/tiktok_crm_event_sets.json';
$endpoint = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';

function tcd_json(string $file, array $default=[]): array {
    if (!is_file($file)) return $default;
    $v = json_decode((string)@file_get_contents($file), true);
    return is_array($v) ? $v : $default;
}
function tcd_rows(string $file): array {
    $out=[];
    if (!is_file($file)) return $out;
    $fh=@fopen($file,'rb'); if(!$fh) return $out;
    while(($line=fgets($fh))!==false){$r=json_decode($line,true);if(is_array($r))$out[]=$r;}
    fclose($fh); return $out;
}
function tcd_append(string $file,array $row): void {
    $fh=@fopen($file,'ab'); if(!$fh) throw new RuntimeException('delivery_log_open_failed');
    @flock($fh,LOCK_EX);
    fwrite($fh,json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    @flock($fh,LOCK_UN); fclose($fh); @chmod($file,0600);
}
function tcd_s(mixed $v): string { return is_scalar($v) ? trim((string)$v) : ''; }
function tcd_phone(string $v): string {
    $d=preg_replace('/\D+/','',$v)??'';
    if(str_starts_with($d,'00'))$d=substr($d,2);
    return $d;
}
function tcd_time(string $v): int {
    if ($v==='') return time();
    $t=strtotime($v); return $t===false?time():$t;
}
function tcd_send(string $endpoint,string $token,array $payload): array {
    $ch=curl_init($endpoint);
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Access-Token: '.$token,'Content-Type: application/json','Accept: application/json'],
        CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        CURLOPT_CONNECTTIMEOUT=>8,
        CURLOPT_TIMEOUT=>20,
        CURLOPT_FOLLOWLOCATION=>false
    ]);
    $body=curl_exec($ch);$errno=curl_errno($ch);$err=curl_error($ch);$http=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    $decoded=is_string($body)?json_decode($body,true):null;
    $apiCode=is_array($decoded)?(int)($decoded['code']??-1):-1;
    $message=is_array($decoded)?tcd_s($decoded['message']??''):'';
    $ok=$errno===0&&$http>=200&&$http<300&&$apiCode===0;
    return ['ok'=>$ok,'http_status'=>$http,'api_code'=>$apiCode,'error'=>$errno?$err:($ok?null:($message!==''?$message:'tiktok_rejected'))];
}

$config=tcd_json($configFile,[]);
$result=['ok'=>true,'adapter'=>'tiktok_crm_direct_v1','sent'=>0,'failed'=>0,'skipped'=>0,'clients'=>[]];
if(!is_dir($poolRoot)||!is_array($config['clients']??null)){
    $result['status']='no_pool_or_config';
    echo json_encode($result,JSON_UNESCAPED_SLASHES)."\n";exit;
}

foreach($config['clients'] as $clientId=>$client){
    if(!is_array($client))continue;
    $set=is_array($client['website_quality_crm_event_set']??null)?$client['website_quality_crm_event_set']:[];
    if(empty($set['direct_events_api']))continue;
    $setId=tcd_s($set['id']??''); if($setId==='')continue;
    $eventNames=is_array($set['event_names']??null)?$set['event_names']:[];
    $poolDir=$poolRoot.'/'.preg_replace('/[^A-Za-z0-9_.-]/','_',(string)$clientId);
    $tokenFile=$secure.'/tiktok/crm/'.$setId.'/access_token';
    $token=is_file($tokenFile)?trim((string)@file_get_contents($tokenFile)):'';
    $clientResult=['event_set_id'=>$setId,'token_present'=>$token!=='','sent'=>0,'failed'=>0,'skipped'=>0];
    if(!is_dir($poolDir)){$clientResult['status']='pool_missing';$result['clients'][$clientId]=$clientResult;continue;}
    if($token===''){$clientResult['status']='token_missing';$result['clients'][$clientId]=$clientResult;continue;}

    $identity=[];
    foreach(tcd_rows($poolDir.'/identity_map.jsonl') as $r){$lead=tcd_s($r['lead_id']??'');if($lead!=='')$identity[$lead]=$r;}
    $done=[];
    foreach(tcd_rows($poolDir.'/tiktok_crm_delivery.jsonl') as $d){if(!empty($d['success'])){$eid=tcd_s($d['event_id']??'');if($eid!=='')$done[$eid]=true;}}

    foreach(tcd_rows($poolDir.'/routing_outbox.jsonl') as $route){
        if(tcd_s($route['client_id']??'')!==(string)$clientId){$clientResult['skipped']++;continue;}
        if(tcd_s($route['delivery_status']??'')!=='queued'||tcd_s($route['target_platform']??'')!=='tiktok'||tcd_s($route['route_type']??'')!=='online_conversion'){$clientResult['skipped']++;continue;}
        $eventId=tcd_s($route['event_id']??''); if($eventId===''||isset($done[$eventId])){$clientResult['skipped']++;continue;}
        $stage=strtolower(tcd_s($route['stage']??''));$eventName=tcd_s($eventNames[$stage]??'');
        if($eventName===''||!in_array($stage,['interested','qualified','converted','unqualified'],true)){$clientResult['skipped']++;continue;}
        $native=is_array($route['native_ids']??null)?$route['native_ids']:[];$ttclid=tcd_s($native['ttclid']??'');
        if(!empty($set['require_tiktok_click_id'])&&$ttclid===''){$clientResult['skipped']++;continue;}
        $leadId=tcd_s($route['lead_id']??'');$contactId=tcd_s($route['contact_id']??'');$id=$identity[$leadId]??[];$phone=tcd_phone(tcd_s($id['phone']??''));
        $user=[];
        if($phone!=='')$user['phone']=hash('sha256','+'.$phone);
        if($contactId!=='')$user['external_id']=hash('sha256',$contactId);
        if($ttclid!=='')$user['ttclid']=$ttclid;
        if(!$user){$clientResult['failed']++;$result['failed']++;continue;}
        $payload=[
            'event_source'=>'crm',
            'event_source_id'=>$setId,
            'data'=>[[
                'event'=>$eventName,
                'event_time'=>tcd_time(tcd_s($route['occurred_at']??'')),
                'event_id'=>$eventId,
                'user'=>$user,
                'properties'=>['lead_stage'=>$stage,'origin_source'=>'tiktok']
            ]]
        ];
        $resp=tcd_send($endpoint,$token,$payload);
        $delivery=['event_id'=>$eventId,'client_id'=>$clientId,'lead_id'=>$leadId,'event_set_id'=>$setId,'event_name'=>$eventName,'stage'=>$stage,'success'=>(bool)$resp['ok'],'http_status'=>(int)$resp['http_status'],'api_code'=>(int)$resp['api_code'],'error'=>$resp['error'],'created_at'=>gmdate('c')];
        tcd_append($poolDir.'/tiktok_crm_delivery.jsonl',$delivery);
        if($resp['ok']){
            tcd_append($poolDir.'/routing_delivery.jsonl',['event_id'=>$eventId,'client_id'=>$clientId,'lead_id'=>$leadId,'target_platform'=>'tiktok','route_type'=>'online_conversion','provider'=>'tiktok_crm_events_api','event_set_id'=>$setId,'event_name'=>$eventName,'stage'=>$stage,'success'=>true,'http_status'=>(int)$resp['http_status'],'created_at'=>gmdate('c')]);
            $done[$eventId]=true;$clientResult['sent']++;$result['sent']++;
        } else {$clientResult['failed']++;$result['failed']++;}
    }
    $clientResult['status']=$clientResult['failed']>0?'partial_failure':'ready';
    $result['clients'][$clientId]=$clientResult;
}
$result['ok']=$result['failed']===0;
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
