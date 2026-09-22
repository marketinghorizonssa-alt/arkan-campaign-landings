<?php
declare(strict_types=1);

header('Cache-Control: no-store, max-age=0');

$base = __DIR__ . '/data';
$secure = dirname(__DIR__, 4) . '/.marketing';
$tzName = 'Africa/Cairo';
$tz = new DateTimeZone($tzName);

function lr_bootstrap_sheet_feed(string $secure): void {
    $marker=$secure.'/.sheet_feed_live_v2';
    $target=__DIR__.'/sheet_feed.php';
    if (is_file($target) && is_file($marker)) return;
    if (!is_dir($secure)) @mkdir($secure,0700,true);
    $url='https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/marketing-deploy/marketing-hub-v06/sheet_feed.php';
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>25,CURLOPT_FOLLOWLOCATION=>false]);
    $body=curl_exec($ch);$errno=curl_errno($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    if($errno!==0||$status<200||$status>=300||!is_string($body)||strlen($body)<50)return;
    $tmp=$target.'.tmp';
    if(@file_put_contents($tmp,$body,LOCK_EX)===false)return;
    if(!@rename($tmp,$target)){@unlink($tmp);return;}
    @file_put_contents($marker,gmdate('c')."\n",LOCK_EX);
    @chmod($marker,0600);
}
lr_bootstrap_sheet_feed($secure);

