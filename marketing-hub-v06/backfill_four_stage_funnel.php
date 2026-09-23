<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$base=__DIR__.'/data';
$convFile=$base.'/conversations.json';
$eventsFile=$base.'/conversion_events.jsonl';
$googleFile=$base.'/google_conversion_events.jsonl';
$clients=['cl_0e6efd258397db'=>'Bcare','cl_3ea5ae96e05c6b'=>'Almowahid','cl_cbb797950cc8d4'=>'Etizan'];
$googleClients=['cl_0e6efd258397db'=>true,'cl_3ea5ae96e05c6b'=>true];

function loadj(string $f):array{$v=is_file($f)?json_decode((string)file_get_contents($f),true):[];return is_array($v)?$v:[];}
function savej(string $f,array $v):void{$t=$f.'.tmp';file_put_contents($t,json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);rename($t,$f);}
function valid_type(string $t):bool{return !in_array(strtolower(trim($t)),['','unsupported','reaction','system','unknown','revoke','revoked'],true);}
function contains_any(string $text,array $needles):bool{$t=mb_strtolower($text,'UTF-8');foreach($needles as $n)if($n!==''&&mb_strpos($t,mb_strtolower((string)$n,'UTF-8'))!==false)return true;return false;}
function rank_stage(string $s):int{return match($s){'message_received'=>0,'interested'=>1,'qualified'=>2,'purchased','converted'=>3,'lost'=>90,default=>-1};}
function reply_after_staff(array $c):bool{
  $seen=false;
  foreach((array)($c['recent_messages']??[]) as $m){
    if(!is_array($m))continue;
    $d=(string)($m['direction']??'');$t=(string)($m['type']??'');
    if(str_contains($d,'outbound')){$seen=true;continue;}
    if($seen&&str_contains($d,'inbound')&&valid_type($t))return true;
  }
  return false;
}
function serious(array $c):bool{
  $need=['السعر','التكلفة','موعد','احجز','حجز','التوفر','متاح','المدة','المتطلبات','الأوراق','المستندات','نبدأ','ابدأ','أبدأ','التقسيط','الدفع','تحويل','مساند','زيارة','موقعكم','العنوان','استشارة','قضية','عقد','أتعاب','الاتعاب','شركة','تركة','تنفيذ','دعوى','توكيل','price','cost','appointment','book','booking','available','availability','requirements','documents','payment'];
  foreach((array)($c['recent_messages']??[]) as $m){
    if(!is_array($m)||!str_contains((string)($m['direction']??''),'inbound'))continue;
    $type=(string)($m['type']??'');if(!valid_type($type))continue;
    if(contains_any((string)($m['text']??''),$need))return true;
    if(in_array($type,['document','image','location','contacts'],true))return true;
  }
  return false;
}
function explicit_conversion(array $c):bool{
  $need=['تم الدفع','دفعت','تم التحويل','حولت','حوّلت','تم الحجز','حجزت الموعد','تم تأكيد الحجز','تم التعاقد','وقعت العقد','وقّعت العقد','تم توقيع العقد','تم إصدار العقد','تم اصدار العقد','تم قبول الطلب','paid','payment done','payment completed','booking confirmed','contract signed','order confirmed'];
  foreach((array)($c['recent_messages']??[]) as $m){
    if(!is_array($m)||!str_contains((string)($m['direction']??''),'inbound'))continue;
    if(!valid_type((string)($m['type']??'')))continue;
    if(contains_any((string)($m['text']??''),$need))return true;
  }
  return false;
}
function desired(array $c):string{
  $valid=(int)($c['valid_inbound_count']??$c['inbound_count']??0);
  $out=(int)($c['outbound_count']??0);
  if($valid<1)return '';
  if($valid>=3&&$out>=1&&explicit_conversion($c))return 'converted';
  if($valid>=3&&$out>=1&&reply_after_staff($c)&&serious($c))return 'qualified';
  if($valid>=2)return 'interested';
  return 'message_received';
}
function read_events(string $f):array{
  $out=[];if(!is_file($f))return$out;$h=fopen($f,'rb');
  while(($line=fgets($h))!==false){$r=json_decode($line,true);if(is_array($r))$out[]=$r;}
  fclose($h);return$out;
}
function has_event(array $rows,string $conv,string $stage):bool{
  foreach($rows as $r){if((string)($r['conversation_id']??'')===$conv&&(string)($r['event']??'')===$stage)return true;}
  return false;
}
function has_google(array $rows,string $eid):bool{
  foreach($rows as $r)if((string)($r['event_id']??'')===$eid)return true;
  return false;
}
function append_line(string $f,array $r):void{file_put_contents($f,json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n",FILE_APPEND|LOCK_EX);}

$convs=loadj($convFile);$evRows=read_events($eventsFile);$gRows=read_events($googleFile);
$changed=0;$stats=[];$details=[];
foreach($clients as $cid=>$name)$stats[$cid]=['name'=>$name,'message_received'=>0,'interested'=>0,'qualified'=>0,'converted'=>0,'lost'=>0,'unchanged_manual'=>0];

foreach($convs as $id=>$c){
  if(!is_array($c))continue;$cid=(string)($c['client_id']??'');if(!isset($clients[$cid]))continue;
  $manual=(string)($c['tag_source']??'')!==''&&(string)($c['tag_source']??'')!=='automation';
  $cur=(string)($c['current_tag']??'');
  if($manual){
    $shown=$cur==='purchased'?'converted':$cur;
    if(isset($stats[$cid][$shown]))$stats[$cid][$shown]++;
    $stats[$cid]['unchanged_manual']++;continue;
  }
  $want=desired($c);if($want==='')continue;
  $curNorm=$cur==='purchased'?'converted':$cur;
  // Preserve Lost and never downgrade a positive stage.
  if($cur==='lost'){$stats[$cid]['lost']++;continue;}
  if($curNorm!==''&&rank_stage($curNorm)>rank_stage($want))$want=$curNorm;
  if($curNorm===''||$curNorm!==$want){
    $now=gmdate('c');$c['current_tag']=$want;$c['tagged_at']=$now;$c['tag_source']='automation';
    $c['auto_label_reason']=match($want){
      'message_received'=>'first_valid_customer_message',
      'interested'=>'two_valid_customer_messages',
      'qualified'=>'three_plus_messages_two_way_serious_intent',
      'converted'=>'explicit_completed_business_outcome',default=>'funnel_backfill'
    };
    $c['auto_label_confidence']=match($want){'message_received'=>1.0,'interested'=>0.96,'qualified'=>0.93,'converted'=>0.99,default=>0.8};
    $hist=is_array($c['label_history']??null)?$c['label_history']:[];
    $hist[]=['tag'=>$want,'source'=>'automation','reason'=>$c['auto_label_reason'],'confidence'=>$c['auto_label_confidence'],'at'=>$now,'backfill'=>true];
    if(count($hist)>20)$hist=array_slice($hist,-20);$c['label_history']=$hist;
    $convs[$id]=$c;$changed++;
    $details[]=['conversation_id'=>$id,'client'=>$name,'from'=>$curNorm?:'none','to'=>$want];

    if(!has_event($evRows,(string)$id,$want)){
      $er=['id'=>'mev_'.bin2hex(random_bytes(7)),'event'=>$want,'conversation_id'=>(string)$id,'client_id'=>$cid,'number_id'=>(string)($c['number_id']??''),'ycloud_connection_id'=>(string)($c['ycloud_connection_id']??''),'waba_id'=>(string)($c['waba_id']??''),'business_number'=>(string)($c['business_number']??''),'customer_number'=>(string)($c['customer_number']??''),'contact_name'=>(string)($c['contact_name']??''),'ctwa_clid'=>$c['ctwa_clid']??null,'source'=>'automation_backfill','created_at'=>$now];
      append_line($eventsFile,$er);$evRows[]=$er;
    }
  } else {
    $convs[$id]=$c;
  }

  $final=(string)($convs[$id]['current_tag']??$want);$shown=$final==='purchased'?'converted':$final;
  if(isset($stats[$cid][$shown]))$stats[$cid][$shown]++;

  if(isset($googleClients[$cid])){
    $stageMap=['message_received'=>'message_sent','interested'=>'interested','qualified'=>'qualified','converted'=>'converted','purchased'=>'converted'];
    $gs=$stageMap[$shown]??'';
    if($gs!==''){
      $eid='gcv_'.substr(hash('sha256',$cid.'|'.$id.'|'.$gs),0,32);
      if(!has_google($gRows,$eid)){
        $gr=['event_id'=>$eid,'client_id'=>$cid,'conversation_id'=>(string)$id,'stage'=>$gs,'occurred_at'=>(string)($convs[$id]['first_seen_at']??gmdate('c')),'source_event_id'=>'backfill','created_at'=>gmdate('c')];
        append_line($googleFile,$gr);$gRows[]=$gr;
      }
    }
  }
}
if($changed)savej($convFile,$convs);
echo json_encode(['ok'=>true,'changed'=>$changed,'stats'=>$stats,'details'=>array_slice($details,0,100)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";
