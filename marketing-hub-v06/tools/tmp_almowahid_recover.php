<?php
declare(strict_types=1);
$base='/home/u878466595/domains/hositee.com/public_html/marketing/data';
date_default_timezone_set('Africa/Cairo');
function jl($f){$x=is_file($f)?json_decode((string)file_get_contents($f),true):[];return is_array($x)?$x:[];}
function ph($v){return preg_replace('/\D+/','',(string)$v)??'';}
function txt($m){$t=(string)($m['type']??'');if($t==='text')return trim((string)($m['text']['body']??''));foreach(['image','video','document','audio'] as $k){if($t===$k){$x=$m[$k]??[];return trim((string)($x['caption']??('['.$k.']')));}}return '['.($t?:'unknown').']';}
$clients=jl($base.'/clients.json');$nums=jl($base.'/whatsapp_numbers.json');
$names=[];$match=[];
foreach($clients as $k=>$c){if(!is_array($c))continue;$id=(string)($c['id']??(is_string($k)?$k:''));$nm=(string)($c['name']??'');$names[$id]=$nm;if(stripos($nm,'almowahid')!==false||mb_stripos($nm,'الموحد')!==false||mb_stripos($nm,'موحد')!==false)$match[$id]=$nm;}
$byPhone=[];$byWaba=[];$business=[];
foreach($nums as $n){if(!is_array($n))continue;$cid=(string)($n['client_id']??'');if(!isset($match[$cid]))continue;$p=ph($n['phone_number']??'');$w=(string)($n['waba_id']??'');if($p!==''){$byPhone[$p]=$cid;$business[$cid][]='+'.$p;}if($w!=='')$byWaba[$w]=$cid;}
$state=[];$f=is_file($base.'/raw_events.jsonl')?fopen($base.'/raw_events.jsonl','rb'):false;
while($f&&($ln=fgets($f))!==false){$e=json_decode($ln,true);if(!is_array($e))continue;$ty=(string)($e['type']??'');$pl=is_array($e['payload']??null)?$e['payload']:[];$m=null;$dir='';
if($ty==='whatsapp.inbound_message.received'&&is_array($pl['whatsappInboundMessage']??null)){$m=$pl['whatsappInboundMessage'];$dir='in';}
elseif($ty==='whatsapp.smb.message.echoes'&&is_array($pl['whatsappMessage']??null)){$m=$pl['whatsappMessage'];$dir='out';}
elseif($ty==='whatsapp.smb.history'){if(is_array($pl['whatsappInboundMessage']??null)){$m=$pl['whatsappInboundMessage'];$dir='in';}elseif(is_array($pl['whatsappMessage']??null)){$m=$pl['whatsappMessage'];$dir='out';}}
if(!$m||!$dir)continue;$biz=ph($dir==='in'?($m['to']??''):($m['from']??''));$w=(string)($m['wabaId']??'');$cid=$byPhone[$biz]??($byWaba[$w]??'');if($cid===''||!isset($match[$cid]))continue;$cust=ph($dir==='in'?($m['from']??''):($m['to']??''));if($cust===''||$cust===$biz)continue;$ts=(string)($m['sendTime']??$pl['createTime']??$e['createTime']??'');$ep=strtotime($ts);if(!$ep)continue;$k=$cid.'|'.$cust;if(!isset($state[$k]))$state[$k]=['cid'=>$cid,'phone'=>$cust,'biz'=>$biz,'first'=>null,'events'=>[],'ref'=>[]];$it=['ep'=>$ep,'ts'=>$ts,'dir'=>$dir,'text'=>txt($m)];$state[$k]['events'][]=$it;if($state[$k]['first']===null||$ep<$state[$k]['first']['ep'])$state[$k]['first']=$it;if($dir==='in'&&!$state[$k]['ref']&&is_array($m['referral']??null))$state[$k]['ref']=$m['referral'];}
if($f)fclose($f);
$out=[];foreach($state as $x){$fr=$x['first'];if(!$fr||$fr['dir']!=='in')continue;$local=date('Y-m-d H:i:s',$fr['ep']);if(substr($local,0,10)!=='2026-09-22')continue;usort($x['events'],fn($a,$b)=>$a['ep']<=>$b['ep']);$ins=[];foreach($x['events'] as $v)if($v['dir']==='in'&&$v['text']!=='')$ins[]=$v['text'];$ref=$x['ref'];$src='Organic/Direct';if(!empty($ref['ctwa_clid']))$src='Meta Ads';$blob=strtolower(json_encode($ref,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));foreach($ins as $t)$blob.=' '.mb_strtolower($t);if(str_contains($blob,'google')||str_contains($blob,'gclid')||str_contains($blob,'gbraid')||str_contains($blob,'wbraid'))$src='Google Ads';elseif(str_contains($blob,'tiktok')||str_contains($blob,'تيك توك'))$src='TikTok Ads';$out[]=['client_id'=>$x['cid'],'client'=>$match[$x['cid']], 'customer_phone'=>'+'.$x['phone'],'business_number'=>'+'.$x['biz'],'first_local'=>$local,'source'=>$src,'headline'=>(string)($ref['headline']??''),'messages'=>array_slice($ins,0,10),'ref'=>$ref];}
usort($out,fn($a,$b)=>strcmp($a['first_local'],$b['first_local']));
echo json_encode(['matching_clients'=>$match,'business_numbers'=>$business,'count'=>count($out),'rows'=>$out],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
