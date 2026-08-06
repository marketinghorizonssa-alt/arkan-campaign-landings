<?php
declare(strict_types=1);

require __DIR__ . '/app/config.php';
require __DIR__ . '/app/leads.php';

date_default_timezone_set('Asia/Riyadh');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');

function gadsReply(int $status, array $body = []): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    gadsReply(405, ['message' => 'method_not_allowed']);
}

$raw = file_get_contents('php://input');
$payload = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($payload)) {
    gadsReply(400, ['message' => 'invalid_json']);
}

$providedKey = trim((string)($payload['google_key'] ?? $payload['Google_key'] ?? ''));
$expectedHash = 'c2ddbd089d0bcd02e85881e3cd4ac0a22c12dc1b41e963dec1ffbaf3ef8f968c';
if ($providedKey === '' || !hash_equals($expectedHash, hash('sha256', $providedKey))) {
    gadsReply(403, ['message' => 'invalid_google_key']);
}

$sourceLeadId = cleanText($payload['lead_id'] ?? '', 255);
if ($sourceLeadId === '') {
    gadsReply(400, ['message' => 'missing_lead_id']);
}

$answers = [];
foreach (($payload['user_column_data'] ?? []) as $column) {
    if (!is_array($column)) continue;
    $columnId = strtoupper(cleanText($column['column_id'] ?? '', 80));
    if ($columnId !== '') $answers[$columnId] = cleanText($column['string_value'] ?? '', 500);
}

try {
    $pdo = leadDb();
    $existing = $pdo->prepare('SELECT lead_id FROM leads WHERE source_lead_id = :source_lead_id LIMIT 1');
    $existing->execute([':source_lead_id' => $sourceLeadId]);
    if ($existing->fetchColumn() !== false) gadsReply(200, []);

    try {
        $submittedAt = !empty($payload['lead_submit_time'])
            ? (new DateTimeImmutable((string)$payload['lead_submit_time']))->setTimezone(new DateTimeZone('Asia/Riyadh'))->format(DateTimeInterface::ATOM)
            : (new DateTimeImmutable('now', new DateTimeZone('Asia/Riyadh')))->format(DateTimeInterface::ATOM);
    } catch (Throwable) {
        $submittedAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Riyadh')))->format(DateTimeInterface::ATOM);
    }

    $safeId = preg_replace('/[^A-Za-z0-9_-]+/', '', $sourceLeadId) ?: substr(hash('sha256', $sourceLeadId), 0, 32);
    $fullName = $answers['FULL_NAME'] ?? trim(($answers['FIRST_NAME'] ?? '') . ' ' . ($answers['LAST_NAME'] ?? ''));
    $phone = $answers['PHONE_NUMBER'] ?? '';
    $isTest = filter_var($payload['is_test'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $fields = leadFields();
    $row = array_fill_keys($fields, '');
    $row['lead_id'] = 'ARK-GADS-' . substr($safeId, 0, 96);
    $row['submitted_at'] = $submittedAt;
    $row['platform_source'] = 'google_ads';
    $row['form_id'] = cleanText($payload['form_id'] ?? '', 100);
    $row['landing_page_id'] = 'GOOGLE_LEAD_FORM';
    $row['landing_url'] = 'https://arkan2030.com/حلول-التمويل-العقاري/';
    $row['full_name'] = $fullName;
    $row['phone'] = $phone;
    $row['normalized_phone'] = normalizePhone($phone);
    $row['city'] = $answers['CITY'] ?? '';
    $row['property_type'] = $answers['PROPERTY_TYPE'] ?? ($answers['PRODUCT'] ?? '');
    $row['eligibility_status'] = 'قيد المراجعة';
    $row['lead_status'] = $isTest ? 'اختبار Google' : 'جديد';
    $row['updated_at'] = $submittedAt;
    $row['privacy_consent'] = 'TRUE';
    $row['privacy_version'] = 'google-lead-form-v1';
    $row['consent_at'] = $submittedAt;
    $row['utm_source'] = 'google';
    $row['utm_medium'] = 'lead_form';
    $row['utm_campaign'] = 'arkan_search_16ag_v1';
    $row['gclid'] = cleanText($payload['gcl_id'] ?? '', 255);
    $row['campaign_id'] = cleanText($payload['campaign_id'] ?? '', 100);
    $row['campaign_name'] = 'ARKAN | Search | 16AG | KSA | Core+Test | v1';
    $row['ad_group_id'] = cleanText($payload['adgroup_id'] ?? '', 100);
    $row['ad_id'] = cleanText($payload['creative_id'] ?? '', 100);
    $row['network'] = 'google_lead_form';
    $row['first_landing_url'] = $row['landing_url'];
    $row['source_lead_id'] = $sourceLeadId;
    $row['duplicate_key'] = 'google_ads:' . $sourceLeadId;
    $row['processing_status'] = $isTest ? 'Test' : 'New';

    $quoted = array_map(static fn(string $field): string => '"' . $field . '"', $fields);
    $params = array_map(static fn(string $field): string => ':' . $field, $fields);
    $stmt = $pdo->prepare('INSERT INTO leads (' . implode(',', $quoted) . ') VALUES (' . implode(',', $params) . ')');
    $bound = [];
    foreach ($fields as $field) $bound[':' . $field] = $row[$field] ?? '';
    $stmt->execute($bound);

    gadsReply(200, []);
} catch (Throwable $error) {
    error_log('ARKAN_GADS_V2_ERROR ' . $error->getMessage());
    gadsReply(500, ['message' => 'temporary_storage_error']);
}
