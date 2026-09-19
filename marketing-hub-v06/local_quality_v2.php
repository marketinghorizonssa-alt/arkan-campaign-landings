<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
parse_str((string)(getenv('Q2_QUERY') ?: ''), $_GET);

$base=__DIR__.'/data';
$rawFile=$base.'/raw_events.jsonl';
$connectionsFile=$base.'/ycloud_connections.json';
$clientsFile=$base.'/clients.json';

function q2_json(string $file,array $default=[]):array{if(!is_file($file))return$default;$v=json_decode((string)@file_get_contents($file),true);return is_array($v)?$v:$default;}
function q2_s(mixed $v):string{return is_scalar($v)?trim((string)$v):'';}
function q2_lower(string $s):string{return function_exists('mb_strtolower')?mb_strtolower($s,'UTF-8'):strtolower($s);}
function q2_clean(string $s):string{
    $s=preg_replace('/[\p{Cf}\p{Cc}\p{Cs}]+/u','',$s)??$s;
    $s=preg_replace('/\s+/u',' ',trim($s))??trim($s);
    return $s;
}
function q2_text(array $m):string{
    $type=q2_s($m['type']??'');
    if(in_array($type,['revoke','reaction','unknown','unsupported'],true))return '';
    if($type==='text')return q2_clean(q2_s($m['text']['body']??''));
    foreach(['image','video','document','audio'] as $k){if($type===$k&&isset($m[$k])&&is_array($m[$k])){$c=q2_clean(q2_s($m[$k]['caption']??''));return $c!==''?$c:'['.$k.']';}}
    if($type==='location')return '[location]';
    if($type==='contacts')return '[contacts]';
    if($type==='interactive')return '[interactive]';
    return '';
}
function q2_contains(string $text,array $needles):bool{$t=q2_lower(q2_clean($text));foreach($needles as $n){$n=q2_lower(q2_clean((string)$n));if($n!==''&&str_contains($t,$n))return true;}return false;}
function q2_exactish(string $text,array $vals):bool{
    $t=q2_lower(q2_clean($text));$t=preg_replace('/[\p{P}\p{S}\s]+/u','',$t)??$t;
    foreach($vals as $v){$x=q2_lower(q2_clean((string)$v));$x=preg_replace('/[\p{P}\p{S}\s]+/u','',$x)??$x;if($x!==''&&$t===$x)return true;}return false;
}
function q2_tiktok_template(string $text):bool{
    $t=q2_lower(q2_clean($text));
    return str_contains($t,'tiktok')&&(str_contains($t,'صادفت')||str_contains($t,'came across your ad')||str_contains($t,'أود معرفة المزيد')||str_contains($t,'would like to find out more'));
}
function q2_generic_template(string $text):bool{
    $t=q2_lower(q2_clean($text));
    if(q2_tiktok_template($t))return true;
    return str_contains($t,'مرحبا أركان التنفيذية، أرغب في استشارة بخصوص اختيار العقار والمسار')&&!str_contains($t,'المدينة:')&&!str_contains($t,'جهة العمل:');
}
function q2_is_attachment(string $type):bool{return in_array($type,['image','document','video','audio','location','contacts'],true);}
function q2_is_ack(string $text):bool{return q2_exactish($text,['تمام','طيب','اوكي','أوكي','ok','okay','شكرا','شكراً','شكرًا','ماشي','نعم','اي','إي','ايوه','أيوه','thanks','thank you','👍','👌','🙏']);}
function q2_is_low_signal(string $text):bool{return q2_exactish($text,['هلا','هلا والله','مرحبا','مرحباً','اهلا','أهلا','السلام عليكم','وعليكم السلام','هاي','hi','hello']);}
function q2_is_confusion(string $text):bool{return q2_exactish($text,['مين','مين انت','مين أنت','انت مين','أنت مين','الو','ألو','هلو','انت','أنت','مين حضرتك','انت الي ارسلت','أنت اللي ارسلت','من معي','مين معي']);}
function q2_field_count(string $text):int{
    $patterns=['المدينة:','نوع العقار:','جهة العمل:','الراتب','الدخل','الالتزامات','البنك','القسط','الدفعة','الميزانية','مبلغ التمويل','العمر:','الحالة الوظيفية'];
    $c=0;foreach($patterns as $p)if(q2_contains($text,[$p]))$c++;return$c;
}
function q2_source(array $firstInbound):array{
    $text=q2_s($firstInbound['text']??'');$m=is_array($firstInbound['raw']??null)?$firstInbound['raw']:[];$r=is_array($m['referral']??null)?$m['referral']:[];
    $clid=q2_s($r['ctwa_clid']??$r['ctwaClid']??'');$src=q2_lower(q2_s($r['source_url']??$r['sourceUrl']??'').' '.q2_s($r['headline']??''));
    if($clid!==''||str_contains($src,'facebook')||str_contains($src,'instagram'))return['source'=>'meta','confidence'=>'high','reason'=>$clid!==''?'ctwa_clid':'referral'];
    if(q2_tiktok_template($text)||str_contains($src,'tiktok'))return['source'=>'tiktok','confidence'=>'high','reason'=>q2_tiktok_template($text)?'platform_template':'referral'];
    if(q2_contains($text,['المصدر: Google Ads','المصدر:Google Ads']))return['source'=>'google','confidence'=>'medium','reason'=>'message_reference'];
    return['source'=>'unknown','confidence'=>'low','reason'=>'no_platform_evidence'];
}
function q2_eval(array $msgs):array{
    $in=[];$out=[];$staffRequestedDocs=false;$attachmentAfterRequest=false;$meaningful=[];$confusions=0;$acks=0;$lowSignals=0;$structured=0;$serviceIntent=false;$nextStep=false;$explicitNegative=false;$explicitConverted=false;$unrelated=false;$requestIndex=null;
    $serviceWords=['عقار','العقار','تمويل','التمويل','رهن','مديونية','قرض','وحدة','شقة','فيلا','تملك','التملك','استشارة','استشاره','شراء','بيت','راتب','سمة','دفعة','ميزانية','بنك'];
    $nextWords=['اتصل','اتصال','كلمني','كلّمني','موعد','احجز','حجز','نبدأ','ابدأ','ابدأوا','الخطوة التالية','وش المطلوب','ايش المطلوب','كيف ارسل','كيف أرسل','ارسلك','أرسلك','ارسل لكم','أرسل لكم','متى','موقعكم'];
    $negativeWords=['غير مهتم','مش مهتم','ما ابغى','ما أبغى','لا اريد','لا أريد','وقف التواصل','لا تتواصل','الغاء','إلغاء','not interested','do not contact','unsubscribe','cancel'];
    $convertedWords=['تم التعاقد','وقعت العقد','وقّعت العقد','تم توقيع العقد','اشتريت العقار','اشتريت الوحدة','تم شراء العقار','تم شراء الوحدة','contract signed','property purchased','unit purchased'];
    $unrelatedWords=['وظيفه','وظيفة','وظايف','وظائف','توظيف','ابي وظيفة','أبي وظيفة','ابغى وظيفة','أبغى وظيفة'];
    $docRequestWords=['تعريف بالراتب','تقرير سمة','ارسال المستندات','إرسال المستندات','ارسل المستندات','أرسل المستندات','ارسل الأوراق','أرسل الأوراق','المستندات المطلوبة','الأوراق المطلوبة'];
    foreach($msgs as $i=>$m){
        $dir=(string)$m['direction'];$type=(string)$m['type'];$text=(string)$m['text'];
        if($dir==='outbound'){
            $out[]=$m;
            if(q2_contains($text,$docRequestWords)){$staffRequestedDocs=true;$requestIndex=$i;}
            continue;
        }
        $in[]=$m;
        if($text===''||q2_generic_template($text))continue;
        if(q2_is_ack($text)){$acks++;continue;}
        if(q2_is_low_signal($text)){$lowSignals++;continue;}
        if(q2_is_confusion($text)){$confusions++;continue;}
        if(q2_is_attachment($type)){
            $meaningful[]=$m;if($requestIndex!==null&&$i>$requestIndex)$attachmentAfterRequest=true;continue;
        }
        $clean=q2_clean($text);$len=function_exists('mb_strlen')?mb_strlen($clean,'UTF-8'):strlen($clean);if($clean===''||$len<2||!preg_match('/[\p{L}\p{N}]/u',$clean))continue;
        $meaningful[]=$m;
        $structured=max($structured,q2_field_count($clean));
        if(q2_contains($clean,$serviceWords))$serviceIntent=true;
        if(q2_contains($clean,$nextWords))$nextStep=true;
        if(q2_contains($clean,$negativeWords))$explicitNegative=true;
        if(q2_contains($clean,$convertedWords))$explicitConverted=true;
        if(q2_contains($clean,$unrelatedWords))$unrelated=true;
    }
    $meaningfulCount=count($meaningful);$inCount=count($in);$outCount=count($out);
    $hasCustomerAfterStaff=false;$seenStaff=false;foreach($msgs as $m){if($m['direction']==='outbound')$seenStaff=true;elseif($seenStaff&&(string)$m['text']!==''&&!q2_generic_template((string)$m['text'])&&!q2_is_ack((string)$m['text'])&&!q2_is_low_signal((string)$m['text'])){$hasCustomerAfterStaff=true;break;}}
    $confusionOnly=$confusions>=2&&$meaningfulCount===0&&$outCount>0;
    $templateOnly=$inCount>0&&$meaningfulCount===0&&$confusions===0&&$acks===0&&$lowSignals===0;
    $score=0;$reasons=[];
    if($serviceIntent){$score+=22;$reasons[]='service_specific_intent';}
    if($structured>=2){$score+=18;$reasons[]='structured_fit_data';}
    if($structured>=4){$score+=10;$reasons[]='rich_fit_profile';}
    if($nextStep){$score+=16;$reasons[]='next_step_intent';}
    if($hasCustomerAfterStaff&&$meaningfulCount>0){$score+=12;$reasons[]='meaningful_reply_after_staff';}
    if($meaningfulCount>=2){$score+=8;$reasons[]='multiple_meaningful_replies';}
    if($staffRequestedDocs){$score+=3;$reasons[]='staff_requested_docs';}
    if($attachmentAfterRequest){$score+=38;$reasons[]='attachment_after_requested_docs';}
    if($explicitNegative){$score-=100;$reasons[]='explicit_negative';}
    if($unrelated){$score-=80;$reasons[]='unrelated_intent';}
    if($confusionOnly){$score-=70;$reasons[]='confusion_only';}
    if($templateOnly){$reasons[]='template_only_no_customer_signal';}
    $score=max(0,min(100,$score));
    $stage='new';$confidence=0.70;
    if($explicitConverted){$stage='converted';$score=max($score,98);$confidence=0.98;$reasons[]='explicit_conversion';}
    elseif($explicitNegative||$unrelated||$confusionOnly){$stage='unqualified';$confidence=$explicitNegative?0.98:($unrelated?0.95:0.93);}
    elseif($attachmentAfterRequest){$stage='qualified';$score=max($score,88);$confidence=0.94;}
    elseif(($structured>=3&&$serviceIntent)||($serviceIntent&&$nextStep&&$meaningfulCount>=1)||($score>=68&&$meaningfulCount>=2)){$stage='qualified';$confidence=0.88;}
    elseif(($serviceIntent&&$meaningfulCount>=1)||$nextStep||$structured>=1||($hasCustomerAfterStaff&&$meaningfulCount>=2&&$score>=20)){$stage='interested';$confidence=0.84;}
    elseif($templateOnly){$stage='new';$score=0;$confidence=0.98;}
    return[
        'stage'=>$stage,'quality_score'=>$score,'confidence'=>$confidence,
        'attributes'=>[
            'template_only'=>$templateOnly,'meaningful_customer_messages'=>$meaningfulCount,'customer_replied_after_staff'=>$hasCustomerAfterStaff,
            'service_intent'=>$serviceIntent,'structured_fit_fields'=>$structured,'next_step_intent'=>$nextStep,
            'staff_requested_docs'=>$staffRequestedDocs,'attachment_after_requested_docs'=>$attachmentAfterRequest,
            'confusion_messages'=>$confusions,'low_signal_messages'=>$lowSignals,'confusion_only'=>$confusionOnly,'explicit_negative'=>$explicitNegative,
            'unrelated_intent'=>$unrelated,'explicit_conversion'=>$explicitConverted,'inbound_messages'=>$inCount,'outbound_messages'=>$outCount
        ],
        'reasons'=>array_values(array_unique($reasons))
    ];
}

