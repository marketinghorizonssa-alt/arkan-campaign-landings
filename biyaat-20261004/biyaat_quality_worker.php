<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/biyaat_quality.php';
$secure='/home/u878466595/.marketing/biyaat';if(!is_dir($secure))mkdir($secure,0700,true);
$lock=fopen($secure.'/worker.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){echo '{"ok":true,"status":"already_running"}';exit;}
function bw_json(string $p):array{$v=json_decode((string)@file_get_contents($p),true);return is_array($v)?$v:[];}
function bw_save(string $p,array $v):void{$t=$p.'.tmp.'.bin2hex(random_bytes(4));if(file_put_contents($t,json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),LOCK_EX)===false)throw new RuntimeException('write_failed');chmod($t,0600);if(!rename($t,$p))throw new RuntimeException('rename_failed');}
function bw_rows(string $p):array{$o=[];if($fh=@fopen($p,'rb')){while(($l=fgets($fh))!==false){$r=json_decode($l,true);if(is_array($r))$o[]=$r;}fclose($fh);}return$o;}
function bw_append(string $p,array $r):void{$fh=fopen($p,'ab');flock($fh,LOCK_EX);fwrite($fh,json_encode($r,JSON_UNESCAPED_SLASHES)."\n");flock($fh,LOCK_UN);fclose($fh);chmod($p,0600);}
function bw_send(string $token,array $payload):array{
    $ch=curl_init('https://business-api.tiktok.com/open_api/v1.3/event/track/');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Access-Token: '.$token,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES),CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>false]);$body=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_errno($ch);curl_close($ch);$j=is_string($body)?json_decode($body,true):null;$code=is_array($j)?(int)($j['code']??-1):-1;return ['ok'=>$err===0&&$http>=200&&$http<300&&$code===0,'http'=>$http,'code'=>$code,'message'=>is_array($j)?substr((string)($j['message']??''),0,250):'transport_error'];
}
$cfg=bw_json($secure.'/config.json');$start=(int)($cfg['not_before_ts']??time());$state=bw_json($secure.'/state.json');$visits=[];
if(in_array('--preflight',$argv??[],true)){$token=trim((string)@file_get_contents('/home/u878466595/.marketing/tiktok/offline/7689940634064519189/access_token'));$r=$token!==''?bw_send($token,['event_source'=>'web','event_source_id'=>'DAUJ4C3C77U2INVDGUD0','data'=>[]]):['ok'=>false,'message'=>'token_missing'];bw_save($secure.'/preflight.json',$r);echo json_encode($r,JSON_UNESCAPED_SLASHES);exit;}
foreach(bw_rows($secure.'/attribution.jsonl')as$v)if(empty($v['test'])&&(int)($v['created_ts']??0)>=$start&&(int)($v['created_ts']??0)>=time()-30*86400)$visits[(string)$v['ref']]=$v;
$convs=bw_json(__DIR__.'/data/conversations.json');$fileHash=hash_file('sha256',__DIR__.'/data/conversations.json');$evaluations=[];$changes=[];$counts=[];$candidates=[];$synced=0;$blocked=0;
foreach($convs as$id=>$c){
    if(($c['client_id']??'')!=='cl_d554e1c6bfe460'||($c['ycloud_connection_id']??'')!=='yc_b5d5c813cc6559')continue;
    $q=bq_stage($c);$stage=(string)$q['stage'];$counts[$stage]=($counts[$stage]??0)+1;$evaluations[$id]=['stage'=>$stage,'confidence'=>$q['confidence'],'score'=>$q['score'],'reason'=>$q['reason'],'evaluated_message_at'=>$c['last_message_at']??'','evaluated_at'=>gmdate('c')];
    if(!bq_manual($c)&&(($c['current_tag']??'')!==$stage||($c['quality_profile']??'')!=='biyaat_semantic_v1')){$changes[$id]=['old'=>$c['current_tag']??'','message_at'=>$c['last_message_at']??'','q'=>$q];}
    if(!in_array($stage,['interested','qualified','converted'],true))continue;
    $visit=null;foreach((array)($c['recent_messages']??[])as$m){if(!str_contains((string)($m['direction']??''),'inbound'))continue;if(preg_match_all('/BIA-AT-[A-F0-9]{24}/i',(string)($m['text']??''),$matches)){foreach($matches[0]as$ref){$r=$visits[strtoupper($ref)]??null;if($r&&(!$visit||$r['created_ts']>$visit['created_ts']))$visit=$r;}}}
    if(!$visit){$blocked++;continue;}
    $when=strtotime((string)($q['signal_at']??$c['tagged_at']??$c['last_message_at']??''))?:0;
    if($when<(int)$visit['created_ts']||$when<$start||$when>time()+300){$blocked++;continue;}
    $key=hash('sha256','cl_d554e1c6bfe460|'.$id.'|first_interested');$prev=$state[$key]??[];
    if(!empty($prev['sent'])||(int)($prev['next_retry_at']??0)>time())continue;
    $phone=preg_replace('/\D+/','',(string)($c['customer_number']??''))??'';if(str_starts_with($phone,'00'))$phone=substr($phone,2);if($phone===''||empty($visit['ttclid'])){$blocked++;continue;}
    $user=['phone'=>hash('sha256','+'.$phone),'external_id'=>hash('sha256','cl_d554e1c6bfe460|'.$id),'ttclid'=>$visit['ttclid']];
    if(!empty($visit['ttp']))$user['ttp']=$visit['ttp'];if(!empty($visit['ip']))$user['ip']=$visit['ip'];if(!empty($visit['user_agent']))$user['user_agent']=$visit['user_agent'];
    if(count($candidates)<20)$candidates[$key]=['event_source'=>'web','event_source_id'=>'DAUJ4C3C77U2INVDGUD0','data'=>[['event'=>'Contact','event_time'=>$when,'event_id'=>'bia_interest_'.$key,'user'=>$user,'page'=>['url'=>$visit['page_url']],'properties'=>['status'=>'interested']]]];
}
// Merge only records unchanged since the read; a newly received message always wins.
if($changes){$fresh=bw_json(__DIR__.'/data/conversations.json');foreach($changes as$id=>$x){if(!isset($fresh[$id])||bq_manual($fresh[$id])||($fresh[$id]['last_message_at']??'')!==$x['message_at']||($fresh[$id]['current_tag']??'')!==$x['old'])continue;$q=$x['q'];$fresh[$id]['current_tag']=$q['stage'];$fresh[$id]['quality_profile']='biyaat_semantic_v1';$fresh[$id]['quality_score']=$q['score'];$fresh[$id]['auto_label_reason']=$q['reason'];$fresh[$id]['auto_label_confidence']=$q['confidence'];$fresh[$id]['tag_source']='automation';$fresh[$id]['tagged_at']=gmdate('c');$synced++;}if($synced>0&&hash_file('sha256',__DIR__.'/data/conversations.json')===hash('sha256',json_encode($convs,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)))bw_save(__DIR__.'/data/conversations.json',$fresh);elseif($synced>0&&hash_file('sha256',__DIR__.'/data/conversations.json')===$fileHash)bw_save(__DIR__.'/data/conversations.json',$fresh);else$synced=0;}
bw_save($secure.'/quality.json',['at'=>gmdate('c'),'stages'=>$counts,'records'=>$evaluations]);
$tokenPath='/home/u878466595/.marketing/tiktok/offline/7689940634064519189/access_token';$token=is_file($tokenPath)?trim((string)file_get_contents($tokenPath)):'';$sent=0;$failed=0;
foreach($candidates as$key=>$payload){if($token===''){$failed++;continue;}$r=bw_send($token,$payload);$attempts=(int)($state[$key]['attempts']??0)+1;$state[$key]=['sent'=>$r['ok'],'event_id'=>$payload['data'][0]['event_id'],'attempts'=>$attempts,'next_retry_at'=>$r['ok']?0:time()+min(21600,60*(2**min(8,$attempts))),'last_api_code'=>$r['code'],'at'=>gmdate('c')];bw_append($secure.'/web_delivery.jsonl',['event_id'=>$payload['data'][0]['event_id'],'success'=>$r['ok'],'http_status'=>$r['http'],'api_code'=>$r['code'],'message'=>$r['message'],'at'=>gmdate('c')]);if($r['ok'])$sent++;else$failed++;}
bw_save($secure.'/state.json',$state);
$result=['ok'=>$failed===0,'at'=>gmdate('c'),'classifier'=>'biyaat_semantic_v1','stages'=>$counts,'crm_labels_updated'=>$synced,'attributed_visits'=>count($visits),'eligible_interested'=>count($candidates),'sent'=>$sent,'failed'=>$failed,'without_verified_website_visit'=>$blocked,'token_present'=>$token!=='','zero_backfill'=>true];bw_save($secure.'/health.json',$result);echo json_encode($result,JSON_UNESCAPED_SLASHES);
