<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$requestedLimit = max(1, min(500, (int)($_GET['limit'] ?? 200)));
$_GET['limit'] = '500';
ob_start();
include __DIR__ . '/lead_quality.php';
$baseRaw = ob_get_clean();
$base = json_decode((string)$baseRaw, true);
if (!is_array($base) || !($base['ok'] ?? false)) {
    http_response_code(502);
    echo json_encode(['ok'=>false,'error'=>'base_quality_unavailable'], JSON_UNESCAPED_SLASHES);
    exit;
}

function lqe_clean(mixed $v, int $max=500): string {
    $s = trim((string)$v);
    $s = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $s) ?? '';
    return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
}
function lqe_mask(string $v): string {
    if ($v === '') return '';
    $n = strlen($v);
    return $n <= 8 ? substr($v, 0, 2) . '***' : substr($v, 0, 4) . '***' . substr($v, -4);
}
function lqe_pct(int $a, int $b): float { return $b > 0 ? round(($a/$b)*100, 1) : 0.0; }
function lqe_read_map(string $file): array {
    $byRef=[]; $byLead=[];
    if (!is_file($file)) return [$byRef,$byLead];
    $fh=@fopen($file,'rb'); if(!$fh) return [$byRef,$byLead];
    while (($line=fgets($fh)) !== false) {
        $r=json_decode($line,true); if(!is_array($r)) continue;
        $ref=strtoupper(lqe_clean($r['ref_token']??'',80));
        $lead=strtoupper(lqe_clean($r['lead_id']??'',100));
        if($ref!=='') $byRef[$ref]=$r;
        if($lead!=='') $byLead[$lead]=$r;
    }
    fclose($fh); return [$byRef,$byLead];
}
function lqe_reference(string $message): array {
    $ref=''; $lead='';
    if (preg_match('/ARK-AT-[A-Z0-9]{12,40}/i',$message,$m)) $ref=strtoupper($m[0]);
    if (preg_match('/ARK-WEB-[0-9]{8}-[0-9]{6}-[A-F0-9]{6}/i',$message,$m)) $lead=strtoupper($m[0]);
    return [$ref,$lead];
}
function lqe_summary(array $leads): array {
    $s=['total_leads'=>count($leads),'attributed_leads'=>0,'unknown_leads'=>0,'attribution_rate'=>0.0,'sources'=>[],'crm_status'=>[],'engagement'=>[]];
    foreach($leads as $lead){
        $src=(string)($lead['source']??'unknown');
        if(!isset($s['sources'][$src]))$s['sources'][$src]=['leads'=>0,'inbound_messages'=>0,'outbound_messages'=>0,'customer_replied_after_staff'=>0,'reply_after_staff_rate'=>0.0,'crm_status'=>[],'engagement'=>[],'confidence'=>[]];
        $s['sources'][$src]['leads']++;
        $s['sources'][$src]['inbound_messages']+=(int)($lead['inbound_count']??0);
        $s['sources'][$src]['outbound_messages']+=(int)($lead['outbound_count']??0);
        if(!empty($lead['customer_replied_after_staff']))$s['sources'][$src]['customer_replied_after_staff']++;
        $status=lqe_clean($lead['crm_status']??'') ?: 'unclassified';
        $eng=lqe_clean($lead['engagement_label']??'') ?: 'unknown';
        $conf=lqe_clean($lead['source_confidence']??'') ?: 'low';
        $s['sources'][$src]['crm_status'][$status]=($s['sources'][$src]['crm_status'][$status]??0)+1;
        $s['sources'][$src]['engagement'][$eng]=($s['sources'][$src]['engagement'][$eng]??0)+1;
        $s['sources'][$src]['confidence'][$conf]=($s['sources'][$src]['confidence'][$conf]??0)+1;
        $s['crm_status'][$status]=($s['crm_status'][$status]??0)+1;
        $s['engagement'][$eng]=($s['engagement'][$eng]??0)+1;
        if($src==='unknown')$s['unknown_leads']++; else $s['attributed_leads']++;
    }
    $s['attribution_rate']=lqe_pct($s['attributed_leads'],$s['total_leads']);
    foreach($s['sources'] as &$row)$row['reply_after_staff_rate']=lqe_pct($row['customer_replied_after_staff'],$row['leads']);
    unset($row);
    return $s;
}

