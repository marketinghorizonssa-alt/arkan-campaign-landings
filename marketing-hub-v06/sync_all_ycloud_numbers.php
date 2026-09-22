<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$base = __DIR__ . '/data';
$secureDir = dirname(__DIR__, 4) . '/.marketing';
$ycloudSecureDir = $secureDir . '/ycloud';
$legacyKeyFile = $secureDir . '/ycloud_api_key';
$connectionsFile = $base . '/ycloud_connections.json';
$numbersFile = $base . '/whatsapp_numbers.json';
$stateFile = $secureDir . '/ycloud_number_sync_last_run';

function ns_json(string $file, array $default=[]): array {
    if (!is_file($file)) return $default;
    $v = json_decode((string)@file_get_contents($file), true);
    return is_array($v) ? $v : $default;
}
function ns_save(string $file, array $data): bool {
    $tmp = $file . '.tmp.' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
    if (!@rename($tmp, $file)) { @unlink($tmp); return false; }
    return true;
}
function ns_phone(string $v): string { return preg_replace('/\D+/', '', $v) ?? ''; }
function ns_key(string $id, string $secureBase, string $legacyKeyFile): string {
    $file = $id === 'yc_legacy' ? $legacyKeyFile : $secureBase . '/' . $id . '/api_key';
    return is_file($file) ? trim((string)@file_get_contents($file)) : '';
}
function ns_list(?array $j): array {
    if (!$j) return [];
    if (array_is_list($j)) return $j;
    foreach (['items','list','results'] as $k) if (isset($j[$k]) && is_array($j[$k]) && array_is_list($j[$k])) return $j[$k];
    if (isset($j['data']) && is_array($j['data'])) {
        if (array_is_list($j['data'])) return $j['data'];
        foreach (['items','list','results'] as $k) if (isset($j['data'][$k]) && is_array($j['data'][$k]) && array_is_list($j['data'][$k])) return $j['data'][$k];
    }
    return [];
}
function ns_get_numbers(string $key): array {
    $ch = curl_init('https://api.ycloud.com/v2/whatsapp/phoneNumbers?limit=100&includeTotal=true');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>['X-API-Key: '.$key,'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT=>8,
        CURLOPT_TIMEOUT=>20,
        CURLOPT_FOLLOWLOCATION=>false
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $json = is_string($body) ? json_decode($body, true) : null;
    return ['ok'=>$errno===0 && $status>=200 && $status<300, 'status'=>$status, 'error'=>$errno?$error:null, 'items'=>ns_list(is_array($json)?$json:null)];
}
function ns_counts(array $numbers): array {
    $out=[];
    foreach ($numbers as $n) {
        if (!is_array($n)) continue;
        $cid=(string)($n['client_id']??'');
        if ($cid!=='') $out[$cid]=($out[$cid]??0)+1;
    }
    ksort($out);
    return $out;
}
function ns_bootstrap_reporting(string $secureDir): void {
    $marker=$secureDir.'/.reporting_live_feed_v1_deployed';
    if (is_file($marker)) return;
    if (!is_dir($secureDir)) @mkdir($secureDir,0700,true);
    $baseUrl='https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/marketing-deploy/marketing-hub-v06/';
    $files=['lead_report_api.php','sheet_feed.php','reports.php'];
    foreach($files as $file){
        $ch=curl_init($baseUrl.$file);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>25,CURLOPT_FOLLOWLOCATION=>false]);
        $body=curl_exec($ch);$errno=curl_errno($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if($errno!==0||$status<200||$status>=300||!is_string($body)||strlen($body)<50)return;
        $target=__DIR__.'/'.$file;$tmp=$target.'.deploy.tmp';
        if(@file_put_contents($tmp,$body,LOCK_EX)===false)return;
        if(!@rename($tmp,$target)){@unlink($tmp);return;}
    }
    @file_put_contents($marker,gmdate('c')."\n",LOCK_EX);
    @chmod($marker,0600);
}

ns_bootstrap_reporting($secureDir);

