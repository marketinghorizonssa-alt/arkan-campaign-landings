<?php
declare(strict_types=1);
/*
 * Horizons Stage Engine v3 (2026-10-17: auto-replies are not staff, client prefill templates, Persian/Urdu letters,
 *   job/CV & vendor seekers, strict 'qualified' (needs a serious next step) for law clients)
 * v2 (2026-10-11): + disqualified / spam negative events
 * Single source of lead-stage evaluation for every WhatsApp (YCloud) client.
 *  - Evaluates each conversation (ignores platform prefill templates, greetings, stickers, acks).
 *  - Upgrades current_tag only (never downgrades; manual tags always win;
 *    client-specific semantic workers (naqel/biyaat) stay authoritative for their clients).
 *  - On a NEW stage it appends ONE conversion event (conversation|stage is unique).
 *    flush_outbox.php is the only sender of these events to YCloud
 *    (YCloud -> TikTok Message Event Set / Meta / etc.).
 *  - Zero backfill beyond 7 days: older transitions are recorded as baseline, never sent.
 * Included by flush_outbox.php (cron). Not web-accessible on its own.
 */
if (PHP_SAPI !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) { http_response_code(404); exit; }

if (!function_exists('hse_run')) {

function hse_s(mixed $v): string { return is_scalar($v) ? trim((string)$v) : ''; }
function hse_lower(string $s): string { return mb_strtolower($s, 'UTF-8'); }
function hse_clean(string $s): string {
    $s = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]+/u', '', $s) ?? $s;
    return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
}
function hse_norm(string $s): string { $s = hse_lower(hse_clean($s)); return preg_replace('/[\p{P}\p{S}\s]+/u', '', $s) ?? $s; }
function hse_has(string $text, array $needles): bool {
    $t = hse_lower(hse_clean($text)); if ($t === '') return false;
    foreach ($needles as $n) { $n = hse_lower((string)$n); if ($n !== '' && mb_strpos($t, $n) !== false) return true; }
    return false;
}
function hse_valid_type(string $t): bool { return !in_array(strtolower(trim($t)), ['', 'unsupported', 'reaction', 'system', 'unknown', 'revoke', 'revoked', 'order', 'edit', 'ephemeral', 'deleted'], true); }

