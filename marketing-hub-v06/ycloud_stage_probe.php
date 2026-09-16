<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$base = __DIR__ . '/data';
$secure = dirname(__DIR__, 4) . '/.marketing';
$connectionsFile = $base . '/ycloud_connections.json';
$connections = is_file($connectionsFile) ? json_decode((string)file_get_contents($connectionsFile), true) : [];
if (!is_array($connections)) $connections = [];
$names = ['interested','qualified','purchased','lost','unqualified','converted','ai_interested','ai_qualified','ai_converted','ai_unqualified'];
function key_file(string $secure, string $id): string {
    return $id === 'yc_legacy' ? $secure . '/ycloud_api_key' : $secure . '/ycloud/' . $id . '/api_key';
}
function status_for(string $key, string $name): int {
    $ch = curl_init('https://api.ycloud.com/v2/event/definitions/' . rawurlencode($name));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['X-API-Key: ' . $key, 'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return $status;
}
$out = [];
foreach ($connections as $k => $c) {
    if (!is_array($c)) continue;
    $id = (string)($c['id'] ?? (is_string($k) ? $k : ''));
    if ($id === '') continue;
    $kf = key_file($secure, $id);
    $key = is_file($kf) ? trim((string)file_get_contents($kf)) : '';
    $row = ['connection_id'=>$id, 'client_id'=>(string)($c['client_id'] ?? ''), 'connected'=>$key !== '', 'definitions'=>[]];
    if ($key !== '') foreach ($names as $name) $row['definitions'][$name] = status_for($key, $name);
    $out[] = $row;
}
echo json_encode(['ok'=>true,'connections'=>$out], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . "\n";
