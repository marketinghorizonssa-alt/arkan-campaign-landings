<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$base = __DIR__ . '/data';
if (!is_dir($base)) @mkdir($base, 0775, true);
$store = $base . '/attribution_events.jsonl';
$secretFile = dirname(__DIR__, 4) . '/.marketing/arkan_attribution_secret';

function attr_out(array $payload, int $status=200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function attr_clean(mixed $v, int $max=500): string {
    $s = trim((string)$v);
    $s = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $s) ?? '';
    return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
}
function attr_append(string $file, array $row): bool {
    $fh = @fopen($file, 'ab');
    if (!$fh) return false;
    @flock($fh, LOCK_EX);
    $ok = fwrite($fh, json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n") !== false;
    @flock($fh, LOCK_UN);
    fclose($fh);
    return $ok;
}
function attr_source(array $d): string {
    $utm = strtolower(attr_clean($d['utm_source'] ?? '', 80));
    if (attr_clean($d['gclid'] ?? '') !== '' || attr_clean($d['wbraid'] ?? '') !== '' || attr_clean($d['gbraid'] ?? '') !== '') return 'google';
    if (attr_clean($d['ttclid'] ?? '') !== '') return 'tiktok';
    if (attr_clean($d['fbclid'] ?? '') !== '') return 'meta';
    if (str_contains($utm, 'google') || str_contains($utm, 'adwords')) return 'google';
    if (str_contains($utm, 'tiktok')) return 'tiktok';
    if (str_contains($utm, 'facebook') || str_contains($utm, 'instagram') || str_contains($utm, 'meta')) return 'meta';
    return $utm !== '' ? $utm : 'website';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') attr_out(['ok'=>false,'error'=>'method_not_allowed'], 405);
$raw = (string)file_get_contents('php://input');
if ($raw === '' || strlen($raw) > 65536) attr_out(['ok'=>false,'error'=>'invalid_payload'], 400);
$secret = is_file($secretFile) ? trim((string)@file_get_contents($secretFile)) : '';
if ($secret === '') attr_out(['ok'=>false,'error'=>'secret_not_configured'], 503);
$provided = trim((string)($_SERVER['HTTP_X_ARKAN_SIGNATURE'] ?? ''));
$expected = 'sha256=' . hash_hmac('sha256', $raw, $secret);
if ($provided === '' || !hash_equals($expected, $provided)) attr_out(['ok'=>false,'error'=>'invalid_signature'], 401);
$data = json_decode($raw, true);
if (!is_array($data)) attr_out(['ok'=>false,'error'=>'invalid_json'], 400);

$ref = strtoupper(attr_clean($data['ref_token'] ?? '', 80));
$leadId = strtoupper(attr_clean($data['lead_id'] ?? '', 100));
if ($ref !== '' && !preg_match('/^ARK-AT-[A-Z0-9]{12,40}$/', $ref)) attr_out(['ok'=>false,'error'=>'invalid_ref_token'], 422);
if ($leadId !== '' && !preg_match('/^ARK-WEB-[0-9]{8}-[0-9]{6}-[A-F0-9]{6}$/', $leadId)) attr_out(['ok'=>false,'error'=>'invalid_lead_id'], 422);
if ($ref === '' && $leadId === '') attr_out(['ok'=>false,'error'=>'missing_reference'], 422);

$fields = ['utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid','gbraid','wbraid','ttclid','fbclid','campaign_id','campaign_name','ad_group_id','ad_group_name','ad_id','keyword','match_type','device','network','landing_page_id','landing_path','first_landing_url'];
$tracking = [];
foreach ($fields as $field) $tracking[$field] = attr_clean($data[$field] ?? '', $field === 'first_landing_url' ? 800 : 255);
$source = attr_source($tracking);
$row = [
    'id' => 'atr_' . bin2hex(random_bytes(8)),
    'ref_token' => $ref,
    'lead_id' => $leadId,
    'event_type' => attr_clean($data['event_type'] ?? 'capture', 60),
    'source' => $source,
    'tracking' => $tracking,
    'captured_at' => attr_clean($data['captured_at'] ?? '', 80),
    'received_at' => gmdate('c'),
];
if (!attr_append($store, $row)) attr_out(['ok'=>false,'error'=>'store_failed'], 500);
attr_out(['ok'=>true,'source'=>$source,'ref_token'=>$ref ?: null,'lead_id'=>$leadId ?: null]);
