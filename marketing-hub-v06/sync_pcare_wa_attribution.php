<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

$feed='https://pcare.sa/pcare-wa-attribution-feed.php?token=HZNpcare_7R4mN2qL9xK6vT3sD8pF5wC1aG0yB4uJ';
$hub='https://marketing.hositee.com/wa_click_attribution.php';
$secure=dirname(__DIR__,4).'/.marketing';
$stateFile=$secure.'/pcare_wa_attr_sync_state.json';
$attributionClients=[
    'cl_0e6efd258397db'=>'+966505952042',
    'cl_3ea5ae96e05c6b'=>'+966537033347',
];
if(!is_dir($secure))@mkdir($secure,0700,true);

function req(string $url,string $method='GET',?array $payload=null):array{
    $ch=curl_init($url);
    $headers=['Accept: application/json'];
    if($payload!==null)$headers[]='Content-Type: application/json';
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false
    ]);
    if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $body=curl_exec($ch);$errno=curl_errno($ch);$err=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    $json=is_string($body)?json_decode($body,true):null;
    return ['ok'=>$errno===0&&$status>=200&&$status<300,'status'=>$status,'error'=>$errno?$err:null,'json'=>is_array($json)?$json:null];
}
function jload(string $f):array{
    if(!is_file($f))return[];
    $v=json_decode((string)file_get_contents($f),true);
    return is_array($v)?$v:[];
}
function jsave(string $f,array $v):bool{
    $tmp=$f.'.tmp';
    if(file_put_contents($tmp,json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX)===false)return false;
    return rename($tmp,$f);
}


function norm_phone(string $v):string{
    return preg_replace('/\D+/','',$v)??'';
}
function click_epoch(array $row):int{
    // browser_time is the actual user click time. captured_at can be delayed by the
    // P Care -> Hub sync and may arrive after the WhatsApp message.
    foreach(['browser_time','created_at','captured_at'] as $k){
        $raw=trim((string)($row[$k]??''));
        if($raw==='')continue;
        $t=strtotime($raw);
        if($t)return $t;
    }
    return 0;
}
function copy_click_attribution(array &$conv,array $click,string $clickId):void{
    $conv['traffic_source_key']=(string)($click['traffic_source_key']??'website');
    $conv['traffic_source_label']=(string)($click['traffic_source_label']??'Website / YCloud Chat Link');
    $specific=in_array(strtolower((string)($click['traffic_source_key']??'')),['google','tiktok','meta','snapchat','microsoft_ads','linkedin','x'],true);
    $conv['traffic_source_confidence']=$specific?'high':'medium';
    $conv['traffic_source_reason']='unique_browser_click_then_message_within_5m';
    $conv['attribution_match_method']='single_unique_browser_5m_window';
    $conv['ycloud_chatlink_click_id']=$clickId;
    $conv['chatlink_source_url']=(string)($click['source_url']??'');
    $conv['attribution_landing_url']=(string)($click['landing_url']??'');
    $conv['attribution_referrer']=(string)($click['referrer']??'');
    $conv['attribution_params']=is_array($click['query_params']??null)?$click['query_params']:[];
    $conv['attribution_utm']=is_array($click['utm']??null)?$click['utm']:[];
    foreach([
        'gclid'=>'google_gclid','gbraid'=>'google_gbraid','wbraid'=>'google_wbraid','dclid'=>'google_dclid',
        'fbclid'=>'meta_fbclid','ttclid'=>'tiktok_ttclid','msclkid'=>'microsoft_msclkid',
        'li_fat_id'=>'linkedin_li_fat_id','twclid'=>'x_twclid'
    ] as $rk=>$ck){if(!empty($click[$rk]))$conv[$ck]=(string)$click[$rk];}
    if(!empty($click['scclid']))$conv['snapchat_scclid']=(string)$click['scclid'];
    elseif(!empty($click['ScCid']))$conv['snapchat_scclid']=(string)$click['ScCid'];
    foreach((array)($click['utm']??[]) as $uk=>$uv){
        if(str_starts_with((string)$uk,'utm_'))$conv[(string)$uk]=(string)$uv;
    }
    $conv['attribution_resolved_at']=gmdate('c');
    $conv['updated_at']=gmdate('c');
}
function mark_unknown(array &$conv,string $reason):void{
    $conv['traffic_source_key']='unknown';
    $conv['traffic_source_label']='Unknown';
    $conv['traffic_source_confidence']='none';
    $conv['traffic_source_reason']=$reason;
    $conv['attribution_match_method']='timestamp_unknown';
    $conv['attribution_resolved_at']=gmdate('c');
    $conv['updated_at']=gmdate('c');
}