[$byRef,$byLead]=lqe_read_map(__DIR__.'/data/attribution_events.jsonl');
$all=[]; $joined=0;
foreach (($base['leads']??[]) as $lead) {
    if(!is_array($lead))continue;
    [$ref,$leadId]=lqe_reference((string)($lead['first_message']??''));
    $map=null; $via='';
    if($ref!==''&&isset($byRef[$ref])){$map=$byRef[$ref];$via='visit_ref';}
    elseif($leadId!==''&&isset($byLead[$leadId])){$map=$byLead[$leadId];$via='lead_id';}
    if(is_array($map)){
        $joined++;
        $tracking=is_array($map['tracking']??null)?$map['tracking']:[];
        $source=lqe_clean($map['source']??'');
        if($source!==''){
            $lead['source']=$source;
            $lead['source_confidence']='high';
            $lead['source_reason']='server_attribution_'.$via;
        }
        $lead['utm']=[
            'source'=>lqe_clean($tracking['utm_source']??'')?:null,
            'medium'=>lqe_clean($tracking['utm_medium']??'')?:null,
            'campaign'=>lqe_clean($tracking['utm_campaign']??'')?:null,
            'content'=>lqe_clean($tracking['utm_content']??'')?:null,
            'term'=>lqe_clean($tracking['utm_term']??'')?:null,
        ];
        $lead['click_ids']=[
            'gclid'=>lqe_mask(lqe_clean($tracking['gclid']??'',255)),
            'wbraid'=>lqe_mask(lqe_clean($tracking['wbraid']??'',255)),
            'gbraid'=>lqe_mask(lqe_clean($tracking['gbraid']??'',255)),
            'ttclid'=>lqe_mask(lqe_clean($tracking['ttclid']??'',255)),
            'fbclid'=>lqe_mask(lqe_clean($tracking['fbclid']??'',255)),
            'ctwa_clid'=>$lead['click_ids']['ctwa_clid']??'',
        ];
        $lead['campaign']=[
            'campaign_id'=>lqe_clean($tracking['campaign_id']??'')?:null,
            'campaign_name'=>lqe_clean($tracking['campaign_name']??'')?:null,
            'ad_group_id'=>lqe_clean($tracking['ad_group_id']??'')?:null,
            'ad_group_name'=>lqe_clean($tracking['ad_group_name']??'')?:null,
            'ad_id'=>lqe_clean($tracking['ad_id']??'')?:null,
            'keyword'=>lqe_clean($tracking['keyword']??'')?:null,
            'match_type'=>lqe_clean($tracking['match_type']??'')?:null,
            'device'=>lqe_clean($tracking['device']??'')?:null,
            'network'=>lqe_clean($tracking['network']??'')?:null,
        ];
        $lead['attribution_ref']=$ref?:null;
        $lead['attribution_lead_id']=$leadId?:null;
        $lead['attribution_join']=$via;
    }
    $all[]=$lead;
}

$sourceFilter=strtolower(lqe_clean($_GET['source']??''));
if($sourceFilter!=='')$all=array_values(array_filter($all,fn(array $l):bool=>strtolower((string)($l['source']??''))===$sourceFilter));
$summary=lqe_summary($all);
$base['summary']=$summary;
$base['leads']=array_slice($all,0,$requestedLimit);
$base['filters']['limit']=$requestedLimit;
$base['filters']['source']=$sourceFilter?:null;
$base['diagnostics']['attribution_records_by_ref']=count($byRef);
$base['diagnostics']['attribution_records_by_lead']=count($byLead);
$base['diagnostics']['attribution_joins']=$joined;
$base['diagnostics']['returned_leads']=count($base['leads']);
$base['diagnostics']['matching_leads_before_limit']=count($all);
$base['generated_at']=gmdate('c');

echo json_encode($base, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
