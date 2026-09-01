<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$base = __DIR__ . '/data';
if (!is_dir($base)) @mkdir($base, 0775, true);
$secureDir = dirname(__DIR__, 4) . '/.marketing';
$ycloudSecureDir = $secureDir . '/ycloud';
$legacyKeyFile = $secureDir . '/ycloud_api_key';
if (!is_dir($secureDir)) @mkdir($secureDir, 0700, true);
if (!is_dir($ycloudSecureDir)) @mkdir($ycloudSecureDir, 0700, true);

function load_json(string $file, array $default=[]): array {
    if (!is_file($file)) return $default;
    $v = json_decode((string)@file_get_contents($file), true);
    return is_array($v) ? $v : $default;
}
function save_json(string $file, array $data): bool {
    $tmp = $file . '.tmp';
    if (@file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
    return @rename($tmp, $file);
}
function append_jsonl(string $file, array $data): bool {
    $fh = @fopen($file, 'ab');
    if (!$fh) return false;
    @flock($fh, LOCK_EX);
    $ok = fwrite($fh, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n") !== false;
    @flock($fh, LOCK_UN);
    fclose($fh);
    return $ok;
}
function read_jsonl(string $file, int $limit=100): array {
    if (!is_file($file)) return [];
    $lines = @file($file, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) return [];
    $lines = array_slice($lines, -$limit);
    $out = [];
    foreach ($lines as $line) {
        $r = json_decode($line, true);
        if (is_array($r)) $out[] = $r;
    }
    return array_reverse($out);
}
function body_json(): array {
    $v = json_decode((string)file_get_contents('php://input'), true);
    return is_array($v) ? $v : [];
}
function normalize_phone(string $phone): string {
    $phone = trim($phone);
    $plus = str_starts_with($phone, '+') ? '+' : '';
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    return $digits === '' ? '' : $plus . $digits;
}
function make_id(string $prefix): string { return $prefix . '_' . bin2hex(random_bytes(7)); }
function safe_connection_id(string $id): bool { return (bool)preg_match('/^yc_[a-z0-9_]{4,80}$/', $id); }
function connection_dir(string $secureBase, string $id): string { return $secureBase . '/' . $id; }
function connection_key_file(string $secureBase, string $id, string $legacyKeyFile): string {
    return $id === 'yc_legacy' ? $legacyKeyFile : connection_dir($secureBase, $id) . '/api_key';
}
function connection_secret_file(string $secureBase, string $id): string { return connection_dir($secureBase, $id) . '/webhook_secret'; }
function connection_token_file(string $secureBase, string $id): string { return connection_dir($secureBase, $id) . '/setup_token'; }
function read_secret(string $file): string { return is_file($file) ? trim((string)@file_get_contents($file)) : ''; }
function ycloud_request(string $apiKey, string $method, string $path, ?array $payload=null): array {
    $url = 'https://api.ycloud.com' . $path;
    $ch = curl_init($url);
    $headers = ['X-API-Key: ' . $apiKey, 'Accept: application/json'];
    if ($payload !== null) $headers[] = 'Content-Type: application/json';
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $body = curl_exec($ch);
    $errno = curl_errno($ch); $error = curl_error($ch); $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    $decoded = is_string($body) ? json_decode($body, true) : null;
    return ['ok'=>$errno===0 && $status>=200 && $status<300,'status'=>$status,'body'=>is_array($decoded)?$decoded:null,'error'=>$errno?$error:null];
}
function extract_list(?array $j): array {
    if (!$j) return [];
    if (array_is_list($j)) return $j;
    foreach (['items','list','results'] as $k) if (isset($j[$k]) && is_array($j[$k]) && array_is_list($j[$k])) return $j[$k];
    if (isset($j['data']) && is_array($j['data'])) {
        if (array_is_list($j['data'])) return $j['data'];
        foreach (['items','list','results'] as $k) if (isset($j['data'][$k]) && is_array($j['data'][$k]) && array_is_list($j['data'][$k])) return $j['data'][$k];
    }
    return [];
}
function ensure_legacy_connection(string $connectionsFile, string $legacyKeyFile): array {
    $connections = load_json($connectionsFile, []);
    if (is_file($legacyKeyFile) && filesize($legacyKeyFile) > 10 && !isset($connections['yc_legacy'])) {
        $connections['yc_legacy'] = [
            'id'=>'yc_legacy','client_id'=>'','label'=>'Existing test YCloud','status'=>'connected','legacy'=>true,
            'webhook_url'=>'https://marketing.hositee.com/webhook.php','created_at'=>gmdate('c'),'connected_at'=>gmdate('c'),'updated_at'=>gmdate('c')
        ];
        save_json($connectionsFile, $connections);
    }
    return $connections;
}
function connected_state(array $conn, string $secureBase, string $legacyKeyFile): bool {
    $id = (string)($conn['id'] ?? '');
    if ($id === '') return false;
    return read_secret(connection_key_file($secureBase, $id, $legacyKeyFile)) !== '';
}
function client_connection_counts(array $connections): array {
    $out=[]; foreach ($connections as $c) { $cid=(string)($c['client_id']??''); if($cid!=='') $out[$cid]=($out[$cid]??0)+1; } return $out;
}
function sync_detected_numbers(string $convFile, string $numberFile, array $connections): array {
    $numbers = load_json($numberFile, []); $conversations = load_json($convFile, []); $changed=false;
    $defaultLegacy = isset($connections['yc_legacy']) ? 'yc_legacy' : '';
    foreach ($conversations as $c) {
        $phone=normalize_phone((string)($c['business_number']??'')); $waba=(string)($c['waba_id']??'');
        if($phone===''&&$waba==='') continue;
        $connId=(string)($c['ycloud_connection_id']??''); if($connId===''&&$defaultLegacy!=='') $connId=$defaultLegacy;
        $found='';
        foreach($numbers as $nid=>$n){ if(normalize_phone((string)($n['phone_number']??''))===$phone && (string)($n['waba_id']??'')===$waba){$found=(string)$nid;break;} }
        $clientId = $connId!=='' && isset($connections[$connId]) ? (string)($connections[$connId]['client_id']??'') : '';
        if($found===''){
            $id='num_'.substr(sha1($phone.'|'.$waba),0,12); $numbers[$id]=[
                'id'=>$id,'client_id'=>$clientId,'ycloud_connection_id'=>$connId,'label'=>'Detected WhatsApp','phone_number'=>$phone,'waba_id'=>$waba,
                'provider'=>'ycloud','status'=>$clientId!==''?'assigned':'detected','detected'=>true,'created_at'=>gmdate('c'),'updated_at'=>gmdate('c')
            ]; $changed=true;
        } else {
            $itemChanged=false;
            if($connId!=='' && empty($numbers[$found]['ycloud_connection_id'])){$numbers[$found]['ycloud_connection_id']=$connId;$itemChanged=true;}
            if($clientId!=='' && empty($numbers[$found]['client_id'])){$numbers[$found]['client_id']=$clientId;$numbers[$found]['status']='assigned';$itemChanged=true;}
            if($itemChanged){$numbers[$found]['updated_at']=gmdate('c');$changed=true;}
        }
    }
    if($changed) save_json($numberFile,$numbers); return $numbers;
}
function resolve_number(array $conv, array $numbers): ?array {
    $phone=normalize_phone((string)($conv['business_number']??'')); $waba=(string)($conv['waba_id']??'');
    foreach($numbers as $n) if(normalize_phone((string)($n['phone_number']??''))===$phone && (string)($n['waba_id']??'')===$waba) return $n;
    foreach($numbers as $n) if($phone!=='' && normalize_phone((string)($n['phone_number']??''))===$phone) return $n;
    return null;
}
function create_setup_token(string $secureBase, string $id): string {
    $dir=connection_dir($secureBase,$id); if(!is_dir($dir)) @mkdir($dir,0700,true); @chmod($dir,0700);
    $token=bin2hex(random_bytes(24)); $file=connection_token_file($secureBase,$id); @file_put_contents($file,$token."\n",LOCK_EX); @chmod($file,0600); return $token;
}
function sync_connection_numbers(string $apiKey, string $connectionId, string $clientId, string $numbersFile): array {
    $resp=ycloud_request($apiKey,'GET','/v2/whatsapp/phoneNumbers?limit=100&includeTotal=true');
    if(!$resp['ok']) return ['ok'=>false,'status'=>$resp['status'],'count'=>0,'error'=>$resp['error']];
    $items=extract_list($resp['body']); $numbers=load_json($numbersFile,[]); $count=0;
    foreach($items as $p){
        if(!is_array($p)) continue;
        $phone=normalize_phone((string)($p['phoneNumber']??$p['displayPhoneNumber']??'')); $waba=(string)($p['wabaId']??''); if($phone===''&&$waba==='') continue;
        $id=''; foreach($numbers as $nid=>$n){if(normalize_phone((string)($n['phone_number']??''))===$phone && (string)($n['waba_id']??'')===$waba){$id=(string)$nid;break;}}
        if($id==='') $id='num_'.substr(sha1($phone.'|'.$waba),0,12);
        $now=gmdate('c'); $numbers[$id]=[
            'id'=>$id,'client_id'=>$clientId,'ycloud_connection_id'=>$connectionId,
            'label'=>(string)($p['verifiedName']??$p['displayName']??$p['newName']??($numbers[$id]['label']??'WhatsApp')),
            'phone_number'=>$phone,'waba_id'=>$waba,'provider'=>'ycloud','status'=>'assigned','remote_status'=>(string)($p['status']??''),'detected'=>true,
            'created_at'=>(string)($numbers[$id]['created_at']??$now),'updated_at'=>$now
        ]; $count++;
    }
    save_json($numbersFile,$numbers); return ['ok'=>true,'status'=>200,'count'=>$count,'error'=>null];
}

$action=(string)($_GET['action']??'health');
$convFile=$base.'/conversations.json'; $conversionFile=$base.'/conversion_events.jsonl'; $deliveryFile=$base.'/platform_delivery.jsonl'; $rawFile=$base.'/raw_events.jsonl';
$clientsFile=$base.'/clients.json'; $numbersFile=$base.'/whatsapp_numbers.json'; $mappingsFile=$base.'/platform_mappings.json'; $connectionsFile=$base.'/ycloud_connections.json';
$connections=ensure_legacy_connection($connectionsFile,$legacyKeyFile);

if($action==='health'){
    $connected=0; foreach($connections as $c) if(connected_state($c,$ycloudSecureDir,$legacyKeyFile)) $connected++;
    echo json_encode(['ok'=>true,'version'=>'0.9','storage'=>'hostinger-json','ycloud_connections'=>count($connections),'ycloud_connected'=>$connected,'time'=>gmdate('c')]); exit;
}
if($action==='summary'){
    $c=load_json($convFile,[]); $clients=load_json($clientsFile,[]); $numbers=sync_detected_numbers($convFile,$numbersFile,$connections); $today=gmdate('Y-m-d'); $count=0;
    foreach(read_jsonl($conversionFile,1000) as $e) if(str_starts_with((string)($e['created_at']??''),$today)) $count++;
    $connected=0; foreach($connections as $co) if(connected_state($co,$ycloudSecureDir,$legacyKeyFile)) $connected++;
    echo json_encode(['ok'=>true,'clients'=>count($clients),'whatsapp_numbers'=>count($numbers),'conversations'=>count($c),'events_today'=>$count,'ycloud_connections'=>count($connections),'ycloud_connected'=>$connected]); exit;
}
if($action==='clients'){
    $clients=array_values(load_json($clientsFile,[])); $counts=client_connection_counts($connections);
    foreach($clients as &$c){$c['ycloud_connections']=$counts[(string)$c['id']]??0;} unset($c); usort($clients,fn($a,$b)=>strcmp((string)($a['name']??''),(string)($b['name']??'')));
    echo json_encode(['ok'=>true,'clients'=>$clients],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}
if($action==='client_save'&&$_SERVER['REQUEST_METHOD']==='POST'){
    $body=body_json(); $name=trim((string)($body['name']??'')); if($name===''){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'name_required']);exit;}
    $clients=load_json($clientsFile,[]); $id=trim((string)($body['id']??'')); if($id===''||!isset($clients[$id]))$id=make_id('cl'); $now=gmdate('c');
    $clients[$id]=['id'=>$id,'name'=>$name,'status'=>(string)($body['status']??($clients[$id]['status']??'active')),'created_at'=>(string)($clients[$id]['created_at']??$now),'updated_at'=>$now]; save_json($clientsFile,$clients);
    echo json_encode(['ok'=>true,'client'=>$clients[$id]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}
if($action==='ycloud_connections'){
    $numbers=sync_detected_numbers($convFile,$numbersFile,$connections); $out=[];
    foreach($connections as $c){$id=(string)$c['id'];$c['connected']=connected_state($c,$ycloudSecureDir,$legacyKeyFile);$c['numbers_count']=count(array_filter($numbers,fn($n)=>(string)($n['ycloud_connection_id']??'')===$id));$out[]=$c;}
    usort($out,fn($a,$b)=>strcmp((string)($b['updated_at']??''),(string)($a['updated_at']??''))); echo json_encode(['ok'=>true,'connections'=>$out],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}
if($action==='ycloud_connection_create'&&$_SERVER['REQUEST_METHOD']==='POST'){
    $body=body_json();$clientId=trim((string)($body['client_id']??''));$label=trim((string)($body['label']??''));$clients=load_json($clientsFile,[]);
    if($clientId===''||!isset($clients[$clientId])){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'client_required']);exit;}
    $id=make_id('yc');$now=gmdate('c');$webhook='https://marketing.hositee.com/webhook.php?connection='.rawurlencode($id);
    $connections[$id]=['id'=>$id,'client_id'=>$clientId,'label'=>$label!==''?$label:($clients[$clientId]['name'].' YCloud'),'status'=>'pending','webhook_url'=>$webhook,'created_at'=>$now,'updated_at'=>$now]; save_json($connectionsFile,$connections);
    $token=create_setup_token($ycloudSecureDir,$id);$setup='https://marketing.hositee.com/connect.php?c='.rawurlencode($id).'&token='.$token;
    echo json_encode(['ok'=>true,'connection'=>$connections[$id],'setup_url'=>$setup],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}
if($action==='ycloud_setup_link'&&$_SERVER['REQUEST_METHOD']==='POST'){
    $body=body_json();$id=trim((string)($body['connection_id']??''));if(!safe_connection_id($id)||!isset($connections[$id])||$id==='yc_legacy'){http_response_code(404);echo json_encode(['ok'=>false,'error'=>'connection_not_found']);exit;}
    $token=create_setup_token($ycloudSecureDir,$id);echo json_encode(['ok'=>true,'setup_url'=>'https://marketing.hositee.com/connect.php?c='.rawurlencode($id).'&token='.$token]);exit;
}
if($action==='ycloud_sync'&&$_SERVER['REQUEST_METHOD']==='POST'){
    $body=body_json();$id=trim((string)($body['connection_id']??''));if(!safe_connection_id($id)||!isset($connections[$id])){http_response_code(404);echo json_encode(['ok'=>false,'error'=>'connection_not_found']);exit;}
    $key=read_secret(connection_key_file($ycloudSecureDir,$id,$legacyKeyFile));if($key===''){http_response_code(409);echo json_encode(['ok'=>false,'error'=>'not_connected']);exit;}
    $res=sync_connection_numbers($key,$id,(string)($connections[$id]['client_id']??''),$numbersFile);if(!$res['ok']){http_response_code(502);echo json_encode(['ok'=>false,'error'=>'ycloud_sync_failed','status'=>$res['status']]);exit;}
    $connections[$id]['last_sync_at']=gmdate('c');$connections[$id]['updated_at']=gmdate('c');save_json($connectionsFile,$connections);echo json_encode(['ok'=>true,'numbers_synced'=>$res['count']]);exit;
}
if($action==='numbers'){
    $numbers=array_values(sync_detected_numbers($convFile,$numbersFile,$connections));usort($numbers,fn($a,$b)=>strcmp((string)($a['phone_number']??''),(string)($b['phone_number']??'')));echo json_encode(['ok'=>true,'numbers'=>$numbers],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}
if($action==='number_save'&&$_SERVER['REQUEST_METHOD']==='POST'){
    $body=body_json();$phone=normalize_phone((string)($body['phone_number']??''));$waba=trim((string)($body['waba_id']??''));$clientId=trim((string)($body['client_id']??''));$label=trim((string)($body['label']??''));$connId=trim((string)($body['ycloud_connection_id']??''));
    if($phone===''&&$waba===''){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'phone_or_waba_required']);exit;}$clients=load_json($clientsFile,[]);if($clientId!==''&&!isset($clients[$clientId])){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'client_not_found']);exit;}
    if($connId!==''&&!isset($connections[$connId])){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'ycloud_connection_not_found']);exit;}if($connId!==''&&$clientId===''&&isset($connections[$connId]))$clientId=(string)($connections[$connId]['client_id']??'');
    $numbers=sync_detected_numbers($convFile,$numbersFile,$connections);$id=trim((string)($body['id']??''));if($id===''||!isset($numbers[$id])){foreach($numbers as $nid=>$n)if(normalize_phone((string)($n['phone_number']??''))===$phone&&(string)($n['waba_id']??'')===$waba){$id=(string)$nid;break;}}
    if($id===''||!isset($numbers[$id]))$id=make_id('num');$now=gmdate('c');$numbers[$id]=['id'=>$id,'client_id'=>$clientId,'ycloud_connection_id'=>$connId!==''?$connId:(string)($numbers[$id]['ycloud_connection_id']??''),'label'=>$label!==''?$label:(string)($numbers[$id]['label']??'WhatsApp'),'phone_number'=>$phone,'waba_id'=>$waba,'provider'=>'ycloud','status'=>$clientId!==''?'assigned':'detected','detected'=>(bool)($numbers[$id]['detected']??false),'created_at'=>(string)($numbers[$id]['created_at']??$now),'updated_at'=>$now];save_json($numbersFile,$numbers);echo json_encode(['ok'=>true,'number'=>$numbers[$id]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}
if($action==='platform_mappings'){ $m=array_values(load_json($mappingsFile,[]));usort($m,fn($a,$b)=>strcmp((string)($b['updated_at']??''),(string)($a['updated_at']??'')));echo json_encode(['ok'=>true,'mappings'=>$m],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit; }
if($action==='platform_mapping_save'&&$_SERVER['REQUEST_METHOD']==='POST'){
    $body=body_json();$platform=strtolower(trim((string)($body['platform']??'')));$clientId=trim((string)($body['client_id']??''));$numberId=trim((string)($body['number_id']??''));$allowed=['meta','tiktok','google','snapchat','x','linkedin'];$clients=load_json($clientsFile,[]);$numbers=sync_detected_numbers($convFile,$numbersFile,$connections);
    if(!in_array($platform,$allowed,true)||$clientId===''||!isset($clients[$clientId])){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'platform_and_client_required']);exit;}if($numberId!==''&&!isset($numbers[$numberId])){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'number_not_found']);exit;}
    $m=load_json($mappingsFile,[]);$id=trim((string)($body['id']??''));if($id===''||!isset($m[$id])){foreach($m as $mid=>$x)if((string)($x['platform']??'')===$platform&&(string)($x['client_id']??'')===$clientId&&(string)($x['number_id']??'')===$numberId){$id=(string)$mid;break;}}
    if($id===''||!isset($m[$id]))$id=make_id('map');$now=gmdate('c');$m[$id]=['id'=>$id,'platform'=>$platform,'client_id'=>$clientId,'number_id'=>$numberId,'account_id'=>trim((string)($body['account_id']??'')),'account_name'=>trim((string)($body['account_name']??'')),'event_set_id'=>trim((string)($body['event_set_id']??'')),'status'=>trim((string)($body['status']??'configured'))?:'configured','created_at'=>(string)($m[$id]['created_at']??$now),'updated_at'=>$now];save_json($mappingsFile,$m);echo json_encode(['ok'=>true,'mapping'=>$m[$id]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}
if($action==='conversations'){ $c=array_values(load_json($convFile,[]));usort($c,fn($a,$b)=>strcmp((string)($b['last_message_at']??''),(string)($a['last_message_at']??'')));echo json_encode(['ok'=>true,'conversations'=>$c],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit; }
if($action==='events'){echo json_encode(['ok'=>true,'events'=>read_jsonl($conversionFile,200)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if($action==='deliveries'){echo json_encode(['ok'=>true,'deliveries'=>read_jsonl($deliveryFile,200)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if($action==='raw'){echo json_encode(['ok'=>true,'events'=>read_jsonl($rawFile,50)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if($action==='tag'&&$_SERVER['REQUEST_METHOD']==='POST'){
    $body=body_json();$id=(string)($body['conversation_id']??'');$tag=strtolower(trim((string)($body['tag']??'')));$allowed=['interested','qualified','purchased','lost'];if($id===''||!in_array($tag,$allowed,true)){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'invalid_input']);exit;}
    $c=load_json($convFile,[]);if(!isset($c[$id])){http_response_code(404);echo json_encode(['ok'=>false,'error'=>'conversation_not_found']);exit;}$changed=(($c[$id]['current_tag']??null)!==$tag);$now=gmdate('c');$c[$id]['current_tag']=$tag;$c[$id]['tagged_at']=$now;save_json($convFile,$c);$delivery=null;
    if($changed&&$tag!=='lost'){
        $numbers=sync_detected_numbers($convFile,$numbersFile,$connections);$num=resolve_number($c[$id],$numbers);$connId=(string)($c[$id]['ycloud_connection_id']??($num['ycloud_connection_id']??''));if($connId===''&&isset($connections['yc_legacy']))$connId='yc_legacy';$clientId=(string)($num['client_id']??($connections[$connId]['client_id']??''));$numberId=(string)($num['id']??'');
        $eventId=make_id('mev');$event=['id'=>$eventId,'event'=>$tag,'conversation_id'=>$id,'client_id'=>$clientId,'number_id'=>$numberId,'ycloud_connection_id'=>$connId,'waba_id'=>$c[$id]['waba_id']??'','business_number'=>$c[$id]['business_number']??'','customer_number'=>$c[$id]['customer_number']??'','contact_name'=>$c[$id]['contact_name']??'','ctwa_clid'=>$c[$id]['ctwa_clid']??null,'source'=>'manual_tag','created_at'=>$now];append_jsonl($conversionFile,$event);
        $key=$connId!==''?read_secret(connection_key_file($ycloudSecureDir,$connId,$legacyKeyFile)):'';
        if($key!==''&&!empty($event['customer_number'])){$resp=ycloud_request($key,'POST','/v2/event/events',['eventName'=>$tag,'occurTime'=>$now,'contactPhoneNumber'=>$event['customer_number']]);$delivery=['id'=>make_id('del'),'event_id'=>$eventId,'provider'=>'ycloud','ycloud_connection_id'=>$connId,'event'=>$tag,'success'=>(bool)$resp['ok'],'http_status'=>(int)$resp['status'],'error'=>$resp['error'],'created_at'=>gmdate('c')];}
        else{$delivery=['id'=>make_id('del'),'event_id'=>$eventId,'provider'=>'ycloud','ycloud_connection_id'=>$connId,'event'=>$tag,'success'=>false,'http_status'=>0,'error'=>$connId===''?'ycloud_connection_not_resolved':($key===''?'ycloud_connection_not_connected':'missing_customer_number'),'created_at'=>gmdate('c')];}
        append_jsonl($deliveryFile,$delivery);
    }
    echo json_encode(['ok'=>true,'changed'=>$changed,'tag'=>$tag,'delivery'=>$delivery],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}
http_response_code(404);echo json_encode(['ok'=>false,'error'=>'unknown_action']);
