<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
    exit;
}

$base = __DIR__ . '/data';
$rawFile = $base . '/raw_events.jsonl';
$convFile = $base . '/conversations.json';
$connectionsFile = $base . '/ycloud_connections.json';
$clientsFile = $base . '/clients.json';

function lq_json(string $file, array $default=[]): array {
    if (!is_file($file)) return $default;
    $v = json_decode((string)@file_get_contents($file), true);
    return is_array($v) ? $v : $default;
}
function lq_str(mixed $v): string { return is_scalar($v) ? trim((string)$v) : ''; }
function lq_pick(array $a, array $keys): string {
    foreach ($keys as $k) if (array_key_exists($k, $a) && lq_str($a[$k]) !== '') return lq_str($a[$k]);
    return '';
}
function lq_phone(string $v): string { return preg_replace('/\D+/', '', $v) ?? ''; }
function lq_conv_id(string $waba, string $customer): string { return substr(hash('sha256', $waba . '|' . $customer), 0, 24); }
function lq_mask_phone(string $v): string {
    $d = lq_phone($v); if ($d === '') return '';
    return '***' . substr($d, -4);
}
function lq_ts(string $v): ?int {
    if ($v === '') return null; $t = strtotime($v); return $t === false ? null : $t;
}
function lq_clean_text(string $s, int $limit=180): string {
    $s = preg_replace('/[\p{C}\p{M}]+/u', '', $s) ?? $s;
    $s = preg_replace('/\s+/u', ' ', trim($s)) ?? trim($s);
    if (function_exists('mb_substr')) return mb_substr($s, 0, $limit, 'UTF-8');
    return substr($s, 0, $limit);
}
function lq_message_text(array $m): string {
    $type = lq_str($m['type'] ?? '');
    if ($type === 'text') return lq_str($m['text']['body'] ?? '');
    foreach (['image','video','document','audio'] as $k) {
        if ($type === $k && isset($m[$k]) && is_array($m[$k])) {
            $caption = lq_str($m[$k]['caption'] ?? '');
            if ($caption !== '') return $caption;
        }
    }
    return '';
}
function lq_referral(array $m): array {
    $r = isset($m['referral']) && is_array($m['referral']) ? $m['referral'] : [];
    return [
        'source_url'=>lq_pick($r, ['sourceUrl','source_url']),
        'source_id'=>lq_pick($r, ['sourceId','source_id']),
        'source_type'=>lq_pick($r, ['sourceType','source_type']),
        'headline'=>lq_pick($r, ['headline']),
        'body'=>lq_pick($r, ['body']),
        'media_type'=>lq_pick($r, ['mediaType','media_type']),
        'image_url'=>lq_pick($r, ['imageUrl','image_url']),
        'ctwa_clid'=>lq_pick($r, ['ctwaClid','ctwa_clid']),
    ];
}
function lq_url_params(string $url): array {
    if ($url === '') return [];
    $query = parse_url($url, PHP_URL_QUERY);
    if (!is_string($query) || $query === '') {
        $p = strpos($url, '?'); if ($p !== false) $query = substr($url, $p + 1);
    }
    if (!is_string($query) || $query === '') return [];
    $out=[]; parse_str($query, $out); return is_array($out) ? $out : [];
}
function lq_extract_ids(string $haystack, array $params=[]): array {
    $keys=['gclid','wbraid','gbraid','ttclid','fbclid','ctwa_clid','ctwaClid','utm_source','utm_medium','utm_campaign','utm_content','utm_term'];
    $out=[];
    foreach ($keys as $k) {
        if (isset($params[$k]) && lq_str($params[$k]) !== '') $out[$k] = lq_str($params[$k]);
    }
    foreach (['gclid','wbraid','gbraid','ttclid','fbclid','ctwa_clid'] as $k) {
        if (!isset($out[$k]) && preg_match('/(?:[?&\s]|^)' . preg_quote($k,'/') . '=([^&\s]+)/i', $haystack, $m)) $out[$k] = urldecode($m[1]);
    }
    if (isset($out['ctwaClid']) && !isset($out['ctwa_clid'])) $out['ctwa_clid']=$out['ctwaClid'];
    unset($out['ctwaClid']);
    return $out;
}
function lq_ascii_compact(string $s): string { return strtolower(preg_replace('/[^a-z0-9]+/i', '', $s) ?? ''); }
function lq_source(array $lead): array {
    $r = $lead['referral'] ?? [];
    $url = strtolower(lq_str($r['source_url'] ?? ''));
    $head = strtolower(lq_clean_text(lq_str(($r['headline'] ?? '') . ' ' . ($r['body'] ?? '')), 500));
    $msg = lq_str($lead['first_message_raw'] ?? '');
    $msgClean = strtolower(lq_clean_text($msg, 500));
    $ascii = lq_ascii_compact($msg);
    $ids = $lead['tracking'] ?? [];
    $utm = strtolower(lq_str($ids['utm_source'] ?? ''));

    if (lq_str($ids['ttclid'] ?? '') !== '') return ['source'=>'tiktok','confidence'=>'high','reason'=>'ttclid'];
    if (lq_str($ids['gclid'] ?? '') !== '' || lq_str($ids['wbraid'] ?? '') !== '' || lq_str($ids['gbraid'] ?? '') !== '') return ['source'=>'google','confidence'=>'high','reason'=>'google_click_id'];
    if (lq_str($ids['fbclid'] ?? '') !== '' || lq_str($ids['ctwa_clid'] ?? '') !== '' || lq_str($r['ctwa_clid'] ?? '') !== '') return ['source'=>'meta','confidence'=>'high','reason'=>'meta_click_id'];
    if ($utm !== '') {
        if (str_contains($utm,'tiktok')) return ['source'=>'tiktok','confidence'=>'high','reason'=>'utm_source'];
        if (str_contains($utm,'google') || str_contains($utm,'adwords')) return ['source'=>'google','confidence'=>'high','reason'=>'utm_source'];
        if (str_contains($utm,'facebook') || str_contains($utm,'instagram') || str_contains($utm,'meta')) return ['source'=>'meta','confidence'=>'high','reason'=>'utm_source'];
    }
    $urlHay = $url . ' ' . $head;
    if (str_contains($urlHay,'tiktok')) return ['source'=>'tiktok','confidence'=>'high','reason'=>'referral'];
    if (str_contains($urlHay,'google') || str_contains($urlHay,'adwords')) return ['source'=>'google','confidence'=>'high','reason'=>'referral'];
    if (str_contains($urlHay,'facebook') || str_contains($urlHay,'instagram') || str_contains($urlHay,'meta.com') || str_contains($urlHay,'fb.com')) return ['source'=>'meta','confidence'=>'high','reason'=>'referral'];
    if (str_contains($ascii,'tiktok') || str_contains($msgClean,'صادفت إعلانك على tiktok') || str_contains($msgClean,'صادفت اعلانك على tiktok')) return ['source'=>'tiktok','confidence'=>'high','reason'=>'first_message_template'];
    if (str_contains($ascii,'googleads') || str_contains($ascii,'googlead') || str_contains($msgClean,'إعلانك على google') || str_contains($msgClean,'اعلانك على google') || str_contains($msgClean,'جوجل')) return ['source'=>'google','confidence'=>'medium','reason'=>'first_message_text'];
    if (str_contains($ascii,'instagram') || str_contains($ascii,'facebook') || str_contains($msgClean,'انستجرام') || str_contains($msgClean,'انستغرام') || str_contains($msgClean,'فيسبوك')) return ['source'=>'meta','confidence'=>'medium','reason'=>'first_message_text'];
    return ['source'=>'unknown','confidence'=>'low','reason'=>'no_attribution_signal'];
}
function lq_engagement(array $lead): array {
    $in=(int)($lead['inbound_count'] ?? 0); $out=(int)($lead['outbound_count'] ?? 0);
    $replied=(bool)($lead['customer_replied_after_staff'] ?? false);
    $tag=lq_str($lead['current_tag'] ?? '');
    if ($tag === 'purchased') return ['label'=>'purchased','score'=>100];
    if ($tag === 'qualified') return ['label'=>'qualified','score'=>90];
    if ($tag === 'lost') return ['label'=>'lost','score'=>10];
    if ($tag === 'interested') return ['label'=>'interested','score'=>75];
    if ($in >= 3 && $out >= 1 && $replied) return ['label'=>'strong_engagement','score'=>65];
    if ($in >= 2 && $out >= 1 && $replied) return ['label'=>'two_way','score'=>55];
    if ($out === 0) return ['label'=>'no_staff_reply','score'=>20];
    return ['label'=>'one_touch','score'=>30];
}
function lq_mask_id(string $v): string {
    if ($v === '') return '';
    $n=strlen($v); if ($n <= 8) return substr($v,0,2).'***';
    return substr($v,0,4).'***'.substr($v,-4);
}
function lq_percent(int|float $a, int|float $b): float { return $b > 0 ? round(($a/$b)*100,1) : 0.0; }

