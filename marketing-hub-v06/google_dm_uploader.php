<?php
declare(strict_types=1);
// HORIZONS -> Google Ads offline conversions, direct via the Data Manager API (no Google Sheet).
// - One hub stage -> exactly one conversion action (google_offline_config.json).
// - Sends GCLID/GBRAID/WBRAID when present + SHA-256 phone (E.164 with +) for Enhanced Conversions for Leads.
// - Each (action, order id) is sent once; the order id matches the old sheet/Etizan ids so Google de-duplicates.
// - Per-client isolation: an error in one client never stops the others.
// Usage (CLI only): php google_dm_uploader.php [--dry-run] [--status] [--force]
if (PHP_SAPI !== 'cli' && !defined('GDM_EMBEDDED')) { http_response_code(404); exit; }

$gdmArgv = isset($argv) && is_array($argv) ? $argv : [];
$gdmDry = in_array('--dry-run', $gdmArgv, true);
$gdmForce = in_array('--force', $gdmArgv, true) || $gdmDry;
$gdmHome = dirname(__DIR__, 4);
$gdmPrivate = $gdmHome . '/.marketing/google_dm';
$gdmPublicStatusDir = __DIR__ . '/_private_status';
if (!is_dir($gdmPrivate)) { @mkdir($gdmPrivate, 0700, true); }
if (!is_dir($gdmPublicStatusDir)) { @mkdir($gdmPublicStatusDir, 0700, true); }
if (!is_file($gdmPublicStatusDir . '/.htaccess')) { @file_put_contents($gdmPublicStatusDir . '/.htaccess', "Require all denied\nDeny from all\n"); }

