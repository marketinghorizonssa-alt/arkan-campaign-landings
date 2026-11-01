<?php
declare(strict_types=1);
function ara_s(mixed $v):string{return is_scalar($v)?trim((string)$v):'';}
function ara_phone(mixed $v):string{return preg_replace('/\D+/','',ara_s($v))??'';}
function ara_first(array $c):int{return strtotime(ara_s($c['first_customer_message_at']??''))?:0;}
function ara_click_time(array $r):int{$server=strtotime(ara_s($r['created_at']??$r['captured_at']??''))?:0;$browser=strtotime(ara_s($r['browser_time']??''))?:0;return $server>0&&$browser>0&&abs($server-$browser)<=120?$browser:$server;}
function ara_click_valid(array $r,string $business,string $customer='',string $token=''):bool{
 if(($r['client_id']??'')!=='cl_76c4e019afc588'||!empty($r['is_test'])||ara_phone($r['business_number']??'')===''||ara_phone($r['business_number']??'')!==ara_phone($business))return false;
 if(!empty($r['matched_customer'])&&ara_phone($r['matched_customer'])!==ara_phone($customer))return false;
 if(($r['token_version']??'')!=='hzn1'||empty($r['token_verified']))return false;
 return $token===''||(!empty($r['click_token'])&&hash_equals(ara_s($r['click_token']),$token));
}
function ara_apply(array &$c,array $r,string $id,string $method):void{
 $c['traffic_source_previous']=$c['traffic_source_key']??'';$c['traffic_source_key']=$r['traffic_source_key']??'website';$c['traffic_source_label']=$r['traffic_source_label']??'Website';$c['traffic_source_confidence']=$method==='single_unique_browser_5m_window'?'medium':'high';$c['traffic_source_reason']=$method;$c['attribution_match_method']=$method;$c['attribution_matched_at']=gmdate('c');
 $c['horizons_wa_click_id']=$id;$c['ycloud_chatlink_click_id']=$id;$c['chatlink_source_url']=$r['source_url']??'';$c['attribution_landing_url']=$r['landing_url']??'';$c['attribution_referrer']=$r['referrer']??'';
 foreach(['query_params'=>'attribution_params','utm'=>'attribution_utm','first_touch'=>'attribution_first_touch','last_touch'=>'attribution_last_touch','current_touch'=>'attribution_current_touch','touch_history'=>'attribution_touch_history'] as $from=>$to)$c[$to]=$r[$from]??[];
 foreach(['gclid'=>'google_gclid','gbraid'=>'google_gbraid','wbraid'=>'google_wbraid','dclid'=>'google_dclid','ttclid'=>'tiktok_ttclid','fbclid'=>'meta_fbclid','msclkid'=>'microsoft_msclkid','li_fat_id'=>'linkedin_li_fat_id','twclid'=>'x_twclid'] as $from=>$to){unset($c[$to]);if(!empty($r[$from]))$c[$to]=$r[$from];}
 foreach((array)($r['utm']??[]) as $k=>$v)if(str_starts_with((string)$k,'utm_'))$c[$k]=$v;
}
function ara_resolve(array &$convs,array &$clicks,int $now):array{
 $stats=['resolved'=>0,'ambiguous'=>0,'unknown'=>0,'deferred'=>0];$pending=[];
 foreach($convs as $id=>$c){if(!is_array($c)||($c['client_id']??'')!=='cl_76c4e019afc588')continue;$m=$c['attribution_match_method']??'';$retry=$m==='timestamp_unknown'&&($c['traffic_source_reason']??'')==='no_click_within_5m';if($m!=='timestamp_pending'&&!$retry)continue;if(!in_array($c['traffic_source_key']??'unknown',['','unknown','website','organic'],true))continue;$t=ara_first($c);if(!$t||ara_phone($c['business_number']??'')==='')continue;if($t+300>$now){$stats['deferred']++;continue;}$pending[$id]=$t;}
 foreach($pending as $id=>$first){$c=$convs[$id];$hits=[];foreach($clicks as $key=>$r){if(!is_array($r)||!ara_click_valid($r,ara_s($c['business_number']??''),ara_s($c['customer_number']??'')))continue;$t=ara_click_time($r);if(!$t||$t>$first||$t<$first-300)continue;if(!empty($r['matched_at'])&&($r['match_method']??'')!=='expired_no_message')continue;$hits[$key]=$t;}
  $ambiguous=count($hits)>1;
  if(count($hits)===1){$key=(string)array_key_first($hits);$t=$hits[$key];$recipients=[];foreach($convs as $oid=>$other){if(!is_array($other)||($other['client_id']??'')!=='cl_76c4e019afc588'||ara_phone($other['business_number']??'')!==ara_phone($c['business_number']??''))continue;$om=$other['attribution_match_method']??'';if(!in_array($om,['','timestamp_pending','timestamp_unknown'],true))continue;$ot=ara_first($other);if($ot>=$t&&$ot<=$t+300)$recipients[$oid]=true;}
   if(count($recipients)===1){ara_apply($convs[$id],$clicks[$key],$key,'single_unique_browser_5m_window');$clicks[$key]['matched_at']=gmdate('c',$now);$clicks[$key]['matched_customer']=$c['customer_number']??'';$clicks[$key]['matched_business']=$c['business_number']??'';$clicks[$key]['match_method']='single_unique_browser_5m_window';$stats['resolved']++;continue;}$ambiguous=true;
  }
  $convs[$id]['traffic_source_key']='unknown';$convs[$id]['traffic_source_label']='Unknown / Unattributed';$convs[$id]['traffic_source_confidence']='unknown';$convs[$id]['traffic_source_reason']=$ambiguous?'ambiguous_click_or_sender_within_5m':'no_click_within_5m';$convs[$id]['attribution_match_method']='timestamp_unknown';$stats[$ambiguous?'ambiguous':'unknown']++;
 }
 return $stats;
}
