<?php
declare(strict_types=1);
// Pure ARKAN-only rules. No network, file writes, or automatic CRM status updates.
function arc_s(mixed $v): string {return is_scalar($v)?trim((string)$v):'';}
function arc_phone(mixed $v): string {return preg_replace('/\D+/','',arc_s($v))??'';}
function arc_origin_allowed(string $origin): bool {return $origin===''||in_array(strtolower(rtrim($origin,'/')),['https://marketing.hositee.com','https://marketing.hositee.com:443'],true);}
function arc_test_record(array $c): bool {return !empty($c['is_test'])||!empty($c['test'])||in_array(strtolower(arc_s($c['environment']??'')),['test','sandbox'],true)||preg_match('/(^|[\s_-])(test|testing|اختبار|تجربة)([\s_-]|$)/iu',arc_s($c['contact_name']??''))===1;}
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
function arc_review_contact_key(array $c): string {return hash('sha256',arc_phone($c['customer_number']??'').'|'.arc_phone($c['business_number']??'').'|cl_76c4e019afc588');}
function arc_review_signature(array $r,string $key): string {
 unset($r['signature']);ksort($r);return hash_hmac('sha256',json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$key);
}
function arc_assistant_review(array $c,?string $key=null): array {
 if(($c['tag_source']??'')!=='assistant_review'||empty($c['manual_override']))return [];
 $r=$c['assistant_review']??[];if(!is_array($r)||($r['actor']??'')!=='assistant_conversation_review'||($r['criteria_version']??'')!=='arkan-evidence-v2'||($r['contact_key']??'')!==arc_review_contact_key($c))return [];
 if($key===null){static $secret=null;if($secret===null)$secret=trim((string)@file_get_contents(dirname(__DIR__,4).'/.marketing/arkan-review-20261101/review-key'));$key=$secret;}
 if($key===''||!hash_equals(arc_review_signature($r,$key),arc_s($r['signature']??'')))return [];
 return $r;
}
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
 $current=arc_stage(arc_s($c['current_tag']??''));
 if(($c['tag_source']??'')==='assistant_review'){
  $r=arc_assistant_review($c);if(!$r||($r['stage']??'')!==$current||$current!==$stage||!empty($r['requires_review'])||empty($r['evidence_message_ids'])||empty($r['google_qualified']))return '';
  if($stage==='converted'&&arc_s($c['conversion_evidence_reference']??'')==='')return '';
  return arc_s($r['evidence_at']??'');
 }
 if(!arc_manual($c)||($current!==$stage&&!($stage==='qualified'&&$current==='converted')))return '';
 if($stage==='converted'&&arc_s($c['conversion_evidence_reference']??'')==='')return '';
 if(($c['manual_verified_stage']??'')===$stage&&arc_s($c['manual_verified_at']??'')!=='')return arc_s($c['manual_verified_at']);
 foreach(array_reverse($c['label_history']??[]) as $h)if(($h['source']??'')==='manual'&&arc_stage(arc_s($h['tag']??''))===$stage&&arc_s($h['at']??'')!=='')return arc_s($h['at']);
 return '';
}
function arc_contains(string $text,array $words): bool {foreach($words as $word)if(str_contains($text,$word))return true;return false;}
function arc_observed(array $messages): array {
 $staff=false;$reply=false;$intent=false;$finance=false;$next=false;$docsRequested=false;$docsReceived=false;$meaningful=0;$valid=0;$casework=false;$supplier=0;$declined=false;$job=false;
 foreach($messages as $m){$dir=arc_s($m['direction']??'');$type=arc_s($m['type']??'');$txt=arc_s($m['text']??'');
  if($dir==='outbound'){$staff=true;
   if(arc_contains($txt,['تعريف بالراتب','تقرير سمة','أرسل المستندات','ارسل المستندات','أرسل الأوراق','ارسل الاوراق']))$docsRequested=true;
   if(arc_contains($txt,['حسبتك طلعت','أدرس لك طلبك','ادرس لك طلبك','طلبك تحت الدراسة','اشوف حسبتك','شغالين على طلبك','السداد المبكر كم','وش الالتزامات الي عليك']))$casework=true;
   if(arc_contains($txt,['عطني عرض السعر','سجل حضور ونصراف','جهز لي الكمية','الفاتورة حق الإجار','بغيت اطلب مويه','كم سعر الكرتون','خذ المقاسات نتفق اعطيك عربون','كم سعرها','الحواله جتك']))$supplier++;
   if(arc_contains($txt,['للاسف نعتذر منك','للأسف نعتذر منك','حاليا لا والله رفضو طلبك','للاسف مانقدر','حاولت امشي طلبك بس للاسف']))$declined=true;continue;}
  if($dir!=='inbound'||in_array($type,['','reaction','unsupported','system','unknown','revoke','revoked'],true))continue;
  $valid++;if($staff)$reply=true;if($docsRequested&&in_array($type,['image','document'],true))$docsReceived=true;
  if(arc_contains($txt,['ابغى شغل','ابغى وظيفة','أبحث عن وظيفة','باحث عن عمل']))$job=true;
  if($txt===''||preg_match('/^\[(image|document|audio|video|sticker|location|contacts)\]$/i',$txt)||in_array($txt,['السلام عليكم','وعليكم السلام','هلا','مرحبا','تمام','طيب','نعم','شكرا','شكراً'],true))continue;
  $service=arc_contains($txt,['تمويل','مديونية','مديونيه','قرض','قروض','رهن','استقطاع','سداد مبكر','إعادة جدولة','اعادة جدولة','عقار','التزامات','سمة']);
  if(arc_contains($txt,['صادفت إعلانك على TikTok','came across your ad on TikTok'])&&!$service)continue;
  $meaningful++;$intent=$intent||$service;
  $finance=$finance||(arc_contains($txt,['راتب','دخل','قسط','أقساط','اقساط','استقطاع','التزام','المتبقي','البنك'])&&preg_match('/[0-9٠-٩]{2,}/u',$txt));
  $next=$next||arc_contains($txt,['وش المطلوب','ايش المطلوب','وش الخطوة','الخطوة الجاية','أرسل الأوراق','ارسل الاوراق','ارسل المستندات','كلمني','اتصلوا','دق علي','أبغى موعد','ابي موعد','أرسل لك','ارسل لك','نبدأ','ابدأ']);
 }
 $stage=$meaningful>0?'interested':'message_received';$reason=$meaningful>0?'customer_intent_needs_review':'first_valid_customer_message';
 if($intent&&$finance&&$reply&&$casework&&($docsReceived||($meaningful>=2&&$next))){$stage='qualified';$reason='specific_financial_casework_candidate_requires_evidence_review';}
 if($declined){$stage='lost';$reason='explicit_staff_decline_needs_review';}
 if($job||($supplier>=2&&!$finance)){$stage='unqualified';$reason=$job?'job_request_not_financial_customer':'operational_supplier_not_acquisition_candidate';}
 return ['stage'=>$stage,'reason'=>$reason,'valid_inbound'=>$valid];
}
function arc_quality(array $c,array $messages): array {
 $observed=arc_observed($messages);$manual=arc_manual($c);$tag=arc_stage(arc_s($c['current_tag']??''));
 $review=arc_assistant_review($c);$assistant=(bool)$review;
 $allowed=['message_received','interested','qualified','converted','unqualified','disqualified','spam','lost'];
 $key=$manual&&in_array($tag,$allowed,true)?$tag:$observed['stage'];
 if($key==='unqualified'&&in_array($c['unqualified_type']??'',['spam','disqualified'],true))$key=$c['unqualified_type'];
 $verified=in_array($key,['qualified','converted'],true)&&arc_verified_time($c,$key)!=='';
 return ['key'=>$key,'label'=>ucwords(str_replace('_',' ',$key)),'score'=>$assistant?null:(isset($c['ai_quality_score'])&&is_numeric($c['ai_quality_score'])&&(int)$c['ai_quality_score']>0?(int)$c['ai_quality_score']:null),'summary'=>$assistant?arc_s($review['reason']??'') :($manual?'تقييم يدوي محفوظ':$observed['reason']),'reason'=>$assistant?'assistant_evidence_review':($manual?'manual_crm_review':$observed['reason']),'manual_override'=>$manual,'requires_review'=>$assistant?!empty($review['requires_review']):(in_array($key,['qualified','converted'],true)&&!$verified),'manual_verified'=>$verified&&!$assistant,'evidence_verified'=>$assistant||$verified,'postback_verified'=>false,'method'=>$assistant?'assistant_conversation_review':($manual?'manual_override':'arkan_conservative_review')];
}
function arc_source(array $source): array {
 if(($source['key']??'')==='organic'&&($source['reason']??'')==='direct_whatsapp_no_ad_or_site_signal')return ['key'=>'unknown','label'=>'Unknown / Unattributed','confidence'=>'unknown','reason'=>'no_attribution_evidence'];
 return $source;
}
