<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$base=__DIR__.'/data';
$convFile=$base.'/conversations.json';
$clientsFile=$base.'/clients.json';
$eventsFile=$base.'/conversion_events.jsonl';

function jload(string $f):array{$v=is_file($f)?json_decode((string)file_get_contents($f),true):[];return is_array($v)?$v:[];}
function jsave(string $f,array $v):void{$t=$f.'.tmp';file_put_contents($t,json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);rename($t,$f);}
function valid_type(string $t):bool{return !in_array(strtolower(trim($t)),['','unsupported','reaction','system','unknown','revoke','revoked'],true);}
function lower(string $s):string{return function_exists('mb_strtolower')?mb_strtolower($s,'UTF-8'):strtolower($s);}
function any_text(string $text,array $needles):bool{$t=lower($text);foreach($needles as $n){$n=lower((string)$n);if($n!==''&&str_contains($t,$n))return true;}return false;}
function rank_stage(string $s):int{return match($s){'message_received'=>0,'interested'=>1,'qualified'=>2,'purchased','converted'=>3,'lost'=>90,default=>-1};}
function valid_inbound(array $c):int{
  $stored=(int)($c['valid_inbound_count']??0);$seen=0;
  foreach((array)($c['recent_messages']??[]) as $m){
    if(!is_array($m))continue;
    if(str_contains((string)($m['direction']??''),'inbound')&&valid_type((string)($m['type']??'')))$seen++;
  }
  return max($stored,$seen,(int)($c['inbound_count']??0)>0?min((int)$c['inbound_count'],max(1,$seen)):0);
}
function replied_after_staff(array $c):bool{
  $staff=false;
  foreach((array)($c['recent_messages']??[]) as $m){
    if(!is_array($m))continue;$d=(string)($m['direction']??'');$t=(string)($m['type']??'');
    if(str_contains($d,'outbound')){$staff=true;continue;}
    if($staff&&str_contains($d,'inbound')&&valid_type($t))return true;
  }
  return false;
}
function serious_signal(array $c):bool{
  $need=['السعر','سعر','التكلفة','تكلفة','موعد','احجز','حجز','التوفر','متاح','المدة','المتطلبات','الأوراق','المستندات','نبدأ','ابدأ','أبدأ','الدفع','تحويل','زيارة','موقعكم','العنوان','استشارة','قضية','عقد','أتعاب','الاتعاب','شركة','تركة','تنفيذ','دعوى','توكيل','عرض','تفاصيل','price','cost','appointment','book','booking','available','availability','requirements','documents','payment','contract','quote'];
  foreach((array)($c['recent_messages']??[]) as $m){
    if(!is_array($m)||!str_contains((string)($m['direction']??''),'inbound'))continue;
    $type=(string)($m['type']??'');if(!valid_type($type))continue;
    if(any_text((string)($m['text']??''),$need))return true;
    if(in_array($type,['document','image','location','contacts'],true))return true;
  }
  return false;
}
function explicit_conversion(array $c):bool{
  $need=['تم الدفع','دفعت','تم التحويل','حولت','حوّلت','تم الحجز','حجزت الموعد','تم تأكيد الحجز','تم التعاقد','وقعت العقد','وقّعت العقد','تم توقيع العقد','تم إصدار العقد','تم اصدار العقد','تم قبول الطلب','تم الشراء','اشتريت','paid','payment done','payment completed','booking confirmed','contract signed','order confirmed','purchased'];
  foreach((array)($c['recent_messages']??[]) as $m){
    if(!is_array($m)||!str_contains((string)($m['direction']??''),'inbound'))continue;
    if(!valid_type((string)($m['type']??'')))continue;
    if(any_text((string)($m['text']??''),$need))return true;
  }
  return false;
}
function desired(array $c):string{
  $valid=valid_inbound($c);$out=(int)($c['outbound_count']??0);
  if($valid<1)return '';
  if($valid>=3&&$out>=1&&explicit_conversion($c))return 'converted';
  if($valid>=3&&$out>=1&&replied_after_staff($c)&&serious_signal($c))return 'qualified';
  if($valid>=2)return 'interested';
  return 'message_received';
}
function event_keys(string $f):array{
  $out=[];if(!is_file($f))return$out;$h=fopen($f,'rb');
  while(($line=fgets($h))!==false){$r=json_decode($line,true);if(!is_array($r))continue;$cid=(string)($r['conversation_id']??'');$ev=(string)($r['event']??'');if($cid!==''&&$ev!=='')$out[$cid.'|'.$ev]=true;}
  fclose($h);return$out;
}
function append_event(string $f,array $c,string $stage,array &$keys):void{
  $id=(string)($c['id']??'');$key=$id.'|'.$stage;if($id===''||isset($keys[$key]))return;
  $row=['id'=>'mev_'.bin2hex(random_bytes(7)),'event'=>$stage,'conversation_id'=>$id,'client_id'=>(string)($c['client_id']??''),'number_id'=>(string)($c['number_id']??''),'ycloud_connection_id'=>(string)($c['ycloud_connection_id']??''),'waba_id'=>(string)($c['waba_id']??''),'business_number'=>(string)($c['business_number']??''),'customer_number'=>(string)($c['customer_number']??''),'contact_name'=>(string)($c['contact_name']??''),'ctwa_clid'=>$c['ctwa_clid']??null,'source'=>'funnel_backfill','created_at'=>gmdate('c')];
  file_put_contents($f,json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n",FILE_APPEND|LOCK_EX);$keys[$key]=true;
}

$clientsRaw=jload($clientsFile);$names=[];
foreach($clientsRaw as $k=>$v){if(!is_array($v))continue;$id=(string)($v['id']??(is_string($k)?$k:''));if($id!=='')$names[$id]=(string)($v['name']??$id);}
$convs=jload($convFile);$keys=event_keys($eventsFile);$changed=0;$eligible=0;$stats=[];$unmarked=[];$changes=[];
foreach($names as $cid=>$name)$stats[$cid]=['name'=>$name,'message_received'=>0,'interested'=>0,'qualified'=>0,'converted'=>0,'lost'=>0,'no_inbound'=>0];

foreach($convs as $id=>$c){
  if(!is_array($c))continue;$cid=(string)($c['client_id']??'');if($cid===''||!isset($names[$cid]))continue;
  $valid=valid_inbound($c);
  if($valid<1){$stats[$cid]['no_inbound']++;continue;}
  $eligible++;
  $want=desired($c);$cur=strtolower((string)($c['current_tag']??''));$src=strtolower((string)($c['tag_source']??''));
  if($cur==='purchased')$cur='converted';

  $preserveManual=($cur!=='' && $cur!=='new' && $src!=='automation');
  if($preserveManual){
    $final=$cur;
    if($final==='purchased')$final='converted';
    if(!in_array($final,['message_received','interested','qualified','converted','lost'],true))$final=$want;
  }else{
    $final=$want;
    if($cur==='lost')$final='lost';
    elseif(in_array($cur,['message_received','interested','qualified','converted'],true)&&rank_stage($cur)>rank_stage($want))$final=$cur;
  }

  $old=(string)($c['current_tag']??'');
  if($old!==$final){
    $c['current_tag']=$final;$c['tagged_at']=gmdate('c');
    if(!$preserveManual)$c['tag_source']='automation';
    $reason=match($final){'message_received'=>'first_valid_customer_message','interested'=>'two_valid_customer_messages','qualified'=>'three_plus_messages_two_way_serious_intent','converted'=>'explicit_completed_business_outcome','lost'=>'manual_or_negative_status',default=>'funnel_backfill'};
    if(!$preserveManual){$c['auto_label_reason']=$reason;$c['auto_label_confidence']=match($final){'message_received'=>1.0,'interested'=>0.96,'qualified'=>0.93,'converted'=>0.99,default=>0.9};}
    $hist=is_array($c['label_history']??null)?$c['label_history']:[];
    $hist[]=['tag'=>$final,'source'=>$preserveManual?'legacy_manual':'automation','reason'=>$reason,'confidence'=>$preserveManual?1.0:($c['auto_label_confidence']??0.9),'at'=>gmdate('c'),'backfill'=>true];
    if(count($hist)>20)$hist=array_slice($hist,-20);$c['label_history']=$hist;
    $convs[$id]=$c;$changed++;$changes[]=['conversation_id'=>$id,'client'=>$names[$cid],'from'=>$old?:'none','to'=>$final];
  }else{$convs[$id]=$c;}

  $finalNow=(string)($convs[$id]['current_tag']??$final);
  if($finalNow==='purchased')$finalNow='converted';
  if(isset($stats[$cid][$finalNow]))$stats[$cid][$finalNow]++;
  else $unmarked[]=['conversation_id'=>$id,'client'=>$names[$cid],'current_tag'=>$finalNow];

  append_event($eventsFile,$convs[$id],$finalNow,$keys);
}
if($changed)jsave($convFile,$convs);

echo json_encode(['ok'=>true,'clients'=>count($names),'eligible_referrals'=>$eligible,'changed'=>$changed,'unmarked_count'=>count($unmarked),'unmarked'=>$unmarked,'stats'=>$stats,'changes'=>array_slice($changes,0,200)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";
