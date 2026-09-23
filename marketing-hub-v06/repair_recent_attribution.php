<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$base=__DIR__.'/data';
$clickFile=$base.'/chatlink_clicks.json';
$convFile=$base.'/conversations.json';
$googleQueue=$base.'/google_conversion_events.jsonl';
$targets=['cl_0e6efd258397db'=>true,'cl_3ea5ae96e05c6b'=>true];

function jl(string $f):array{$v=is_file($f)?json_decode((string)file_get_contents($f),true):[];return is_array($v)?$v:[];}
function js(string $f,array $v):bool{$t=$f.'.tmp';if(file_put_contents($t,json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX)===false)return false;return rename($t,$f);}
function np(string $v):string{return preg_replace('/\D+/','',$v)??'';}
function ce(array $r):int{foreach(['browser_time','created_at','captured_at'] as $k){$s=trim((string)($r[$k]??''));if($s!==''&&($t=strtotime($s)))return$t;}return 0;}
function specific(string $k):bool{return in_array(strtolower($k),['google','tiktok','meta','snapchat','microsoft_ads','linkedin','x'],true);}
function copy_attr(array &$c,array $r,string $clickId):void{
  $c['traffic_source_key']=(string)($r['traffic_source_key']??'website');
  $c['traffic_source_label']=(string)($r['traffic_source_label']??'Website');
  $c['traffic_source_confidence']=specific((string)($r['traffic_source_key']??''))?'high':'medium';
  $c['traffic_source_reason']='unique_browser_click_then_message_within_5m_repair';
  $c['attribution_match_method']='single_unique_browser_5m_window';
  $c['ycloud_chatlink_click_id']=$clickId;
  $c['chatlink_source_url']=(string)($r['source_url']??'');
  $c['attribution_landing_url']=(string)($r['landing_url']??'');
  $c['attribution_referrer']=(string)($r['referrer']??'');
  $c['attribution_params']=is_array($r['query_params']??null)?$r['query_params']:[];
  $c['attribution_utm']=is_array($r['utm']??null)?$r['utm']:[];
  foreach(['gclid'=>'google_gclid','gbraid'=>'google_gbraid','wbraid'=>'google_wbraid','dclid'=>'google_dclid','fbclid'=>'meta_fbclid','ttclid'=>'tiktok_ttclid','msclkid'=>'microsoft_msclkid','li_fat_id'=>'linkedin_li_fat_id','twclid'=>'x_twclid'] as $rk=>$ck)if(!empty($r[$rk]))$c[$ck]=(string)$r[$rk];
  if(!empty($r['scclid']))$c['snapchat_scclid']=(string)$r['scclid'];elseif(!empty($r['ScCid']))$c['snapchat_scclid']=(string)$r['ScCid'];
  foreach((array)($r['utm']??[]) as $k=>$v)if(str_starts_with((string)$k,'utm_'))$c[(string)$k]=(string)$v;
  $c['attribution_resolved_at']=gmdate('c');$c['updated_at']=gmdate('c');
}
function queue_google(string $file,array &$c):void{
  if(($c['traffic_source_key']??'')!=='google')return;
  $cid=(string)($c['client_id']??'');$conv=(string)($c['id']??'');if($cid===''||$conv==='')return;
  $eid='gcv_'.substr(hash('sha256',$cid.'|'.$conv.'|message_sent'),0,32);
  $seen=false;
  if(is_file($file)&&($fh=fopen($file,'rb'))){while(($line=fgets($fh))!==false){$r=json_decode($line,true);if(is_array($r)&&($r['event_id']??'')===$eid){$seen=true;break;}}fclose($fh);}
  if(!$seen)file_put_contents($file,json_encode(['event_id'=>$eid,'client_id'=>$cid,'conversation_id'=>$conv,'stage'=>'message_sent','occurred_at'=>$c['first_seen_at']??gmdate('c'),'source_event_id'=>$c['last_source_event_id']??'','created_at'=>gmdate('c')],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n",FILE_APPEND|LOCK_EX);
  $c['google_message_sent_queued_at']=$c['google_message_sent_queued_at']??gmdate('c');
}

$clicks=jl($clickFile);$convs=jl($convFile);$eligible=[];$candByConv=[];$convByClick=[];
foreach($convs as $id=>$c){
  if(!is_array($c)||!isset($targets[(string)($c['client_id']??'')]))continue;
  $src=strtolower((string)($c['traffic_source_key']??''));$m=(string)($c['attribution_match_method']??'');
  if(specific($src)&&!str_starts_with($m,'timestamp_'))continue;
  $first=strtotime((string)($c['first_seen_at']??''));if(!$first)continue;
  $eligible[$id]=['first'=>$first,'client'=>(string)$c['client_id'],'business'=>np((string)($c['business_number']??''))];
}
foreach($eligible as $cid=>$p){
  foreach($clicks as $clickId=>$r){
    if(!is_array($r)||(string)($r['client_id']??'')!==$p['client'])continue;
    $mc=np((string)($r['matched_customer']??''));
    if($mc!==''&&$mc!==np((string)($convs[$cid]['customer_number']??'')))continue;
    $b=np((string)($r['business_number']??''));if($b!==''&&$p['business']!==''&&$b!==$p['business'])continue;
    $t=ce($r);if(!$t||$t>$p['first']||$p['first']>$t+300)continue;
    $candByConv[$cid][]=$clickId;$convByClick[$clickId][]=$cid;
  }
}
$repaired=[];$ambiguous=[];
foreach($eligible as $cid=>$p){
  $cs=array_values(array_unique($candByConv[$cid]??[]));
  if(count($cs)!==1){if(count($cs)>1)$ambiguous[]=['conversation_id'=>$cid,'clicks'=>$cs];continue;}
  $clickId=$cs[0];$cv=array_values(array_unique($convByClick[$clickId]??[]));
  if(count($cv)!==1){$ambiguous[]=['conversation_id'=>$cid,'click_id'=>$clickId,'conversations'=>$cv];continue;}
  $r=$clicks[$clickId];copy_attr($convs[$cid],$r,$clickId);queue_google($googleQueue,$convs[$cid]);
  $clicks[$clickId]['matched_at']=gmdate('c');$clicks[$clickId]['matched_customer']=(string)($convs[$cid]['customer_number']??'');$clicks[$clickId]['matched_business']=(string)($convs[$cid]['business_number']??'');$clicks[$clickId]['match_method']='single_unique_browser_5m_window_repair';
  $repaired[]=['conversation_id'=>$cid,'customer'=>$convs[$cid]['customer_number']??'','first_seen_at'=>$convs[$cid]['first_seen_at']??'','source'=>$convs[$cid]['traffic_source_key']??'','confidence'=>$convs[$cid]['traffic_source_confidence']??'','click_id'=>$clickId,'browser_time'=>$r['browser_time']??'','gclid'=>$r['gclid']??''];
}
if($repaired){js($convFile,$convs);js($clickFile,$clicks);}
echo json_encode(['ok'=>true,'eligible'=>count($eligible),'repaired'=>count($repaired),'items'=>$repaired,'ambiguous'=>count($ambiguous),'ambiguous_items'=>$ambiguous],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";
