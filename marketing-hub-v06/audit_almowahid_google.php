<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$b=__DIR__.'/data';
$c=json_decode((string)@file_get_contents($b.'/conversations.json'),true);if(!is_array($c))$c=[];
$o=[];
foreach($c as $id=>$v){
 if(!is_array($v)||($v['client_id']??'')!=='cl_3ea5ae96e05c6b'||($v['traffic_source_key']??'')!=='google')continue;
 $o[]=['id'=>$id,'phone'=>$v['customer_number']??'','first'=>$v['first_seen_at']??'','tag'=>$v['current_tag']??'','valid'=>(int)($v['valid_inbound_count']??0),'out'=>(int)($v['outbound_count']??0),'click'=>$v['horizons_wa_click_id']??($v['ycloud_chatlink_click_id']??''),'gclid'=>$v['google_gclid']??'','gbraid'=>$v['google_gbraid']??'','wbraid'=>$v['google_wbraid']??'','method'=>$v['attribution_match_method']??''];
}
usort($o,fn($a,$b)=>strcmp($a['first'],$b['first']));
echo json_encode($o,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