function hse_platform_template(string $text): bool {
    $t = hse_lower(hse_clean($text));
    if ($t === '') return false;
    if (str_contains($t, 'tiktok') && (str_contains($t, 'صادفت') || str_contains($t, 'came across') || str_contains($t, 'أود معرفة') || str_contains($t, 'find out more') || str_contains($t, 'معرّف tiktok') || str_contains($t, 'معرف tiktok'))) return true;
    if (str_contains($t, 'هل يمكنني الحصول على مزيد من المعلومات') || str_contains($t, 'can i get more info') || str_contains($t, 'i want to know more')) return true;
    return false;
}
function hse_greeting_only(string $text): bool {
    $n = hse_norm($text);
    if ($n === '') return true;
    $set = ['السلامعليكم','سلامعليكم','السلامعليكمورحمةالله','السلامعليكمورحمةاللهوبركاته','وعليكمالسلام','وعليكمالسلامورحمةالله','وعليكمالسلامورحمةاللهوبركاته','مرحبا','مرحبًا','اهلا','أهلا','هلا','هلا والله','هلاوالله','صباحالخير','مساءالخير','شكرا','شكرًا','شكراً','تمام','اوكي','أوكي','اوك','ok','okay','thanks','thankyou','ماشي','حسنا','حسنًا','طيب','نعم','اي','ايه','لا','hi','hello','hey','مع','.','؟','?'];
    foreach ($set as $g) if ($n === hse_norm($g)) return true;
    return mb_strlen($n, 'UTF-8') < 2;
}
function hse_intent(string $text, string $type): bool {
    if (in_array(strtolower($type), ['document', 'location', 'contacts'], true)) return true;
    if (strtolower($type) !== 'text' && trim(preg_replace('/^\[[a-z]+\]\s*/', '', $text) ?? '') === '') return false;
    return hse_has($text, [
        'السعر','سعر','بكم','كم سعر','كم التكلفة','كم الحساب','كم ','التكلفة','تكلفة','الرسوم','رسوم','العرض','عرض','خصم','باقة','الباقات',
        'موعد','احجز','حجز','متى','التوفر','متوفر','متاح','المدة','كم يوم','المتطلبات','الأوراق','المستندات','الشروط',
        'ابغى','أبغى','ابي ','أبي ','اريد','أريد','ودي','محتاج','احتاج','أحتاج','جاهز','نبدأ','ابدأ','أبدأ',
        'الدفع','تحويل','تقسيط','التقسيط','زيارة','موقعكم','العنوان','وين مكانكم','وين موقعكم','فرع',
        'استشارة','قضية','عقد','أتعاب','الاتعاب','دعوى','توكيل','محامي','نفقة','حضانة','تركة','تنفيذ',
        'استقدام','عاملة','عامله','شغالة','شغاله','خادمة','سائق','طباخ','طباخة','الجنسية','الفلبين','اثيوبيا','إثيوبيا','كينيا','الهند','بنغلاديش','سيرة','السير الذاتية','تأشيرة','تاشيرة',
        'تلميع','حماية','نانو','سيراميك','ppf','عازل','تظليل','شفط','صدمات','غسيل','سيارتي','السيارة',
        'تسجيل','التسجيل','الروضة','الحضانة','عمر','عمره','عمرها','الدوام','المنهج',
        'راتب','الراتب','تمويل','قرض','عقار','مديونية','ديون','تحصيل','بنك','سداد',
        'نقل','عفش','اثاث','أثاث','تغليف','فك وتركيب','من مدينة','الى مدينة','إلى مدينة',
        'price','cost','how much','quote','appointment','book','booking','available','availability','requirements','documents','payment','contract','visit','location','address'
    ]);
}
function hse_converted(string $text): bool {
    return hse_has($text, ['تم الدفع','دفعت','تم التحويل','حولت المبلغ','حوّلت','ارسلت العربون','أرسلت العربون','تم الحجز','حجزت الموعد','تم تأكيد الحجز','تم التعاقد','وقعت العقد','وقّعت العقد','تم توقيع العقد','تم إصدار العقد','تم اصدار العقد','تم الشراء','اشتريت','paid','payment done','payment completed','booking confirmed','contract signed','order confirmed']);
}
function hse_negative(string $text): bool {
    return hse_has($text, ['مش مهتم','غير مهتم','لست مهتم','ماني مهتم','مو مهتم','ما ابغى','ما أبغى','مابغى','لا اريد','لا أريد','وقف التواصل','لا تتواصل','لا تواصلوا','لا ترسل','بالغلط','غلط بالرقم','رقم خطأ','not interested','do not contact','stop messaging','unsubscribe','wrong number']);
}
function hse_vendor(string $text): bool {
    return hse_has($text, ['مسؤول مبيعات','مندوب مبيعات','مندوب شركة','نقدم لكم','شركتنا','عرض تعاون','طلب تعاون','نتشرف بالتعاون','كتالوج','كتلوج','بروفايل الشركة','we offer','our company','partnership',
        'نقدم خدمات','نقوم بتقديم خدمات','نقوم باعداد','نقوم بإعداد','مصمم جرافيك','مصممة جرافيك','خدمات التصميم',
        'طلب توظيف','طلب عمل','ابحث عن عمل','أبحث عن عمل','ابحث عن وظيفة','أبحث عن وظيفة','سيرتي الذاتية','السيرة الذاتية حقتي','وظيفة شاغرة','شاغر وظيفي','فرصة عمل','فرصة وظيفية','تقديم على وظيفة','التقديم على وظيفة','ابغى وظيفة','أبغى وظيفة','ابي وظيفة','أبي وظيفة','ابغى اشتغل','أبغى أشتغل','اشتغل عندكم','أشتغل عندكم','تتوظفون','توظفون','هل يوجد وظائف','في وظائف','فيه وظائف','job vacancy','looking for a job','looking for job','apply for job','my cv','my resume']);
}
/** Free-service seekers (enabled per client in hse_free_rule_clients()). */
function hse_free_rule_clients(): array { return ['cl_cbb797950cc8d4']; }
function hse_free_seeker(string $text): bool {
    return hse_has($text, ['استشارة مجانية','استشاره مجانيه','استشارة مجانيه','مجانا','مجاناً','مجانًا','ببلاش','بدون مقابل','بدون فلوس','بدون رسوم','ما عندي فلوس','ماعندي فلوس','ما معي فلوس','مامعي فلوس','ما عندي مبلغ','ما اقدر ادفع','ما أقدر أدفع','لا استطيع الدفع','لا أستطيع الدفع','على حسابكم','محامي مجاني','محامي متطوع','تطوعي','free consultation','for free','free of charge','pro bono']);
}
/** Non-target language: Indic/Sinhala/etc. scripts, or Urdu-only letters. */
function hse_foreign_script(string $text): bool {
    return (bool)preg_match('/[\x{0900}-\x{0DFF}\x{0E00}-\x{0E7F}\x{1000}-\x{109F}]|[\x{0679}\x{067E}\x{0686}\x{0688}\x{0691}\x{0698}\x{06A9}\x{06AF}\x{06BA}\x{06BE}\x{06C1}\x{06CC}\x{06D2}\x{06D3}]/u', $text);
}
/** Clients with strict lead rules (law): qualified needs a serious next step; CV/training requests are job seekers. */
function hse_strict_clients(): array { return ['cl_cbb797950cc8d4']; }
/** Client website prefill messages (not written by the customer). */
function hse_client_template(string $client, string $text): bool {
    $t = hse_lower(hse_clean($text)); if ($t === '') return false;
    if ($client === 'cl_cbb797950cc8d4') {
        if (str_contains($t, 'قمت بإرسال طلب تواصل قانوني') || str_contains($t, 'قمت بارسال طلب تواصل قانوني')) return true;
        if ((str_contains($t, 'لدى إتزان') || str_contains($t, 'لدى اتزان')) && (str_contains($t, 'أرغب') || str_contains($t, 'ارغب')) && mb_strlen($t, 'UTF-8') <= 90) return true;
    }
    return false;
}
/** Automatic business replies (welcome / away / "we will reply soon") are not a human staff reply. */
function hse_auto_outbound(string $text): bool {
    return hse_has($text, ['سوف نرد عليك في أسرع وقت','سنرد عليك في أقرب','سنقوم بالرد عليك','شكراً على تواصلكم','شكرا على تواصلكم','شكراً لتواصلك','شكرا لتواصلك','نشكر لك تواصلك','نشكر تواصلك','خارج أوقات العمل','خارج اوقات العمل','رسالة آلية','رد آلي','we will reply','we will get back','thank you for contacting','thanks for contacting']);
}
function hse_job_seeker_strict(string $text): bool {
    return hse_has($text, ['او وظيفه','أو وظيفة','او وظيفة','او وضيفه','أو وضيفه','وضيفه لخريج','وظيفه لخريج','وظيفة لخريج','وظيفة مستشار','طلب وظيفة','طلب وظيفه','وظائف شاغرة','لديكم وظائف','عندكم وظائف','التقديم على وظيفة','التقديم على التدريب','التقديم على تدريب','التقديم لديكم','تقديم على التدريب','التدريب لديكم','تدريب لديكم','يوجد لديكم تدريب','فرصة تدريب','فرصه تدريب','تدريب تعاوني','تدريب منتهي','تمهير','لخريج','حديث تخرج','حديثة تخرج','حديثي التخرج','أتقدم لسعادتكم','اتقدم لسعادتكم','أتقدم بطلب','اتقدم بطلب','سيرتي الذاتية','السيرة الذاتية','السيره الذاتيه','سيرة ذاتية',' cv','cv ','resume']);
}
/** Customer asks for a concrete next step (fees, appointment/visit, power of attorney, taking the case). */
function hse_customer_serious(string $text): bool {
    return hse_has($text, ['كم تاخذ','كم تأخذ','كم الاتعاب','كم الأتعاب','الاتعاب','الأتعاب','أتعاب','اتعاب','كم التكلفة','كم التكلفه','كم تكلف','كم تكلفني','كم السعر','كم سعر','بكم','كم الرسوم','عرض سعر',
        'موعد','احجز','أحجز','حجز موعد','زيارتكم','زيارة المكتب','زيارة مقركم','ازوركم','أزوركم','اجيكم','أجيكم','نجيكم','اجي لكم','أجي لكم','وين موقعكم','وين مكانكم','وين مقركم','وين مكتبكم','وين ف جده','وين ف جدة','وين في جدة','وين في جده','وين في الرياض','وين ف الرياض','موقعكم','لوكيشن','العنوان','دوامكم','اوقات الدوام','أوقات الدوام','نراكم',
        'توكيل','وكالة','اوكلكم','أوكلكم','اوكل محامي','أوكل محامي','ابي اوكل','ابغى اوكل','أبغى أوكل','تمسك القضية','تمسك قضيتي','تمسكون','تترافع','تترافعون','ترفعون','ترفع الدعوى','ترفع لي','تتولى','تتولون','لو اخليك','لو أخليك','عقد اتفاق','عقد الاتعاب','عقد الأتعاب','متى نبدأ','متى نبدا','نبدأ الإجراءات','نبدا الاجراءات']);
}
/** Human staff moved the lead to a next step (appointment, office location, fees, asked for case documents). */
function hse_staff_serious(string $text): bool {
    return hse_has($text, ['تم تأكيد موعد','تأكيد موعد','تم حجز موعد','موعدكم','حضوركم','تفضل بزيارتنا','تفضل بزيارتا','بزيارتنا','maps.app.goo.gl','goo.gl/maps','google.com/maps','زودنا بالعقد','زودنا بالمستندات','زودنا بصورة','ارسل المستندات','أرسل المستندات','ارسل لنا المستندات','ارسل الصك','أرسل الصك','الاتعاب','الأتعاب','عرض سعر','عقد الأتعاب','عقد الاتعاب']);
}
function hse_rank(string $s): int { return match (strtolower($s)) { 'message_received' => 0, 'interested' => 1, 'qualified' => 2, 'purchased', 'converted' => 3, default => -1 }; }

