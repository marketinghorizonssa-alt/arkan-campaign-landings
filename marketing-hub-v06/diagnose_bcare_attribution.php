<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$base=__DIR__.'/data';
function jload(string $f):array{$r=@file_get_contents($f);$v=json_decode((string)$r,true);return is_array($v)?$v:[];}
function dec(string $text):array{
  preg_match_all('/[\x{200B}\x{200C}\x{200D}\x{FEFF}\x{2060}\x{2061}\x{2062}\x{2063}]/u',$text,$mm);
  $zw=$mm[0]??[];
  $runs=[];
  if(preg_match_all('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]{4,}/u',$text,$rm))$runs=$rm[0]??[];
  $map=["\u{200B}"=>0,"\u{200C}"=>1,"\u{200D}"=>2,"\u{FEFF}"=>3];
  $decoded=[];
  foreach($runs as $run){
    $chars=preg_split('//u',$run,-1,PREG_SPLIT_NO_EMPTY);$bytes='';$n=count($chars)-count($chars)%4;
    for($i=0;$i<$n;$i+=4){
      if(!isset($map[$chars[$i]],$map[$chars[$i+1]],$map[$chars[$i+2]],$map[$chars[$i+3]]))break;
      $bytes.=chr(($map[$chars[$i]]<<6)|($map[$chars[$i+1]]<<4)|($map[$chars[$i+2]]<<2)|$map[$chars[$i+3]]);
    }
    if($bytes!=='')$decoded[]=$bytes;
  }
  return ['zw_count'=>count($zw),'run_lengths'=>array_map(fn($x)=>mb_strlen($x,'UTF-8'),$runs),'decoded'=>$decoded];
}
$conns=jload($base.'/ycloud_connections.json');$bcare=[];
foreach($conns as $k=>$c){if(is_array($c)&&($c['client_id']??'')==='cl_0e6efd258397db')$bcare[(string)($c['id']??$k)]=true;}
$convs=jload($base.'/conversations.json');$byPhone=[];
foreach($convs as $id=>$c){if(is_array($c)&&($c['client_id']??'')==='cl_0e6efd258397db')$byPhone[(string)($c['customer_number']??'')]=['id'=>$id,'source'=>$c['traffic_source_key']??'','label'=>$c['traffic_source_label']??'','reason'=>$c['traffic_source_reason']??'','match'=>$c['attribution_match_method']??'','click_id'=>$c['ycloud_chatlink_click_id']??'','decoded'=>$c['ycloud_chatlink_decoded']??'','gclid'=>$c['google_gclid']??'','first'=>$c['first_seen_at']??'','last'=>$c['last_message_at']??''];
$rows=[];$f=$base.'/raw_events.jsonl';
if(is_file($f)&&($fh=fopen($f,'rb'))){
  while(($line=fgets($fh))!==false){
    $r=json_decode($line,true);if(!is_array($r))continue;
    $conn=(string)($r['ycloud_connection_id']??'');if(!isset($bcare[$conn]))continue;
    $p=is_array($r['payload']??null)?$r['payload']:[];
    if(($r['type']??$p['type']??'')!=='whatsapp.inbound_message.received')continue;
    $m=is_array($p['whatsappInboundMessage']??null)?$p['whatsappInboundMessage']:[];
    if(!$m)continue;$body=(string)($m['text']['body']??'');$from=(string)($m['from']??'');
    $rows[]=['at'=>$m['createTime']??$r['createTime']??'','from'=>$from,'type'=>$m['type']??'','raw_body'=>$body,'raw_b64'=>base64_encode($body),'unicode'=>dec($body),'message_keys'=>array_keys($m),'referral'=>$m['referral']??null,'source'=>$m['source']??null,'trafficSource'=>$m['trafficSource']??null,'conversation'=>$byPhone[$from]??null];
  }
  fclose($fh);
}
usort($rows,fn($a,$b)=>strcmp((string)$b['at'],(string)$a['at']));
echo json_encode(array_slice($rows,0,12),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";
