<?php
declare(strict_types=1);
$base='/home/u878466595/domains/hositee.com/public_html/marketing/data';
$start=new DateTimeImmutable('2026-09-19 11:02:34',new DateTimeZone('Asia/Riyadh'));
$end=new DateTimeImmutable('now',new DateTimeZone('Asia/Riyadh'));
function jfile($f){$x=json_decode((string)@file_get_contents($f),true);return is_array($x)?$x:[];}
function dt($s){try{return (new DateTimeImmutable((string)$s))->setTimezone(new DateTimeZone('Asia/Riyadh'));}catch(Throwable $e){return null;}}
function msgtext($m){$t=(string)($m['type']??'');if($t==='text')return trim((string)($m['text']['body']??''));foreach(['image','video','document','audio','sticker'] as $k){if($t===$k&&isset($m[$k])&&is_array($m[$k]))return trim((string)($m[$k]['caption']??''));}return '';}
function norm($s){return function_exists('mb_strtolower')?mb_strtolower((string)$s,'UTF-8'):strtolower((string)$s);}
$clients=jfile("$base/clients.json"); $conns=jfile("$base/ycloud_connections.json"); $nums=jfile("$base/whatsapp_numbers.json");
$courtIds=[];
foreach($clients as $id=>$v){if(!is_array($v))continue;$name=(string)($v['name']??'');$n=norm($name);if(str_contains($n,'cort')||str_contains($n,'court')||str_contains($name,'كورت'))$courtIds[(string)($v['id']??$id)]=$name;}
$courtConn=[];
foreach($conns as $id=>$v){if(is_array($v)&&isset($courtIds[(string)($v['client_id']??'')]))$courtConn[(string)$id]=$v;}
$refSources=[];
$af="$base/attribution_events.jsonl";
if(is_file($af)&&($fh=fopen($af,'rb'))){while(($line=fgets($fh))!==false){$r=json_decode($line,true);if(!is_array($r))continue;$ref=strtoupper(trim((string)($r['ref_token']??'')));$src=strtolower(trim((string)($r['source']??'')));if($ref!==''&&in_array($src,['google','meta','tiktok'],true))$refSources[$ref]=$src;}fclose($fh);}
function src($m,$refs){$ref=is_array($m['referral']??null)?$m['referral']:[];if(trim((string)($ref['ctwa_clid']??''))!==''||trim((string)($ref['source_id']??''))!==''||trim((string)($ref['source_type']??''))!=='')return ['meta','whatsapp_referral'];$t=msgtext($m);$l=norm($t);if(str_contains($l,'المصدر: google ads')||str_contains($l,'source: google ads'))return ['google','message_source_tag'];if(str_contains($l,'المصدر: tiktok ads')||str_contains($l,'source: tiktok ads'))return ['tiktok','message_source_tag'];if(str_contains($l,'المصدر: instagram/meta ads')||str_contains($l,'المصدر: meta ads')||str_contains($l,'المصدر: instagram ads')||str_contains($l,'المصدر: facebook ads'))return ['meta','message_source_tag'];if(preg_match('/ARK-AT-[A-Z0-9]{12,40}/i',$t,$mm)){ $rr=strtoupper($mm[0]); if(isset($refs[$rr]))return [$refs[$rr],'attribution_reference']; }return ['direct_or_organic','unattributed'];}
$first=[];$range=[];$messages=0;$sourceAll=[];$sourceNew=[];$methodNew=[];$daily=[];$connEvents=[];
$rf="$base/raw_events.jsonl";
if(is_file($rf)&&($fh=fopen($rf,'rb'))){while(($line=fgets($fh))!==false){$r=json_decode($line,true);if(!is_array($r)||($r['type']??'')!=='whatsapp.inbound_message.received')continue;$conn=(string)($r['ycloud_connection_id']??'');if(!isset($courtConn[$conn]))continue;$p=is_array($r['payload']??null)?$r['payload']:[];$m=is_array($p['whatsappInboundMessage']??null)?$p['whatsappInboundMessage']:[];$when=dt((string)($m['sendTime']??$r['createTime']??''));if(!$when)continue;$waba=(string)($m['wabaId']??'');$from=(string)($m['from']??'');if($from==='')continue;$key=hash('sha256',$waba.'|'.$from);if(!isset($first[$key])||$when<$first[$key]['when'])$first[$key]=['when'=>$when,'msg'=>$m,'conn'=>$conn];if($when>=$start&&$when<=$end){$messages++;$range[$key]=true;[$s,$meth]=src($m,$refSources);$sourceAll[$s]=($sourceAll[$s]??0)+1;$d=$when->format('Y-m-d');$daily[$d]=($daily[$d]??0)+1;$connEvents[$conn]=($connEvents[$conn]??0)+1;}}fclose($fh);}
$new=0;$newKeys=[];
foreach($first as $key=>$x){if($x['when']>=$start&&$x['when']<=$end){$new++;$newKeys[$key]=true;[$s,$meth]=src($x['msg'],$refSources);$sourceNew[$s]=($sourceNew[$s]??0)+1;$methodNew[$meth]=($methodNew[$meth]??0)+1;}}
$numberRows=[];
foreach($nums as $id=>$v){if(!is_array($v))continue;$cid=(string)($v['client_id']??'');if(isset($courtIds[$cid]))$numberRows[]=['label'=>(string)($v['label']??''),'status'=>(string)($v['status']??''),'provider'=>(string)($v['provider']??''),'remote_status'=>(string)($v['remote_status']??'')];}
$connRows=[];foreach($courtConn as $id=>$v)$connRows[]=['id'=>$id,'status'=>(string)($v['status']??''),'webhook_status'=>(string)($v['webhook_status']??''),'last_sync_at'=>(string)($v['last_sync_at']??''),'events_in_range'=>(int)($connEvents[$id]??0)];
echo json_encode(['ok'=>true,'start'=>$start->format(DateTimeInterface::ATOM),'end'=>$end->format(DateTimeInterface::ATOM),'clients'=>$courtIds,'connections'=>$connRows,'numbers'=>$numberRows,'inbound_messages_in_range'=>$messages,'unique_inbound_contacts_in_range'=>count($range),'first_ever_new_conversations_in_range'=>$new,'all_inbound_sources'=>$sourceAll,'new_conversation_sources'=>$sourceNew,'new_conversation_attribution_methods'=>$methodNew,'daily_inbound_messages'=>$daily,'privacy'=>'aggregated_only_no_customer_numbers_or_message_content'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
?>