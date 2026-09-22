<?php
declare(strict_types=1);
$base='/home/u878466595/domains/hositee.com/public_html/marketing/data';
date_default_timezone_set('Africa/Cairo');
function jl($f){$x=is_file($f)?json_decode((string)file_get_contents($f),true):[];return is_array($x)?$x:[];}
function ph($v){return preg_replace('/\D+/','',(string)$v)??'';}
function textOf($m){$t=(string)($m['type']??'');if($t==='text')return trim((string)($m['text']['body']??''));return '';}
$clients=jl($base.'/clients.json');$nums=jl($base.'/whatsapp_numbers.json');
$cid='';foreach($clients as $k=>$c){if(!is_array($c))continue;$id=(string)($c['id']??(is_string($k)?$k:''));$nm=(string)($c['name']??'');if(mb_stripos($nm,'الموحد')!==false||stripos($nm,'almowahid')!==false){$cid=$id;break;}}
$byPhone=[];$byWaba=[];foreach($nums as $n){if(!is_array($n)||($n['client_id']??'')!==$cid)continue;$p=ph($n['phone_number']??'');$w=(string)($n['waba_id']??'');if($p!=='')$byPhone[$p]=$cid;if($w!=='')$byWaba[$w]=$cid;}
$s=[];$f=is_file($base.'/raw_events.jsonl')?fopen($base.'/raw_events.jsonl','rb'):false;
while($f&&($ln=fgets($f))!==false){$e=json_decode($ln,true);if(!is_array($e))continue;$ty=(string)($e['type']??'');$pl=is_array($e['payload']??null)?$e['payload']:[];$m=null;$dir='';
if($ty==='whatsapp.inbound_message.received'&&is_array($pl['whatsappInboundMessage']??null)){$m=$pl['whatsappInboundMessage'];$dir='in';}
elseif($ty==='whatsapp.smb.history'&&is_array($pl['whatsappInboundMessage']??null)){$m=$pl['whatsappInboundMessage'];$dir='in';}
elseif($ty==='whatsapp.smb.message.echoes'&&is_array($pl['whatsappMessage']??null)){$m=$pl['whatsappMessage'];$dir='out';}
elseif($ty==='whatsapp.smb.history'&&is_array($pl['whatsappMessage']??null)){$m=$pl['whatsappMessage'];$dir='out';}
if(!$m||!$dir)continue;$biz=ph($dir==='in'?($m['to']??''):($m['from']??''));$w=(string)($m['wabaId']??'');$resolved=$byPhone[$biz]??($byWaba[$w]??'');if($resolved!==$cid)continue;$cust=ph($dir==='in'?($m['from']??''):($m['to']??''));if($cust===''||$cust===$biz)continue;$ts=(string)($m['sendTime']??$pl['createTime']??$e['createTime']??'');$ep=strtotime($ts);if(!$ep)continue;$k=$cust;if(!isset($s[$k]))$s[$k]=['first'=>null,'ref'=>[]];if($dir==='in'){if($s[$k]['first']===null||$ep<$s[$k]['first']['ep'])$s[$k]['first']=['ep'=>$ep,'ts'=>$ts,'text'=>textOf($m),'raw'=>$m];if(!$s[$k]['ref']&&is_array($m['referral']??null))$s[$k]['ref']=$m['referral'];}}
if($f)fclose($f);
$out=[];foreach($s as $phone=>$x){$fr=$x['first'];if(!$fr)continue;$local=date('Y-m-d H:i:s',$fr['ep']);if(substr($local,0,10)!=='2026-09-22')continue;$t=$fr['text'];if(mb_stripos($t,'أريد طلب خدمة من موقع الموحد للاستقدام')===false)continue;
$name='';$service='';$nat='';$details='';$page='';
if(preg_match('/الاسم:\s*([^\r\n]+)/u',$t,$m))$name=trim($m[1]);
if(preg_match('/الخدمة:\s*([^\r\n]+)/u',$t,$m))$service=trim($m[1]);
if(preg_match('/الجنسية:\s*([^\r\n]+)/u',$t,$m))$nat=trim($m[1]);
if(preg_match('/التفاصيل:\s*(.*?)(?:الصفحة:|$)/us',$t,$m))$details=trim($m[1]);
if(preg_match('/الصفحة:\s*([^\r\n]+)/u',$t,$m))$page=trim($m[1]);
$ref=$x['ref'];$refj=json_encode($ref,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$out[]=['phone'=>'+'.$phone,'first_local'=>$local,'name'=>$name,'service'=>$service,'nationality'=>$nat,'details'=>$details,'page'=>$page,'ref'=>$refj];
}
usort($out,fn($a,$b)=>strcmp($a['first_local'],$b['first_local']));
echo json_encode(['count'=>count($out),'rows'=>$out],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
