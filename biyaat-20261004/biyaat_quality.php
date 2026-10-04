<?php
declare(strict_types=1);
// Nursery-specific rules. No customer text, child details or scores are exported to ad platforms.
function bq_clean(string $text):string {
    $text=preg_replace('/BIA-AT-[A-F0-9]{24}|REF:[^\r\n]*/i','',$text)??$text;
    $text=strtr($text,['أ'=>'ا','إ'=>'ا','آ'=>'ا','ى'=>'ي','ة'=>'ه']);
    $text=preg_replace('/[\p{M}\p{Cf}]+/u','',$text)??$text;
    return mb_strtolower(trim(preg_replace('/\s+/u',' ',$text)??$text),'UTF-8');
}
function bq_has(string $text,array $terms):bool {foreach($terms as$t)if(str_contains($text,bq_clean($t)))return true;return false;}
function bq_eval(array $messages):array {
    usort($messages,fn($a,$b)=>strcmp((string)($a['at']??''),(string)($b['at']??'')));
    $meaningful=0;$staff=false;$reply=false;$service=false;$age=false;$period=false;$location=false;$next=false;$completed=false;$positiveAt=-1;$negativeAt=-1;$lastPositive='';$reasons=[];
    foreach($messages as$i=>$m){
        $direction=(string)($m['direction']??'');if(str_contains($direction,'outbound')){$staff=true;continue;}if(!str_contains($direction,'inbound'))continue;
        $type=(string)($m['type']??'text');if(in_array($type,['reaction','revoke','unknown','unsupported','system'],true))continue;
        $t=bq_clean((string)($m['text']??''));
        if($t===''||!preg_match('/[\p{L}\p{N}]/u',$t))continue;
        if((str_contains($t,'tiktok')&&bq_has($t,['صادفت','أود معرفة المزيد','came across your ad','would like to find out more']))||$t===bq_clean('السلام عليكم، أرغب بحجز جولة والتعرف على بيئات لطفل المستقبل.'))continue;
        $neutral=['هلا','مرحبا','السلام عليكم','وعليكم السلام','اهلا','اهلين','تمام','طيب','نعم','اي','ايوه','اوكي','شكرا','جزاك الله خير','ok','okay','thanks','hello','hi'];
        $short=preg_replace('/[\p{P}\p{S}\s]+/u','',$t)??$t;
        if(in_array($short,array_map(fn($v)=>preg_replace('/[\p{P}\p{S}\s]+/u','',bq_clean($v)),$neutral),true))continue;
        if(bq_has($t,['غير مهتم','مش مهتم','لست مهتم','ما ابغى','ما ابي','لا اريد','وقف التواصل','لا تتواصل','not interested','do not contact','unsubscribe','ابغى وظيفة','ابي وظيفة','طلب توظيف','فرص عمل','وظائف','وظيفة','مين انت','من معي'])){$negativeAt=$i;continue;}
        $meaningful++;if($staff)$reply=true;
        $s=bq_has($t,['روضة','حضانة','تسجيل','طفل','اطفال','بنتي','ولدي','ابني','منهج','اشتراك','رسوم','قسط','kg','nursery','kindergarten','الاسعار','السعر','التكلفة','كم السعر','كم الرسوم']);
        $a=(bool)preg_match('/(?:عمر|عمره|عمرها|طفلي|بنتي|ولدي).{0,25}(?:[٠-٩0-9]|سنتين|ثلاث|اربع|خمس|ست)|[٠-٩0-9].{0,12}(?:سنوات|سنين|سنة|شهور)/u',$t);
        $p=bq_has($t,['صباح','مساء','صباحية','مسائية','الفترة المفضلة:','period:']);
        $l=bq_has($t,['جدة','الخالدية','الحي','العنوان','الموقع','موقعكم']);
        $n=bq_has($t,['احجز','حجز جولة','زيارة','ازور','اشوف المكان','موعد','ابغى اسجل','ابي اسجل','اريد التسجيل','كيف اسجل','ابدأ التسجيل','نتواصل','كلمني','اتصل','book a visit','enroll']);
        $c=bq_has($t,['تم الدفع','دفعت الرسوم','حولت الرسوم','تم التحويل','تم تأكيد الحجز','تأكد الحجز','تم التسجيل','payment completed','booking confirmed']);
        if(bq_has($t,['لم ادفع','ما دفعت','هل تم','هل الحجز','لسه ما','لم يتم','ما تم']))$c=false;
        if($s||$a||$p||$n||$c){$positiveAt=$i;$lastPositive=(string)($m['at']??'');}
        $service=$service||$s;$age=$age||$a;$period=$period||$p;$location=$location||$l;$next=$next||$n;$completed=$completed||$c;
    }
    $fit=(int)$age+(int)$period+(int)$location;
    $stage='message_received';$confidence=0.95;
    if($negativeAt>$positiveAt){$stage='unqualified';$reasons[]='explicit_rejection_or_out_of_scope';}
    elseif($completed&&($service||$age)&&$reply){$stage='converted';$confidence=0.98;$reasons[]='explicit_completed_nursery_outcome';}
    elseif(($service||$age)&&$fit>=1&&$next&&$reply){$stage='qualified';$confidence=0.92;$reasons[]='nursery_fit_and_next_step_two_way';}
    elseif($positiveAt>=0&&($service||$age||$next)&&$meaningful>=1){$stage='interested';$confidence=0.90;$reasons[]='genuine_nursery_inquiry';}
    else{$reasons[]='no_reliable_nursery_intent';}
    return ['stage'=>$stage,'confidence'=>$confidence,'score'=>match($stage){'interested'=>55,'qualified'=>85,'converted'=>100,'unqualified'=>0,default=>10},'reason'=>implode(';',$reasons),'signal_at'=>$lastPositive,'meaningful_count'=>$meaningful,'fit_fields'=>$fit];
}
function bq_manual(array $conv):bool{return in_array(strtolower((string)($conv['tag_source']??'')),['manual','operator','crm','user'],true);}
function bq_stage(array $conv):array {
    if(bq_manual($conv)){$s=strtolower((string)($conv['current_tag']??'message_received'));if($s==='purchased')$s='converted';return ['stage'=>$s,'confidence'=>1.0,'score'=>0,'reason'=>'manual_override','signal_at'=>$conv['tagged_at']??''];}
    return bq_eval((array)($conv['recent_messages']??[]));
}
