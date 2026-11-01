<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

$feedConfig=jload(dirname(__DIR__,4).'/.marketing/pcare_click_feed.json');$feed=(string)($feedConfig['feed_url']??'');
$hub='https://marketing.hositee.com/wa_click_attribution.php';
$secure=dirname(__DIR__,4).'/.marketing';
$stateFile=$secure.'/pcare_wa_attr_sync_state.json';
$attributionClients=[];
$cfgRaw=@file_get_contents(__DIR__.'/wa_attribution_clients.json');
$cfgJson=is_string($cfgRaw)?json_decode($cfgRaw,true):null;
foreach((array)($cfgJson['clients']??[]) as $cid=>$row){
    $nums=is_array($row['business_numbers']??null)?$row['business_numbers']:[];
    if($cid!==''&&$nums)$attributionClients[(string)$cid]=(string)$nums[0];
}
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
    $server=0;
    foreach(['captured_at','created_at'] as $k){
        $raw=trim((string)($row[$k]??''));
        if($raw==='')continue;
        $s=strtotime($raw);
        if($s){$server=$s;break;}
    }
    $braw=trim((string)($row['browser_time']??''));
    $browser=$braw!==''?(int)strtotime($braw):0;
    // A click cannot happen after the server received it; a browser clock ahead by >60s is wrong.
    if($browser>0&&$server>0&&$browser>$server+60)return $server;
    if($browser>0)return $browser;
    return $server;
}
function first_customer_epoch(array $conv):int{
    foreach(['first_customer_message_at'] as $key){$t=strtotime((string)($conv[$key]??''));if($t)return$t;}
    $t=strtotime((string)($conv['stage_times']['message_started']??''));if($t)return$t;
    $times=[];foreach((array)($conv['recent_messages']??[]) as $m){
        if(!is_array($m)||!str_contains((string)($m['direction']??''),'inbound'))continue;
        if(in_array(strtolower((string)($m['type']??'')),['','unsupported','reaction','system','unknown','revoke','revoked'],true))continue;
        $mt=strtotime((string)($m['at']??''));if($mt)$times[]=$mt;
    }
    // A truncated preview cannot establish the first-ever message time reliably.
    if($times&&max((int)($conv['valid_inbound_count']??0),(int)($conv['inbound_count']??0))<=count($times))return min($times);
    return strtotime((string)($conv['first_seen_at']??''))?:0;
}
function has_customer_message(array $conv):bool{
    if(max((int)($conv['valid_inbound_count']??0),(int)($conv['inbound_count']??0))>0)return true;
    foreach((array)($conv['recent_messages']??[]) as $m){if(is_array($m)&&str_contains((string)($m['direction']??''),'inbound')&&!in_array(strtolower((string)($m['type']??'')),['','unsupported','reaction','system','unknown','revoke','revoked'],true))return true;}
    return !empty($conv['first_customer_message_at'])||!empty($conv['stage_times']['message_started']);
}
function copy_click_attribution(array &$conv,array $click,string $clickId):void{
    $conv['traffic_source_key']=(string)($click['traffic_source_key']??'website');
    $conv['traffic_source_label']=(string)($click['traffic_source_label']??'Website / HORIZONS Attribution');
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
    $conv['attribution_click_at']=gmdate('c',click_epoch($click));$conv['attribution_message_at']=gmdate('c',first_customer_epoch($conv));$conv['attribution_delay_seconds']=first_customer_epoch($conv)-click_epoch($click);
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

// A failed P Care feed must never block the 5-minute resolver for every other client.
$feedError=null;$records=[];
if($feed!==''){
    $f=req($feed);
    if($f['ok']&&is_array($f['json']))$records=is_array($f['json']['records']??null)?$f['json']['records']:[];
    else $feedError=['stage'=>'feed','status'=>$f['status'],'error'=>$f['error']];
}else $feedError=['stage'=>'feed','error'=>'feed_url_missing'];
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
require_once __DIR__.'/crm_lock.php';crm_data_lock();
$clicks=jload($clickFile);
$convs=jload($convFile);
$now=time();
$resolved=0;$ambiguous=0;$expiredUnknown=0;$clickChanged=false;$convChanged=false;

$pending=[];
foreach($convs as $cid=>$conv){
    if(!is_array($conv))continue;
    $convClient=(string)($conv['client_id']??'');
    if($convClient==='cl_76c4e019afc588')continue; // ARKAN uses the stricter isolated resolver below.
    if(!isset($attributionClients[$convClient]))continue;
    $method=(string)($conv['attribution_match_method']??'');
    $retryLate=$method==='timestamp_unknown'&&(string)($conv['traffic_source_reason']??'')==='no_click_within_5m';
    if($method!=='timestamp_pending'&&!$retryLate)continue;
    if(in_array(strtolower((string)($conv['traffic_source_key']??'')),['tiktok','meta','snapchat','microsoft_ads','linkedin','x'],true))continue;
    $first=first_customer_epoch($conv);
    if(!has_customer_message($conv))continue;
    if(!$first)continue;
    $pending[$cid]=['first'=>$first,'business'=>norm_phone((string)($conv['business_number']??'')),'client'=>$convClient];
}

$closedClicks=[];
foreach($clicks as $clickId=>$row){
    if(!is_array($row))continue;
    if(!empty($row['matched_at'])&&(string)($row['match_method']??'')!=='expired_no_message')continue;
    $clickClient=(string)($row['client_id']??'');
    if($clickClient==='cl_76c4e019afc588')continue;
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
        // A late record must not borrow a click that could belong to another sender.
        $candidateId=$candidates[0];$otherSender=false;
        foreach($convs as $otherId=>$otherConv){
            if((string)$otherId===(string)$candidateId||!is_array($otherConv))continue;
            if((string)($otherConv['client_id']??'')!==($meta['client']??''))continue;
            // Conversations already attributed by token/native evidence used their own click.
            $otherMethod=(string)($otherConv['attribution_match_method']??'');
            if($otherMethod!==''&&$otherMethod!=='timestamp_pending'&&$otherMethod!=='timestamp_unknown')continue;
            if(!has_customer_message($otherConv))continue;
            $otherFirst=first_customer_epoch($otherConv);
            $otherBusiness=norm_phone((string)($otherConv['business_number']??''));
            if($meta['business']!==''&&$otherBusiness!==''&&$meta['business']!==$otherBusiness)continue;
            if($otherFirst&&$otherFirst>=$meta['t']&&$otherFirst<=$meta['t']+300){$otherSender=true;break;}
        }
        if($otherSender)continue;
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
        // Double-clicks by the same visitor (same browser + same Google click id) count as one click.
        if(count($candidateClicks)>1){
            $sigs=[];$latestId='';$latestT=-1;
            foreach($candidateClicks as $oid){
                $r=$clicks[$oid]??[];
                $sigs[]=(string)($r['user_agent']??'').'|'.(string)($r['gclid']??'').(string)($r['gbraid']??'').(string)($r['wbraid']??'');
                $ot=(int)($closedClicks[$oid]['t']??0);
                if($ot>$latestT){$latestT=$ot;$latestId=(string)$oid;}
            }
            $uniq=array_values(array_unique($sigs));
            if(count($uniq)===1&&$uniq[0][0]!=='|'&&$latestId!==''){
                foreach($candidateClicks as $oid){
                    if((string)$oid===$latestId)continue;
                    $clicks[$oid]['matched_at']=gmdate('c');
                    $clicks[$oid]['match_method']='duplicate_same_visitor';
                    $handledClicks[$oid]=true;$clickChanged=true;
                }
                $candidateClicks=[$latestId];$clickId=$latestId;
            }
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
        $t=click_epoch($row);
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
require_once __DIR__.'/arkan_attribution_core_20261101.php';
$arkanAttribution=ara_resolve($convs,$clicks,$now);
if($arkanAttribution['resolved']>0){$clickChanged=true;$convChanged=true;}if($arkanAttribution['ambiguous']+$arkanAttribution['unknown']>0)$convChanged=true;
@file_put_contents(__DIR__.'/_private_status/arkan-attribution-status.json',json_encode(['client_id'=>'cl_76c4e019afc588','checked_at'=>gmdate('c'),'registered'=>isset($attributionClients['cl_76c4e019afc588']),'window_seconds'=>300,'counts'=>$arkanAttribution,'synthetic_conversions'=>0]));
if($clickChanged)jsave($clickFile,$clicks);
if($convChanged)jsave($convFile,$convs);
crm_data_unlock();

echo json_encode([
    'ok'=>$failed===0&&$feedError===null,'feed_error'=>$feedError,'records'=>count($records),'sent'=>$sent,'skipped'=>$skipped,'failed'=>$failed,
    'resolver_clients'=>array_keys($attributionClients),'resolved_5m'=>$resolved,'ambiguous_5m'=>$ambiguous,'unknown_no_click'=>$expiredUnknown,'details'=>$details
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
