<?php
declare(strict_types=1);

function jsonResponse(array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function readJsonBody(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 65536) jsonResponse(['ok' => false, 'error' => 'invalid_payload'], 400);
    $data = json_decode($raw, true);
    if (!is_array($data)) jsonResponse(['ok' => false, 'error' => 'invalid_json'], 400);
    return $data;
}
function cleanText(mixed $value, int $max = 255): string {
    $text = trim((string)$value);
    $text = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $text) ?? '';
    return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
}
function normalizeSaudiPhone(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($digits, '00966')) $digits = substr($digits, 2);
    if (str_starts_with($digits, '966') && strlen($digits) === 12) return $digits;
    if (str_starts_with($digits, '05') && strlen($digits) === 10) return '966' . substr($digits, 1);
    if (str_starts_with($digits, '5') && strlen($digits) === 9) return '966' . $digits;
    return '';
}
function leadHeaders(): array {
    return ['Lead ID','تاريخ ووقت الإرسال','مصدر المنصة','اسم/ID النموذج','Landing Page ID','Landing URL','الاسم','رقم الجوال','رقم الجوال الموحّد','المدينة','نوع العقار','جهة العمل','حالة الأهلية','حالة الـLead','المسؤول','وقت أول تواصل','آخر تحديث','ملاحظات','موافقة الخصوصية','نسخة سياسة الخصوصية','وقت الموافقة','utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid','gbraid','wbraid','ttclid','fbclid','Campaign ID','Campaign Name','Ad Group ID','Ad Group Name','Ad ID','Keyword','Match Type','Device','Network','Referrer URL','First Landing URL','Session ID','Source Lead ID','Duplicate Key','Processing Status'];
}
function leadFields(): array {
    return ['lead_id','submitted_at','platform_source','form_id','landing_page_id','landing_url','full_name','phone','normalized_phone','city','property_type','employer_type','eligibility_status','lead_status','owner','first_contact_at','updated_at','notes','privacy_consent','privacy_version','consent_at','utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid','gbraid','wbraid','ttclid','fbclid','campaign_id','campaign_name','ad_group_id','ad_group_name','ad_id','keyword','match_type','device','network','referrer_url','first_landing_url','session_id','source_lead_id','duplicate_key','processing_status'];
}
function leadDb(): PDO {
    $dir = dirname(LEAD_DB_PATH);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('storage_unavailable');
    $pdo = new PDO('sqlite:' . LEAD_DB_PATH, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000;');
    $columns = [];
    foreach (leadFields() as $field) $columns[] = '"' . $field . '" TEXT NOT NULL DEFAULT \'\'';
    $pdo->exec('CREATE TABLE IF NOT EXISTS leads (id INTEGER PRIMARY KEY AUTOINCREMENT,' . implode(',', $columns) . ')');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_leads_phone_time ON leads(normalized_phone, submitted_at)');
    return $pdo;
}
function handleLeadSubmit(): never {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') jsonResponse(['ok' => false, 'error' => 'method_not_allowed'], 405);
    $data = readJsonBody();
    $propertyAliases = ['ready_unit'=>'وحدة جاهزة','self_build'=>'بناء ذاتي','mortgage'=>'رهن عقاري'];
    $employerAliases = ['civil_gov'=>'حكومي مدني','military_gov'=>'حكومي عسكري','semi_gov'=>'شبه حكومي','private'=>'قطاع خاص','retired'=>'متقاعد'];
    $allowedProperties = array_values($propertyAliases);
    $allowedEmployers = array_values($employerAliases);
    $name = cleanText($data['full_name'] ?? '', 120);
    $phone = cleanText($data['phone'] ?? '', 32);
    $normalizedPhone = normalizeSaudiPhone($phone);
    $city = cleanText($data['city'] ?? '', 80);
    $propertyInput = cleanText($data['property_type'] ?? '', 80);
    $employerInput = cleanText($data['employer_type'] ?? '', 80);
    $property = $propertyAliases[$propertyInput] ?? $propertyInput;
    $employer = $employerAliases[$employerInput] ?? $employerInput;
    $consent = in_array($data['privacy_consent'] ?? null, [1, '1', true, 'true', 'on'], true);
    $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    if ($nameLength < 2 || $normalizedPhone === '' || $city === '' || !in_array($property, $allowedProperties, true) || !in_array($employer, $allowedEmployers, true) || !$consent) {
        jsonResponse(['ok' => false, 'error' => 'validation_failed'], 422);
    }
    $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Riyadh')))->format(DateTimeInterface::ATOM);
    $leadId = 'ARK-WEB-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $duplicateKey = hash('sha256', $normalizedPhone . '|' . date('Y-m-d'));
    $pdo = leadDb();
    $recent = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE normalized_phone = :phone AND submitted_at >= datetime('now','-24 hours')");
    $recent->execute([':phone' => $normalizedPhone]);
    $isDuplicate = (int)$recent->fetchColumn() > 0;
    $row = [
        'lead_id' => $leadId,
        'submitted_at' => $now,
        'platform_source' => cleanText($data['platform_source'] ?? ($data['utm_source'] ?? 'website'), 80) ?: 'website',
        'form_id' => cleanText($data['form_id'] ?? 'arkan_landing_form_v1', 100),
        'landing_page_id' => cleanText($data['landing_page_id'] ?? '', 20),
        'landing_url' => cleanText($data['page_url'] ?? '', 500),
        'full_name' => $name,
        'phone' => $phone,
        'normalized_phone' => $normalizedPhone,
        'city' => $city,
        'property_type' => $property,
        'employer_type' => $employer,
        'eligibility_status' => 'قيد المراجعة',
        'lead_status' => $isDuplicate ? 'مكرر محتمل' : 'جديد',
        'owner' => '',
        'first_contact_at' => '',
        'updated_at' => $now,
        'notes' => '',
        'privacy_consent' => 'TRUE',
        'privacy_version' => cleanText($data['privacy_version'] ?? PRIVACY_VERSION, 80),
        'consent_at' => cleanText($data['consent_at'] ?? $now, 80),
        'utm_source' => cleanText($data['utm_source'] ?? '', 160),
        'utm_medium' => cleanText($data['utm_medium'] ?? '', 160),
        'utm_campaign' => cleanText($data['utm_campaign'] ?? '', 200),
        'utm_term' => cleanText($data['utm_term'] ?? '', 200),
        'utm_content' => cleanText($data['utm_content'] ?? '', 200),
        'gclid' => cleanText($data['gclid'] ?? '', 255),
        'gbraid' => cleanText($data['gbraid'] ?? '', 255),
        'wbraid' => cleanText($data['wbraid'] ?? '', 255),
        'ttclid' => cleanText($data['ttclid'] ?? '', 255),
        'fbclid' => cleanText($data['fbclid'] ?? '', 255),
        'campaign_id' => cleanText($data['campaign_id'] ?? '', 100),
        'campaign_name' => cleanText($data['campaign_name'] ?? '', 200),
        'ad_group_id' => cleanText($data['ad_group_id'] ?? '', 100),
        'ad_group_name' => cleanText($data['ad_group_name'] ?? '', 200),
        'ad_id' => cleanText($data['ad_id'] ?? '', 100),
        'keyword' => cleanText($data['keyword'] ?? '', 200),
        'match_type' => cleanText($data['match_type'] ?? '', 60),
        'device' => cleanText($data['device'] ?? '', 60),
        'network' => cleanText($data['network'] ?? '', 60),
        'referrer_url' => cleanText($data['referrer'] ?? '', 500),
        'first_landing_url' => cleanText($data['first_landing_url'] ?? ($data['page_url'] ?? ''), 500),
        'session_id' => cleanText($data['session_id'] ?? '', 120),
        'source_lead_id' => $leadId,
        'duplicate_key' => $duplicateKey,
        'processing_status' => 'New',
    ];
    $fields = leadFields();
    $quoted = array_map(fn(string $f): string => '"' . $f . '"', $fields);
    $params = array_map(fn(string $f): string => ':' . $f, $fields);
    $stmt = $pdo->prepare('INSERT INTO leads (' . implode(',', $quoted) . ') VALUES (' . implode(',', $params) . ')');
    $bound = []; foreach ($fields as $field) $bound[':' . $field] = $row[$field];
    $stmt->execute($bound);
    jsonResponse(['ok' => true, 'lead_token' => $leadId, 'duplicate' => $isDuplicate]);
}
function handleLeadFeed(): never {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { http_response_code(405); exit; }
    $provided = (string)($_GET['token'] ?? '');
    $stored = is_file(LEAD_FEED_TOKEN_PATH) ? trim((string)file_get_contents(LEAD_FEED_TOKEN_PATH)) : '';
    if ($stored === '' || !hash_equals($stored, $provided)) { http_response_code(404); exit; }
    header('Content-Type: text/csv; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, leadHeaders());
    $pdo = leadDb();
    $fields = leadFields();
    $query = 'SELECT ' . implode(',', array_map(fn(string $f): string => '"' . $f . '"', $fields)) . ' FROM leads ORDER BY id ASC';
    foreach ($pdo->query($query) as $row) {
        $values = []; foreach ($fields as $field) $values[] = $row[$field] ?? '';
        fputcsv($out, $values);
    }
    fclose($out);
    exit;
}