function gdm_json(string $f, array $d = []): array {
    if (!is_file($f)) return $d;
    $v = json_decode((string)@file_get_contents($f), true);
    return is_array($v) ? $v : $d;
}
function gdm_write(string $path, array $value): void {
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
    $data = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($data === false || file_put_contents($tmp, $data, LOCK_EX) === false) throw new RuntimeException('write_failed');
    @chmod($tmp, 0600);
    if (!rename($tmp, $path)) { @unlink($tmp); throw new RuntimeException('rename_failed'); }
}
function gdm_s(mixed $v): string { return is_scalar($v) ? trim((string)$v) : ''; }
function gdm_rank(string $tag): int {
    return match (strtolower(trim($tag))) { 'message_received' => 0, 'interested' => 1, 'qualified' => 2, 'purchased', 'converted' => 3, default => -1 };
}
function gdm_stage_rank(string $stage): int {
    return match ($stage) { 'message_started' => 0, 'interested' => 1, 'qualified' => 2, 'converted' => 3, default => 99 };
}
function gdm_valid_inbound(array $c): int {
    $n = 0; $seen = [];
    foreach ((array)($c['recent_messages'] ?? []) as $m) {
        if (!is_array($m) || !str_contains((string)($m['direction'] ?? ''), 'inbound')) continue;
        $t = strtolower(gdm_s($m['type'] ?? ''));
        if (in_array($t, ['', 'unsupported', 'reaction', 'system', 'unknown', 'revoke', 'revoked'], true)) continue;
        $id = gdm_s($m['wamid'] ?? $m['message_id'] ?? $m['source_event_id'] ?? '');
        if ($id !== '' && isset($seen[$id])) continue;
        if ($id !== '') $seen[$id] = true;
        $n++;
    }
    return max((int)($c['valid_inbound_count'] ?? 0), (int)($c['inbound_count'] ?? 0), $n);
}
function gdm_url_click(string $url): array {
    if ($url === '') return ['', ''];
    $q = (string)(parse_url($url, PHP_URL_QUERY) ?? '');
    if ($q === '') return ['', ''];
    parse_str($q, $p);
    foreach (['gclid', 'gbraid', 'wbraid'] as $k) { $v = gdm_s($p[$k] ?? ''); if ($v !== '') return [$k, $v]; }
    return ['', ''];
}
function gdm_click(array $c, array $clicks, array $pool): array {
    foreach (['gclid', 'gbraid', 'wbraid'] as $k) { $v = gdm_s($c['google_' . $k] ?? ''); if ($v !== '') return [$k, $v]; }
    $p = is_array($c['attribution_params'] ?? null) ? $c['attribution_params'] : [];
    foreach (['gclid', 'gbraid', 'wbraid'] as $k) { $v = gdm_s($p[$k] ?? ''); if ($v !== '') return [$k, $v]; }
    foreach (['ycloud_inbound_source_url', 'ycloud_contact_source_url', 'ycloud_message_source_url', 'chatlink_source_url', 'attribution_source_url', 'attribution_landing_url', 'source_url', 'landing_url', 'referrer'] as $uk) {
        $x = gdm_url_click(gdm_s($c[$uk] ?? '')); if ($x[1] !== '') return $x;
    }
    foreach (['horizons_wa_click_id', 'ycloud_chatlink_click_id'] as $ck) {
        $cl = is_array($clicks[gdm_s($c[$ck] ?? '')] ?? null) ? $clicks[gdm_s($c[$ck] ?? '')] : [];
        if (!$cl) continue;
        foreach (['gclid', 'gbraid', 'wbraid'] as $k) { $v = gdm_s($cl[$k] ?? ''); if ($v !== '') return [$k, $v]; }
        foreach (['source_url', 'landing_url', 'page_url', 'referrer', 'original_href'] as $uk) { $x = gdm_url_click(gdm_s($cl[$uk] ?? '')); if ($x[1] !== '') return $x; }
    }
    $native = is_array($pool['native_ids'] ?? null) ? $pool['native_ids'] : [];
    foreach (['gclid', 'gbraid', 'wbraid'] as $k) { $v = gdm_s($native[$k] ?? ''); if ($v !== '') return [$k, $v]; }
    return ['', ''];
}
function gdm_time(string $raw): ?DateTimeImmutable {
    if ($raw === '') return null;
    try { return (new DateTimeImmutable($raw))->setTimezone(new DateTimeZone('Asia/Riyadh')); } catch (Throwable) { return null; }
}
function gdm_stage_time(array $c, string $stage): ?DateTimeImmutable {
    $st = is_array($c['stage_times'] ?? null) ? $c['stage_times'] : [];
    $t = gdm_time(gdm_s($st[$stage] ?? ''));
    if ($t) return $t;
    if ($stage === 'message_started') {
        foreach (['first_customer_message_at', 'first_seen_at', 'created_at'] as $k) { $t = gdm_time(gdm_s($c[$k] ?? '')); if ($t) return $t; }
        return null;
    }
    foreach (['tagged_at', 'updated_at', 'last_message_at'] as $k) { $t = gdm_time(gdm_s($c[$k] ?? '')); if ($t) return $t; }
    return null;
}
function gdm_phone_hash(string $raw): string {
    $d = preg_replace('/\D+/', '', strtr($raw, ['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'])) ?? '';
    if (str_starts_with($d, '00')) $d = substr($d, 2);
    if (str_starts_with($d, '05') && strlen($d) === 10) $d = '966' . substr($d, 1);
    if (str_starts_with($d, '5') && strlen($d) === 9) $d = '966' . $d;
    if (strlen($d) < 8 || strlen($d) > 15) return '';
    return hash('sha256', '+' . $d);
}
function gdm_google(string $method, string $url, ?array $body = null): array {
    global $gdmHome;
    $key = trim((string)@file_get_contents($gdmHome . '/.horizons-google-mcp/mcp-path'));
    if ($key === '') return ['ok' => false, 'status' => 0, 'error' => 'google_mcp_key_missing'];
    $args = ['method' => $method, 'url' => $url];
    if ($body !== null) $args['body'] = $body;
    if ($method !== 'GET') $args['confirm_write'] = true;
    $request = ['jsonrpc' => '2.0', 'id' => bin2hex(random_bytes(6)), 'method' => 'tools/call', 'params' => ['name' => 'google_api_request', 'arguments' => $args]];
    $ch = curl_init('https://google-mcp.thehorizons.sa/mcp?k=' . rawurlencode($key));
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => json_encode($request, JSON_UNESCAPED_SLASHES)]);
    $raw = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $failed = curl_errno($ch) !== 0; curl_close($ch);
    if ($failed || $http !== 200) return ['ok' => false, 'status' => $http, 'error' => 'google_mcp_transport'];
    $rpc = json_decode((string)$raw, true);
    $value = json_decode((string)($rpc['result']['content'][0]['text'] ?? ''), true);
    return is_array($value) ? $value : ['ok' => false, 'status' => 0, 'error' => 'google_mcp_response'];
}
function gdm_reason(array $r): string {
    $e = $r['error'] ?? [];
    if (!is_array($e)) return (string)$e;
    return (string)($e['error']['details'][0]['reason'] ?? $e['error']['message'] ?? $e['error']['status'] ?? 'google_request_failed');
}