function ns_bootstrap_chatlink_attribution(string $secureDir): void {
    $marker=$secureDir.'/.chatlink_attribution_v1_deployed';
    if(is_file($marker))return;
    $baseUrl='https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/marketing-deploy/marketing-hub-v06/';
    foreach(['api.php','webhook.php'] as $file){
        $ch=curl_init($baseUrl.$file);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>25,CURLOPT_FOLLOWLOCATION=>false]);
        $body=curl_exec($ch);$errno=curl_errno($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if($errno!==0||$status<200||$status>=300||!is_string($body)||strlen($body)<100)return;
        $target=__DIR__.'/'.$file;$tmp=$target.'.chatlink.tmp';
        if(@file_put_contents($tmp,$body,LOCK_EX)===false)return;
        if(!@rename($tmp,$target)){@unlink($tmp);return;}
    }
    @file_put_contents($marker,gmdate('c')."\n",LOCK_EX);
}
ns_bootstrap_chatlink_attribution($secureDir);

if (is_file($stateFile) && (time() - (int)@filemtime($stateFile)) < 900) {
    echo json_encode(['ok'=>true,'status'=>'throttled','last_run'=>trim((string)@file_get_contents($stateFile))], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

$connections = ns_json($connectionsFile, []);
$numbers = ns_json($numbersFile, []);
$before = ns_counts($numbers);
$changed = 0;
$remoteTotal = 0;
$results = [];
$now = gmdate('c');

foreach ($connections as $keyId=>$conn) {
    if (!is_array($conn)) continue;
    $id=(string)($conn['id']??(is_string($keyId)?$keyId:''));
    $cid=(string)($conn['client_id']??'');
    if ($id==='' || $cid==='') continue;
    $apiKey=ns_key($id,$ycloudSecureDir,$legacyKeyFile);
    if ($apiKey==='') { $results[$id]=['ok'=>false,'status'=>0,'count'=>0,'error'=>'missing_key']; continue; }
    $resp=ns_get_numbers($apiKey);
    if (!$resp['ok']) { $results[$id]=['ok'=>false,'status'=>$resp['status'],'count'=>0,'error'=>$resp['error']]; continue; }
    $cnt=0;
    foreach ($resp['items'] as $p) {
        if (!is_array($p)) continue;
        $phone=ns_phone((string)($p['phoneNumber']??$p['displayPhoneNumber']??''));
        $waba=(string)($p['wabaId']??'');
        if ($phone==='' && $waba==='') continue;
        $cnt++; $remoteTotal++;
        $found='';
        foreach ($numbers as $nid=>$n) {
            if (!is_array($n)) continue;
            $np=ns_phone((string)($n['phone_number']??''));
            $nw=(string)($n['waba_id']??'');
            if (($phone!=='' && $np===$phone && ($waba==='' || $nw==='' || $nw===$waba)) || (($n['ycloud_connection_id']??'')===$id && $phone!=='' && $np===$phone)) {
                $found=(string)$nid; break;
            }
        }
        if ($found==='') $found='num_'.substr(sha1($phone.'|'.$waba),0,12);
        $old=is_array($numbers[$found]??null)?$numbers[$found]:[];
        $new=[
            'id'=>$found,
            'client_id'=>$cid,
            'ycloud_connection_id'=>$id,
            'label'=>(string)($p['verifiedName']??$p['displayName']??$p['newName']??($old['label']??'WhatsApp')),
            'phone_number'=>$phone!==''?('+'.$phone):(string)($old['phone_number']??''),
            'waba_id'=>$waba!==''?$waba:(string)($old['waba_id']??''),
            'provider'=>'ycloud',
            'status'=>'assigned',
            'remote_status'=>(string)($p['status']??($old['remote_status']??'')),
            'detected'=>true,
            'created_at'=>(string)($old['created_at']??$now),
            'updated_at'=>$now
        ];
        if ($old !== $new) $changed++;
        $numbers[$found]=$new;
    }
    $results[$id]=['ok'=>true,'status'=>200,'count'=>$cnt,'client_id'=>$cid];
}

$saved = $changed===0 ? true : ns_save($numbersFile,$numbers);
if ($saved) {
    if (!is_dir($secureDir)) @mkdir($secureDir,0700,true);
    @file_put_contents($stateFile,$now."\n",LOCK_EX);
    @chmod($stateFile,0600);
}
$after=ns_counts($numbers);
echo json_encode(['ok'=>$saved,'status'=>$saved?'synced':'save_failed','changed'=>$changed,'remote_total'=>$remoteTotal,'before'=>$before,'after'=>$after,'connections'=>$results],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
