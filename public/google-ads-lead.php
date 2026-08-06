<?php
declare(strict_types=1);

require __DIR__ . '/app/config.php';
require __DIR__ . '/app/leads.php';

date_default_timezone_set('Asia/Riyadh');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');

function googleAdsWebhookReply(int $status, array $payload = []): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function googleAdsWebhookFields(array $payload): array {
    $fields = [];
    foreach (($payload['user_column_data'] ?? []) as $column) {
        if (!is_array($column)) continue;
        $id = strtoupper(trim((string)($column['column_id'] ?? '')));
        if ($id === '') continue;
        $fields[$id] = cleanText($column['string_value'] ?? '', 500);
    }
    return $fields;
}

function googleAdsWebhookTime(mixed $value): string {
    try {
        if (is_string($value) && trim($value) !== '') {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Asia/Riyadh'))->format(DateTimeInterface::ATOM);
        }
    } catch (Throwable) {
    }
    return (new DateTimeImmutable('now', new DateTimeZone('Asia/Riyadh')))->format(DateTimeInterface::ATOM);
}

function handleGoogleAdsLeadWebhook(): never {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        googleAdsWebhookReply(405, ['message' => 'method_not_allowed']);
    }

    $raw = file_get_contents('php://input');
    if (!is_string($raw) || $raw === '' || strlen($raw) > 131072) {
        googleAdsWebhookReply(400, ['message' => 'invalid_payload']);
    }
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        googleAdsWebhookReply(400, ['message' => 'invalid_json']);
    }

    $secretPath = dirname(LEAD_DB_PATH) . '/arkan-google-ads-webhook-secret';
    $expectedSecret = is_file($secretPath) ? trim((string)file_get_contents($secretPath)) : '';
    $providedSecret = trim((string)($payload['google_key'] ?? $payload['Google_key'] ?? ''));
    if ($expectedSecret === '' || $providedSecret === '' || !hash_equals($expectedSecret, $providedSecret)) {
        googleAdsWebhookReply(403, ['message' => 'invalid_google_key']);
    }

    $sourceLeadId = cleanText($payload['lead_id'] ?? '', 255);
    if ($sourceLeadId === '') {
        googleAdsWebhookReply(400, ['message' => 'missing_lead_id']);
    }

    try {
        $pdo = leadDb();
        $existing = $pdo->prepare('SELECT lead_id FROM leads WHERE source_lead_id = :source_lead_id LIMIT 1');
        $existing->execute([':source_lead_id' => $sourceLeadId]);
        if ($existing->fetchColumn() !== false) {
            googleAdsWebhookReply(200, []);
        }

        $answers = googleAdsWebhookFields($payload);
        $firstName = $answers['FIRST_NAME'] ?? $answers['GIVEN_NAME'] ?? '';
        $lastName = $answers['LAST_NAME'] ?? $answers['FAMILY_NAME'] ?? '';
        $fullName = $answers['FULL_NAME'] ?? trim($firstName . ' ' . $lastName);
        $phone = $answers['PHONE_NUMBER'] ?? $answers['WORK_PHONE'] ?? '';
        $normalizedPhone = normalizePhone($phone);
        $city = $answers['CITY'] ?? $answers['PREFERRED_LOCATION'] ?? '';
        $propertyType = $answers['PROPERTY_TYPE'] ?? $answers['PRODUCT'] ?? '';
        $isTest = filter_var($payload['is_test'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $submittedAt = googleAdsWebhookTime($payload['lead_submit_time'] ?? null);
        $safeToken = preg_replace('/[^A-Za-z0-9_-]+/', '', $sourceLeadId) ?? '';
        if ($safeToken === '') $safeToken = substr(hash('sha256', $sourceLeadId), 0, 32);
        $leadId = 'ARK-GADS-' . substr($safeToken, 0, 96);
        $campaignId = cleanText($payload['campaign_id'] ?? '', 100);
        $formId = cleanText($payload['form_id'] ?? '', 100);
        $adGroupId = cleanText($payload['adgroup_id'] ?? '', 100);
        $creativeId = cleanText($payload['creative_id'] ?? '', 100);
        $gclid = cleanText($payload['gcl_id'] ?? '', 255);
        $leadSource = cleanText($payload['lead_source'] ?? 'LEAD_FORM', 80);
        $apiVersion = cleanText($payload['api_version'] ?? '', 40);

        $row = [
            'lead_id' => $leadId,
            'submitted_at' => $submittedAt,
            'platform_source' => 'google_ads',
            'form_id' => $formId,
            'landing_page_id' => 'GOOGLE_LEAD_FORM',
            'landing_url' => 'https://arkan2030.com/حلول-التمويل-العقاري/',
            'full_name' => $fullName,
            'phone' => $phone,
            'normalized_phone' => $normalizedPhone,
            'city' => $city,
            'property_type' => $propertyType,
            'employer_type' => '',
            'eligibility_status' => 'قيد المراجعة',
            'lead_status' => $isTest ? 'اختبار Google' : 'جديد',
            'owner' => '',
            'first_contact_at' => '',
            'updated_at' => $submittedAt,
            'notes' => trim('Google Ads lead form; source=' . $leadSource . '; api=' . $apiVersion),
            'privacy_consent' => 'TRUE',
            'privacy_version' => 'google-lead-form-v1',
            'consent_at' => $submittedAt,
            'utm_source' => 'google',
            'utm_medium' => 'lead_form',
            'utm_campaign' => 'arkan_search_16ag_v1',
            'utm_term' => '',
            'utm_content' => '',
            'gclid' => $gclid,
            'gbraid' => '',
            'wbraid' => '',
            'ttclid' => '',
            'fbclid' => '',
            'campaign_id' => $campaignId,
            'campaign_name' => 'ARKAN | Search | 16AG | KSA | Core+Test | v1',
            'ad_group_id' => $adGroupId,
            'ad_group_name' => '',
            'ad_id' => $creativeId,
            'keyword' => '',
            'match_type' => '',
            'device' => '',
            'network' => 'google_lead_form',
            'referrer_url' => '',
            'first_landing_url' => 'https://arkan2030.com/حلول-التمويل-العقاري/',
            'session_id' => '',
            'source_lead_id' => $sourceLeadId,
            'duplicate_key' => 'google_ads:' . $sourceLeadId,
            'processing_status' => $isTest ? 'Test' : 'New',
        ];

        $fields = leadFields();
        $quoted = array_map(static fn(string $field): string => '"' . $field . '"', $fields);
        $params = array_map(static fn(string $field): string => ':' . $field, $fields);
        $stmt = $pdo->prepare('INSERT INTO leads (' . implode(',', $quoted) . ') VALUES (' . implode(',', $params) . ')');
        $bound = [];
        foreach ($fields as $field) $bound[':' . $field] = $row[$field] ?? '';
        $stmt->execute($bound);

        googleAdsWebhookReply(200, []);
    } catch (Throwable $error) {
        error_log('ARKAN_GOOGLE_ADS_WEBHOOK_ERROR ' . $error->getMessage());
        googleAdsWebhookReply(500, ['message' => 'temporary_storage_error']);
    }
}

handleGoogleAdsLeadWebhook();