// ---- run ---------------------------------------------------------------
$gdmLock = fopen($gdmPrivate . '/uploader.lock', 'c');
if (!$gdmLock || !flock($gdmLock, LOCK_EX | LOCK_NB)) { echo json_encode(['ok' => true, 'status' => 'already_running']) . "\n"; return; }
$gdmLedgerPath = $gdmPrivate . '/ledger.json';
$gdmStatusPath = $gdmPrivate . '/status.json';
$gdmLedger = gdm_json($gdmLedgerPath, ['version' => 1, 'events' => [], 'requests' => [], 'retry_after' => [], 'last_run_at' => 0]);
$gdmRun = ['started_at' => gmdate('c'), 'dry_run' => $gdmDry, 'clients' => [], 'errors' => []];

// Throttle: full scan at most every 5 minutes (status polling still runs) unless forced.
$gdmScan = $gdmForce || (time() - (int)($gdmLedger['last_run_at'] ?? 0)) >= 300;
$gdmBudget = microtime(true) + 120;

try {
    $cfg = gdm_json(__DIR__ . '/google_offline_config.json');
    if (!$cfg || empty($cfg['clients'])) throw new RuntimeException('config_missing');
    $login = gdm_s($cfg['login_customer_id'] ?? '8480913009');
    $currency = gdm_s($cfg['currency'] ?? 'SAR');
    $maxAge = (int)($cfg['max_event_age_days'] ?? 85) * 86400;

    // 1) Poll processing status of earlier requests (real results, not just HTTP acceptance).
    if (!$gdmDry) {
        foreach ($gdmLedger['requests'] as $rid => &$req) {
            if (($req['state'] ?? '') !== 'PROCESSING' || ($req['next_check_at'] ?? 0) > time() || microtime(true) > $gdmBudget) continue;
            $res = gdm_google('GET', 'https://datamanager.googleapis.com/v1/requestStatus:retrieve?requestId=' . rawurlencode((string)$rid));
            $req['attempts'] = (int)($req['attempts'] ?? 0) + 1; $req['last_checked_at'] = time();
            if (!empty($res['ok'])) {
                foreach (($res['data']['requestStatusPerDestination'] ?? []) as $d) {
                    if (($d['destination']['productDestinationId'] ?? '') !== $req['action']) continue;
                    $st = (string)($d['requestStatus'] ?? 'PROCESSING');
                    $req['diagnostics'] = $d;
                    if (in_array($st, ['SUCCESS', 'FAILED', 'FAILURE', 'PARTIAL_SUCCESS'], true)) {
                        $errs = $d['errorInfo']['errorCounts'] ?? [];
                        $dupOnly = $errs && !array_diff(array_column($errs, 'reason'), ['PROCESSING_ERROR_REASON_DUPLICATE_GCLID', 'PROCESSING_ERROR_REASON_DUPLICATE_TRANSACTION_ID']);
                        $state = $st === 'SUCCESS' ? 'SUCCESS' : ($dupOnly ? 'ALREADY_PRESENT' : ($st === 'PARTIAL_SUCCESS' ? 'PARTIAL_SUCCESS' : 'FAILED'));
                        $req['state'] = $state; $req['completed_at'] = time();
                        foreach ($req['event_keys'] as $k) { if (isset($gdmLedger['events'][$k])) $gdmLedger['events'][$k]['state'] = $state; }
                    }
                }
            } else {
                $gdmRun['errors'][] = ['operation' => 'status', 'request_id' => $rid, 'reason' => gdm_reason($res)];
            }
            if (($req['state'] ?? '') === 'PROCESSING') $req['next_check_at'] = time() + min(3600, (int)(900 * pow(1.4, min(6, $req['attempts'])))) + random_int(0, 30);
        }
        unset($req);
        gdm_write($gdmLedgerPath, $gdmLedger);
    }

    if ($gdmScan) {
        $base = __DIR__ . '/data';
        $convs = gdm_json($base . '/conversations.json');
        $clicks = gdm_json($base . '/chatlink_clicks.json');
        $pools = [];
        foreach (array_keys($cfg['clients']) as $cid) {
            $pj = gdm_json($gdmHome . '/.marketing/lead_pools/' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $cid) . '/current.json');
            foreach ((array)($pj['records'] ?? []) as $r) { if (is_array($r) && gdm_s($r['conversation_id'] ?? '') !== '') $pools[$cid][gdm_s($r['conversation_id'])] = $r; }
        }

        // 2) Build the pending events per (client, stage).
        $pending = [];
        $arcCrmAudit=['stored_tags'=>[],'manual_verified_tags'=>[],'excluded_test_records'=>0];
        require_once __DIR__.'/arkan_crm_verified_core_20261101.php';
        $arcNumbers=gdm_json($base.'/whatsapp_numbers.json');$arcConnections=gdm_json($base.'/ycloud_connections.json');
        $arcLegacy=gdm_json($gdmHome.'/.marketing/arkan_google_offline/ledger.json');
        foreach ($convs as $convId => $c) {
            if (!is_array($c)) continue;
            $cid = gdm_s($c['client_id'] ?? '');
            if($cid===''){ $arcResolved=arc_client($c,$arcNumbers,$arcConnections); if($arcResolved==='cl_76c4e019afc588')$cid=$arcResolved; }
            $arcIsArkan=$cid==='cl_76c4e019afc588';
            if($arcIsArkan){if(arc_test_record($c)){$arcCrmAudit['excluded_test_records']++;continue;}$arcTag=arc_stage(gdm_s($c['current_tag']??'message_received'));$arcCrmAudit['stored_tags'][$arcTag]=($arcCrmAudit['stored_tags'][$arcTag]??0)+1;if(in_array($arcTag,['qualified','converted'],true)&&arc_verified_time($c,$arcTag)!=='')$arcCrmAudit['manual_verified_tags'][$arcTag]=($arcCrmAudit['manual_verified_tags'][$arcTag]??0)+1;}
            $cc = $cfg['clients'][$cid] ?? null;
            if (!$cc || ($cc['enabled']??true)===false) continue;
            if (gdm_valid_inbound($c) < 1) continue;
            $pool = $pools[$cid][(string)$convId] ?? [];
            [$clickType, $clickVal] = gdm_click($c, $clicks, $pool);
            $src = strtolower(gdm_s($c['traffic_source_key'] ?? ($pool['source'] ?? '')));
            if($arcIsArkan){
                if(empty($cc['consent_verified'])||$src==='tiktok'||!empty($c['tiktok_ttclid'])||!empty($c['ttclid']))continue;
                if(in_array(strtolower(gdm_s($c['current_tag']??'')),['spam','disqualified','unqualified','lost'],true))continue;
            } elseif ($src !== 'google') continue; // Other clients retain their existing source rules.
            $phoneHash = gdm_phone_hash(gdm_s($c['customer_number'] ?? ''));
            if ($clickVal === '' && $phoneHash === '') continue;
            $manual = gdm_s($c['tag_source'] ?? '') === 'manual' || !empty($c['manual_override']);
            // v3 (2026-10-17): the CRM tag is the only source of truth. No bump from inbound count or lead-pool stage.
            $rank = gdm_rank(gdm_s($c['current_tag'] ?? ''));
            if ($rank < 0) $rank = 0;
            foreach (['message_started', 'interested', 'qualified', 'converted'] as $stage) {
                if ($rank < gdm_stage_rank($stage)) continue;
                if($arcIsArkan){
                    $arcTag=arc_stage(gdm_s($c['current_tag']??''));
                    if($stage==='interested'&&(!$manual||$arcTag!==$stage))continue;
                    if(in_array($stage,['qualified','converted'],true)&&arc_verified_time($c,$stage)==='')continue;
                }
                $action = $cc['actions'][$stage] ?? null;
                if (!$action || empty($action['id'])) continue;
                $orderId = 'gcv_' . substr(hash('sha256', $cid . '|' . $convId . '|' . $stage), 0, 32);
                if($arcIsArkan)$orderId='arkg_'.substr(hash('sha256','hub:'.$convId.'|'.$stage),0,40);
                $key = $action['id'] . ':' . $orderId;
                if($arcIsArkan&&isset($arcLegacy['events'][$key]))continue; // Never replay events from the former sender.
                if (isset($gdmLedger['events'][$key]) && !in_array($gdmLedger['events'][$key]['state'] ?? '', ['FAILED', 'RETRY'], true)) continue;
                $at = $arcIsArkan&&in_array($stage,['qualified','converted'],true)?gdm_time(arc_verified_time($c,$stage)):gdm_stage_time($c, $stage);
                if($arcIsArkan&&$stage==='message_started')$at=gdm_time(gdm_s($c['first_customer_message_at']??'')); // Imported-history timestamps are not new contacts.
                if (!$at || $at->getTimestamp() > time() || $at->getTimestamp() < time() - $maxAge) continue;
                $ev = ['eventTimestamp' => $at->format('Y-m-d\TH:i:sP'), 'transactionId' => $orderId, 'eventSource' => 'WEB',
                    'conversionValue' => (float)($action['value'] ?? 0), 'currency' => $currency];
                if ($clickVal !== '') $ev['adIdentifiers'] = [$clickType => $clickVal];
                if ($phoneHash !== '') $ev['userData'] = ['userIdentifiers' => [['phoneNumber' => $phoneHash]]];
                if($arcIsArkan){
                    $ev['consent']=['adUserData'=>'CONSENT_GRANTED','adPersonalization'=>'CONSENT_DENIED'];
                    // One-per-click actions reject BRAID. Use consented first-party matching instead.
                    if(in_array($clickType,['gbraid','wbraid'],true)){if($phoneHash==='')continue;unset($ev['adIdentifiers']);}
                }
                if($arcIsArkan){$arcPending=0;foreach($pending[$cid]??[] as $arcRows)$arcPending+=count($arcRows);if($arcPending>=(int)($cc['max_new_events_per_run']??200))continue;}
                $pending[$cid][$stage][] = ['key' => $key, 'event' => $ev, 'has_click' => $clickVal !== ''];
            }
        }

        // 3) Send: one request per (client, stage) chunk -> that stage's conversion action only.
        foreach ($cfg['clients'] as $cid => $cc) {
            $sum = ['name' => $cc['name'] ?? $cid, 'customer_id' => $cc['customer_id'] ?? '', 'stages' => []];
            try {
                foreach (['message_started', 'interested', 'qualified', 'converted'] as $stage) {
                    $rows = $pending[$cid][$stage] ?? [];
                    $action = (string)($cc['actions'][$stage]['id'] ?? '');
                    $sum['stages'][$stage] = ['action_id' => $action, 'new' => count($rows), 'with_click' => count(array_filter($rows, fn($r) => $r['has_click'])), 'sent' => 0];
                    if (!$rows || $action === '') continue;
                    $rk = $cid . ':' . $stage;
                    if (!$gdmDry && ($gdmLedger['retry_after'][$rk] ?? 0) > time()) { $sum['stages'][$stage]['deferred'] = true; continue; }
                    foreach (array_chunk($rows, 200) as $chunk) {
                        if (microtime(true) > $gdmBudget) break;
                        $body = ['destinations' => [[
                                'operatingAccount' => ['accountType' => 'GOOGLE_ADS', 'accountId' => (string)$cc['customer_id']],
                                'loginAccount' => ['accountType' => 'GOOGLE_ADS', 'accountId' => $login],
                                'productDestinationId' => $action]],
                            'events' => array_column($chunk, 'event'), 'encoding' => 'HEX'];
                        $arcRequestDry=$gdmDry||($cid==='cl_76c4e019afc588'&&($cc['delivery_mode']??'VALIDATE_ONLY')!=='LIVE');
                        if ($arcRequestDry) $body['validateOnly'] = true;
                        $res = gdm_google('POST', 'https://datamanager.googleapis.com/v1/events:ingest', $body);
                        if (empty($res['ok']) || empty($res['data']['requestId'])) {
                            $why = gdm_reason($res);
                            $gdmRun['errors'][] = ['client' => $cc['name'] ?? $cid, 'stage' => $stage, 'http' => $res['status'] ?? 0, 'reason' => $why];
                            if (!$arcRequestDry) {
                                $code = (int)($res['status'] ?? 0);
                                $gdmLedger['retry_after'][$rk] = time() + (($code >= 400 && $code < 500 && $code !== 429) ? 3600 : 300);
                            }
                            break;
                        }
                        $rid = (string)$res['data']['requestId'];
                        if($arcRequestDry){$sum['stages'][$stage]['validated']=($sum['stages'][$stage]['validated']??0)+count($chunk);continue;}
                        $sum['stages'][$stage]['sent'] += count($chunk);
                        $keys = array_column($chunk, 'key');
                        foreach ($keys as $k) $gdmLedger['events'][$k] = ['state' => 'PROCESSING', 'request_id' => $rid, 'sent_at' => time()];
                        $gdmLedger['requests'][$rid] = ['client' => $cid, 'stage' => $stage, 'action' => $action, 'event_keys' => $keys,
                            'submitted_at' => time(), 'next_check_at' => time() + 900, 'attempts' => 0, 'state' => 'PROCESSING',
                            'warnings' => $res['data']['fieldWarnings'] ?? []];
                        unset($gdmLedger['retry_after'][$rk]);
                        gdm_write($gdmLedgerPath, $gdmLedger);
                    }
                }
            } catch (Throwable $e) {
                $gdmRun['errors'][] = ['client' => $cc['name'] ?? $cid, 'reason' => $e->getMessage()];
            }
            $gdmRun['clients'][$cid] = $sum;
        }
        if (!$gdmDry) { $gdmLedger['last_run_at'] = time(); }
    }

    // Prune finished requests older than 30 days to keep the ledger small (events stay for de-dup).
    foreach ($gdmLedger['requests'] as $rid => $req) { if (($req['state'] ?? '') !== 'PROCESSING' && ($req['completed_at'] ?? time()) < time() - 2592000) unset($gdmLedger['requests'][$rid]); }
    if (!$gdmDry) gdm_write($gdmLedgerPath, $gdmLedger);
} catch (Throwable $e) {
    $gdmRun['errors'][] = ['reason' => $e->getMessage()];
}