$connections=lq_json($connectionsFile,[]);
$clients=lq_json($clientsFile,[]);
$conversations=lq_json($convFile,[]);
$clientId=lq_str($_GET['client_id'] ?? '');
$sourceFilter=strtolower(lq_str($_GET['source'] ?? ''));
$limit=max(1,min(500,(int)($_GET['limit'] ?? 200)));
$since=lq_ts(lq_str($_GET['since'] ?? ''));
$untilRaw=lq_str($_GET['until'] ?? '');
$until=lq_ts($untilRaw);
if ($until !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/',$untilRaw)) $until += 86399;

$clientNames=[];
foreach ($clients as $k=>$v) {
    if (is_array($v)) { $id=lq_str($v['id'] ?? (is_string($k)?$k:'')); if ($id!=='') $clientNames[$id]=lq_str($v['name'] ?? $v['label'] ?? $id); }
}
$connClient=[];
foreach ($connections as $k=>$v) {
    if (!is_array($v)) continue; $id=lq_str($v['id'] ?? (is_string($k)?$k:'')); if ($id!=='') $connClient[$id]=lq_str($v['client_id'] ?? '');
}

$leads=[];
foreach ($conversations as $k=>$c) {
    if (!is_array($c)) continue;
    $id=lq_str($c['id'] ?? (is_string($k)?$k:'')); if ($id==='') continue;
    $cid=lq_str($c['client_id'] ?? ''); if ($clientId!=='' && $cid!==$clientId) continue;
    $first=lq_ts(lq_str($c['first_seen_at'] ?? '')); $last=lq_ts(lq_str($c['last_message_at'] ?? ''));
    if ($since!==null && $last!==null && $last<$since) continue;
    if ($until!==null && $first!==null && $first>$until) continue;
    $leads[$id]=[
        'id'=>$id,'client_id'=>$cid,'ycloud_connection_id'=>lq_str($c['ycloud_connection_id'] ?? ''),
        'business_number'=>lq_str($c['business_number'] ?? ''),'customer_number'=>lq_str($c['customer_number'] ?? ''),'waba_id'=>lq_str($c['waba_id'] ?? ''),
        'first_seen_at'=>lq_str($c['first_seen_at'] ?? ''),'last_message_at'=>lq_str($c['last_message_at'] ?? ''),
        'inbound_count'=>(int)($c['inbound_count'] ?? 0),'outbound_count'=>(int)($c['outbound_count'] ?? 0),
        'current_tag'=>$c['current_tag'] ?? null,'tag_source'=>$c['tag_source'] ?? null,'auto_label_reason'=>$c['auto_label_reason'] ?? null,
        'customer_replied_after_staff'=>(bool)($c['last_customer_reply_to_staff'] ?? false),
        'first_message_raw'=>'','first_inbound_ts'=>null,'first_outbound_ts'=>null,'first_response_seconds'=>null,
        'seen_outbound'=>false,'raw_replied_after_staff'=>false,
        'referral'=>['source_url'=>'','source_id'=>'','source_type'=>'','headline'=>'','body'=>'','media_type'=>'','image_url'=>'','ctwa_clid'=>lq_str($c['ctwa_clid'] ?? '')],
        'tracking'=>[], 'raw_inbound_count'=>0,'raw_outbound_count'=>0
    ];
}

$rawLines=0; $malformed=0;
if (is_file($rawFile) && ($fh=@fopen($rawFile,'rb'))) {
    while (($line=fgets($fh)) !== false) {
        $rawLines++; $row=json_decode($line,true); if(!is_array($row)){ $malformed++; continue; }
        $connId=lq_str($row['ycloud_connection_id'] ?? ''); $rowClient=$connClient[$connId] ?? '';
        if ($clientId!=='' && $rowClient!=='' && $rowClient!==$clientId) continue;
        $payload=is_array($row['payload'] ?? null)?$row['payload']:[];
        $type=lq_str($row['type'] ?? $payload['type'] ?? '');
        $isInbound=$type==='whatsapp.inbound_message.received' || ($type==='whatsapp.smb.history' && isset($payload['whatsappInboundMessage']));
        $isOutbound=$type==='whatsapp.smb.message.echoes' || ($type==='whatsapp.smb.history' && isset($payload['whatsappMessage']));
        if (!$isInbound && !$isOutbound) continue;
        $m=$isInbound?($payload['whatsappInboundMessage']??[]):($payload['whatsappMessage']??[]); if(!is_array($m))continue;
        $waba=lq_str($m['wabaId'] ?? ''); $customer=lq_str($isInbound?($m['from']??''):($m['to']??'')); if($waba===''||$customer==='')continue;
        $id=lq_conv_id($waba,$customer);
        if (!isset($leads[$id])) {
            $cid=$rowClient; if($clientId!=='' && $cid!==$clientId)continue;
            $business=lq_str($isInbound?($m['to']??''):($m['from']??''));
            $leads[$id]=['id'=>$id,'client_id'=>$cid,'ycloud_connection_id'=>$connId,'business_number'=>$business,'customer_number'=>$customer,'waba_id'=>$waba,
                'first_seen_at'=>'','last_message_at'=>'','inbound_count'=>0,'outbound_count'=>0,'current_tag'=>null,'tag_source'=>null,'auto_label_reason'=>null,
                'customer_replied_after_staff'=>false,'first_message_raw'=>'','first_inbound_ts'=>null,'first_outbound_ts'=>null,'first_response_seconds'=>null,'seen_outbound'=>false,'raw_replied_after_staff'=>false,
                'referral'=>['source_url'=>'','source_id'=>'','source_type'=>'','headline'=>'','body'=>'','media_type'=>'','image_url'=>'','ctwa_clid'=>''],'tracking'=>[], 'raw_inbound_count'=>0,'raw_outbound_count'=>0];
        }
        $lead=&$leads[$id]; if($lead['client_id']===''&&$rowClient!=='')$lead['client_id']=$rowClient; if($lead['ycloud_connection_id']===''&&$connId!=='')$lead['ycloud_connection_id']=$connId;
        $eventTime=lq_str($m['sendTime'] ?? $row['createTime'] ?? $payload['createTime'] ?? ''); $eventTs=lq_ts($eventTime);
        if ($since!==null && $eventTs!==null && $eventTs<$since) { unset($lead); continue; }
        if ($until!==null && $eventTs!==null && $eventTs>$until) { unset($lead); continue; }
        if ($isInbound) {
            $lead['raw_inbound_count']++;
            if ($lead['first_inbound_ts']===null || ($eventTs!==null && $eventTs<$lead['first_inbound_ts'])) {
                $lead['first_inbound_ts']=$eventTs; $lead['first_message_raw']=lq_message_text($m);
            }
            if ($lead['seen_outbound']) $lead['raw_replied_after_staff']=true;
            $ref=lq_referral($m);
            foreach($ref as $rk=>$rv) if($rv!=='' && lq_str($lead['referral'][$rk]??'')==='') $lead['referral'][$rk]=$rv;
            $url=lq_str($ref['source_url']??''); $params=lq_url_params($url);
            $tracking=lq_extract_ids($url.' '.lq_message_text($m),$params);
            foreach($tracking as $tk=>$tv) if($tv!=='' && !isset($lead['tracking'][$tk])) $lead['tracking'][$tk]=$tv;
            if(lq_str($ref['ctwa_clid']??'')!=='' && !isset($lead['tracking']['ctwa_clid']))$lead['tracking']['ctwa_clid']=$ref['ctwa_clid'];
        } else {
            $lead['raw_outbound_count']++; $lead['seen_outbound']=true;
            if ($lead['first_outbound_ts']===null || ($eventTs!==null && $eventTs<$lead['first_outbound_ts'])) $lead['first_outbound_ts']=$eventTs;
        }
        unset($lead);
    }
    fclose($fh);
}

$out=[];
foreach ($leads as $lead) {
    if ((int)$lead['inbound_count']===0 && (int)$lead['raw_inbound_count']>0) $lead['inbound_count']=$lead['raw_inbound_count'];
    if ((int)$lead['outbound_count']===0 && (int)$lead['raw_outbound_count']>0) $lead['outbound_count']=$lead['raw_outbound_count'];
    $lead['customer_replied_after_staff']=(bool)$lead['customer_replied_after_staff'] || (bool)$lead['raw_replied_after_staff'];
    if ($lead['first_inbound_ts']!==null && $lead['first_outbound_ts']!==null && $lead['first_outbound_ts'] >= $lead['first_inbound_ts']) $lead['first_response_seconds']=$lead['first_outbound_ts']-$lead['first_inbound_ts'];
    $src=lq_source($lead); if($sourceFilter!=='' && $src['source']!==$sourceFilter)continue;
    $eng=lq_engagement($lead); $ids=$lead['tracking'];
    $out[]=[
        'lead_id'=>$lead['id'],'client_id'=>$lead['client_id'],'client_name'=>$clientNames[$lead['client_id']]??$lead['client_id'],
        'phone'=>lq_mask_phone($lead['customer_number']),'customer_hash'=>substr(hash('sha256',lq_phone($lead['customer_number'])),0,12),
        'source'=>$src['source'],'source_confidence'=>$src['confidence'],'source_reason'=>$src['reason'],
        'utm'=>['source'=>$ids['utm_source']??null,'medium'=>$ids['utm_medium']??null,'campaign'=>$ids['utm_campaign']??null,'content'=>$ids['utm_content']??null,'term'=>$ids['utm_term']??null],
        'click_ids'=>['gclid'=>lq_mask_id(lq_str($ids['gclid']??'')),'wbraid'=>lq_mask_id(lq_str($ids['wbraid']??'')),'gbraid'=>lq_mask_id(lq_str($ids['gbraid']??'')),'ttclid'=>lq_mask_id(lq_str($ids['ttclid']??'')),'fbclid'=>lq_mask_id(lq_str($ids['fbclid']??'')),'ctwa_clid'=>lq_mask_id(lq_str($ids['ctwa_clid']??$lead['referral']['ctwa_clid']??''))],
        'ad'=>['source_id'=>$lead['referral']['source_id']?:null,'source_type'=>$lead['referral']['source_type']?:null,'headline'=>lq_clean_text(lq_str($lead['referral']['headline']??''),120)?:null],
        'first_message'=>lq_clean_text(lq_str($lead['first_message_raw']??''),160),
        'first_seen_at'=>$lead['first_seen_at']?:($lead['first_inbound_ts']?gmdate('c',$lead['first_inbound_ts']):null),'last_message_at'=>$lead['last_message_at']?:null,
        'inbound_count'=>(int)$lead['inbound_count'],'outbound_count'=>(int)$lead['outbound_count'],'customer_replied_after_staff'=>(bool)$lead['customer_replied_after_staff'],
        'first_response_seconds'=>$lead['first_response_seconds'],'crm_status'=>$lead['current_tag'],'tag_source'=>$lead['tag_source'],'auto_label_reason'=>$lead['auto_label_reason'],
        'engagement_label'=>$eng['label'],'engagement_score'=>$eng['score']
    ];
}

usort($out,function($a,$b){ return strcmp((string)($b['first_seen_at']??''),(string)($a['first_seen_at']??'')); });
$summary=['total_leads'=>count($out),'attributed_leads'=>0,'unknown_leads'=>0,'attribution_rate'=>0.0,'sources'=>[],'crm_status'=>[],'engagement'=>[]];
foreach($out as $lead){
    $s=$lead['source']; if(!isset($summary['sources'][$s]))$summary['sources'][$s]=['leads'=>0,'inbound_messages'=>0,'outbound_messages'=>0,'customer_replied_after_staff'=>0,'reply_after_staff_rate'=>0.0,'crm_status'=>[],'engagement'=>[],'confidence'=>[]];
    $summary['sources'][$s]['leads']++; $summary['sources'][$s]['inbound_messages']+=(int)$lead['inbound_count']; $summary['sources'][$s]['outbound_messages']+=(int)$lead['outbound_count']; if($lead['customer_replied_after_staff'])$summary['sources'][$s]['customer_replied_after_staff']++;
    $status=lq_str($lead['crm_status']??'') ?: 'unclassified'; $eng=lq_str($lead['engagement_label']??'') ?: 'unknown'; $conf=$lead['source_confidence'];
    $summary['sources'][$s]['crm_status'][$status]=($summary['sources'][$s]['crm_status'][$status]??0)+1; $summary['sources'][$s]['engagement'][$eng]=($summary['sources'][$s]['engagement'][$eng]??0)+1; $summary['sources'][$s]['confidence'][$conf]=($summary['sources'][$s]['confidence'][$conf]??0)+1;
    $summary['crm_status'][$status]=($summary['crm_status'][$status]??0)+1; $summary['engagement'][$eng]=($summary['engagement'][$eng]??0)+1;
    if($s==='unknown')$summary['unknown_leads']++; else $summary['attributed_leads']++;
}
$summary['attribution_rate']=lq_percent($summary['attributed_leads'],$summary['total_leads']);
foreach($summary['sources'] as &$srow)$srow['reply_after_staff_rate']=lq_percent($srow['customer_replied_after_staff'],$srow['leads']); unset($srow);
$shown=array_slice($out,0,$limit);

echo json_encode([
    'ok'=>true,'generated_at'=>gmdate('c'),'filters'=>['client_id'=>$clientId?:null,'source'=>$sourceFilter?:null,'since'=>$_GET['since']??null,'until'=>$_GET['until']??null,'limit'=>$limit],
    'summary'=>$summary,'leads'=>$shown,
    'diagnostics'=>['raw_lines_scanned'=>$rawLines,'malformed_raw_lines'=>$malformed,'matching_leads_before_limit'=>count($out),'returned_leads'=>count($shown),'data_files'=>['raw_events'=>is_file($rawFile),'conversations'=>is_file($convFile),'connections'=>is_file($connectionsFile)]]
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
