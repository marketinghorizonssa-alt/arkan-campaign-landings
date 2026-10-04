<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root='/home/u878466595/domains/hositee.com/public_html/marketing';
$secure='/home/u878466595/.marketing';
$lock=fopen($secure.'/biyaat-audit-20261004.lock','c');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){exit;}
$cached=$secure.'/biyaat-audit-20261004.json';
if(is_file($cached)){echo file_get_contents($cached);exit;}
function ba_json(string $p):array{$v=json_decode((string)@file_get_contents($p),true);return is_array($v)?$v:[];}
function ba_rows(string $p):array{$out=[];if($fh=@fopen($p,'rb')){while(($l=fgets($fh))!==false){$r=json_decode($l,true);if(is_array($r))$out[]=$r;}fclose($fh);}return$out;}
$cid='cl_d554e1c6bfe460';$conn='yc_b5d5c813cc6559';
$c=ba_json($root.'/data/conversations.json');$stages=[];$sources=[];$latest='';$seen=0;$ttids=0;$manual=0;$pendingAI=0;
foreach($c as $v){if(($v['client_id']??'')!==$cid)continue;$seen++;$s=(string)($v['current_tag']??'unclassified');$stages[$s]=($stages[$s]??0)+1;$src=(string)($v['traffic_source_key']??'unknown');$sources[$src]=($sources[$src]??0)+1;if(!empty($v['tiktok_ttclid']))$ttids++;if(($v['tag_source']??'')==='manual')$manual++;$ts=(string)($v['last_message_at']??'');if($ts>$latest)$latest=$ts;if($ts!==($v['ai_evaluated_message_at']??''))$pendingAI++;}
$connections=ba_json($root.'/data/ycloud_connections.json');$config=ba_json($root.'/lead_pool_config.json');
$out=['ok'=>true,'at'=>gmdate('c'),'nursery'=>['conversations'=>$seen,'stages'=>$stages,'sources'=>$sources,'ttclid_present'=>$ttids,'manual_overrides'=>$manual,'pending_ai'=>$pendingAI,'last_message_at'=>$latest,'last_webhook_at'=>$connections[$conn]['last_webhook_at']??null],'pool'=>ba_json($secure.'/lead_pools/'.$cid.'/summary.json'),'tokens'=>['ycloud'=>is_file($secure.'/ycloud/'.$conn.'/api_key'),'offline'=>is_file($secure.'/tiktok/offline/7689940634064519189/access_token')],'token_directory_paths'=>[],'deliveries'=>[]];
foreach((array)glob($secure.'/tiktok/*/*/access_token')as$p)$out['token_directory_paths'][]=str_replace($secure.'/','',$p);
foreach(['routing_outbox.jsonl','routing_delivery.jsonl','tiktok_crm_delivery.jsonl','tiktok_offline_delivery.jsonl'] as $f){$rows=ba_rows($secure.'/lead_pools/'.$cid.'/'.$f);$ok=0;$bad=0;$st=[];$errors=[];foreach($rows as $r){if(!empty($r['success']))$ok++;elseif(array_key_exists('success',$r))$bad++;$stage=(string)($r['stage']??'');$st[$stage]=($st[$stage]??0)+1;if(!empty($r['error']))$errors[(string)$r['error']]=($errors[(string)$r['error']]??0)+1;}$out['deliveries'][$f]=['rows'=>count($rows),'success'=>$ok,'failed'=>$bad,'stages'=>$st,'errors'=>$errors,'last_at'=>end($rows)['created_at']??null];}
putenv('Q2_QUERY=client_id='.$cid.'&limit=200');$_SERVER['REQUEST_METHOD']='GET';ob_start();include $root.'/local_quality_v2.php';$q=json_decode((string)ob_get_clean(),true);putenv('Q2_QUERY');$out['contextual_current_summary']=$q['summary']??null;
$out['contextual_sample']=array_map(fn($r)=>['stage'=>$r['stage'],'attributes'=>$r['attributes'],'reasons'=>$r['reasons']],array_slice($q['leads']??[],0,8));
file_put_contents($cached,json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);chmod($cached,0600);echo file_get_contents($cached);
