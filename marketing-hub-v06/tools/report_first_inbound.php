<?php
declare(strict_types=1);
$base='/home/u878466595/domains/hositee.com/public_html/marketing/data';
function jl($f){$x=is_file($f)?json_decode((string)file_get_contents($f),true):[];return is_array($x)?$x:[];}
function ph($v){return preg_replace('/\D+/','',(string)$v)??'';}
function dayc($s){$t=strtotime((string)$s);return $t?gmdate('Y-m-d',$t+10800):'';}
function mt($m){
 $type=(string)($m['type']??'');
 if($type==='text') return trim((string)($m['text']['body']??''));
 foreach(['image','video','document','audio'] as $k) if($type===$k){$x=$m[$k]??[];return trim((string)($x['caption']??('['.$k.']')));}
 return '['.($type?:'unknown').']';
}
$clients=jl($base.'/clients.json');$nums=jl($base.'/whatsapp_numbers.json');
$names=[];foreach($clients as $c)$names[$c['id']]=$c['name'];
$byPhone=[];$byWaba=[];
foreach($nums as $n){$cid=(string)($n['client_id']??'');if(!$cid)continue;$p=ph($n['phone_number']??'');$w=(string)($n['waba_id']??'');if($p)$byPhone[$p]=$cid;if($w)$byWaba[$w]=$cid;}
$s=[];
$f=fopen($base.'/raw_events.jsonl','rb');
while($f&&($ln=fgets($f))!==false){
 $e=json_decode($ln,true);if(!is_array($e))continue;$ty=(string)($e['type']??'');$pl=$e['payload']??[];$m=null;$dir='';
 if($ty==='whatsapp.inbound_message.received'&&is_array($pl['whatsappInboundMessage']??null)){$m=$pl['whatsappInboundMessage'];$dir='in';}
 elseif($ty==='whatsapp.smb.message.echoes'&&is_array($pl['whatsappMessage']??null)){$m=$pl['whatsappMessage'];$dir='out';}
 elseif($ty==='whatsapp.smb.history'){
  if(is_array($pl['whatsappInboundMessage']??null)){$m=$pl['whatsappInboundMessage'];$dir='in';}
  elseif(is_array($pl['whatsappMessage']??null)){$m=$pl['whatsappMessage'];$dir='out';}
 }
 if(!$m||!$dir)continue;
 $biz=ph($dir==='in'?($m['to']??''):($m['from']??''));$w=(string)($m['wabaId']??'');
 $cid=$byPhone[$biz]??($byWaba[$w]??'');if(!$cid)continue;
 $cust=ph($dir==='in'?($m['from']??''):($m['to']??''));if(!$cust)continue;
 if($cust===$biz)continue;
 $ts=(string)($m['sendTime']??$pl['createTime']??$e['createTime']??'');$ep=strtotime($ts);if(!$ep)continue;
 $k=$cid.'|'.$cust;if(!isset($s[$k]))$s[$k]=['cid'=>$cid,'p'=>$cust,'ev'=>[],'first'=>null,'ref'=>[]];
 $it=['ep'=>$ep,'ts'=>$ts,'dir'=>$dir,'text'=>mt($m),'type'=>(string)($m['type']??'')];
 $s[$k]['ev'][]=$it;
 if($s[$k]['first']===null||$ep<$s[$k]['first']['ep'])$s[$k]['first']=$it;
 if($dir==='in'&&!$s[$k]['ref']&&is_array($m['referral']??null))$s[$k]['ref']=$m['referral'];
}
if($f)fclose($f);
$out=[];$sum=[];
foreach($s as $x){
 $fr=$x['first'];if(!$fr||$fr['dir']!=='in')continue;$d=dayc($fr['ts']);if(!in_array($d,['2026-09-20','2026-09-21'],true))continue;
 usort($x['ev'],fn($a,$b)=>$a['ep']<=>$b['ep']);
 $texts=[];$in=0;$outc=0;
 foreach($x['ev'] as $v){if($v['dir']==='in'){$in++;if($v['text']!=='')$texts[]=$v['text'];}else$outc++;}
 $ref=$x['ref'];$src=!empty($ref['ctwa_clid'])?'Meta Click-to-WhatsApp':(!empty($ref['source_type'])?(string)$ref['source_type']:'Unknown');
 $row=['date'=>$d,'client'=>$names[$x['cid']]??$x['cid'],'phone'=>'+'.$x['p'],'first'=>$fr['ts'],'source'=>$src,'headline'=>(string)($ref['headline']??''),'in'=>$in,'out'=>$outc,'texts'=>array_slice($texts,0,8)];
 $out[]=$row;$sum[$d][$row['client']]=($sum[$d][$row['client']]??0)+1;
}
usort($out,fn($a,$b)=>[$a['date'],$a['client'],$a['first']]<=>[$b['date'],$b['client'],$b['first']]);
echo json_encode(['summary'=>$sum,'rows'=>$out],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