$clients=q2_json($clientsFile,[]);$connections=q2_json($connectionsFile,[]);$clientNames=[];$connClient=[];
foreach($clients as $k=>$c)if(is_array($c)){$id=q2_s($c['id']??(is_string($k)?$k:''));if($id!=='')$clientNames[$id]=q2_s($c['name']??$id);}
foreach($connections as $k=>$c)if(is_array($c)){$id=q2_s($c['id']??(is_string($k)?$k:''));if($id!=='')$connClient[$id]=q2_s($c['client_id']??'');}
$clientFilter=q2_s($_GET['client_id']??'');$sourceFilter=q2_lower(q2_s($_GET['source']??''));$stageFilter=q2_lower(q2_s($_GET['stage']??''));$limit=max(1,min(300,(int)($_GET['limit']??100)));$includeMessages=q2_s($_GET['include_messages']??'0')==='1';
$convs=[];$rawScanned=0;
if(is_file($rawFile)&&($fh=@fopen($rawFile,'rb'))){while(($line=fgets($fh))!==false){$rawScanned++;$row=json_decode($line,true);if(!is_array($row))continue;$payload=is_array($row['payload']??null)?$row['payload']:[];$type=q2_s($row['type']??$payload['type']??'');$conn=q2_s($row['ycloud_connection_id']??'');$cid=$connClient[$conn]??'';if($clientFilter!==''&&$cid!==$clientFilter)continue;$m=null;$dir='';
    if($type==='whatsapp.inbound_message.received'&&isset($payload['whatsappInboundMessage'])){$m=$payload['whatsappInboundMessage'];$dir='inbound';}
    elseif($type==='whatsapp.smb.message.echoes'&&isset($payload['whatsappMessage'])){$m=$payload['whatsappMessage'];$dir='outbound';}
    elseif($type==='whatsapp.smb.history'&&isset($payload['whatsappInboundMessage'])){$m=$payload['whatsappInboundMessage'];$dir='inbound';}
    elseif($type==='whatsapp.smb.history'&&isset($payload['whatsappMessage'])){$m=$payload['whatsappMessage'];$dir='outbound';}
    if(!is_array($m))continue;$waba=q2_s($m['wabaId']??'');$customer=q2_s($dir==='inbound'?($m['from']??''):($m['to']??''));if($waba===''||$customer==='')continue;$id=substr(hash('sha256',$waba.'|'.$customer),0,24);$ts=q2_s($m['sendTime']??$row['createTime']??'');$msg=['direction'=>$dir,'type'=>q2_s($m['type']??'unknown'),'text'=>q2_text($m),'at'=>$ts,'raw'=>$m];if(!isset($convs[$id]))$convs[$id]=['id'=>$id,'client_id'=>$cid,'client_name'=>$clientNames[$cid]??$cid,'messages'=>[],'first_at'=>$ts,'last_at'=>$ts];$convs[$id]['messages'][]=$msg;if($ts!==''&&($convs[$id]['first_at']===''||strcmp($ts,$convs[$id]['first_at'])<0))$convs[$id]['first_at']=$ts;if($ts!==''&&strcmp($ts,$convs[$id]['last_at'])>0)$convs[$id]['last_at']=$ts;
}fclose($fh);}

