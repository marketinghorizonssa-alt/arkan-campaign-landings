<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$base=__DIR__.'/data';
$clients=is_file($base.'/clients.json')?json_decode((string)file_get_contents($base.'/clients.json'),true):[];
$convs=is_file($base.'/conversations.json')?json_decode((string)file_get_contents($base.'/conversations.json'),true):[];
if(!is_array($clients))$clients=[]; if(!is_array($convs))$convs=[];
$arkanIds=[];$clientNames=[];
foreach($clients as $id=>$c){if(!is_array($c))continue;$name=(string)($c['name']??'');$clientNames[(string)$id]=$name;$n=mb_strtolower($name,'UTF-8');if(str_contains($n,'arkan')||str_contains($n,'أركان')||str_contains($n,'اركان'))$arkanIds[(string)$id]=true;}
$rows=[];
foreach($convs as $id=>$c){if(!is_array($c))continue;$cid=(string)($c['client_id']??'');if($arkanIds && !isset($arkanIds[$cid]))continue;
 $msgs=[];foreach((array)($c['recent_messages']??[]) as $m){if(!is_array($m))continue;$msgs[]=['d'=>(string)($m['direction']??''),'t'=>(string)($m['type']??''),'x'=>(string)($m['text']??''),'at'=>(string)($m['at']??'')];}
 $rows[]=['conversation'=>substr((string)$id,0,10),'client_id'=>$cid,'client_name'=>$clientNames[$cid]??'', 'updated_at'=>(string)($c['updated_at']??''),'first_seen_at'=>(string)($c['first_seen_at']??''),'current_tag'=>$c['current_tag']??null,'tag_source'=>$c['tag_source']??null,'inbound_count'=>(int)($c['inbound_count']??0),'outbound_count'=>(int)($c['outbound_count']??0),'reply_after_staff'=>(bool)($c['last_customer_reply_to_staff']??false),'ctwa_clid_present'=>!empty($c['ctwa_clid']),'ad_source_id'=>(string)($c['ad_source_id']??''),'ad_source_type'=>(string)($c['ad_source_type']??''),'ad_headline'=>(string)($c['ad_headline']??''),'messages'=>$msgs];
}
usort($rows,fn($a,$b)=>strcmp((string)$b['updated_at'],(string)$a['updated_at']));
$rows=array_slice($rows,0,40);
echo json_encode(['clients_matched'=>array_keys($arkanIds),'count'=>count($rows),'rows'=>$rows],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
