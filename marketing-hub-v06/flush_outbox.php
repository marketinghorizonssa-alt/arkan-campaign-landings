<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$base = __DIR__ . '/data';
$secureDir = dirname(__DIR__, 4) . '/.marketing';
$keyFile = $secureDir . '/ycloud_api_key';
$eventsFile = $base . '/conversion_events.jsonl';
$deliveryFile = $base . '/platform_delivery.jsonl';

function rows(string $file): array {
    if (!is_file($file)) return [];
    $out = [];
    foreach ((array)file($file, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line) {
        $r = json_decode($line, true);
        if (is_array($r)) $out[] = $r;
    }
    return $out;
}
function append_row(string $file, array $row): void {
    $fh = fopen($file, 'ab');
    if (!$fh) throw new RuntimeException('cannot_open_delivery_log');
    flock($fh, LOCK_EX);
    fwrite($fh, json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n");
    flock($fh, LOCK_UN);
    fclose($fh);
}
function send_ycloud(string $key, array $event): array {
    $payload = [
        'eventName' => (string)$event['event'],
        'occurTime' => (string)($event['created_at'] ?? gmdate('c')),
        'contactPhoneNumber' => (string)($event['customer_number'] ?? ''),
    ];
    $ch = curl_init('https://api.ycloud.com/v2/event/events');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['X-API-Key: ' . $key, 'Accept: application/json', 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ['ok'=>$errno===0 && $status>=200 && $status<300, 'status'=>$status, 'error'=>$errno?$error:null];
}

$key = is_file($keyFile) ? trim((string)file_get_contents($keyFile)) : '';
if ($key === '') { fwrite(STDERR, "ycloud_not_connected\n"); exit(2); }
$successful = [];
foreach (rows($deliveryFile) as $d) {
    if (($d['provider'] ?? '') === 'ycloud' && !empty($d['success']) && !empty($d['event_id'])) $successful[(string)$d['event_id']] = true;
}
$allowed = ['interested'=>true, 'qualified'=>true, 'purchased'=>true];
$sent = 0; $failed = 0; $skipped = 0;
foreach (rows($eventsFile) as $event) {
    $eventId = (string)($event['id'] ?? '');
    $name = (string)($event['event'] ?? '');
    if ($eventId === '' || !isset($allowed[$name]) || isset($successful[$eventId])) { $skipped++; continue; }
    if (empty($event['customer_number'])) { $failed++; continue; }
    $resp = send_ycloud($key, $event);
    append_row($deliveryFile, [
        'id' => 'del_' . bin2hex(random_bytes(8)),
        'event_id' => $eventId,
        'provider' => 'ycloud',
        'event' => $name,
        'success' => (bool)$resp['ok'],
        'http_status' => (int)$resp['status'],
        'error' => $resp['error'],
        'source' => 'outbox_retry',
        'created_at' => gmdate('c'),
    ]);
    if ($resp['ok']) $sent++; else $failed++;
}
echo json_encode(['ok'=>$failed===0,'sent'=>$sent,'failed'=>$failed,'skipped'=>$skipped], JSON_UNESCAPED_SLASHES) . "\n";
