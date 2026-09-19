<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli'){http_response_code(404);exit;}
$base=__DIR__.'/data';$raw=$base.'/raw_events.jsonl';$connFile=$base.'/ycloud_connections.json';
$targets=array_fill_keys(array_filter(array_map('trim',explode(',',(string)getenv('LEAD_IDS')))),true);
function s(mixed $v):string{return is_scalar($v)?trim((string)$v):'';}
function low(string $v):string{return function_exists('mb_strtolower')?mb_strtolower($v,'UTF-8'):strtolower($v);}
function txt(array $m):string{$t=s($m['type']??'');if($t==='text')return s($m['text']['body']??'');foreach(['image','video','document','audio'] as $k)if($t===$k&&isset($m[$k])&&is_array($m[$k]))return s($m[$k]['caption']??'');return'';}
$connections=json_decode((string)@file_get_contents($connFile),true);if(!is_array($connections))$connections=[];
$rows=[];
if(is_file($raw)&&($fh=fopen($raw,'rb'))){while(($line=fgets($fh))!==false){$r=json_decode($line,true);if(!is_array($r))continue;$p=is_array($r['payload']??null)?$r['payload']:[];$type=s($r['type']??'');$m=null;$dir='';
if($type==='whatsapp.inbound_message.received'&&isset($p['whatsappInboundMessage'])){$m=$p['whatsappInboundMessage'];$dir='inbound';}
elseif($type==='whatsapp.smb.message.echoes'&&isset($p['whatsappMessage'])){$m=$p['whatsappMessage'];$dir='outbound';}
elseif($type==='whatsapp.smb.history'&&isset($p['whatsappInboundMessage'])){$m=$p['whatsappInboundMessage'];$dir='inbound';}
elseif($type==='whatsapp.smb.history'&&isset($p['whatsappMessage'])){$m=$p['whatsappMessage'];$dir='outbound';}
if(!is_array($m))continue;$w=s($m['wabaId']??'');$cust=s($dir==='inbound'?($m['from']??''):($m['to']??''));if($w===''||$cust==='')continue;$id=substr(hash('sha256',$w.'|'.$cust),0,24);if(!isset($targets[$id]))continue;$at=s($m['sendTime']??$r['createTime']??'');$rows[$id][]=['direction'=>$dir,'at'=>$at,'type'=>s($m['type']??''),'text'=>txt($m),'referral'=>is_array($m['referral']??null)?$m['referral']:[]];}fclose($fh);}
$words=['تم التعاقد','وقعت العقد','وقّعت العقد','تم توقيع العقد','اشتريت العقار','اشتريت الوحدة','تم شراء العقار','تم شراء الوحدة','contract signed','property purchased','unit purchased'];
$out=[];
foreach(array_keys($targets) as $id){$msgs=$rows[$id]??[];usort($msgs,fn($a,$b)=>strcmp($a['at'],$b['at']));$firstIn=null;$firstOut=null;$hits=[];$ref=false;
foreach($msgs as $m){if($m['direction']==='inbound'&&$firstIn===null)$firstIn=$m;if($m['direction']==='outbound'&&$firstOut===null)$firstOut=$m;if($m['direction']==='inbound'){if(!empty($m['referral']))$ref=true;$l=low($m['text']);foreach($words as $w)if($w!==''&&str_contains($l,low($w))){$hits[]=['at'=>$m['at'],'matched'=>$w,'text'=>$m['text']];break;}}}
$out[$id]=['first_inbound'=>$firstIn?['at'=>$firstIn['at'],'type'=>$firstIn['type'],'text'=>$firstIn['text'],'has_referral'=>!empty($firstIn['referral'])]:null,'first_outbound'=>$firstOut?['at'=>$firstOut['at'],'type'=>$firstOut['type'],'text'=>$firstOut['text']]:null,'outbound_before_first_inbound'=>$firstIn&&$firstOut?strcmp($firstOut['at'],$firstIn['at'])<0:false,'any_referral'=>$ref,'conversion_keyword_hits'=>$hits,'message_count'=>count($msgs)];}
echo json_encode(['ok'=>true,'audits'=>$out],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";