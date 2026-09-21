<?php
declare(strict_types=1);

$base='/home/u878466595/domains/hositee.com/public_html/marketing/data';
function jload(string $f): array {
  if(!is_file($f)) return [];
  $x=json_decode((string)file_get_contents($f),true);
  return is_array($x)?$x:[];
}
function phone(string $v): string { return preg_replace('/\D+/','',$v)??''; }
function ldate(string $s): string {
  $t=strtotime($s); if(!$t) return '';
  return gmdate('Y-m-d',$t+10800);
}
$clients=jload($base.'/clients.json');
$conns=jload($base.'/ycloud_connections.json');
$convs=jload($base.'/conversations.json');

$names=[]; foreach($clients as $c) $names[(string)($c['id']??'')]=(string)($c['name']??'');
$connClient=[]; foreach($conns as $c) $connClient[(string)($c['id']??'')]=(string)($c['client_id']??'');

$state=[];
$rf=$base.'/raw_events.jsonl';
$fh=is_file($rf)?fopen($rf,'rb'):false;
if($fh){
  while(($line=fgets($fh))!==false){
    $e=json_decode($line,true); if(!is_array($e)) continue;
    $type=(string)($e['type']??'');
    $cid=$connClient[(string)($e['ycloud_connection_id']??'')]??'';
    if($cid==='') continue;
    $payload=is_array($e['payload']??null)?$e['payload']:[];
    $m=null; $dir='';
    if($type==='whatsapp.inbound_message.received' && is_array($payload['whatsappInboundMessage']??null)){
      $m=$payload['whatsappInboundMessage']; $dir='inbound';
    } elseif($type==='whatsapp.smb.message.echoes' && is_array($payload['whatsappMessage']??null)){
      $m=$payload['whatsappMessage']; $dir='outbound';
    } elseif($type==='whatsapp.smb.history'){
      if(is_array($payload['whatsappInboundMessage']??null)){ $m=$payload['whatsappInboundMessage']; $dir='inbound'; }
      elseif(is_array($payload['whatsappMessage']??null)){ $m=$payload['whatsappMessage']; $dir='outbound'; }
    }
    if(!is_array($m)||$dir==='') continue;
    $customer=phone((string)($dir==='inbound'?($m['from']??''):($m['to']??'')));
    if($customer==='') continue;
    $ts=(string)($m['sendTime']??$payload['createTime']??$e['createTime']??'');
    $epoch=strtotime($ts); if(!$epoch) continue;
    $key=$cid.'|'.$customer;
    $ref=is_array($m['referral']??null)?$m['referral']:[];
    if(!isset($state[$key])) $state[$key]=['client_id'=>$cid,'phone'=>$customer,'events'=>[],'first'=>null,'ref'=>[]];
    $item=['ts'=>$epoch,'time'=>$ts,'dir'=>$dir,'type'=>(string)($m['type']??''),'ref'=>$ref];
    $state[$key]['events'][]=$item;
    if($state[$key]['first']===null || $epoch < $state[$key]['first']['ts']) $state[$key]['first']=$item;
    if($dir==='inbound' && $ref && !$state[$key]['ref']) $state[$key]['ref']=$ref;
  }
  fclose($fh);
}

$out=[];
foreach($state as $key=>$s){
  $first=$s['first']; if(!$first || $first['dir']!=='inbound') continue;
  $d=ldate($first['time']);
  if($d!=='2026-09-20' && $d!=='2026-09-21') continue;
  $cid=$s['client_id']; $p=$s['phone'];
  $recent=[]; $tag=null; $ctwa=''; $stype=''; $headline='';
  foreach($convs as $c){
    if((string)($c['client_id']??'')!==$cid || phone((string)($c['customer_number']??''))!==$p) continue;
    if(!empty($c['current_tag'])) $tag=(string)$c['current_tag'];
    if(!empty($c['ctwa_clid'])) $ctwa=(string)$c['ctwa_clid'];
    if(!empty($c['ad_source_type'])) $stype=(string)$c['ad_source_type'];
    if(!empty($c['ad_headline'])) $headline=(string)$c['ad_headline'];
    foreach((array)($c['recent_messages']??[]) as $m) $recent[]=$m;
  }
  usort($recent,fn($a,$b)=>strcmp((string)($a['at']??''),(string)($b['at']??'')));
  if(count($recent)>12) $recent=array_slice($recent,-12);
  $ref=$s['ref'];
  if($ctwa==='' && !empty($ref['ctwa_clid'])) $ctwa=(string)$ref['ctwa_clid'];
  if($stype==='' && !empty($ref['source_type'])) $stype=(string)$ref['source_type'];
  if($headline==='' && !empty($ref['headline'])) $headline=(string)$ref['headline'];
  $source=$ctwa!==''?'Meta Click-to-WhatsApp':($stype!==''?$stype:'Unknown');
  $out[]=[
    'date'=>$d,'client'=>$names[$cid]??$cid,'client_id'=>$cid,'phone'=>'+'.$p,
    'first_inbound_at'=>$first['time'],'source'=>$source,'ad_headline'=>$headline,
    'tag'=>$tag,'recent_messages'=>$recent
  ];
}
usort($out,function($a,$b){ return [$a['date'],$a['client'],$a['first_inbound_at']] <=> [$b['date'],$b['client'],$b['first_inbound_at']]; });
$summary=[];
foreach($out as $r){ $summary[$r['date']][$r['client']]=($summary[$r['date']][$r['client']]??0)+1; }
echo json_encode(['summary'=>$summary,'rows'=>$out],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
