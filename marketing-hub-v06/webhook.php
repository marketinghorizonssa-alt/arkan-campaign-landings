<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, YCloud-Signature, X-Webhook-Endpoint-ID');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$base = __DIR__ . '/data';
if (!is_dir($base)) { @mkdir($base, 0775, true); }

function load_json(string $file, array $default = []): array {
    if (!is_file($file)) return $default;
    $raw = @file_get_contents($file);
    if ($raw === false || trim($raw) === '') return $default;
    $v = json_decode($raw, true);
    return is_array($v) ? $v : $default;
}
function save_json(string $file, array $data): bool {
    $tmp = $file . '.tmp';
    $ok = @file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), LOCK_EX);
    if ($ok === false) return false;
    return @rename($tmp, $file);
}
function append_jsonl(string $file, array $data): bool {
    $fh = @fopen($file, 'ab'); if (!$fh) return false;
    @flock($fh, LOCK_EX);
    $ok = fwrite($fh, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n") !== false;
    @flock($fh, LOCK_UN); fclose($fh); return $ok;
}
function msg_text(array $m): string {
    $type = (string)($m['type'] ?? 'unknown');
    if ($type === 'text') return (string)($m['text']['body'] ?? '');
    foreach (['image','video','document','audio','sticker'] as $k) {
        if ($type === $k && isset($m[$k])) {
            $caption = trim((string)($m[$k]['caption'] ?? ''));
            if ($caption !== '') return $caption;
            $filename = trim((string)($m[$k]['filename'] ?? ''));
            return $filename !== '' ? '['.$k.'] '.$filename : '['.$k.']';
        }
    }
    if ($type === 'location') {
        $loc = $m['location'] ?? [];
        return '[location] ' . trim((string)($loc['name'] ?? $loc['address'] ?? ''));
    }
    if ($type === 'contacts') return '[contacts]';
    if ($type === 'reaction') return '[reaction] ' . (string)($m['reaction']['emoji'] ?? '');
    if ($type === 'interactive') return '[interactive]';
    return '['.$type.']';
}
function event_seen(string $file, string $id): bool {
    if ($id === '' || !is_file($file)) return false;
    $fh = fopen($file, 'rb'); if (!$fh) return false;
    while (($line = fgets($fh)) !== false) {
        $row = json_decode($line, true);
        if (is_array($row) && ($row['id'] ?? '') === $id) { fclose($fh); return true; }
    }
    fclose($fh); return false;
}
function add_conversion_event(string $file, array $conv, string $eventName, string $sourceEventId, array $extra = []): void {
    append_jsonl($file, array_merge([
        'id' => 'mev_' . bin2hex(random_bytes(8)),
        'event' => $eventName,
        'conversation_id' => $conv['id'],
        'waba_id' => $conv['waba_id'] ?? '',
        'business_number' => $conv['business_number'] ?? '',
        'customer_number' => $conv['customer_number'] ?? '',
        'contact_name' => $conv['contact_name'] ?? '',
        'ctwa_clid' => $conv['ctwa_clid'] ?? null,
        'source_event_id' => $sourceEventId,
        'created_at' => gmdate('c')
    ], $extra));
}

$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'empty_body']); exit; }
$event = json_decode($raw, true);
if (!is_array($event)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_json']); exit; }

$type = (string)($event['type'] ?? 'unknown');
$eventId = (string)($event['id'] ?? '');
$rawFile = $base . '/raw_events.jsonl';
$convFile = $base . '/conversations.json';
$conversionFile = $base . '/conversion_events.jsonl';
$systemFile = $base . '/system_events.jsonl';

if ($eventId !== '' && event_seen($rawFile, $eventId)) {
    echo json_encode(['ok'=>true,'duplicate'=>true,'event_type'=>$type], JSON_UNESCAPED_SLASHES); exit;
}
append_jsonl($rawFile, ['id'=>$eventId,'type'=>$type,'createTime'=>$event['createTime'] ?? gmdate('c'),'payload'=>$event]);

$conversations = load_json($convFile, []);
$conversationUpdated = false;
$conversionCreated = 0;

if ($type === 'whatsapp.inbound_message.received' && isset($event['whatsappInboundMessage']) && is_array($event['whatsappInboundMessage'])) {
    $m = $event['whatsappInboundMessage'];
    $waba = (string)($m['wabaId'] ?? '');
    $customer = (string)($m['from'] ?? '');
    $business = (string)($m['to'] ?? '');
    $id = substr(hash('sha256', $waba.'|'.$customer), 0, 24);
    $isNew = !isset($conversations[$id]);
    $profile = is_array($m['customerProfile'] ?? null) ? $m['customerProfile'] : [];
    $referral = is_array($m['referral'] ?? null) ? $m['referral'] : [];
    $prev = $conversations[$id] ?? [];
    $conv = array_merge($prev, [
        'id'=>$id,
        'waba_id'=>$waba,
        'business_number'=>$business,
        'customer_number'=>$customer,
        'contact_name'=>(string)($profile['name'] ?? ($prev['contact_name'] ?? $customer)),
        'contact_username'=>(string)($profile['username'] ?? ($prev['contact_username'] ?? '')),
        'first_seen_at'=>$prev['first_seen_at'] ?? (string)($m['sendTime'] ?? $event['createTime'] ?? gmdate('c')),
        'last_message_at'=>(string)($m['sendTime'] ?? $event['createTime'] ?? gmdate('c')),
        'last_message_text'=>msg_text($m),
        'last_message_type'=>(string)($m['type'] ?? 'unknown'),
        'last_direction'=>'inbound',
        'last_source_event_id'=>$eventId,
        'current_tag'=>$prev['current_tag'] ?? null,
        'ctwa_clid'=>(string)($referral['ctwa_clid'] ?? ($prev['ctwa_clid'] ?? '')),
        'ad_source_id'=>(string)($referral['source_id'] ?? ($prev['ad_source_id'] ?? '')),
        'ad_source_type'=>(string)($referral['source_type'] ?? ($prev['ad_source_type'] ?? '')),
        'ad_headline'=>(string)($referral['headline'] ?? ($prev['ad_headline'] ?? '')),
        'updated_at'=>gmdate('c')
    ]);
    $conversations[$id] = $conv;
    save_json($convFile, $conversations);
    $conversationUpdated = true;
    if ($isNew) { add_conversion_event($conversionFile, $conv, 'conversation_started', $eventId, ['origin'=>'whatsapp_inbound']); $conversionCreated++; }
}
elseif ($type === 'whatsapp.smb.message.echoes' && isset($event['whatsappMessage']) && is_array($event['whatsappMessage'])) {
    $m = $event['whatsappMessage'];
    $waba = (string)($m['wabaId'] ?? '');
    $business = (string)($m['from'] ?? '');
    $customer = (string)($m['to'] ?? '');
    $id = substr(hash('sha256', $waba.'|'.$customer), 0, 24);
    $prev = $conversations[$id] ?? [];
    $profile = is_array($m['customerProfile'] ?? null) ? $m['customerProfile'] : [];
    $conv = array_merge($prev, [
        'id'=>$id,
        'waba_id'=>$waba,
        'business_number'=>$business,
        'customer_number'=>$customer,
        'contact_name'=>$prev['contact_name'] ?? $customer,
        'contact_username'=>(string)($profile['username'] ?? ($prev['contact_username'] ?? '')),
        'first_seen_at'=>$prev['first_seen_at'] ?? (string)($m['sendTime'] ?? $event['createTime'] ?? gmdate('c')),
        'last_message_at'=>(string)($m['sendTime'] ?? $event['createTime'] ?? gmdate('c')),
        'last_message_text'=>msg_text($m),
        'last_message_type'=>(string)($m['type'] ?? 'unknown'),
        'last_direction'=>'outbound_app',
        'last_source_event_id'=>$eventId,
        'current_tag'=>$prev['current_tag'] ?? null,
        'ctwa_clid'=>$prev['ctwa_clid'] ?? '',
        'updated_at'=>gmdate('c')
    ]);
    $conversations[$id] = $conv;
    save_json($convFile, $conversations);
    $conversationUpdated = true;
}
else {
    append_jsonl($systemFile, ['id'=>$eventId,'type'=>$type,'createTime'=>$event['createTime'] ?? gmdate('c'),'data'=>$event]);
}

echo json_encode([
    'ok'=>true,
    'event_type'=>$type,
    'event_id'=>$eventId,
    'stored'=>true,
    'conversation_updated'=>$conversationUpdated,
    'events_created'=>$conversionCreated
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