/** Evaluate one conversation. Returns [stage, reason, at(epoch), meaningful_count].
 *  stage ∈ message_received|interested|qualified|converted|disqualified|spam */
function hse_evaluate(array $c, array $clientTemplates, string $client = '', int $now = 0, array $outTemplates = []): array {
    $now = $now ?: time();
    $msgs = (array)($c['recent_messages'] ?? []);
    usort($msgs, fn($a, $b) => strcmp((string)($a['at'] ?? ''), (string)($b['at'] ?? '')));
    $meaningful = 0; $intentMsgs = 0; $hadStaff = false; $replyAfterStaff = 0; $mediaAfterStaff = false;
    $stage = 'message_received'; $reason = 'no_meaningful_customer_message'; $at = 0; $seen = [];
    $negativeAt = 0; $vendorAt = 0; $freeAt = 0; $foreignAt = 0; $foreignMsgs = 0; $arabicMsgs = 0; $lastInbound = 0; $convertedAt = 0; $lastOutAny = 0;
    $freeRule = in_array($client, hse_free_rule_clients(), true);
    $strict = in_array($client, hse_strict_clients(), true); $custSerious = false; $staffSeriousOpen = false; $staffSeriousReplied = false;
    foreach ($msgs as $m) {
        if (!is_array($m)) continue;
        $dir = (string)($m['direction'] ?? '');
        $ts = strtotime((string)($m['at'] ?? '')) ?: 0;
        if (str_contains($dir, 'outbound')) {
            if ($ts > $lastOutAny) $lastOutAny = $ts; // v3.2: any message we sent (human or auto)
            $ot = (string)($m['text'] ?? ''); $on = hse_norm($ot);
            if (hse_auto_outbound($ot) || ($on !== '' && isset($outTemplates[$on]))) continue; // auto-reply, not a human
            $hadStaff = true; if (hse_staff_serious($ot)) $staffSeriousOpen = true;
            continue;
        }
        if (!str_contains($dir, 'inbound')) continue;
        if ($ts > $lastInbound) $lastInbound = $ts;
        $type = (string)($m['type'] ?? '');
        if (!hse_valid_type($type)) continue;
        $id = (string)($m['wamid'] ?? $m['message_id'] ?? '');
        if ($id !== '') { if (isset($seen[$id])) continue; $seen[$id] = true; }
        $text = (string)($m['text'] ?? '');
        $n = hse_norm($text);
        if (hse_platform_template($text) || hse_client_template($client, $text) || ($n !== '' && isset($clientTemplates[$n]))) continue;
        if (strtolower($type) === 'sticker') continue;
        if (hse_foreign_script($text)) { $foreignMsgs++; $foreignAt = $ts; continue; }
        if (strtolower($type) === 'text' && hse_greeting_only($text)) continue;
        if (hse_negative($text)) { $negativeAt = $ts; continue; }
        if (hse_vendor($text) || ($strict && hse_job_seeker_strict($text))) { $vendorAt = $ts; continue; }
        if ($freeRule && hse_free_seeker($text)) { $freeAt = $ts; }
        if (strtolower($type) === 'text' && preg_match('/\p{Arabic}|[a-z]{3,}/iu', $text)) $arabicMsgs++;
        $meaningful++;
        $hasIntent = hse_intent($text, $type);
        if (hse_customer_serious($text)) $custSerious = true;
        if ($staffSeriousOpen) $staffSeriousReplied = true;
        if ($hasIntent) $intentMsgs++;
        if ($hadStaff) { $replyAfterStaff++; if (in_array(strtolower($type), ['document', 'image'], true)) $mediaAfterStaff = true; }
        if (!$convertedAt && hse_converted($text) && $hadStaff && $meaningful >= 2) $convertedAt = $ts;
        $seriousOk = !$strict || $custSerious || $staffSeriousReplied;
        if (hse_rank($stage) < 2 && $hadStaff && $replyAfterStaff >= 1 && $seriousOk && (($meaningful >= 3 && $intentMsgs >= 2 && $replyAfterStaff >= 2) || ($mediaAfterStaff && $meaningful >= 2))) {
            $stage = 'qualified'; $reason = $mediaAfterStaff ? 'documents_or_media_after_staff_reply' : 'three_meaningful_messages_two_way_with_intent'; if ($strict) $reason .= $custSerious ? '+customer_next_step' : '+staff_next_step'; $at = $ts;
        } elseif (hse_rank($stage) < 1 && ($meaningful >= 2 || ($meaningful >= 1 && $hasIntent))) {
            $stage = 'interested'; $reason = $meaningful >= 2 ? 'two_meaningful_customer_messages' : 'meaningful_message_with_service_intent'; $at = $ts;
        }
    }
    if ($convertedAt) return ['converted', 'explicit_completed_business_outcome', $convertedAt, $meaningful];
    // DisQualified: job/vendor, free-service seeker, or explicit not interested (never over qualified)
    if ($vendorAt && (hse_rank($stage) < 2 || ($strict && hse_rank($stage) < 3))) return ['disqualified', 'job_or_vendor_seeker', $vendorAt, $meaningful];
    if ($freeAt && hse_rank($stage) < 2) return ['disqualified', 'free_service_seeker', $freeAt, $meaningful];
    // Spam: non-target language, or only template/greeting/emoji and silent for 24h
    if ($foreignMsgs > 0 && $arabicMsgs === 0 && hse_rank($stage) < 2) return ['spam', 'non_target_language', $foreignAt, $meaningful];
    if ($stage === 'message_received' && $negativeAt > 0) return ['disqualified', 'explicit_not_interested', $negativeAt, $meaningful];
    // v3.2 (2026-10-20, AbdUllah): we replied and the customer sent nothing real for 1h after our last message -> spam (re-evaluated if they reply)
    if ($stage === 'message_received' && $meaningful === 0 && $lastInbound > 0 && $lastOutAny >= $lastInbound && ($now - $lastOutAny) >= 3600) return ['spam', 'no_reply_1h_after_our_message', $lastOutAny + 3600, $meaningful];
    if ($stage === 'message_received' && $meaningful === 0 && $lastInbound > 0 && ($now - $lastInbound) >= 86400) return ['spam', 'template_or_emoji_only_no_reply_24h', $lastInbound + 86400, $meaningful];
    return [$stage, $reason, $at, $meaningful];
}

