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
$keyFile = $secureDir . '/ycloud_api_key';

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
function make_id(string $prefix): string {
    return $prefix . '_' . bin2hex(random_bytes(7));
}
function ycloud_request(string $apiKey, string $method, string $path, ?array $payload=null): array {
    $url = 'https://api.ycloud.com' . $path;
    $ch = curl_init($url);
    $headers = ['X-API-Key: ' . $apiKey, 'Accept: application/json'];
    if ($payload !== null) $headers[] = 'Content-Type: application/json';
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $decoded = is_string($body) ? json_decode($body, true) : null;
    return [
        'ok' => $errno === 0 && $status >= 200 && $status < 300,
        'status' => $status,
        'body' => is_array($decoded) ? $decoded : null,
        'error' => $errno ? $error : null,
    ];
}
function read_secret(string $file): string {
    if (!is_file($file)) return '';
    return trim((string)@file_get_contents($file));
}
function sync_detected_numbers(string $convFile, string $numberFile): array {
    $numbers = load_json($numberFile, []);
    $conversations = load_json($convFile, []);
    $index = [];
    foreach ($numbers as $id => $n) {
        $k = normalize_phone((string)($n['phone_number'] ?? '')) . '|' . (string)($n['waba_id'] ?? '');
        if ($k !== '|') $index[$k] = (string)$id;
    }
    $changed = false;
    foreach ($conversations as $c) {
        $phone = normalize_phone((string)($c['business_number'] ?? ''));
        $waba = (string)($c['waba_id'] ?? '');
        if ($phone === '' && $waba === '') continue;
        $k = $phone . '|' . $waba;
        if (isset($index[$k])) continue;
        $id = 'num_' . substr(sha1($k), 0, 12);
        $numbers[$id] = [
            'id' => $id,
            'client_id' => '',
            'label' => 'Detected WhatsApp',
            'phone_number' => $phone,
            'waba_id' => $waba,
            'provider' => 'ycloud',
            'status' => 'detected',
            'detected' => true,
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
        ];
        $index[$k] = $id;
        $changed = true;
    }
    if ($changed) save_json($numberFile, $numbers);
    return $numbers;
}

$action = (string)($_GET['action'] ?? 'health');
$convFile = $base . '/conversations.json';
$conversionFile = $base . '/conversion_events.jsonl';
$deliveryFile = $base . '/platform_delivery.jsonl';
$rawFile = $base . '/raw_events.jsonl';
$clientsFile = $base . '/clients.json';
$numbersFile = $base . '/whatsapp_numbers.json';
$mappingsFile = $base . '/platform_mappings.json';