// Totals per conversion action (no personal data) for monitoring.
$totals = [];
foreach ($gdmLedger['events'] as $k => $ev) { $a = explode(':', (string)$k)[0]; $s = (string)($ev['state'] ?? '?'); $totals[$a][$s] = ($totals[$a][$s] ?? 0) + 1; }
$gdmStatus = ['ok' => empty($gdmRun['errors']), 'checked_at' => gmdate('c'), 'scanned' => $gdmScan, 'run' => $gdmRun, 'totals_by_action' => $totals,
    'open_requests' => count(array_filter($gdmLedger['requests'], fn($r) => ($r['state'] ?? '') === 'PROCESSING'))];
// ARKAN-only receipts persist between full scans; no customer identifiers are exposed.
try {
    $arcReceiptFile=$gdmPublicStatusDir.'/arkan-google-bridge-status.json';
    $arcReceipt=gdm_json($arcReceiptFile,[]);
    $arcReceipt['customer_id']='9201402498';$arcReceipt['checked_at']=gmdate('c');
    $arcRun=$gdmRun['clients']['cl_76c4e019afc588']??null;
    if(is_array($arcRun)){$arcReceipt['last_scan']=$arcRun;$arcReceipt['crm_audit']=$arcCrmAudit??[];}
    $arcReceipt['requests']=[];
    foreach($gdmLedger['requests'] as $arcRid=>$arcReq)if(($arcReq['client']??'')==='cl_76c4e019afc588')$arcReceipt['requests'][]=['request_id'=>$arcRid,'stage'=>$arcReq['stage'],'state'=>$arcReq['state'],'count'=>count($arcReq['event_keys']??[]),'submitted_at'=>$arcReq['submitted_at']??null,'diagnostics'=>$arcReq['diagnostics']??null];
    $arcReceipt['errors']=array_values(array_filter($gdmRun['errors'],fn($x)=>($x['client']??'')==='ARKAN'));
    gdm_write($arcReceiptFile,$arcReceipt);
} catch(Throwable) {}
try { gdm_write($gdmDry ? $gdmPrivate . '/dry_run_status.json' : $gdmStatusPath, $gdmStatus); gdm_write($gdmPublicStatusDir . ($gdmDry ? '/google_dm_dry_run.json' : '/google_dm_status.json'), $gdmStatus); } catch (Throwable) {}
flock($gdmLock, LOCK_UN); fclose($gdmLock);
echo json_encode($gdmStatus, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
