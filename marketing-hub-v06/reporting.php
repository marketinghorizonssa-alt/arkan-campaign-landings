<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'method_not_allowed']); exit; }

$base = __DIR__ . '/data';
$rawFile = $base . '/raw_events.jsonl';
$attributionFile = $base . '/attribution_events.jsonl';
$clientsFile = $base . '/clients.json';
$connectionsFile = $base . '/ycloud_connections.json';

function r_json(string $file, array $default=[]): array {
    if (!is_file($file)) return $default;
    $v = json_decode((string)@file_get_contents($file), true);
    return is_array($v) ? $v : $default;
}
function r_text(array $message): string {
    $type = (string)($message['type'] ?? '');
    if ($type === 'text') return trim((string)($message['text']['body'] ?? ''));
    foreach (['image','video','document','audio','sticker'] as $k) {
        if ($type === $k && isset($message[$k]) && is_array($message[$k])) {
            return trim((string)($message[$k]['caption'] ?? ''));
        }
    }
    return '';
}
function r_date(string $value, DateTimeZone $tz): ?DateTimeImmutable {
    if ($value === '') return null;
    try { return (new DateTimeImmutable($value))->setTimezone($tz); } catch (Throwable) { return null; }
}
function r_source_label(string $source): string {
    return match ($source) {
        'google' => 'Google Ads',
        'meta' => 'Meta / Instagram Ads',
        'tiktok' => 'TikTok Ads',
        'direct_or_organic' => 'Direct / Organic / Unattributed',
        default => $source,
    };
}
function r_source_from_message(array $message, array $refSources): array {
    $referral = is_array($message['referral'] ?? null) ? $message['referral'] : [];
    $ctwa = trim((string)($referral['ctwa_clid'] ?? ''));
    $sourceId = trim((string)($referral['source_id'] ?? ''));
    $sourceType = trim((string)($referral['source_type'] ?? ''));
    if ($ctwa !== '' || $sourceId !== '' || $sourceType !== '') {
        return ['source'=>'meta','method'=>'whatsapp_referral'];
    }

    $text = r_text($message);
    if ($text !== '') {
        $lower = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        if (str_contains($lower, 'المصدر: google ads') || str_contains($lower, 'source: google ads')) return ['source'=>'google','method'=>'message_source_tag'];
        if (str_contains($lower, 'المصدر: tiktok ads') || str_contains($lower, 'source: tiktok ads')) return ['source'=>'tiktok','method'=>'message_source_tag'];
        if (str_contains($lower, 'المصدر: instagram/meta ads') || str_contains($lower, 'المصدر: meta ads') || str_contains($lower, 'المصدر: instagram ads') || str_contains($lower, 'المصدر: facebook ads')) return ['source'=>'meta','method'=>'message_source_tag'];
        if (preg_match('/ARK-AT-[A-Z0-9]{12,40}/i', $text, $m)) {
            $ref = strtoupper($m[0]);
            if (isset($refSources[$ref])) return ['source'=>$refSources[$ref],'method'=>'attribution_reference'];
        }
    }
    return ['source'=>'direct_or_organic','method'=>'unattributed'];
}
function r_empty_day(): array {
    return [
        'inbound_messages'=>0,
        'outbound_messages'=>0,
        'unique_inbound_contacts'=>0,
        'new_conversations'=>0,
        'sources'=>[],
        'source_labels'=>[],
        'attribution_methods'=>[],
        'new_conversation_sources'=>[],
        'new_conversation_source_labels'=>[],
        'new_conversation_attribution_methods'=>[],
    ];
}
function r_add_source(array &$bucket, string $source, string $method): void {
    $bucket['sources'][$source] = ($bucket['sources'][$source] ?? 0) + 1;
    $bucket['source_labels'][r_source_label($source)] = ($bucket['source_labels'][r_source_label($source)] ?? 0) + 1;
    $bucket['attribution_methods'][$method] = ($bucket['attribution_methods'][$method] ?? 0) + 1;
}
function r_add_new_conversation_source(array &$bucket, string $source, string $method): void {
    $bucket['new_conversation_sources'][$source] = ($bucket['new_conversation_sources'][$source] ?? 0) + 1;
    $bucket['new_conversation_source_labels'][r_source_label($source)] = ($bucket['new_conversation_source_labels'][r_source_label($source)] ?? 0) + 1;
    $bucket['new_conversation_attribution_methods'][$method] = ($bucket['new_conversation_attribution_methods'][$method] ?? 0) + 1;
}