$f=req($feed);
if(!$f['ok']||!is_array($f['json'])){echo json_encode(['ok'=>false,'stage'=>'feed','status'=>$f['status'],'error'=>$f['error']]);exit(1);}
$records=is_array($f['json']['records']??null)?$f['json']['records']:[];
$state=jload($stateFile);
$sent=0;$skipped=0;$failed=0;$details=[];
foreach($records as $clickId=>$row){
    if(!is_array($row))continue;
    $row['client_id']='cl_0e6efd258397db';
    $row['click_id']=(string)$clickId;
    $hash=sha1(json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    if(($state[$clickId]['hash']??'')===$hash){$skipped++;continue;}
    $r=req($hub,'POST',$row);
    if($r['ok']){
        $sent++;$state[$clickId]=['hash'=>$hash,'synced_at'=>gmdate('c')];
    }else{
        $failed++;$details[]=['click_id'=>$clickId,'status'=>$r['status'],'error'=>$r['error']];
    }
}
jsave($stateFile,$state);

// Resolve timestamp-pending leads for P Care and Almowahid after the complete 5-minute click window closes.
$base=__DIR__.'/data';
$clickFile=$base.'/chatlink_clicks.json';
$convFile=$base.'/conversations.json';
$clicks=jload($clickFile);
$convs=jload($convFile);
$now=time();
$resolved=0;$ambiguous=0;$expiredUnknown=0;$clickChanged=false;$convChanged=false;

$pending=[];
foreach($convs as $cid=>$conv){
    if(!is_array($conv))continue;
    $convClient=(string)($conv['client_id']??'');
    if(!isset($attributionClients[$convClient]))continue;
    if((string)($conv['attribution_match_method']??'')!=='timestamp_pending')continue;
    $first=strtotime((string)($conv['first_seen_at']??''));
    if(!$first)continue;
    $pending[$cid]=['first'=>$first,'business'=>norm_phone((string)($conv['business_number']??'')),'client'=>$convClient];
}

$closedClicks=[];
foreach($clicks as $clickId=>$row){
    if(!is_array($row)||!empty($row['matched_at']))continue;
    $clickClient=(string)($row['client_id']??'');
    if(!isset($attributionClients[$clickClient]))continue;
    $t=click_epoch($row);
    if(!$t||$t+300>$now)continue; // wait for full 5-minute window
    $closedClicks[$clickId]=['t'=>$t,'business'=>norm_phone((string)($row['business_number']??'')),'client'=>$clickClient,'row'=>$row];
}

$handledConvs=[];$handledClicks=[];
foreach($closedClicks as $clickId=>$meta){
    if(isset($handledClicks[$clickId]))continue;
    $candidates=[];
    foreach($pending as $cid=>$p){
        if(isset($handledConvs[$cid]))continue;
        if(($p['client']??'')!==($meta['client']??''))continue;
        if($p['first']<$meta['t']||$p['first']>$meta['t']+300)continue;
        if($meta['business']!==''&&$p['business']!==''&&$meta['business']!==$p['business'])continue;
        $candidates[]=$cid;
    }

    if(count($candidates)===1){
        $cid=$candidates[0];
        // A conversation must itself have exactly one closed click in the prior 5 minutes.
        $candidateClicks=[];
        foreach($closedClicks as $otherId=>$other){
            if(isset($handledClicks[$otherId]))continue;
            if(($other['client']??'')!==($pending[$cid]['client']??''))continue;
            if($other['t']>$pending[$cid]['first']||$other['t']<$pending[$cid]['first']-300)continue;
            if($other['business']!==''&&$pending[$cid]['business']!==''&&$other['business']!==$pending[$cid]['business'])continue;
            $candidateClicks[]=$otherId;
        }
        if(count($candidateClicks)===1){
            copy_click_attribution($convs[$cid],$clicks[$clickId],$clickId);
            $clicks[$clickId]['matched_at']=gmdate('c');
            $clicks[$clickId]['matched_customer']=(string)($convs[$cid]['customer_number']??'');
            $clicks[$clickId]['matched_business']=(string)($convs[$cid]['business_number']??'');
            $clicks[$clickId]['match_method']='single_unique_browser_5m_window';
            $handledConvs[$cid]=true;$handledClicks[$clickId]=true;
            $resolved++;$clickChanged=true;$convChanged=true;
        }else{
            mark_unknown($convs[$cid],'ambiguous_multiple_clicks_within_5m');
            $handledConvs[$cid]=true;$ambiguous++;$convChanged=true;
            foreach($candidateClicks as $oid){
                $clicks[$oid]['matched_at']=gmdate('c');
                $clicks[$oid]['match_method']='ambiguous_multiple_clicks';
                $handledClicks[$oid]=true;$clickChanged=true;
            }
        }
    }elseif(count($candidates)>1){
        foreach($candidates as $cid){
            mark_unknown($convs[$cid],'ambiguous_multiple_senders_within_5m');
            $handledConvs[$cid]=true;$ambiguous++;$convChanged=true;
        }
        $clicks[$clickId]['matched_at']=gmdate('c');
        $clicks[$clickId]['match_method']='ambiguous_multiple_senders';
        $handledClicks[$clickId]=true;$clickChanged=true;
    }else{
        $clicks[$clickId]['matched_at']=gmdate('c');
        $clicks[$clickId]['match_method']='expired_no_message';
        $handledClicks[$clickId]=true;$clickChanged=true;
    }
}

// A pending message that is older than 5 minutes with no usable click is explicitly Unknown.
foreach($pending as $cid=>$p){
    if(isset($handledConvs[$cid]))continue;
    if($p['first']+300>$now)continue;
    $hasAny=false;
    foreach($clicks as $row){
        if(!is_array($row)||(string)($row['client_id']??'')!==($p['client']??''))continue;
        $t=strtotime((string)($row['captured_at']??''));
        if(!$t||$t>$p['first']||$t<$p['first']-300)continue;
        $b=norm_phone((string)($row['business_number']??''));
        if($b!==''&&$p['business']!==''&&$b!==$p['business'])continue;
        $hasAny=true;break;
    }
    if(!$hasAny){
        mark_unknown($convs[$cid],'no_click_within_5m');
        $expiredUnknown++;$convChanged=true;
    }
}
if($clickChanged)jsave($clickFile,$clicks);
if($convChanged)jsave($convFile,$convs);

echo json_encode([
    'ok'=>$failed===0,'records'=>count($records),'sent'=>$sent,'skipped'=>$skipped,'failed'=>$failed,
    'resolver_clients'=>array_keys($attributionClients),'resolved_5m'=>$resolved,'ambiguous_5m'=>$ambiguous,'unknown_no_click'=>$expiredUnknown,'details'=>$details
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
