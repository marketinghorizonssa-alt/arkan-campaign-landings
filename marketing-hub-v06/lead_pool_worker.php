<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$base = __DIR__ . '/data';
$secure = dirname(__DIR__, 4) . '/.marketing';
$configFile = __DIR__ . '/lead_pool_config.json';
$poolRoot = $secure . '/lead_pools';
$secretFile = $secure . '/lead_pool_secret';

function lp_json(string $file, array $default=[]): array {
    if (!is_file($file)) return $default;
    $v = json_decode((string)@file_get_contents($file), true);
    return is_array($v) ? $v : $default;
}
function lp_s(mixed $v): string { return is_scalar($v) ? trim((string)$v) : ''; }
function lp_phone(string $v): string {
    $d = preg_replace('/\D+/', '', $v) ?? '';
    if (str_starts_with($d, '00')) $d = substr($d, 2);
    return $d;
}
function lp_safe_id(string $v): string { return preg_replace('/[^A-Za-z0-9_.-]/', '_', $v) ?: '_'; }
function lp_mkdir(string $dir): void { if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('mkdir_failed:' . $dir); }
function lp_atomic_json(string $file, array $data): void {
    lp_mkdir(dirname($file));
    $tmp = $file . '.tmp.' . bin2hex(random_bytes(4));
    $raw = json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    if ($raw === false || @file_put_contents($tmp, $raw . "\n", LOCK_EX) === false) throw new RuntimeException('write_failed:' . $file);
    @chmod($tmp, 0600);
    if (!@rename($tmp, $file)) { @unlink($tmp); throw new RuntimeException('rename_failed:' . $file); }
}
function lp_write_jsonl(string $file, array $rows): void {
    lp_mkdir(dirname($file));
    $tmp = $file . '.tmp.' . bin2hex(random_bytes(4));
    $fh = @fopen($tmp, 'wb'); if (!$fh) throw new RuntimeException('open_failed:' . $file);
    foreach ($rows as $row) fwrite($fh, json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n");
    fclose($fh); @chmod($tmp, 0600);
    if (!@rename($tmp, $file)) { @unlink($tmp); throw new RuntimeException('rename_failed:' . $file); }
}
function lp_append_jsonl(string $file, array $row): void {
    lp_mkdir(dirname($file));
    $fh = @fopen($file, 'ab'); if (!$fh) throw new RuntimeException('append_failed:' . $file);
    @flock($fh, LOCK_EX); fwrite($fh, json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n"); @flock($fh, LOCK_UN); fclose($fh); @chmod($file, 0600);
}
function lp_hmac(string $secret, string ...$parts): string { return hash_hmac('sha256', implode('|', $parts), $secret); }
function lp_msg_text(array $m): string {
    $type = lp_s($m['type'] ?? '');
    if ($type === 'text') return lp_s($m['text']['body'] ?? '');
    foreach (['image','video','document','audio'] as $k) if ($type === $k && isset($m[$k]) && is_array($m[$k])) return lp_s($m[$k]['caption'] ?? '');
    return '';
}
function lp_refs(string $text): array {
    $ref=''; $lead='';
    if (preg_match('/ARK-AT-[A-Z0-9]{12,40}/i', $text, $m)) $ref = strtoupper($m[0]);
    if (preg_match('/ARK-WEB-[0-9]{8}-[0-9]{6}-[A-F0-9]{6}/i', $text, $m)) $lead = strtoupper($m[0]);
    return [$ref,$lead];
}
function lp_source(string $raw): string {
    $s = strtolower(trim($raw));
    if (in_array($s, ['google','tiktok','meta','snapchat','organic','unknown'], true)) return $s;
    if (in_array($s, ['website','direct','referral'], true)) return 'organic';
    if (str_contains($s,'google')) return 'google';
    if (str_contains($s,'tiktok')) return 'tiktok';
    if (str_contains($s,'facebook') || str_contains($s,'instagram') || str_contains($s,'meta')) return 'meta';
    if (str_contains($s,'snap')) return 'snapchat';
    return $s === '' ? 'unknown' : 'organic';
}
function lp_client_cfg(array $config, string $clientId): array {
    $d = is_array($config['client_defaults'] ?? null) ? $config['client_defaults'] : [];
    $c = is_array($config['clients'][$clientId] ?? null) ? $config['clients'][$clientId] : [];
    return array_replace($d, $c);
}
function lp_capture(string $file, array $get=[]): array {
    $_GET = $get; $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start(); include $file; $raw = ob_get_clean();
    $v = json_decode((string)$raw, true);
    return is_array($v) ? $v : [];
}

lp_mkdir($secure); lp_mkdir($poolRoot);
if (!is_file($secretFile)) { @file_put_contents($secretFile, bin2hex(random_bytes(32)), LOCK_EX); @chmod($secretFile, 0600); }
$secret = trim((string)@file_get_contents($secretFile));
if ($secret === '') throw new RuntimeException('lead_pool_secret_unavailable');
$config = lp_json($configFile, []);
if (!$config) throw new RuntimeException('lead_pool_config_unavailable');

$baseQuality = lp_capture(__DIR__ . '/lead_quality.php', ['limit'=>'500']);
putenv('Q2_QUERY=limit=5000');
$contextQuality = lp_capture(__DIR__ . '/local_quality_v2.php');
putenv('Q2_QUERY');

$baseByLead=[];
foreach (($baseQuality['leads'] ?? []) as $r) if (is_array($r) && lp_s($r['lead_id'] ?? '') !== '') $baseByLead[lp_s($r['lead_id'])] = $r;
$evalByLead=[];
foreach (($contextQuality['leads'] ?? []) as $r) if (is_array($r) && lp_s($r['lead_id'] ?? '') !== '') $evalByLead[lp_s($r['lead_id'])] = $r;

$conversationStore = lp_json($base . '/conversations.json', []);
$connections = lp_json($base . '/ycloud_connections.json', []); $connClient=[];
foreach ($connections as $k=>$v) if (is_array($v)) { $id=lp_s($v['id'] ?? (is_string($k)?$k:'')); if($id!=='') $connClient[$id]=lp_s($v['client_id']??''); }

$identity=[];
$rawFile=$base.'/raw_events.jsonl';
if (is_file($rawFile) && ($fh=@fopen($rawFile,'rb'))) {
    while (($line=fgets($fh)) !== false) {
        $row=json_decode($line,true); if(!is_array($row))continue;
        $payload=is_array($row['payload']??null)?$row['payload']:[]; $type=lp_s($row['type']??$payload['type']??'');
        $m=null; $in=false;
        if($type==='whatsapp.inbound_message.received' && isset($payload['whatsappInboundMessage'])){$m=$payload['whatsappInboundMessage'];$in=true;}
        elseif($type==='whatsapp.smb.message.echoes' && isset($payload['whatsappMessage'])){$m=$payload['whatsappMessage'];}
        elseif($type==='whatsapp.smb.history' && isset($payload['whatsappInboundMessage'])){$m=$payload['whatsappInboundMessage'];$in=true;}
        elseif($type==='whatsapp.smb.history' && isset($payload['whatsappMessage'])){$m=$payload['whatsappMessage'];}
        if(!is_array($m))continue;
        $waba=lp_s($m['wabaId']??''); $customer=lp_s($in?($m['from']??''):($m['to']??'')); if($waba===''||$customer==='')continue;
        $conv=substr(hash('sha256',$waba.'|'.$customer),0,24); $conn=lp_s($row['ycloud_connection_id']??''); $cid=$connClient[$conn]??'';
        if(!isset($identity[$conv])) $identity[$conv]=['client_id'=>$cid,'phone'=>$customer,'waba_id'=>$waba,'ref_token'=>'','attribution_lead_id'=>'','ctwa_clid'=>''];
        if($identity[$conv]['client_id']===''&&$cid!=='')$identity[$conv]['client_id']=$cid;
        if($in){
            [$ref,$lid]=lp_refs(lp_msg_text($m)); if($ref!==''&&$identity[$conv]['ref_token']==='')$identity[$conv]['ref_token']=$ref; if($lid!==''&&$identity[$conv]['attribution_lead_id']==='')$identity[$conv]['attribution_lead_id']=$lid;
            $rr=is_array($m['referral']??null)?$m['referral']:[]; $ctwa=lp_s($rr['ctwa_clid']??$rr['ctwaClid']??''); if($ctwa!==''&&$identity[$conv]['ctwa_clid']==='')$identity[$conv]['ctwa_clid']=$ctwa;
        }
    }
    fclose($fh);
}

$byRef=[];$byAttrLead=[];
$attrFile=$base.'/attribution_events.jsonl';
if(is_file($attrFile)&&($fh=@fopen($attrFile,'rb'))){while(($line=fgets($fh))!==false){$r=json_decode($line,true);if(!is_array($r))continue;$ref=strtoupper(lp_s($r['ref_token']??''));$lid=strtoupper(lp_s($r['lead_id']??''));if($ref!=='')$byRef[$ref]=$r;if($lid!=='')$byAttrLead[$lid]=$r;}fclose($fh);}

$allLeadIds=array_values(array_unique(array_merge(array_keys($baseByLead),array_keys($evalByLead),array_keys($identity))));
$clients=[];$quarantine=[];
foreach($allLeadIds as $convId){
    $b=$baseByLead[$convId]??[]; $e=$evalByLead[$convId]??[]; $id=$identity[$convId]??[];
    $clientId=lp_s($e['client_id']??$b['client_id']??$id['client_id']??'');
    if($clientId===''){ $quarantine[]=['conversation_id'=>$convId,'reason'=>'missing_client_id']; continue; }
    $cfg=lp_client_cfg($config,$clientId); if(isset($cfg['enabled'])&&!$cfg['enabled'])continue;
    $phone=lp_phone(lp_s($id['phone']??''));
    $contactId='ct_' . substr(lp_hmac($secret,$clientId,'contact',$phone!==''?$phone:$convId),0,32);
    $leadId='ld_' . substr(lp_hmac($secret,$clientId,'lead',$convId),0,32);
    $manualTag=strtolower(lp_s($b['crm_status']??'')); $tagSource=strtolower(lp_s($b['tag_source']??''));
    $manual=false; $manualStage='';
    if($manualTag!=='' && $tagSource!=='automation'){
        $manual=true; $manualStage=match($manualTag){'purchased'=>'converted','interested'=>'interested','qualified'=>'qualified','lost'=>'lost','unqualified'=>'unqualified',default=>''};
    }
    $calibrated=(bool)($cfg['quality_calibrated']??false);
    $conv=is_array($conversationStore[$convId]??null)?$conversationStore[$convId]:[];
    $autoTag=strtolower(lp_s($conv['current_tag']??''));
    $autoStage=match($autoTag){'message_received'=>'message_received','purchased','converted'=>'converted','interested'=>'interested','qualified'=>'qualified','lost'=>'lost','unqualified'=>'unqualified',default=>''};
    $acceptAutomation=(bool)($cfg['accept_automation_stage']??false);
    $stage=$manualStage!==''?$manualStage:(($acceptAutomation&&$autoStage!=='')?$autoStage:($calibrated?strtolower(lp_s($e['stage']??'message_received')):'message_received'));
    if($stage==='new'||$stage==='')$stage='message_received';
    if(!in_array($stage,$config['stages']??[],true))$stage='message_received';

    $ref=strtoupper(lp_s($id['ref_token']??'')); $attrLead=strtoupper(lp_s($id['attribution_lead_id']??'')); $attr=null; $join='';
    if($ref!==''&&isset($byRef[$ref])){$attr=$byRef[$ref];$join='visit_ref';}
    elseif($attrLead!==''&&isset($byAttrLead[$attrLead])){$attr=$byAttrLead[$attrLead];$join='lead_id';}
    $tracking=is_array($attr['tracking']??null)?$attr['tracking']:[];
    $source=$attr?lp_source(lp_s($attr['source']??'')):lp_source(lp_s(($e['source']['source']??null)?:($b['source']??($conv['traffic_source_key']??''))));
    $sourceConfidence=$attr?'high':lp_s(($e['source']['confidence']??null)?:($b['source_confidence']??'low'));
    $sourceReason=$attr?('server_attribution_'.$join):lp_s(($e['source']['reason']??null)?:($b['source_reason']??'no_evidence'));
    $native=[
        'gclid'=>lp_s($tracking['gclid']??($conv['google_gclid']??'')),
        'wbraid'=>lp_s($tracking['wbraid']??($conv['google_wbraid']??'')),
        'gbraid'=>lp_s($tracking['gbraid']??($conv['google_gbraid']??'')),
        'ttclid'=>lp_s($tracking['ttclid']??($conv['tiktok_ttclid']??'')),
        'fbclid'=>lp_s($tracking['fbclid']??($conv['meta_fbclid']??'')),
        'ctwa_clid'=>lp_s($id['ctwa_clid']??($conv['ctwa_clid']??'')),
        'sccid'=>lp_s($tracking['sccid']??$tracking['ScCid']??$tracking['sc_click_id']??($conv['snapchat_scclid']??''))
    ];
    $campaign=['campaign_id'=>lp_s($tracking['campaign_id']??''),'campaign_name'=>lp_s($tracking['campaign_name']??''),'ad_group_id'=>lp_s($tracking['ad_group_id']??''),'ad_group_name'=>lp_s($tracking['ad_group_name']??''),'ad_id'=>lp_s($tracking['ad_id']??''),'keyword'=>lp_s($tracking['keyword']??''),'match_type'=>lp_s($tracking['match_type']??'')];
    $phoneHash=$phone!==''?hash('sha256',$phone):'';
    $record=[
        'client_id'=>$clientId,'client_name'=>lp_s($e['client_name']??''),'contact_id'=>$contactId,'lead_id'=>$leadId,'conversation_id'=>$convId,
        'stage'=>$stage,'quality_score'=>(int)($e['quality_score']??0),'confidence'=>(float)($e['confidence']??0),'quality_profile'=>lp_s($cfg['quality_profile']??($calibrated?'calibrated':'uncalibrated')),
        'manual_override'=>$manual,'source'=>$source,'source_confidence'=>$sourceConfidence,'source_reason'=>$sourceReason,'campaign'=>$campaign,'native_ids'=>$native,
        'first_party'=>['phone_sha256'=>$phoneHash,'email_sha256'=>'','contact_id'=>$contactId],
        'first_seen_at'=>lp_s($e['first_seen_at']??$b['first_seen_at']??''),'last_seen_at'=>lp_s($e['last_seen_at']??$b['last_message_at']??''),
        'attributes'=>is_array($e['attributes']??null)?$e['attributes']:[],'reasons'=>is_array($e['reasons']??null)?$e['reasons']:[]
    ];
    $clients[$clientId]['records'][$leadId]=$record;
    $clients[$clientId]['identity'][$leadId]=['client_id'=>$clientId,'contact_id'=>$contactId,'lead_id'=>$leadId,'conversation_id'=>$convId,'phone'=>$phone,'phone_sha256'=>$phoneHash,'waba_id'=>lp_s($id['waba_id']??''),'updated_at'=>gmdate('c')];
}

$platforms=$config['platforms']??['google','tiktok','meta','snapchat']; $exportStages=$config['quality_exports']??['interested','qualified','unqualified','converted'];
$result=['ok'=>true,'version'=>'lead-pool-v1','generated_at'=>gmdate('c'),'clients'=>[],'quarantine'=>count($quarantine),'routes_queued'=>0,'routes_blocked_consent'=>0];
foreach($clients as $clientId=>$bundle){
    $cfg=lp_client_cfg($config,$clientId); $dir=$poolRoot.'/'.lp_safe_id($clientId); lp_mkdir($dir);
    $records=$bundle['records']; $previous=lp_json($dir.'/state.json',[]); $firstRun=!is_file($dir.'/state.json'); $nextState=[]; $stageSource=[];
    foreach($records as $leadId=>$r){
        $stage=$r['stage'];$source=$r['source'];$stageSource[$stage][$source][]=$r;
        $prev=is_array($previous[$leadId]??null)?$previous[$leadId]:[]; $prevStage=lp_s($prev['stage']??'');
        if(!$firstRun && $prevStage!=='' && $prevStage!==$stage && in_array($stage,$exportStages,true)){
            $eventId='ev_' . substr(lp_hmac($secret,$clientId,$leadId,$prevStage,$stage,lp_s($r['last_seen_at'])),0,32);
            $polarity=lp_s($config['stage_polarity'][$stage]??'neutral'); $origin=$source;
            $broadcastAll=(bool)($cfg['broadcast_qualified_to_all_platforms']??false);
            if($broadcastAll && in_array($stage,['qualified','converted'],true)){
                foreach($platforms as $target){
                    $route=[
                        'event_id'=>$eventId.'_'.$target,
                        'client_id'=>$clientId,'lead_id'=>$leadId,'contact_id'=>$r['contact_id'],
                        'stage'=>$stage,'polarity'=>$polarity,'origin_source'=>$origin,'target_platform'=>$target,
                        'route_type'=>'first_party_offline',
                        'attribution'=>'platform_match_from_real_crm_outcome',
                        'first_party'=>$r['first_party'],
                        'native_ids'=>$r['native_ids'],
                        'campaign'=>$r['campaign'],
                        'occurred_at'=>$r['last_seen_at']?:gmdate('c'),
                        'delivery_status'=>'queued'
                    ];
                    lp_append_jsonl($dir.'/routing_outbox.jsonl',$route);$result['routes_queued']++;
                }
            } else {
                if(in_array($origin,$platforms,true) && (bool)($cfg['source_native_delivery']??true)){
                    $route=['event_id'=>$eventId,'client_id'=>$clientId,'lead_id'=>$leadId,'contact_id'=>$r['contact_id'],'stage'=>$stage,'polarity'=>$polarity,'origin_source'=>$origin,'target_platform'=>$origin,'route_type'=>'online_conversion','attribution'=>'source_native','native_ids'=>$r['native_ids'],'campaign'=>$r['campaign'],'occurred_at'=>$r['last_seen_at']?:gmdate('c'),'delivery_status'=>'queued'];
                    lp_append_jsonl($dir.'/routing_outbox.jsonl',$route);$result['routes_queued']++;
                }
                foreach($platforms as $target){
                    if($target===$origin)continue;
                    $consent=(bool)($cfg['consent_verified']??false); $status=$consent?'queued':'blocked_consent';
                    $route=['event_id'=>$eventId.'_'.$target,'client_id'=>$clientId,'lead_id'=>$leadId,'contact_id'=>$r['contact_id'],'stage'=>$stage,'polarity'=>$polarity,'origin_source'=>$origin,'target_platform'=>$target,'route_type'=>'first_party_offline','attribution'=>'none_do_not_credit_target_platform','first_party'=>$r['first_party'],'occurred_at'=>$r['last_seen_at']?:gmdate('c'),'delivery_status'=>$status];
                    lp_append_jsonl($dir.'/routing_outbox.jsonl',$route); if($status==='queued')$result['routes_queued']++;else$result['routes_blocked_consent']++;
                }
            }
        }
        $nextState[$leadId]=['stage'=>$stage,'source'=>$source,'last_seen_at'=>$r['last_seen_at'],'updated_at'=>gmdate('c')];
    }
    foreach(($config['stages']??[]) as $stage)foreach(($config['sources']??[]) as $source)lp_write_jsonl($dir.'/stages/'.$stage.'/'.$source.'.jsonl',$stageSource[$stage][$source]??[]);
    lp_atomic_json($dir.'/current.json',['client_id'=>$clientId,'generated_at'=>gmdate('c'),'records'=>$records]);
    lp_write_jsonl($dir.'/identity_map.jsonl',array_values($bundle['identity']));
    lp_atomic_json($dir.'/state.json',$nextState);
    $summary=['client_id'=>$clientId,'generated_at'=>gmdate('c'),'first_run_baseline'=>$firstRun,'total'=>count($records),'stages'=>[],'sources'=>[],'matrix'=>[],'quality_calibrated'=>(bool)($cfg['quality_calibrated']??false),'consent_verified'=>(bool)($cfg['consent_verified']??false)];
    foreach($records as $r){$summary['stages'][$r['stage']]=($summary['stages'][$r['stage']]??0)+1;$summary['sources'][$r['source']]=($summary['sources'][$r['source']]??0)+1;$summary['matrix'][$r['stage']][$r['source']]=($summary['matrix'][$r['stage']][$r['source']]??0)+1;}
    lp_atomic_json($dir.'/summary.json',$summary); $result['clients'][$clientId]=$summary;
}
if($quarantine)lp_write_jsonl($poolRoot.'/_quarantine/missing_client.jsonl',$quarantine);
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";