$tzName = trim((string)($_GET['tz'] ?? 'Asia/Riyadh'));
try { $tz = new DateTimeZone($tzName); } catch (Throwable) { $tzName = 'Asia/Riyadh'; $tz = new DateTimeZone($tzName); }
$today = new DateTimeImmutable('now', $tz);
$fromStr = trim((string)($_GET['from'] ?? $today->modify('-1 day')->format('Y-m-d')));
$toStr = trim((string)($_GET['to'] ?? $today->format('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromStr) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
    http_response_code(422); echo json_encode(['ok'=>false,'error'=>'invalid_date']); exit;
}
try {
    $from = new DateTimeImmutable($fromStr . ' 00:00:00', $tz);
    $to = new DateTimeImmutable($toStr . ' 23:59:59', $tz);
} catch (Throwable) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'invalid_date']); exit; }
if ($to < $from || $from->diff($to)->days > 31) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'date_range_too_large','max_days'=>31]); exit; }

$clientsRaw = r_json($clientsFile, []);
$connectionsRaw = r_json($connectionsFile, []);
$clientNames = [];
foreach ($clientsRaw as $id => $row) if (is_array($row)) $clientNames[(string)($row['id'] ?? $id)] = (string)($row['name'] ?? $id);
$connectionClient = [];
foreach ($connectionsRaw as $id => $row) if (is_array($row)) $connectionClient[(string)($row['id'] ?? $id)] = (string)($row['client_id'] ?? '');

$refSources = [];
if (is_file($attributionFile)) {
    $fh = fopen($attributionFile, 'rb');
    if ($fh) {
        while (($line = fgets($fh)) !== false) {
            $row = json_decode($line, true);
            if (!is_array($row)) continue;
            $ref = strtoupper(trim((string)($row['ref_token'] ?? '')));
            $source = strtolower(trim((string)($row['source'] ?? '')));
            if ($ref !== '' && in_array($source, ['google','meta','tiktok'], true)) $refSources[$ref] = $source;
        }
        fclose($fh);
    }
}

$days = [];
for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) $days[$d->format('Y-m-d')] = r_empty_day();
$clients = [];
$seenDailyContacts = [];
$seenConversations = [];
$seenRangeContacts = [];
$seenClientRangeContacts = [];

