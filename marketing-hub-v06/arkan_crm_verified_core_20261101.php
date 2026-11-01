<?php
declare(strict_types=1);
// Pure ARKAN-only rules. No network, file writes, or automatic CRM status updates.
function arc_s(mixed $v): string {return is_scalar($v)?trim((string)$v):'';}
function arc_phone(mixed $v): string {return preg_replace('/\D+/','',arc_s($v))??'';}
function arc_origin_allowed(string $origin): bool {return $origin===''||in_array(strtolower(rtrim($origin,'/')),['https://marketing.hositee.com','https://marketing.hositee.com:443'],true);}
function arc_dm_summary(array $d,int $submitted): array {
 $errors=$d['errorInfo']['errorCounts']??[];$received=(int)($d['eventsIngestionStatus']['recordCount']??0);$status=arc_s($d['requestStatus']??'');
 $failed=count($errors)===1?(int)$errors[0]['recordCount']:($status==='SUCCESS'?0:null);
 return ['google_request_status'=>$status,'submitted'=>$submitted,'received'=>$received,'processed_without_reported_errors'=>$received===$submitted&&$failed!==null?max(0,$received-$failed):null,'reported_error_records'=>$failed,'error_counts'=>$errors,'individual_record_outcomes_known'=>$status==='SUCCESS'];
}
function arc_client(array $c,array $numbers,array $connections): string {
 $direct=arc_s($c['client_id']??'');if($direct!=='')return $direct;
 $connection=arc_s($c['ycloud_connection_id']??'');
 if($connection!==''&&arc_s($connections[$connection]['client_id']??'')!=='')return arc_s($connections[$connection]['client_id']);
 $phone=arc_phone($c['business_number']??'');$waba=arc_s($c['waba_id']??'');$matches=[];
 foreach($numbers as $n){$cid=arc_s($n['client_id']??'');if($cid==='')continue;if(($phone!==''&&arc_phone($n['phone_number']??'')===$phone)||($waba!==''&&arc_s($n['waba_id']??'')===$waba))$matches[$cid]=true;}
 return count($matches)===1?(string)array_key_first($matches):'';
}
function arc_manual(array $c): bool {return !empty($c['manual_override'])||($c['tag_source']??'')==='manual';}
function arc_stage(string $s): string {return $s==='purchased'?'converted':$s;}
function arc_tag_decision(array $c,string $tag,string $reference,string $now): array {
 $tag=arc_stage($tag);$manual=$tag!=='auto';$next=$manual?$tag:'message_received';
 $changed=arc_stage(arc_s($c['current_tag']??''))!==$next||arc_manual($c)!==$manual;
 $ref=$reference!==''?$reference:arc_s($c['conversion_evidence_reference']??'');
 if($manual&&$tag==='converted'&&$ref==='')return ['ok'=>false,'error'=>'conversion_evidence_required'];
 $out=['ok'=>true,'changed'=>$changed,'evidence_reference'=>substr($ref,0,160)];
 if($manual&&in_array($tag,['qualified','converted'],true)){
  $old=arc_s($c['manual_verified_at']??'');
  $at=$changed||$old===''?$now:$old;
  $out+=['manual_verified_stage'=>$tag,'manual_verified_at'=>$at,'verification_id'=>'arkv_'.substr(hash('sha256',arc_s($c['id']??'').'|'.$tag.'|'.$at),0,32)];
 }
 return $out;
}
function arc_verified_time(array $c,string $stage): string {
 if(!arc_manual($c)||arc_stage(arc_s($c['current_tag']??''))!==$stage)return '';
 if($stage==='converted'&&arc_s($c['conversion_evidence_reference']??'')==='')return '';
 if(($c['manual_verified_stage']??'')===$stage&&arc_s($c['manual_verified_at']??'')!=='')return arc_s($c['manual_verified_at']);
 foreach(array_reverse($c['label_history']??[]) as $h)if(($h['source']??'')==='manual'&&arc_stage(arc_s($h['tag']??''))===$stage&&arc_s($h['at']??'')!=='')return arc_s($h['at']);
 return '';
}
function arc_contains(string $text,array $words): bool {foreach($words as $word)if(str_contains($text,$word))return true;return false;}
function arc_observed(array $messages): array {
 $staff=false;$reply=false;$intent=false;$finance=false;$next=false;$docsRequested=false;$docsReceived=false;$meaningful=0;$valid=0;
 foreach($messages as $m){$dir=arc_s($m['direction']??'');$type=arc_s($m['type']??'');$txt=arc_s($m['text']??'');
  if($dir==='outbound'){$staff=true;if(arc_contains($txt,['تعريف بالراتب','تقرير سمة','أرسل المستندات','ارسل المستندات','أرسل الأوراق','ارسل الاوراق']))$docsRequested=true;continue;}
  if($dir!=='inbound'||in_array($type,['','reaction','unsupported','system','unknown','revoke','revoked'],true))continue;
  $valid++;if($staff)$reply=true;if($docsRequested&&in_array($type,['image','document'],true))$docsReceived=true;
  if($txt===''||preg_match('/^\[(image|document|audio|video|sticker|location|contacts)\]$/i',$txt)||in_array($txt,['السلام عليكم','وعليكم السلام','هلا','مرحبا','تمام','طيب','نعم','شكرا','شكراً'],true))continue;
  $service=arc_contains($txt,['تمويل','مديونية','مديونيه','قرض','قروض','رهن','استقطاع','سداد مبكر','إعادة جدولة','اعادة جدولة','عقار','التزامات','سمة']);
  if(arc_contains($txt,['صادفت إعلانك على TikTok','came across your ad on TikTok'])&&!$service)continue;
  $meaningful++;$intent=$intent||$service;
  $finance=$finance||(arc_contains($txt,['راتب','دخل','قسط','أقساط','اقساط','استقطاع','التزام','المتبقي','البنك'])&&preg_match('/[0-9٠-٩]{2,}/u',$txt));
  $next=$next||arc_contains($txt,['وش المطلوب','ايش المطلوب','وش الخطوة','الخطوة الجاية','أرسل الأوراق','ارسل الاوراق','ارسل المستندات','كلمني','اتصلوا','دق علي','أبغى موعد','ابي موعد','أرسل لك','ارسل لك','نبدأ','ابدأ']);
 }
 $stage=$meaningful>0?'interested':'message_received';$reason=$meaningful>0?'customer_intent_needs_review':'first_valid_customer_message';
 if($intent&&$reply&&($docsReceived||($meaningful>=2&&$finance&&$next))){$stage='qualified';$reason=$docsReceived?'requested_documents_received_needs_review':'financial_details_and_next_step_need_review';}
 return ['stage'=>$stage,'reason'=>$reason,'valid_inbound'=>$valid];
}
function arc_quality(array $c,array $messages): array {
 $observed=arc_observed($messages);$manual=arc_manual($c);$tag=arc_stage(arc_s($c['current_tag']??''));
 $allowed=['message_received','interested','qualified','converted','unqualified','disqualified','spam','lost'];
 $key=$manual&&in_array($tag,$allowed,true)?$tag:$observed['stage'];
 if($key==='unqualified'&&in_array($c['unqualified_type']??'',['spam','disqualified'],true))$key=$c['unqualified_type'];
 $verified=in_array($key,['qualified','converted'],true)&&arc_verified_time($c,$key)!=='';
 return ['key'=>$key,'label'=>ucwords(str_replace('_',' ',$key)),'score'=>isset($c['ai_quality_score'])&&is_numeric($c['ai_quality_score'])&&(int)$c['ai_quality_score']>0?(int)$c['ai_quality_score']:null,'summary'=>$manual?'تقييم يدوي محفوظ':$observed['reason'],'reason'=>$manual?'manual_crm_review':$observed['reason'],'manual_override'=>$manual,'requires_review'=>in_array($key,['qualified','converted'],true)&&!$verified,'manual_verified'=>$verified,'postback_verified'=>false,'method'=>$manual?'manual_override':'arkan_conservative_review'];
}
function arc_source(array $source): array {
 if(($source['key']??'')==='organic'&&($source['reason']??'')==='direct_whatsapp_no_ad_or_site_signal')return ['key'=>'unknown','label'=>'Unknown / Unattributed','confidence'=>'unknown','reason'=>'no_attribution_evidence'];
 return $source;
}
