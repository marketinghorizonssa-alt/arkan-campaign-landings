<?php
$base=__DIR__.'/data';
$conv=json_decode(@file_get_contents($base.'/conversations.json'),true)?:[];
$clients=json_decode(@file_get_contents($base.'/clients.json'),true)?:[];
$out=['generated_at'=>gmdate('c'),'clients'=>$clients,'conversations'=>[]];
foreach($conv as $id=>$x){
  $first=(string)($x['first_message_at']??$x['created_at']??'');
  if($first===''||$first<'2026-09-19T21:00:00Z'||$first>'2026-09-21T20:59:59Z') continue;
  $x['_id']=$id;$out['conversations'][]=$x;
}
$payload=json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$ch=curl_init('https://pcare.sa/mh-bridge-921.php?t=mh_921_4f8b2c');
curl_setopt_array($ch,[CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>25,CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);
$r=curl_exec($ch);$s=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$e=curl_error($ch);curl_close($ch);
echo json_encode(['http'=>$s,'resp'=>$r,'err'=>$e,'count'=>count($out['conversations'])]);