function lr_json(string $file, array $default=[]): array {
    if (!is_file($file)) return $default;
    $v = json_decode((string)@file_get_contents($file), true);
    return is_array($v) ? $v : $default;
}
function lr_s(mixed $v): string { return is_scalar($v) ? trim((string)$v) : ''; }
function lr_phone(mixed $v): string { return preg_replace('/\D+/', '', lr_s($v)) ?? ''; }
function lr_clean(string $v): string {
    $v = preg_replace('/[\p{Cf}\p{Cc}\p{Cs}]+/u', '', $v) ?? $v;
    return preg_replace('/\s+/u', ' ', trim($v)) ?? trim($v);
}
function lr_lower(string $v): string { return function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v); }
function lr_text(array $m): string {
    $type = lr_s($m['type'] ?? '');
    if ($type === 'text') return lr_clean(lr_s($m['text']['body'] ?? ''));
    foreach (['image','video','document','audio','sticker'] as $k) {
        if ($type === $k) {
            $x = is_array($m[$k] ?? null) ? $m[$k] : [];
            $caption = lr_clean(lr_s($x['caption'] ?? ''));
            return $caption !== '' ? $caption : '['.$k.']';
        }
    }
    if ($type === 'location') return '[location]';
    if ($type === 'contacts') return '[contacts]';
    if ($type === 'interactive') return '[interactive]';
    if ($type === 'reaction') return '[reaction]';
    if ($type === 'revoke') return '[revoke]';
    return $type !== '' ? '['.$type.']' : '';
}
function lr_date(string $v): bool { return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $v); }
function lr_iso_local(string $ts, DateTimeZone $tz): string {
    if ($ts === '') return '';
    try { return (new DateTimeImmutable($ts))->setTimezone($tz)->format('Y-m-d H:i:s'); }
    catch (Throwable) { return ''; }
}
function lr_contains(string $text, array $needles): bool {
    $t = lr_lower(lr_clean($text));
    foreach ($needles as $n) if ($n !== '' && str_contains($t, lr_lower((string)$n))) return true;
    return false;
}
function lr_source(array $firstInbound, array $messages): array {
    $raw = is_array($firstInbound['raw'] ?? null) ? $firstInbound['raw'] : [];
    $ref = is_array($raw['referral'] ?? null) ? $raw['referral'] : [];
    $clid = lr_s($ref['ctwa_clid'] ?? $ref['ctwaClid'] ?? '');
    $refText = lr_lower(
        lr_s($ref['source_url'] ?? $ref['sourceUrl'] ?? '') . ' ' .
        lr_s($ref['headline'] ?? '') . ' ' .
        lr_s($ref['source_type'] ?? $ref['sourceType'] ?? '')
    );
    $text = '';
    foreach ($messages as $m) if (($m['direction'] ?? '') === 'inbound') $text .= ' '.lr_s($m['text'] ?? '');
    $low = lr_lower($text);

    if ($clid !== '' || str_contains($refText, 'facebook') || str_contains($refText, 'instagram')) {
        return ['key'=>'meta','label'=>'Meta Ads','confidence'=>'high','reason'=>$clid !== '' ? 'ctwa_clid' : 'referral'];
    }
    if (str_contains($refText, 'tiktok') || str_contains($low, 'tiktok') || str_contains($low, 'تيك توك')) {
        return ['key'=>'tiktok','label'=>'TikTok Ads','confidence'=>'high','reason'=>str_contains($refText,'tiktok') ? 'referral' : 'message_template'];
    }
    if (str_contains($refText, 'google') || lr_contains($text, ['المصدر: Google Ads','المصدر:Google Ads','source: google ads','جوجل ادز','google ads'])) {
        return ['key'=>'google','label'=>'Google Ads','confidence'=>'high','reason'=>str_contains($refText,'google') ? 'referral' : 'message_source_tag'];
    }
    if (str_contains($refText, 'snap') || lr_contains($text, ['المصدر: Snapchat Ads','المصدر:Snapchat Ads','snapchat ads','سناب شات','سناب'])) {
        return ['key'=>'snapchat','label'=>'Snapchat Ads','confidence'=>'high','reason'=>str_contains($refText,'snap') ? 'referral' : 'message_source_tag'];
    }
    if (str_contains($refText, 'linkedin') || str_contains($low, 'linkedin')) {
        return ['key'=>'linkedin','label'=>'LinkedIn Ads','confidence'=>'high','reason'=>'platform_evidence'];
    }
    if (str_contains($refText, 'x.com') || str_contains($refText, 'twitter')) {
        return ['key'=>'x','label'=>'X Ads','confidence'=>'high','reason'=>'referral'];
    }
    return ['key'=>'organic','label'=>'Organic / Direct','confidence'=>'low','reason'=>'no_tracked_ad_attribution'];
}
function lr_stage(array $ai): array {
    $tag = lr_lower(lr_s($ai['current_tag'] ?? ''));
    $manual = ['interested'=>'interested','qualified'=>'qualified','purchased'=>'converted','converted'=>'converted','lost'=>'unqualified'];
    if ($tag !== '' && isset($manual[$tag])) {
        return [
            'key'=>$manual[$tag],
            'label'=>match($manual[$tag]){'qualified'=>'Qualified','interested'=>'Interested','converted'=>'Converted','unqualified'=>'Unqualified',default=>'New'},
            'score'=>(int)($ai['ai_quality_score'] ?? 0),
            'summary'=>lr_s($ai['ai_summary'] ?? '') ?: 'Manual evaluation',
            'reason'=>lr_s($ai['ai_reason'] ?? ''),
            'method'=>'manual'
        ];
    }
    $stage = lr_lower(lr_s($ai['ai_stage'] ?? ''));
    if (!in_array($stage, ['new','interested','qualified','converted','unqualified'], true)) $stage = 'new';
    return [
        'key'=>$stage,
        'label'=>match($stage){'qualified'=>'Qualified','interested'=>'Interested','converted'=>'Converted','unqualified'=>'Unqualified',default=>'New / Not evaluated'},
        'score'=>(int)($ai['ai_quality_score'] ?? 0),
        'summary'=>lr_s($ai['ai_summary'] ?? ''),
        'reason'=>lr_s($ai['ai_reason'] ?? ''),
        'method'=>lr_s($ai['ai_stage'] ?? '') !== '' ? 'ai' : 'not_evaluated'
    ];
}
function lr_client_id(array $m, string $direction, array $byPhone, array $byWaba): string {
    $business = lr_phone($direction === 'inbound' ? ($m['to'] ?? '') : ($m['from'] ?? ''));
    $waba = lr_s($m['wabaId'] ?? $m['waba_id'] ?? '');
    if ($business !== '' && isset($byPhone[$business])) return (string)$byPhone[$business];
    if ($waba !== '' && isset($byWaba[$waba])) return (string)$byWaba[$waba];
    return '';
}
function lr_build(string $clientFilter, string $fromStr, string $toStr, DateTimeZone $tz, string $base): array {
    $clientsRaw = lr_json($base.'/clients.json', []);
    $numbersRaw = lr_json($base.'/whatsapp_numbers.json', []);
    $conversationsRaw = lr_json($base.'/conversations.json', []);

    $clients=[]; $clientNames=[];
    foreach ($clientsRaw as $k=>$c) {
        if (!is_array($c)) continue;
        $id=lr_s($c['id'] ?? (is_string($k)?$k:''));
        if ($id==='') continue;
        $clientNames[$id]=lr_s($c['name'] ?? $id);
        $clients[]=['id'=>$id,'name'=>$clientNames[$id]];
    }
    if ($clientFilter !== '' && !isset($clientNames[$clientFilter])) throw new RuntimeException('client_not_found');

    $byPhone=[]; $byWaba=[];
    foreach ($numbersRaw as $n) {
        if (!is_array($n)) continue;
        $cid=lr_s($n['client_id'] ?? '');
        if ($cid==='' || !isset($clientNames[$cid])) continue;
        $p=lr_phone($n['phone_number'] ?? '');
        $w=lr_s($n['waba_id'] ?? '');
        if ($p!=='') $byPhone[$p]=$cid;
        if ($w!=='') $byWaba[$w]=$cid;
    }

    $aiByKey=[];
    foreach ($conversationsRaw as $c) {
        if (!is_array($c)) continue;
        $business=lr_phone($c['business_number'] ?? '');
        $waba=lr_s($c['waba_id'] ?? '');
        $cid=$business!==''&&isset($byPhone[$business])?(string)$byPhone[$business]:($waba!==''&&isset($byWaba[$waba])?(string)$byWaba[$waba]:'');
        $customer=lr_phone($c['customer_number'] ?? '');
        if ($cid===''||$customer==='') continue;
        $key=$cid.'|'.$customer;
        $stamp=lr_s($c['ai_evaluated_at'] ?? $c['last_message_at'] ?? '');
        if (!isset($aiByKey[$key]) || strcmp($stamp, lr_s($aiByKey[$key]['_stamp'] ?? '')) >= 0) {
            $c['_stamp']=$stamp; $aiByKey[$key]=$c;
        }
    }

    $threads=[];
    $rawFile=$base.'/raw_events.jsonl';
    if (is_file($rawFile) && ($fh=@fopen($rawFile,'rb'))) {
        while (($line=fgets($fh))!==false) {
            $row=json_decode($line,true); if(!is_array($row)) continue;
            $payload=is_array($row['payload']??null)?$row['payload']:[];
            $type=lr_s($row['type'] ?? $payload['type'] ?? '');
            $m=null; $direction='';
            if ($type==='whatsapp.inbound_message.received' && is_array($payload['whatsappInboundMessage']??null)) {$m=$payload['whatsappInboundMessage'];$direction='inbound';}
            elseif ($type==='whatsapp.smb.message.echoes' && is_array($payload['whatsappMessage']??null)) {$m=$payload['whatsappMessage'];$direction='outbound';}
            elseif ($type==='whatsapp.smb.history' && is_array($payload['whatsappInboundMessage']??null)) {$m=$payload['whatsappInboundMessage'];$direction='inbound';}
            elseif ($type==='whatsapp.smb.history' && is_array($payload['whatsappMessage']??null)) {$m=$payload['whatsappMessage'];$direction='outbound';}
            if (!is_array($m) || $direction==='') continue;

            $cid=lr_client_id($m,$direction,$byPhone,$byWaba);
            if ($cid==='' || ($clientFilter!=='' && $cid!==$clientFilter)) continue;
            $business=lr_phone($direction==='inbound'?($m['to']??''):($m['from']??''));
            $customer=lr_phone($direction==='inbound'?($m['from']??''):($m['to']??''));
            if ($customer==='' || ($business!==''&&$customer===$business)) continue;
            $ts=lr_s($m['sendTime'] ?? $payload['createTime'] ?? $row['createTime'] ?? '');
            if ($ts==='') continue;
            try {$epoch=(new DateTimeImmutable($ts))->getTimestamp();} catch(Throwable) {continue;}
            $key=$cid.'|'.$customer;
            if(!isset($threads[$key])) $threads[$key]=[
                'client_id'=>$cid,'customer_phone'=>'+'.$customer,'first_inbound_epoch'=>null,'first_inbound'=>null,'messages'=>[]
            ];
            $msgId=lr_s($m['id'] ?? $m['messageId'] ?? $row['id'] ?? '');
            if ($msgId==='') $msgId=sha1($key.'|'.$direction.'|'.$ts.'|'.lr_s($m['type']??'').'|'.lr_text($m));
            $threads[$key]['messages'][$msgId]=[
                'id'=>$msgId,'direction'=>$direction,'type'=>lr_s($m['type']??'unknown'),'text'=>lr_text($m),
                'at_utc'=>$ts,'at_local'=>lr_iso_local($ts,$tz),'epoch'=>$epoch,'raw'=>$m
            ];
            if ($direction==='inbound' && ($threads[$key]['first_inbound_epoch']===null || $epoch<$threads[$key]['first_inbound_epoch'])) {
                $threads[$key]['first_inbound_epoch']=$epoch;
                $threads[$key]['first_inbound']=$threads[$key]['messages'][$msgId];
            }
        }
        fclose($fh);
    }

    $start=(new DateTimeImmutable($fromStr.' 00:00:00',$tz))->getTimestamp();
    $end=(new DateTimeImmutable($toStr.' 23:59:59',$tz))->getTimestamp();
    $leads=[]; $summary=[
        'new_customers'=>0,'messages'=>0,'paid_ads'=>0,'organic'=>0,
        'quality'=>['converted'=>0,'qualified'=>0,'interested'=>0,'new'=>0,'unqualified'=>0],
        'sources'=>[]
    ];

    foreach($threads as $key=>$t) {
        $firstEpoch=$t['first_inbound_epoch'];
        if($firstEpoch===null || $firstEpoch<$start || $firstEpoch>$end) continue;
        $messages=array_values($t['messages']);
        usort($messages,fn($a,$b)=>($a['epoch']<=>$b['epoch']));
        $messages=array_values(array_filter($messages,fn($m)=>$m['epoch']>=$firstEpoch && $m['epoch']<=$end));
        $src=lr_source($t['first_inbound'],$messages);
        $quality=lr_stage($aiByKey[$key]??[]);
        $messageRows=[];
        foreach($messages as $m) {
            $messageRows[]=['direction'=>$m['direction'],'type'=>$m['type'],'text'=>$m['text'],'at'=>$m['at_local']];
        }
        $leadId=substr(hash('sha256',$key.'|'.$firstEpoch),0,16);
        $leads[]=[
            'lead_id'=>$leadId,
            'client_id'=>$t['client_id'],
            'client_name'=>$clientNames[$t['client_id']]??$t['client_id'],
            'customer_phone'=>$t['customer_phone'],
            'first_contact_at'=>lr_iso_local((string)$t['first_inbound']['at_utc'],$tz),
            'source'=>$src,
            'quality'=>$quality,
            'message_count'=>count($messageRows),
            'messages'=>$messageRows
        ];
        $summary['new_customers']++;
        $summary['messages']+=count($messageRows);
        if($src['key']==='organic') $summary['organic']++; else $summary['paid_ads']++;
        $summary['sources'][$src['key']]=($summary['sources'][$src['key']]??0)+1;
        $summary['quality'][$quality['key']]=($summary['quality'][$quality['key']]??0)+1;
    }
    usort($leads,fn($a,$b)=>strcmp($a['first_contact_at'],$b['first_contact_at']));
    arsort($summary['sources']);
    return [
        'ok'=>true,'version'=>'lead-report-v1','timezone'=>$tz->getName(),'from'=>$fromStr,'to'=>$toStr,
        'client_id'=>$clientFilter?:null,'client_name'=>$clientFilter!==''?($clientNames[$clientFilter]??$clientFilter):'All clients',
        'generated_at'=>(new DateTimeImmutable('now',$tz))->format(DateTimeInterface::ATOM),
        'definition'=>'New customer = earliest inbound WhatsApp message in available synced history falls inside the selected date range; client resolved by business WhatsApp number/WABA.',
        'summary'=>$summary,'leads'=>$leads,'clients'=>$clients
    ];
}
function lr_csv(array $report): never {
    $name=preg_replace('/[^A-Za-z0-9_-]+/','_',lr_s($report['client_name']??'client')) ?: 'client';
    $filename='whatsapp_leads_'.$name.'_'.($report['from']??'').'_'.$report['to'].'.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo "\xEF\xBB\xBF";
    $out=fopen('php://output','wb');
    fputcsv($out,['Client','From','To','Lead #','Customer phone','First contact','Source','Source confidence','Evaluation','Quality score','Evaluation summary','Message time','Direction','Message type','Message text']);
    $i=0;
    foreach(($report['leads']??[]) as $lead){
        $i++;
        $msgs=$lead['messages']??[];
        if(!$msgs)$msgs=[['at'=>'','direction'=>'','type'=>'','text'=>'']];
        foreach($msgs as $m){
            fputcsv($out,[
                $lead['client_name']??'', $report['from']??'', $report['to']??'', $i,
                $lead['customer_phone']??'', $lead['first_contact_at']??'',
                $lead['source']['label']??'', $lead['source']['confidence']??'',
                $lead['quality']['label']??'', $lead['quality']['score']??0, $lead['quality']['summary']??'',
                $m['at']??'', $m['direction']??'', $m['type']??'', $m['text']??''
            ]);
        }
    }
    fclose($out); exit;
}