/** Per-client automatic outbound texts: the same business message seen in >= 8 different conversations. */
function hse_out_templates(array $convs, array $clients): array {
    $cnt = [];
    foreach ($convs as $c) {
        if (!is_array($c)) continue; $cl = hse_s($c['client_id'] ?? ''); if (!isset($clients[$cl])) continue;
        $seenHere = [];
        foreach ((array)($c['recent_messages'] ?? []) as $m) {
            if (!is_array($m) || !str_contains((string)($m['direction'] ?? ''), 'outbound')) continue;
            $n = hse_norm((string)($m['text'] ?? '')); if (mb_strlen($n, 'UTF-8') < 25 || isset($seenHere[$n])) continue;
            $seenHere[$n] = true; $cnt[$cl][$n] = ($cnt[$cl][$n] ?? 0) + 1;
        }
    }
    $out = []; foreach ($cnt as $cl => $arr) foreach ($arr as $n => $k) if ($k >= 8) $out[$cl][$n] = true;
    return $out;
}

function hse_run(string $dir, array $opts = []): array {
    $dry = !empty($opts['dry_run']);
    $base = $dir . '/data';
    $secure = dirname($dir, 4) . '/.marketing';
    $stateDir = $secure . '/stage_engine';
    if (!$dry && !is_dir($stateDir)) @mkdir($stateDir, 0700, true);
    $stateFile = $stateDir . '/state.json';
    $state = is_file($stateFile) ? (json_decode((string)file_get_contents($stateFile), true) ?: []) : [];
    $firstRun = empty($state['initialized_at']);
    $sendWindow = 7 * 86400; $now = time();
    $convFile = $base . '/conversations.json'; $eventsFile = $base . '/conversion_events.jsonl';
    $convs = json_decode((string)@file_get_contents($convFile), true); if (!is_array($convs)) return ['ok' => false, 'error' => 'conversations_unreadable'];
    $connections = json_decode((string)@file_get_contents($base . '/ycloud_connections.json'), true) ?: [];
    $clients = []; foreach ($connections as $k => $cn) if (is_array($cn) && hse_s($cn['client_id'] ?? '') !== '') $clients[hse_s($cn['client_id'])] = true;
    $authoritative = ['naqel_semantic_v1', 'biyaat_semantic_v1'];
    // existing emitted stages (conversation|stage)
    $emitted = [];
    if (is_file($eventsFile) && ($fh = fopen($eventsFile, 'rb'))) { while (($l = fgets($fh)) !== false) { $r = json_decode($l, true); if (!is_array($r)) continue; $cid = hse_s($r['conversation_id'] ?? ''); $ev = strtolower(hse_s($r['event'] ?? '')); if ($ev === 'purchased') $ev = 'converted'; if ($cid !== '' && $ev !== '') $emitted[$cid . '|' . $ev] = true; } fclose($fh); }
    $baseline = is_array($state['baseline'] ?? null) ? $state['baseline'] : [];
    // auto-detect per-client website prefill templates (first inbound text seen in >=5 conversations)
    $firstText = [];
    foreach ($convs as $id => $c) {
        if (!is_array($c) || !isset($clients[hse_s($c['client_id'] ?? '')])) continue;
        foreach ((array)($c['recent_messages'] ?? []) as $m) { if (is_array($m) && str_contains((string)($m['direction'] ?? ''), 'inbound')) { $n = hse_norm((string)($m['text'] ?? '')); if (mb_strlen($n, 'UTF-8') >= 12) $firstText[hse_s($c['client_id'])][$n] = ($firstText[hse_s($c['client_id'])][$n] ?? 0) + 1; break; } }
    }
    $templates = []; foreach ($firstText as $cl => $arr) foreach ($arr as $n => $cnt) if ($cnt >= 5) $templates[$cl][$n] = true;
    $outTemplates = hse_out_templates($convs, $clients);

    $report = ['ok' => true, 'dry_run' => $dry, 'first_run' => $firstRun, 'evaluated' => 0, 'tag_upgrades' => 0, 'events_new' => 0, 'events_baseline' => 0, 'by_client' => [], 'templates' => array_map('count', $templates)];
    $tagChanges = []; $newEvents = [];
    foreach ($convs as $id => $c) {
        if (!is_array($c)) continue;
        $client = hse_s($c['client_id'] ?? ''); if (!isset($clients[$client])) continue;
        if (hse_s($c['customer_number'] ?? '') === '') continue;
        $report['evaluated']++; $staleAuth = false;
        $current = strtolower(hse_s($c['current_tag'] ?? '')); if ($current === 'purchased') $current = 'converted';
        $manual = strtolower(hse_s($c['tag_source'] ?? '')) === 'manual' || ($client === 'cl_76c4e019afc588' && !empty($c['manual_override']));
        $profile = hse_s($c['quality_profile'] ?? '');
        if ($manual || in_array($profile, $authoritative, true)) {
            $target = $current; $negType = ''; $reason = $manual ? 'manual_tag' : $profile; $at = strtotime(hse_s($c['tagged_at'] ?? '')) ?: (strtotime(hse_s($c['last_message_at'] ?? '')) ?: $now);
            // v3.1 (2026-10-18): manual / client-worker spam & disqualified tags also send their negative event.
            $ut0 = strtolower(hse_s($c['unqualified_type'] ?? '')); if ($current === 'unqualified' && in_array($ut0, ['spam', 'disqualified'], true)) $negType = $ut0;
            // Never push stale stages: manual tags count from the tagging time, client workers from the last message.
            $fresh0 = $manual ? (strtotime(hse_s($c['tagged_at'] ?? '')) ?: 0) : (strtotime(hse_s($c['last_message_at'] ?? '')) ?: 0);
            $staleAuth = $fresh0 <= 0 || ($now - $fresh0) > $sendWindow;
        } else {
            [$evalStage, $reason, $at] = hse_evaluate($c, $templates[$client] ?? [], $client, $now, $outTemplates[$client] ?? []);
            $firstAt = strtotime(hse_s($c['first_customer_message_at'] ?? '')) ?: (strtotime(hse_s($c['first_seen_at'] ?? '')) ?: 0);
            $window = $evalStage === 'converted' ? 30 * 86400 : 7 * 86400;
            if ($evalStage !== 'unqualified' && hse_rank($evalStage) >= 1 && $firstAt > 0 && $at > 0 && ($at - $firstAt) > $window) { $evalStage = $current !== '' ? $current : 'message_received'; $reason = 'outside_lead_window'; $at = 0; }
            $target = $current; $negType = '';
            if ($evalStage === 'disqualified' || $evalStage === 'spam') {
                $negType = $evalStage;
                $maxRank = $evalStage === 'disqualified' ? 1 : 0; // disqualified may override 'interested'; spam only new contacts
                if (hse_rank($current) <= $maxRank) $target = 'unqualified';
                else $negType = '';
            }
            elseif (hse_rank($evalStage) > hse_rank($current)) $target = $evalStage;
            if ($target !== $current) $tagChanges[$id] = ['stage' => $target, 'neg' => $negType ?? '', 'reason' => $reason, 'at' => $at ?: $now, 'old' => $current, 'message_at' => hse_s($c['last_message_at'] ?? '')];
            if ($at <= 0) $at = strtotime(hse_s($c['tagged_at'] ?? '')) ?: (strtotime(hse_s($c['last_message_at'] ?? '')) ?: $now);
        }
        // emit events for every positive stage reached (and unqualified)
        $chain = [];
        $r = hse_rank($target);
        if ($r >= 1) $chain[] = 'interested'; if ($r >= 2) $chain[] = 'qualified'; if ($r >= 3) $chain[] = 'converted';
        if ($negType !== '' && $target === 'unqualified' && !isset($emitted[$id . '|unqualified']) && !isset($emitted[$id . '|disqualified']) && !isset($emitted[$id . '|spam'])) {
            // only fresh conversations: last activity within the send window, else baseline silently
            $lastAct = strtotime(hse_s($c['last_message_at'] ?? '')) ?: 0;
            if ($lastAct > 0 && $now - $lastAct <= $sendWindow) $chain[] = $negType; else $baseline[$id . '|' . $negType] = gmdate('c');
        }
        foreach ($chain as $st) {
            $key = $id . '|' . $st;
            if (isset($emitted[$key]) || isset($baseline[$key])) continue;
            if ($staleAuth) { $baseline[$key] = gmdate('c'); continue; }
            $stTime = (int)$at;
            $stageTimes = (array)($c['stage_times'] ?? []);
            if ($st !== $target && !empty($stageTimes[$st])) $stTime = strtotime((string)$stageTimes[$st]) ?: $stTime;
            if ($firstRun && $now - $stTime > $sendWindow) { $baseline[$key] = gmdate('c'); $report['events_baseline']++; continue; }
            if (!$firstRun && $now - $stTime > $sendWindow) { $stTime = $now; }
            $newEvents[] = ['id' => 'mev_se_' . bin2hex(random_bytes(8)), 'event' => $st, 'conversation_id' => (string)$id, 'client_id' => $client,
                'number_id' => hse_s($c['number_id'] ?? ''), 'ycloud_connection_id' => hse_s($c['ycloud_connection_id'] ?? ''), 'waba_id' => hse_s($c['waba_id'] ?? ''),
                'business_number' => hse_s($c['business_number'] ?? ''), 'customer_number' => hse_s($c['customer_number'] ?? ''), 'contact_name' => hse_s($c['contact_name'] ?? ''),
                'traffic_source_key' => hse_s($c['traffic_source_key'] ?? ''), 'source' => 'stage_engine_v3', 'stage_reason' => $reason, 'created_at' => gmdate('c', $stTime)];
            $emitted[$key] = true;
            $report['by_client'][$client][$st] = ($report['by_client'][$client][$st] ?? 0) + 1;
        }
    }
    $report['tag_upgrades'] = count($tagChanges); $report['events_new'] = count($newEvents);
    if ($dry) { $report['sample_changes'] = array_slice($tagChanges, 0, 15, true); $report['tag_changes_by'] = []; foreach ($tagChanges as $x) { $k = $x['old'] . '->' . $x['stage']; $report['tag_changes_by'][$k] = ($report['tag_changes_by'][$k] ?? 0) + 1; } return $report; }

    // write tag upgrades under the shared CRM lock, only if the conversation did not change meanwhile
    if ($tagChanges) {
        require_once $dir . '/crm_lock.php'; crm_data_lock();
        try {
            $fresh = json_decode((string)@file_get_contents($convFile), true);
            if (is_array($fresh)) {
                $applied = 0;
                foreach ($tagChanges as $id => $x) {
                    if (!isset($fresh[$id]) || !is_array($fresh[$id])) continue;
                    $f = $fresh[$id];
                    if (strtolower(hse_s($f['tag_source'] ?? '')) === 'manual' || (hse_s($f['client_id'] ?? '') === 'cl_76c4e019afc588' && !empty($f['manual_override'])) || in_array(hse_s($f['quality_profile'] ?? ''), $authoritative, true)) continue;
                    $fc = strtolower(hse_s($f['current_tag'] ?? '')); if ($fc === 'purchased') $fc = 'converted';
                    if ($x['stage'] !== 'unqualified' && hse_rank($x['stage']) <= hse_rank($fc)) continue;
                    if ($x['stage'] === 'unqualified' && hse_rank($fc) > ((($x['neg'] ?? '') === 'disqualified') ? 1 : 0)) continue;
                    $f['current_tag'] = $x['stage']; $f['tag_source'] = 'automation'; $f['tagged_at'] = gmdate('c', (int)$x['at']);
                    $f['auto_label_reason'] = $x['reason']; $f['stage_engine'] = 'v3'; if (($x['neg'] ?? '') !== '') $f['unqualified_type'] = $x['neg'];
                    $st = is_array($f['stage_times'] ?? null) ? $f['stage_times'] : []; if (empty($st[$x['stage']])) $st[$x['stage']] = gmdate('c', (int)$x['at']); $f['stage_times'] = $st;
                    $h = is_array($f['label_history'] ?? null) ? $f['label_history'] : []; $h[] = ['tag' => $x['stage'], 'type' => $x['neg'] ?? '', 'source' => 'stage_engine_v1', 'reason' => $x['reason'], 'at' => gmdate('c')]; if (count($h) > 20) $h = array_slice($h, -20); $f['label_history'] = $h;
                    $fresh[$id] = $f; $applied++;
                }
                if ($applied > 0) { $tmp = $convFile . '.tmp.se'; if (@file_put_contents($tmp, json_encode($fresh, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) !== false) @rename($tmp, $convFile); }
                $report['tag_upgrades_applied'] = $applied;
            }
        } finally { crm_data_unlock(); }
    }
    if ($newEvents) {
        $fh = @fopen($eventsFile, 'ab');
        if ($fh) { @flock($fh, LOCK_EX); foreach ($newEvents as $e) fwrite($fh, json_encode($e, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"); @flock($fh, LOCK_UN); fclose($fh); }
    }
    if ($firstRun) $state['initialized_at'] = gmdate('c');
    $state['baseline'] = $baseline; $state['last_run'] = gmdate('c'); $state['last_report'] = ['evaluated' => $report['evaluated'], 'tag_upgrades' => $report['tag_upgrades'], 'events_new' => $report['events_new']];
    $tmp = $stateFile . '.tmp'; if (@file_put_contents($tmp, json_encode($state, JSON_UNESCAPED_SLASHES), LOCK_EX) !== false) { @chmod($tmp, 0600); @rename($tmp, $stateFile); }
    return $report;
}

}
