<?php
declare(strict_types=1);

$dbPath = '/home/u878466595/private/arkan-leads.sqlite';
$outFile = '/home/u878466595/domains/hositee.com/public_html/marketing/data/attribution_events.jsonl';

if (!is_file($dbPath)) { fwrite(STDERR, "ARKAN_BACKFILL_FAIL db_missing\n"); exit(2); }
@mkdir(dirname($outFile), 0775, true);

function bf_clean(mixed $v, int $max=800): string {
    $s = trim((string)$v);
    $s = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $s) ?? '';
    return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
}
function bf_source(array $r): string {
    $utm = strtolower(bf_clean($r['utm_source'] ?? '', 80));
    if (bf_clean($r['gclid'] ?? '') !== '' || bf_clean($r['wbraid'] ?? '') !== '' || bf_clean($r['gbraid'] ?? '') !== '') return 'google';
    if (bf_clean($r['ttclid'] ?? '') !== '') return 'tiktok';
    if (bf_clean($r['fbclid'] ?? '') !== '') return 'meta';
    if (str_contains($utm,'google') || str_contains($utm,'adwords')) return 'google';
    if (str_contains($utm,'tiktok')) return 'tiktok';
    if (str_contains($utm,'facebook') || str_contains($utm,'instagram') || str_contains($utm,'meta')) return 'meta';
    return $utm !== '' ? $utm : 'website';
}
function bf_existing_leads(string $file): array {
    $seen=[];
    if (!is_file($file)) return $seen;
    $fh=@fopen($file,'rb'); if(!$fh) return $seen;
    while (($line=fgets($fh)) !== false) {
        $r=json_decode($line,true);
        if(!is_array($r))continue;
        $lead=strtoupper(bf_clean($r['lead_id']??'',100));
        if($lead!=='')$seen[$lead]=true;
    }
    fclose($fh); return $seen;
}
function bf_append(string $file, array $row): bool {
    $fh=@fopen($file,'ab'); if(!$fh)return false;
    @flock($fh,LOCK_EX);
    $ok=fwrite($fh,json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n")!==false;
    @flock($fh,LOCK_UN); fclose($fh); return $ok;
}

$pdo = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$columns = ['lead_id','submitted_at','utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid','gbraid','wbraid','ttclid','fbclid','campaign_id','campaign_name','ad_group_id','ad_group_name','ad_id','keyword','match_type','device','network','landing_page_id','landing_url','first_landing_url'];
$sql='SELECT '.implode(',',array_map(static fn($c)=>'"'.$c.'"',$columns)).' FROM leads ORDER BY id ASC';
$seen=bf_existing_leads($outFile);
$stats=['rows'=>0,'with_signal'=>0,'inserted'=>0,'already_present'=>0,'google'=>0,'tiktok'=>0,'meta'=>0,'other'=>0];
foreach ($pdo->query($sql) as $r) {
    $stats['rows']++;
    $leadId=strtoupper(bf_clean($r['lead_id']??'',100));
    if(!preg_match('/^ARK-WEB-[0-9]{8}-[0-9]{6}-[A-F0-9]{6}$/',$leadId))continue;
    $source=bf_source($r);
    $hasSignal = bf_clean($r['gclid']??'')!=='' || bf_clean($r['wbraid']??'')!=='' || bf_clean($r['gbraid']??'')!=='' || bf_clean($r['ttclid']??'')!=='' || bf_clean($r['fbclid']??'')!=='' || bf_clean($r['utm_source']??'')!=='';
    if(!$hasSignal)continue;
    $stats['with_signal']++;
    if(isset($seen[$leadId])){$stats['already_present']++;continue;}
    $tracking=[];
    foreach(['utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid','gbraid','wbraid','ttclid','fbclid','campaign_id','campaign_name','ad_group_id','ad_group_name','ad_id','keyword','match_type','device','network','landing_page_id','first_landing_url'] as $k) $tracking[$k]=bf_clean($r[$k]??'', $k==='first_landing_url'?800:255);
    if($tracking['first_landing_url']==='')$tracking['first_landing_url']=bf_clean($r['landing_url']??'',800);
    $tracking['landing_path']='';
    $row=[
        'id'=>'atr_bf_'.bin2hex(random_bytes(8)),
        'ref_token'=>'',
        'lead_id'=>$leadId,
        'event_type'=>'historical_form_backfill',
        'source'=>$source,
        'tracking'=>$tracking,
        'captured_at'=>bf_clean($r['submitted_at']??'',80),
        'received_at'=>gmdate('c'),
    ];
    if(!bf_append($outFile,$row)){fwrite(STDERR,"ARKAN_BACKFILL_FAIL append_failed\n");exit(3);}
    $seen[$leadId]=true;$stats['inserted']++;
    if(isset($stats[$source]))$stats[$source]++;else$stats['other']++;
}

echo 'ARKAN_BACKFILL_OK '.json_encode($stats,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