$action=lr_s($_GET['action']??'report');
$today=(new DateTimeImmutable('now',$tz))->format('Y-m-d');
$from=lr_s($_GET['from']??$today);
$to=lr_s($_GET['to']??$today);
$client=lr_s($_GET['client_id']??'');
if(!lr_date($from)||!lr_date($to)){http_response_code(422);header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'error'=>'invalid_date']);exit;}
try{
    $a=new DateTimeImmutable($from,$tz);$b=new DateTimeImmutable($to,$tz);
    if($a>$b){http_response_code(422);header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'error'=>'from_after_to']);exit;}
    if($b->diff($a)->days>92){http_response_code(422);header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'error'=>'range_too_large','max_days'=>93]);exit;}
    $report=lr_build($client,$from,$to,$tz,$base);
    if($action==='csv') lr_csv($report);
    if($action==='drive_export'){
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);throw new RuntimeException('method_not_allowed');}
        if(!is_dir($secure) && !@mkdir($secure,0700,true)){http_response_code(500);throw new RuntimeException('secure_dir_unavailable');}
        $stateFile=$secure.'/google_report_state.json';
        $state=[
            'client_id'=>$client,
            'client_name'=>$report['client_name']??'',
            'from'=>$from,
            'to'=>$to,
            'updated_at'=>(new DateTimeImmutable('now',$tz))->format(DateTimeInterface::ATOM),
            'new_customers'=>(int)($report['summary']['new_customers']??0)
        ];
        $tmp=$stateFile.'.tmp';
        if(file_put_contents($tmp,json_encode($state,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX)===false || !rename($tmp,$stateFile)){
            http_response_code(500);header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'error'=>'drive_state_write_failed']);exit;
        }
        $sheetId='1EEWHlQkf3Y3Fzv0e2uZ3fWHxD14_mRM1gkQfTlYfNT4';
        $sheetUrl='https://docs.google.com/spreadsheets/d/'.$sheetId.'/edit';
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>true,'url'=>$sheetUrl,'sheet_id'=>$sheetId,'updated_at'=>$state['updated_at'],'new_customers'=>$state['new_customers']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
    if(http_response_code()<400)http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
