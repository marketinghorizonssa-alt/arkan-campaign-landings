<?php
declare(strict_types=1);
$base='/home/u878466595/domains/hositee.com/public_html/marketing/data';
date_default_timezone_set('Africa/Cairo');
$reportDate=getenv('REPORT_DATE') ?: date('Y-m-d');
function jl(string $f): array { if(!is_file($f)) return []; $x=json_decode((string)file_get_contents($f),true); return is_array($x)?$x:[]; }
function ph($v): string { return preg_replace('/\D+/','',(string)$v)??''; }
function localday(string $s): string { $t=strtotime($s); return $t?date('Y-m-d',$t):''; }
function msgtext(array $m): string {
  $type=(string)($m['type']??'');
  if($type==='text') return trim((string)($m['text']['body']??''));
  foreach(['image','video','document','audio'] as $k) if($type===$k){ $x=$m[$k]??[]; $cap=trim((string)($x['caption']??'')); return $cap!==''?$cap:'['.$k.']'; }
  return '['.($type?:'unknown').']';
}
$clients=jl($base.'/clients.json'); $nums=jl($base.'/whatsapp_numbers.json');
$names=[]; foreach($clients as $c) $names[(string)($c['id']??'')]=(string)($c['name']??'');
$byPhone=[]; $byWaba=[];
foreach($nums as $n){ $cid=(string)($n['client_id']??''); if($cid==='') continue; $p=ph($n['phone_number']??''); $w=(string)($n['waba_id']??''); if($p!=='') $byPhone[$p]=$cid; if($w!=='') $byWaba[$w]=$cid; }
$state=[]; $rf=$base.'/raw_events.jsonl'; $fh=is_file($rf)?fopen($rf,'rb'):false;
if($fh){
  while(($ln=fgets($fh))!==false){
    $e=json_decode($ln,true); if(!is_array($e)) continue; $ty=(string)($e['type']??''); $pl=is_array($e['payload']??null)?$e['payload']:[]; $m=null; $dir='';
    if($ty==='whatsapp.inbound_message.received' && is_array($pl['whatsappInboundMessage']??null)){ $m=$pl['whatsappInboundMessage']; $dir='in'; }
    elseif($ty==='whatsapp.smb.message.echoes' && is_array($pl['whatsappMessage']??null)){ $m=$pl['whatsappMessage']; $dir='out'; }
    elseif($ty==='whatsapp.smb.history'){
      if(is_array($pl['whatsappInboundMessage']??null)){ $m=$pl['whatsappInboundMessage']; $dir='in'; }
      elseif(is_array($pl['whatsappMessage']??null)){ $m=$pl['whatsappMessage']; $dir='out'; }
    }
    if(!$m||$dir==='') continue;
    $biz=ph($dir==='in'?($m['to']??''):($m['from']??'')); $w=(string)($m['wabaId']??'');
    $cid=$byPhone[$biz]??($byWaba[$w]??''); if($cid==='') continue;
    $cust=ph($dir==='in'?($m['from']??''):($m['to']??'')); if($cust===''||$cust===$biz) continue;
    $ts=(string)($m['sendTime']??$pl['createTime']??$e['createTime']??''); $ep=strtotime($ts); if(!$ep) continue;
    $k=$cid.'|'.$cust; if(!isset($state[$k])) $state[$k]=['cid'=>$cid,'phone'=>$cust,'biz'=>$biz,'events'=>[],'first'=>null,'ref'=>[]];
    $it=['ep'=>$ep,'ts'=>$ts,'dir'=>$dir,'text'=>msgtext($m),'type'=>(string)($m['type']??'')];
    $state[$k]['events'][]=$it; if($state[$k]['first']===null||$ep<$state[$k]['first']['ep']) $state[$k]['first']=$it;
    if($dir==='in' && !$state[$k]['ref'] && is_array($m['referral']??null)) $state[$k]['ref']=$m['referral'];
  }
  fclose($fh);
}
$review=jl($base.'/automation_review_state.json'); $reviewed=is_array($review['reviewed']??null)?$review['reviewed']:[];
$rows=[]; $summary=[]; $newKeys=[];
foreach($state as $key=>$x){
  $fr=$x['first']; if(!$fr||$fr['dir']!=='in'||localday($fr['ts'])!==$reportDate) continue;
  if(isset($reviewed[$key])) continue;
  usort($x['events'],fn($a,$b)=>$a['ep']<=>$b['ep']);
  $texts=[]; foreach($x['events'] as $v) if($v['dir']==='in' && $v['text']!=='') $texts[]=$v['text'];
  $ref=$x['ref']; $source='Unknown';
  if(!empty($ref['ctwa_clid'])) $source='Meta Click-to-WhatsApp'; elseif(!empty($ref['source_type'])) $source=(string)$ref['source_type'];
  foreach($texts as $t){ if(stripos($t,'TikTok')!==false || mb_stripos($t,'تيك توك')!==false){ $source='TikTok'; break; } }
  $row=['key'=>$key,'date'=>$reportDate,'client'=>$names[$x['cid']]??$x['cid'],'client_id'=>$x['cid'],'phone'=>'+'.$x['phone'],'business_number'=>'+'.$x['biz'],'first'=>$fr['ts'],'source'=>$source,'headline'=>(string)($ref['headline']??''),'messages'=>array_slice($texts,0,12)];
  $rows[]=$row; $summary[$row['client']]=($summary[$row['client']]??0)+1; $newKeys[]=$key;
}
usort($rows,fn($a,$b)=>[$a['client'],$a['first']]<=>[$b['client'],$b['first']]);
$now=date(DATE_ATOM); foreach($newKeys as $k) $reviewed[$k]=['report_date'=>$reportDate,'reviewed_at'=>$now];
file_put_contents($base.'/automation_review_state.json',json_encode(['updated_at'=>$now,'reviewed'=>$reviewed],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);
echo json_encode(['date'=>$reportDate,'summary'=>$summary,'count'=>count($rows),'rows'=>$rows],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