if (is_file($rawFile)) {
    $fh = fopen($rawFile, 'rb');
    if ($fh) {
        while (($line = fgets($fh)) !== false) {
            $row = json_decode($line, true);
            if (!is_array($row)) continue;
            $type = (string)($row['type'] ?? '');
            if (!in_array($type, ['whatsapp.inbound_message.received','whatsapp.outbound_message.sent'], true)) continue;
            $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
            $messageKey = $type === 'whatsapp.inbound_message.received' ? 'whatsappInboundMessage' : 'whatsappOutboundMessage';
            $message = is_array($payload[$messageKey] ?? null) ? $payload[$messageKey] : [];
            $when = r_date((string)($message['sendTime'] ?? $row['createTime'] ?? ''), $tz);
            if (!$when) continue;
            $connId = (string)($row['ycloud_connection_id'] ?? '');
            $clientId = $connectionClient[$connId] ?? '';
            if ($clientId === '') $clientId = 'unassigned';
            $waba = (string)($message['wabaId'] ?? '');
            $customer = $type === 'whatsapp.inbound_message.received' ? (string)($message['from'] ?? '') : (string)($message['to'] ?? '');
            $contactKey = hash('sha256', $waba . '|' . $customer);
            $convKey = $clientId . '|' . $contactKey;
            $isFirstInbound = false;
            if ($type === 'whatsapp.inbound_message.received' && !isset($seenConversations[$convKey])) {
                $seenConversations[$convKey] = true;
                $isFirstInbound = true;
            }
            if ($when < $from || $when > $to) continue;
            $day = $when->format('Y-m-d');
            $clientName = $clientNames[$clientId] ?? ($clientId === 'unassigned' ? 'Unassigned' : $clientId);
            if (!isset($clients[$clientId])) $clients[$clientId] = ['client_id'=>$clientId,'client_name'=>$clientName,'days'=>[],'totals'=>r_empty_day(),'unique_contacts_range'=>0];
            if (!isset($clients[$clientId]['days'][$day])) $clients[$clientId]['days'][$day] = r_empty_day();

            if ($type === 'whatsapp.inbound_message.received') {
                $days[$day]['inbound_messages']++;
                $clients[$clientId]['days'][$day]['inbound_messages']++;
                $clients[$clientId]['totals']['inbound_messages']++;
                $sourceInfo = r_source_from_message($message, $refSources);
                r_add_source($days[$day], $sourceInfo['source'], $sourceInfo['method']);
                r_add_source($clients[$clientId]['days'][$day], $sourceInfo['source'], $sourceInfo['method']);
                r_add_source($clients[$clientId]['totals'], $sourceInfo['source'], $sourceInfo['method']);

                $dailyKey = $day . '|' . $clientId . '|' . $contactKey;
                if (!isset($seenDailyContacts[$dailyKey])) {
                    $seenDailyContacts[$dailyKey] = true;
                    $days[$day]['unique_inbound_contacts']++;
                    $clients[$clientId]['days'][$day]['unique_inbound_contacts']++;
                    $clients[$clientId]['totals']['unique_inbound_contacts']++;
                }
                if (!isset($seenRangeContacts[$contactKey])) $seenRangeContacts[$contactKey] = true;
                $clientRangeKey = $clientId . '|' . $contactKey;
                if (!isset($seenClientRangeContacts[$clientRangeKey])) {
                    $seenClientRangeContacts[$clientRangeKey] = true;
                    $clients[$clientId]['unique_contacts_range']++;
                }
                if ($isFirstInbound) {
                    $days[$day]['new_conversations']++;
                    $clients[$clientId]['days'][$day]['new_conversations']++;
                    $clients[$clientId]['totals']['new_conversations']++;
                    r_add_new_conversation_source($days[$day], $sourceInfo['source'], $sourceInfo['method']);
                    r_add_new_conversation_source($clients[$clientId]['days'][$day], $sourceInfo['source'], $sourceInfo['method']);
                    r_add_new_conversation_source($clients[$clientId]['totals'], $sourceInfo['source'], $sourceInfo['method']);
                }
            } else {
                $days[$day]['outbound_messages']++;
                $clients[$clientId]['days'][$day]['outbound_messages']++;
                $clients[$clientId]['totals']['outbound_messages']++;
            }
        }
        fclose($fh);
    }
}

$totals = r_empty_day();
foreach ($days as $bucket) {
    foreach (['inbound_messages','outbound_messages','unique_inbound_contacts','new_conversations'] as $k) $totals[$k] += (int)$bucket[$k];
    foreach ($bucket['sources'] as $k=>$v) $totals['sources'][$k] = ($totals['sources'][$k] ?? 0) + $v;
    foreach ($bucket['source_labels'] as $k=>$v) $totals['source_labels'][$k] = ($totals['source_labels'][$k] ?? 0) + $v;
    foreach ($bucket['attribution_methods'] as $k=>$v) $totals['attribution_methods'][$k] = ($totals['attribution_methods'][$k] ?? 0) + $v;
    foreach ($bucket['new_conversation_sources'] as $k=>$v) $totals['new_conversation_sources'][$k] = ($totals['new_conversation_sources'][$k] ?? 0) + $v;
    foreach ($bucket['new_conversation_source_labels'] as $k=>$v) $totals['new_conversation_source_labels'][$k] = ($totals['new_conversation_source_labels'][$k] ?? 0) + $v;
    foreach ($bucket['new_conversation_attribution_methods'] as $k=>$v) $totals['new_conversation_attribution_methods'][$k] = ($totals['new_conversation_attribution_methods'][$k] ?? 0) + $v;
}

ksort($days);
usort($clients, fn($a,$b) => strcmp((string)$a['client_name'], (string)$b['client_name']));

echo json_encode([
    'ok'=>true,
    'version'=>'1.1',
    'timezone'=>$tzName,
    'from'=>$fromStr,
    'to'=>$toStr,
    'generated_at'=>(new DateTimeImmutable('now', $tz))->format(DateTimeInterface::ATOM),
    'privacy'=>'aggregated_only_no_message_content_or_customer_numbers',
    'days'=>$days,
    'totals'=>array_merge($totals, ['unique_contacts_range'=>count($seenRangeContacts)]),
    'clients'=>$clients,
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