if ($action === 'health') {
    echo json_encode([
        'ok' => true,
        'version' => '0.8',
        'storage' => 'hostinger-json',
        'ycloud_connected' => is_file($keyFile) && filesize($keyFile) > 10,
        'time' => gmdate('c')
    ]);
    exit;
}
if ($action === 'summary') {
    $c = load_json($convFile, []);
    $clients = load_json($clientsFile, []);
    $numbers = sync_detected_numbers($convFile, $numbersFile);
    $today = gmdate('Y-m-d');
    $count = 0;
    foreach (read_jsonl($conversionFile, 1000) as $e) {
        if (str_starts_with((string)($e['created_at'] ?? ''), $today)) $count++;
    }
    echo json_encode([
        'ok' => true,
        'clients' => count($clients),
        'whatsapp_numbers' => count($numbers),
        'conversations' => count($c),
        'events_today' => $count,
        'ycloud_connected' => is_file($keyFile) && filesize($keyFile) > 10,
    ]);
    exit;
}
if ($action === 'clients') {
    $clients = array_values(load_json($clientsFile, []));
    usort($clients, fn($a,$b) => strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')));
    echo json_encode(['ok'=>true,'clients'=>$clients], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
if ($action === 'client_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = body_json();
    $name = trim((string)($body['name'] ?? ''));
    if ($name === '') { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'name_required']); exit; }
    $clients = load_json($clientsFile, []);
    $id = trim((string)($body['id'] ?? ''));
    if ($id === '' || !isset($clients[$id])) $id = make_id('cl');
    $now = gmdate('c');
    $clients[$id] = [
        'id' => $id,
        'name' => $name,
        'status' => (string)($body['status'] ?? ($clients[$id]['status'] ?? 'active')),
        'created_at' => (string)($clients[$id]['created_at'] ?? $now),
        'updated_at' => $now,
    ];
    save_json($clientsFile, $clients);
    echo json_encode(['ok'=>true,'client'=>$clients[$id]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
if ($action === 'numbers') {
    $numbers = array_values(sync_detected_numbers($convFile, $numbersFile));
    usort($numbers, fn($a,$b) => strcmp((string)($a['phone_number'] ?? ''), (string)($b['phone_number'] ?? '')));
    echo json_encode(['ok'=>true,'numbers'=>$numbers], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
if ($action === 'number_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = body_json();
    $phone = normalize_phone((string)($body['phone_number'] ?? ''));
    $waba = trim((string)($body['waba_id'] ?? ''));
    $clientId = trim((string)($body['client_id'] ?? ''));
    $label = trim((string)($body['label'] ?? ''));
    if ($phone === '' && $waba === '') { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'phone_or_waba_required']); exit; }
    if ($clientId !== '') {
        $clients = load_json($clientsFile, []);
        if (!isset($clients[$clientId])) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'client_not_found']); exit; }
    }
    $numbers = sync_detected_numbers($convFile, $numbersFile);
    $id = trim((string)($body['id'] ?? ''));
    if ($id === '' || !isset($numbers[$id])) {
        foreach ($numbers as $nid => $n) {
            if (normalize_phone((string)($n['phone_number'] ?? '')) === $phone && (string)($n['waba_id'] ?? '') === $waba) { $id = (string)$nid; break; }
        }
    }
    if ($id === '' || !isset($numbers[$id])) $id = make_id('num');
    $now = gmdate('c');
    $numbers[$id] = [
        'id' => $id,
        'client_id' => $clientId,
        'label' => $label !== '' ? $label : (string)($numbers[$id]['label'] ?? 'WhatsApp'),
        'phone_number' => $phone,
        'waba_id' => $waba,
        'provider' => (string)($body['provider'] ?? ($numbers[$id]['provider'] ?? 'ycloud')),
        'status' => $clientId !== '' ? 'assigned' : 'detected',
        'detected' => (bool)($numbers[$id]['detected'] ?? false),
        'created_at' => (string)($numbers[$id]['created_at'] ?? $now),
        'updated_at' => $now,
    ];
    save_json($numbersFile, $numbers);
    echo json_encode(['ok'=>true,'number'=>$numbers[$id]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
if ($action === 'platform_mappings') {
    $mappings = array_values(load_json($mappingsFile, []));
    usort($mappings, fn($a,$b) => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')));
    echo json_encode(['ok'=>true,'mappings'=>$mappings], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
if ($action === 'platform_mapping_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = body_json();
    $platform = strtolower(trim((string)($body['platform'] ?? '')));
    $clientId = trim((string)($body['client_id'] ?? ''));
    $numberId = trim((string)($body['number_id'] ?? ''));
    $allowed = ['meta','tiktok','google','snapchat','x','linkedin'];
    if (!in_array($platform, $allowed, true) || $clientId === '') { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'platform_and_client_required']); exit; }
    $clients = load_json($clientsFile, []);
    $numbers = sync_detected_numbers($convFile, $numbersFile);
    if (!isset($clients[$clientId])) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'client_not_found']); exit; }
    if ($numberId !== '' && !isset($numbers[$numberId])) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'number_not_found']); exit; }
    $mappings = load_json($mappingsFile, []);
    $id = trim((string)($body['id'] ?? ''));
    if ($id === '' || !isset($mappings[$id])) {
        foreach ($mappings as $mid => $m) {
            if ((string)($m['platform'] ?? '') === $platform && (string)($m['client_id'] ?? '') === $clientId && (string)($m['number_id'] ?? '') === $numberId) { $id = (string)$mid; break; }
        }
    }
    if ($id === '' || !isset($mappings[$id])) $id = make_id('map');
    $now = gmdate('c');
    $mappings[$id] = [
        'id' => $id,
        'platform' => $platform,
        'client_id' => $clientId,
        'number_id' => $numberId,
        'account_id' => trim((string)($body['account_id'] ?? '')),
        'account_name' => trim((string)($body['account_name'] ?? '')),
        'event_set_id' => trim((string)($body['event_set_id'] ?? '')),
        'status' => trim((string)($body['status'] ?? 'configured')) ?: 'configured',
        'created_at' => (string)($mappings[$id]['created_at'] ?? $now),
        'updated_at' => $now,
    ];
    save_json($mappingsFile, $mappings);
    echo json_encode(['ok'=>true,'mapping'=>$mappings[$id]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
if ($action === 'conversations') {
    $c = array_values(load_json($convFile, []));
    usort($c, fn($a,$b) => strcmp((string)($b['last_message_at'] ?? ''), (string)($a['last_message_at'] ?? '')));
    echo json_encode(['ok'=>true,'conversations'=>$c], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
if ($action === 'events') {
    echo json_encode(['ok'=>true,'events'=>read_jsonl($conversionFile,200)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
if ($action === 'deliveries') {
    echo json_encode(['ok'=>true,'deliveries'=>read_jsonl($deliveryFile,200)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
if ($action === 'raw') {
    echo json_encode(['ok'=>true,'events'=>read_jsonl($rawFile,50)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
if ($action === 'tag' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = body_json();
    if (!$body) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_json']); exit; }
    $id = (string)($body['conversation_id'] ?? '');
    $tag = strtolower(trim((string)($body['tag'] ?? '')));
    $allowed = ['interested','qualified','purchased','lost'];
    if ($id === '' || !in_array($tag, $allowed, true)) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'invalid_input']); exit; }
    $c = load_json($convFile, []);
    if (!isset($c[$id])) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'conversation_not_found']); exit; }

    $changed = (($c[$id]['current_tag'] ?? null) !== $tag);
    $now = gmdate('c');
    $c[$id]['current_tag'] = $tag;
    $c[$id]['tagged_at'] = $now;
    save_json($convFile, $c);

    $delivery = null;
    if ($changed && $tag !== 'lost') {
        $eventId = make_id('mev');
        $event = [
            'id' => $eventId,
            'event' => $tag,
            'conversation_id' => $id,
            'waba_id' => $c[$id]['waba_id'] ?? '',
            'business_number' => $c[$id]['business_number'] ?? '',
            'customer_number' => $c[$id]['customer_number'] ?? '',
            'contact_name' => $c[$id]['contact_name'] ?? '',
            'ctwa_clid' => $c[$id]['ctwa_clid'] ?? null,
            'source' => 'manual_tag',
            'created_at' => $now,
        ];
        append_jsonl($conversionFile, $event);

        $apiKey = read_secret($keyFile);
        if ($apiKey !== '' && !empty($event['customer_number'])) {
            $resp = ycloud_request($apiKey, 'POST', '/v2/event/events', [
                'eventName' => $tag,
                'occurTime' => $now,
                'contactPhoneNumber' => $event['customer_number'],
            ]);
            $delivery = [
                'id' => make_id('del'),
                'event_id' => $eventId,
                'provider' => 'ycloud',
                'event' => $tag,
                'success' => (bool)$resp['ok'],
                'http_status' => (int)$resp['status'],
                'error' => $resp['error'],
                'created_at' => gmdate('c'),
            ];
        } else {
            $delivery = [
                'id' => make_id('del'),
                'event_id' => $eventId,
                'provider' => 'ycloud',
                'event' => $tag,
                'success' => false,
                'http_status' => 0,
                'error' => $apiKey === '' ? 'not_connected' : 'missing_customer_number',
                'created_at' => gmdate('c'),
            ];
        }
        append_jsonl($deliveryFile, $delivery);
    }
    echo json_encode(['ok'=>true,'changed'=>$changed,'tag'=>$tag,'delivery'=>$delivery], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(404);
echo json_encode(['ok'=>false,'error'=>'unknown_action']);