$rows=[];$counts=[];$sourceCounts=[];
foreach($convs as $c){usort($c['messages'],fn($a,$b)=>strcmp((string)$a['at'],(string)$b['at']));$firstIn=null;foreach($c['messages'] as $m)if($m['direction']==='inbound'){$firstIn=$m;break;}$src=$firstIn?q2_source($firstIn):['source'=>'unknown','confidence'=>'low','reason'=>'no_inbound'];$ev=q2_eval($c['messages']);if($sourceFilter!==''&&$src['source']!==$sourceFilter)continue;if($stageFilter!==''&&$ev['stage']!==$stageFilter)continue;$counts[$ev['stage']]=($counts[$ev['stage']]??0)+1;$sourceCounts[$src['source']]=($sourceCounts[$src['source']]??0)+1;$row=['lead_id'=>$c['id'],'client_id'=>$c['client_id'],'client_name'=>$c['client_name'],'source'=>$src,'stage'=>$ev['stage'],'quality_score'=>$ev['quality_score'],'confidence'=>$ev['confidence'],'attributes'=>$ev['attributes'],'reasons'=>$ev['reasons'],'first_seen_at'=>$c['first_at'],'last_seen_at'=>$c['last_at']];if($includeMessages){$preview=[];foreach($c['messages'] as $m){$t=(string)$m['text'];if(q2_generic_template($t))$t='[platform_template]';$preview[]=['d'=>$m['direction'],'type'=>$m['type'],'text'=>$t,'at'=>$m['at']];if(count($preview)>=12)break;}$row['messages']=$preview;}$rows[]=$row;}
usort($rows,fn($a,$b)=>strcmp((string)$b['last_seen_at'],(string)$a['last_seen_at']));$total=count($rows);$rows=array_slice($rows,0,$limit);
echo json_encode(['ok'=>true,'version'=>'local-quality-v2-calibration','read_only'=>true,'generated_at'=>gmdate('c'),'filters'=>['client_id'=>$clientFilter?:null,'source'=>$sourceFilter?:null,'stage'=>$stageFilter?:null,'limit'=>$limit],'summary'=>['matching_leads'=>$total,'stages'=>$counts,'sources'=>$sourceCounts],'leads'=>$rows,'diagnostics'=>['raw_events_scanned'=>$rawScanned]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";
