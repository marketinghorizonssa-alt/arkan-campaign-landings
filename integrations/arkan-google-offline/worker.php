<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/arkan_google_offline_core.php';
$home=dirname(__DIR__,4);$private=$home.'/.marketing/arkan_google_offline';
if(!is_dir($private)&&!mkdir($private,0700,true))throw new RuntimeException('private_dir_failed');
$lock=fopen($private.'/worker.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){echo '{"ok":true,"state":"already_running"}';exit;}
$dry=in_array('--dry-run',$argv,true);$statusOnly=in_array('--status',$argv,true);
$ledger=ag_json($private.'/ledger.json')+['events'=>[],'requests'=>[],'observed'=>[],'retry_after'=>[]];
$actions=['message_started'=>'7842377643','interested'=>'7842424315','qualified'=>'7842424318','converted'=>'7842424441','lead_received'=>'7842383475','contacted'=>'7842383478'];
$customer='9201402498';$sheet='1MndJdQ9nQJ9wRwEnx7V18T3RiGqBzD9Seea6Aqq08rE';$now=time();$errors=[];$counts=[];$pending=[];$sheetUpdates=[];
function ag_google(string $method,string $url,?array $body=null): array {
 global $home;$key=trim((string)@file_get_contents($home.'/.horizons-google-mcp/mcp-path'));if($key==='')throw new RuntimeException('google_mcp_key_missing');
 $args=['method'=>$method,'url'=>$url];if($body!==null)$args['body']=$body;if($method!=='GET')$args['confirm_write']=true;
 $ch=curl_init('https://google-mcp.thehorizons.sa/mcp?k='.rawurlencode($key));curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>45,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode(['jsonrpc'=>'2.0','id'=>bin2hex(random_bytes(4)),'method'=>'tools/call','params'=>['name'=>'google_api_request','arguments'=>$args]],JSON_THROW_ON_ERROR)]);
 $raw=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($code!==200||!is_string($raw))throw new RuntimeException('google_mcp_transport_'.$code);
 $rpc=json_decode($raw,true);$r=json_decode($rpc['result']['content'][0]['text']??'',true);if(!is_array($r))throw new RuntimeException('invalid_google_response');return $r;
}
function ag_error(array $r): string {$e=$r['error']??[];return is_array($e)?json_encode($e['error']??$e,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):ag_s($e);}
function ag_add(string $record,string $stage,array $click,string $time): void {
 global $pending,$ledger,$counts,$now,$actions;$ev=ag_event($record,$stage,$click,$time,$now);if(!$ev){$counts['invalid_or_missing_time_or_click']=($counts['invalid_or_missing_time_or_click']??0)+1;return;}
 $key=$actions[$stage].':'.$ev['transactionId'];if(isset($ledger['events'][$key]))return;$pending[$stage][$key]=$ev;
}
try {
 if($statusOnly){echo json_encode(ag_json($private.'/status.json'),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
 // Diagnostics report destination processing, never just HTTP acceptance.
 if(!$dry)foreach($ledger['requests'] as $rid=>&$req){
  if(($req['state']??'')!=='PROCESSING'||($req['next_check_at']??0)>$now)continue;
  $r=ag_google('GET','https://datamanager.googleapis.com/v1/requestStatus:retrieve?requestId='.rawurlencode($rid));
  if(empty($r['ok'])){$errors[]=['operation'=>'diagnostics','reason'=>ag_error($r)];$req['next_check_at']=$now+1800;continue;}
  foreach($r['data']['requestStatusPerDestination']??[] as $d){
   if(($d['destination']['operatingAccount']['accountId']??'')!==$customer||($d['destination']['productDestinationId']??'')!==$req['action'])continue;
   $state=$d['requestStatus']??'PROCESSING';$req['diagnostics']=$d;
   if(in_array($state,['SUCCESS','FAILED','FAILURE','PARTIAL_SUCCESS'],true)){
    $n=(int)($d['eventsIngestionStatus']['recordCount']??-1);$errs=$d['errorInfo']['errorCounts']??[];
    $dup=$errs&&!array_diff(array_column($errs,'reason'),['PROCESSING_ERROR_REASON_DUPLICATE_GCLID','PROCESSING_ERROR_REASON_DUPLICATE_TRANSACTION_ID']);
    $req['state']=($state==='SUCCESS'&&$n===count($req['keys']))?'SUCCESS':(($dup&&$n===count($req['keys']))?'ALREADY_PRESENT':'NEEDS_REVIEW');
    foreach($req['keys'] as $k)$ledger['events'][$k]['state']=$req['state'];
   }
  }
  $req['next_check_at']=$now+1800;
 }unset($req);
 $root=dirname(__DIR__).'/arkan-realestate-solutions';require $root.'/app/config.php';
 $db=new PDO('sqlite:'.LEAD_DB_PATH,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $leads=[];foreach($db->query('SELECT * FROM leads') as $r){$id=ag_s($r['lead_id']??'');if($id==='')continue;$leads[$id]=$r;
  if(ag_test($r)||str_contains(ag_s($r['lead_status']??''),'مكرر')||strtolower(ag_s($r['processing_status']??''))==='duplicate'){continue;}
  $click=ag_click($r);if(!$click)continue;$counts['matched_google_form_leads']=($counts['matched_google_form_leads']??0)+1;ag_add('form:'.$id,'lead_received',$click,ag_s($r['submitted_at']??''));
 }
 // The CRM worksheet remains authoritative for evaluated form leads; join by Lead ID, never phone guessing.
 $url='https://sheets.googleapis.com/v4/spreadsheets/'.$sheet.'/values/'.rawurlencode("'خدمة العملاء'!A1:T10000");$r=ag_google('GET',$url);
 if(empty($r['ok']))throw new RuntimeException('crm_sheet_read_'.ag_error($r));$rows=$r['data']['values']??[];$head=$rows[0]??[];
 if(($head[10]??'')!=='Lead ID'||($head[12]??'')!=='حالة العميل (TikTok)'||($head[18]??'')!=='تاريخ تغيير حالة العميل')throw new RuntimeException('crm_sheet_schema_changed');
 $seen=[];foreach(array_slice($rows,1) as $i=>$row){$id=ag_s($row[10]??'');if($id==='')continue;if(isset($seen[$id])){$counts['duplicate_crm_rows']=($counts['duplicate_crm_rows']??0)+1;continue;}$seen[$id]=true;
  $lead=$leads[$id]??null;if(!$lead||ag_test($lead)||str_contains(ag_s($lead['lead_status']??''),'مكرر'))continue;$click=ag_click($lead);if(!$click)continue;
  $raw=ag_s($row[12]??'');$obs=ag_observe($ledger['observed'],$id,$raw,ag_s($row[18]??''),$now,$dry);$stage=$obs['stage'];
  if(ag_negative($raw))$display='غير مؤهل — لا تُرسل مراحل الجودة';
  elseif($stage&&!$obs['time']){$counts['historical_status_missing_time']=($counts['historical_status_missing_time']??0)+1;$display='معلّق: تاريخ تغيير الحالة غير محفوظ';}
  elseif($stage){$at=ag_time($obs['time']);$submitted=ag_time(ag_s($lead['submitted_at']??''));if(!$at||!$submitted||$at<$submitted){$display='معلّق: التاريخ يسبق استلام الإحالة';$counts['invalid_stage_time']=($counts['invalid_stage_time']??0)+1;}
   else{ag_add('form:'.$id,$stage,$click,$obs['time']);$display='مرحلة '.$stage.' جاهزة للمزامنة';}}
  else $display='مرتبط بمعرّف جوجل — بانتظار تقييم الجودة';
  if(!$dry&&$obs['write_time']!==null)$sheetUpdates[]=['range'=>"'خدمة العملاء'!S".($i+2),'values'=>[[$obs['write_time']]]];
  if(!$dry&&($row[19]??'')!==$display)$sheetUpdates[]=['range'=>"'خدمة العملاء'!T".($i+2),'values'=>[[$display]]];
 }
 // WhatsApp stages use only actual hub classifications and known stage timestamps.
 foreach(ag_json(__DIR__.'/data/conversations.json') as $id=>$c){if(($c['client_id']??'')!=='cl_76c4e019afc588')continue;
  $click=ag_click($c);if(!$click)continue;$src=strtolower(ag_s($c['traffic_source_key']??''));if(in_array($src,['tiktok','meta','snapchat'],true))continue;
  if((int)($c['valid_inbound_count']??$c['inbound_count']??0)<1)continue;
  ag_add('hub:'.$id,'message_started',$click,ag_s($c['stage_times']['message_started']??$c['first_customer_message_at']??$c['first_seen_at']??''));
  $tag=ag_s($c['current_tag']??'');if(ag_negative($tag))continue;$rank=['interested'=>1,'qualified'=>2,'converted'=>3,'purchased'=>3][$tag]??0;
  foreach(['interested'=>1,'qualified'=>2,'converted'=>3] as $stage=>$n){if($rank<$n)continue;$t=ag_s($c['stage_times'][$stage]??'');if($t===''){foreach($c['label_history']??[] as $h)if(ag_stage(ag_s($h['tag']??''))===$stage){$t=ag_s($h['at']??'');break;}}if($t!=='')ag_add('hub:'.$id,$stage,$click,$t);else $counts['hub_status_missing_time']=($counts['hub_status_missing_time']??0)+1;}
 }
 if(!$dry)ag_save($private.'/ledger.json',$ledger);
 if($sheetUpdates){$w=ag_google('POST','https://sheets.googleapis.com/v4/spreadsheets/'.$sheet.'/values:batchUpdate',['valueInputOption'=>'RAW','data'=>$sheetUpdates]);if(empty($w['ok']))$errors[]=['operation'=>'crm_status','reason'=>ag_error($w)];}
 $sent=[];foreach($pending as $stage=>$map){if(!$dry&&($ledger['retry_after'][$stage]??0)>$now)continue;
  foreach(array_chunk($map,100,true) as $chunk){$body=['destinations'=>[['operatingAccount'=>['accountType'=>'GOOGLE_ADS','accountId'=>$customer],'loginAccount'=>['accountType'=>'GOOGLE_ADS','accountId'=>'8480913009'],'productDestinationId'=>$actions[$stage]]],'events'=>array_values($chunk),'encoding'=>'HEX'];if($dry)$body['validateOnly']=true;
   $r=ag_google('POST','https://datamanager.googleapis.com/v1/events:ingest',$body);if(empty($r['ok'])||empty($r['data']['requestId'])){$errors[]=['stage'=>$stage,'reason'=>ag_error($r),'http'=>$r['status']??0];$ledger['retry_after'][$stage]=$now+1800;break;}
   $sent[$stage]=($sent[$stage]??0)+count($chunk);if($dry)continue;$rid=$r['data']['requestId'];foreach($chunk as $k=>$ev)$ledger['events'][$k]=['state'=>'PROCESSING','stage'=>$stage,'request_id'=>$rid,'sent_at'=>gmdate('c',$now)];
   $ledger['requests'][$rid]=['action'=>$actions[$stage],'stage'=>$stage,'keys'=>array_keys($chunk),'state'=>'PROCESSING','submitted_at'=>gmdate('c',$now),'next_check_at'=>$now+900,'field_warnings'=>$r['data']['fieldWarnings']??[]];unset($ledger['retry_after'][$stage]);ag_save($private.'/ledger.json',$ledger);
  }
 }
 $totals=[];foreach($ledger['events'] as $ev){$st=$ev['stage'];$state=$ev['state'];$totals[$st][$state]=($totals[$st][$state]??0)+1;}
 $requests=[];foreach($ledger['requests'] as $rid=>$req)$requests[]=['request_id'=>$rid,'stage'=>$req['stage'],'state'=>$req['state'],'count'=>count($req['keys']),'diagnostics'=>$req['diagnostics']??null];
 $out=['ok'=>!$errors,'customer_id'=>$customer,'checked_at'=>gmdate('c'),'dry_run'=>$dry,'counts'=>$counts,'pending'=>array_map('count',$pending),'sent_or_validated'=>$sent,'totals'=>$totals,'requests'=>$requests,'errors'=>$errors];
 if(!$dry){ag_save($private.'/ledger.json',$ledger);ag_save($private.'/status.json',$out);}else ag_save($private.'/dry-run.json',$out);
 echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){$out=['ok'=>false,'checked_at'=>gmdate('c'),'reason'=>$e->getMessage()];if(!$dry)ag_save($private.'/status.json',$out);echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit(1);}
