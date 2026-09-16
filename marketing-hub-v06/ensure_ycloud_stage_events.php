<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$base = __DIR__ . '/data';
$secure = dirname(__DIR__, 4) . '/.marketing';
$connectionsFile = $base . '/ycloud_connections.json';
$platformConfigFile = __DIR__ . '/platform_stage_config.json';
$connections = is_file($connectionsFile) ? json_decode((string)file_get_contents($connectionsFile), true) : [];
if (!is_array($connections)) $connections = [];
$platformConfig = is_file($platformConfigFile) ? json_decode((string)file_get_contents($platformConfigFile), true) : [];
if (!is_array($platformConfig)) $platformConfig = [];
$filterClient = trim((string)getenv('YCLOUD_STAGE_CLIENT_ID'));
$filterConnection = trim((string)getenv('YCLOUD_STAGE_CONNECTION_ID'));

$definitions = [
    'interested' => ['label'=>'Interested Lead','description'=>'Marketing quality engine classified the WhatsApp contact as interested.'],
    'qualified' => ['label'=>'Qualified Lead','description'=>'Marketing quality engine classified the WhatsApp contact as qualified.'],
    'converted' => ['label'=>'Converted Lead','description'=>'Marketing quality engine classified the WhatsApp contact as converted.'],
    'unqualified' => ['label'=>'Unqualified Lead','description'=>'Marketing quality engine classified the WhatsApp contact as unqualified.'],
];
$stageLabels = [
    'interested' => 'Preferred / Interested Lead',
    'qualified' => 'High Intent / Qualified Lead',
    'converted' => 'Effective / Converted Customer',
    'unqualified' => 'Invalid / Unqualified Lead',
];

function yc_key_file(string $secure, string $id): string {
    return $id === 'yc_legacy' ? $secure . '/ycloud_api_key' : $secure . '/ycloud/' . $id . '/api_key';
}
function yc_request(string $key, string $method, string $path, ?array $payload=null): array {
    $ch = curl_init('https://api.ycloud.com' . $path);
    $headers = ['X-API-Key: ' . $key, 'Accept: application/json'];
    if ($payload !== null) $headers[] = 'Content-Type: application/json';
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ['ok'=>$errno===0 && $status>=200 && $status<300,'status'=>$status,'error'=>$errno?$error:null,'body'=>is_string($body)?json_decode($body,true):null];
}

$out = [];
foreach ($connections as $k => $c) {
    if (!is_array($c)) continue;
    $id = (string)($c['id'] ?? (is_string($k) ? $k : ''));
    if ($id === '') continue;
    $clientId = (string)($c['client_id'] ?? '');
    if ($filterClient !== '' && $clientId !== $filterClient) continue;
    if ($filterConnection !== '' && $id !== $filterConnection) continue;
    $kf = yc_key_file($secure, $id);
    $key = is_file($kf) ? trim((string)file_get_contents($kf)) : '';
    $row = ['connection_id'=>$id,'client_id'=>$clientId,'connected'=>$key !== '','events'=>[]];
    if ($key === '') { $out[]=$row; continue; }

    $defs = $definitions;
    $clientMap = $platformConfig['tiktok']['client_stage_events'][$clientId] ?? null;
    if (is_array($clientMap)) {
        foreach ($clientMap as $stage => $eventName) {
            $stage = strtolower(trim((string)$stage));
            $eventName = trim((string)$eventName);
            if ($eventName === '') continue;
            $defs[$eventName] = [
                'label' => ($stageLabels[$stage] ?? ucfirst($stage)) . ' (TikTok)',
                'description' => 'Client-specific TikTok quality signal mapped from internal stage ' . $stage . '.',
            ];
        }
    }

    foreach ($defs as $name=>$meta) {
        $get = yc_request($key, 'GET', '/v2/event/definitions/' . rawurlencode($name));
        if ($get['status'] === 200) {
            $row['events'][$name] = ['status'=>'exists','http_status'=>200];
            continue;
        }
        if ($get['status'] !== 404) {
            $row['events'][$name] = ['status'=>'check_failed','http_status'=>$get['status']];
            continue;
        }
        $create = yc_request($key, 'POST', '/v2/event/definitions', [
            'name'=>$name,
            'label'=>$meta['label'],
            'description'=>$meta['description'],
            'objectType'=>'CONTACT',
            'properties'=>[],
        ]);
        $row['events'][$name] = ['status'=>$create['ok']?'created':'create_failed','http_status'=>$create['status']];
    }
    $out[] = $row;
}

echo json_encode(['ok'=>true,'filters'=>['client_id'=>$filterClient?:null,'connection_id'=>$filterConnection?:null],'connections'=>$out], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "\n";
