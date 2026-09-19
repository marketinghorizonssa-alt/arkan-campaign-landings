<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$base = __DIR__ . '/data';
$rawFile = $base . '/raw_events.jsonl';
$clientsFile = $base . '/clients.json';
$connectionsFile = $base . '/ycloud_connections.json';

$fromStr = trim((string)(getenv('FROM') ?: '2026-08-01'));
$toStr = trim((string)(getenv('TO') ?: gmdate('Y-m-d')));
$clientFilter = trim((string)(getenv('CLIENT_ID') ?: ''));

function pr_json(string $file, array $default=[]): array {
    if (!is_file($file)) return $default;
    $v = json_decode((string)@file_get_contents($file), true);
    return is_array($v) ? $v : $default;
}
function pr_s(mixed $v): string { return is_scalar($v) ? trim((string)$v) : ''; }
function pr_lower(string $s): string { return function_exists('mb_strtolower') ? mb_strtolower($s,'UTF-8') : strtolower($s); }
function pr_clean(string $s): string {
    $s = preg_replace('/[\p{Cf}\p{Cc}\p{Cs}]+/u','',$s) ?? $s;
    return preg_replace('/\s+/u',' ',trim($s)) ?? trim($s);
}
function pr_contains(string $text, array $needles): bool {
    $t = pr_lower(pr_clean($text));
    foreach ($needles as $n) {
        $n = pr_lower(pr_clean((string)$n));
        if ($n !== '' && str_contains($t,$n)) return true;
    }
    return false;
}
function pr_text(array $m): string {
    $type = pr_s($m['type'] ?? '');
    if ($type === 'text') return pr_clean(pr_s($m['text']['body'] ?? ''));
    foreach (['image','video','document','audio'] as $k) {
        if ($type === $k && isset($m[$k]) && is_array($m[$k])) {
            $c = pr_clean(pr_s($m[$k]['caption'] ?? ''));
            return $c !== '' ? $c : '['.$k.']';
        }
    }
    if ($type === 'location') return '[location]';
    if ($type === 'contacts') return '[contacts]';
    if ($type === 'interactive') return '[interactive]';
    return '';
}
function pr_tiktok_template(string $text): bool {
    $t = pr_lower(pr_clean($text));
    return str_contains($t,'tiktok') && (
        str_contains($t,'صادفت') ||
        str_contains($t,'came across your ad') ||
        str_contains($t,'أود معرفة المزيد') ||
        str_contains($t,'would like to find out more')
    );
}
function pr_source(array $firstInbound): array {
    $text = pr_s($firstInbound['text'] ?? '');
    $m = is_array($firstInbound['raw'] ?? null) ? $firstInbound['raw'] : [];
    $r = is_array($m['referral'] ?? null) ? $m['referral'] : [];
    $clid = pr_s($r['ctwa_clid'] ?? $r['ctwaClid'] ?? '');
    $src = pr_lower(
        pr_s($r['source_url'] ?? $r['sourceUrl'] ?? '') . ' ' .
        pr_s($r['headline'] ?? '') . ' ' .
        pr_s($r['source_type'] ?? $r['sourceType'] ?? '')
    );
    if ($clid !== '' || str_contains($src,'facebook') || str_contains($src,'instagram')) {
        return ['source'=>'meta','confidence'=>'high','reason'=>$clid !== '' ? 'ctwa_clid' : 'referral'];
    }
    if (pr_tiktok_template($text) || str_contains($src,'tiktok')) {
        return ['source'=>'tiktok','confidence'=>'high','reason'=>pr_tiktok_template($text) ? 'platform_template' : 'referral'];
    }
    if (pr_contains($text,['المصدر: Google Ads','المصدر:Google Ads','source: google ads'])) {
        return ['source'=>'google','confidence'=>'high','reason'=>'message_source_tag'];
    }
    if (pr_contains($text,['المصدر: Snapchat Ads','المصدر:Snapchat Ads','المصدر: Snap Ads','source: snapchat ads'])) {
        return ['source'=>'snapchat','confidence'=>'high','reason'=>'message_source_tag'];
    }
    return ['source'=>'unknown','confidence'=>'low','reason'=>'no_platform_evidence'];
}
function pr_exactish(string $text,array $vals): bool {
    $t=pr_lower(pr_clean($text)); $t=preg_replace('/[\p{P}\p{S}\s]+/u','',$t)??$t;
    foreach($vals as $v){$x=pr_lower(pr_clean((string)$v));$x=preg_replace('/[\p{P}\p{S}\s]+/u','',$x)??$x;if($x!==''&&$t===$x)return true;}
    return false;
}
function pr_generic_template(string $text): bool {
    $t=pr_lower(pr_clean($text));
    if (pr_tiktok_template($t)) return true;
    return str_contains($t,'مرحبا أركان التنفيذية، أرغب في استشارة بخصوص اختيار العقار والمسار')
        && !str_contains($t,'المدينة:') && !str_contains($t,'جهة العمل:');
}
function pr_is_ack(string $text): bool {
    return pr_exactish($text,['تمام','طيب','اوكي','أوكي','ok','okay','شكرا','شكراً','شكرًا','ماشي','نعم','اي','إي','ايوه','أيوه','thanks','thank you','👍','👌','🙏']);
}
function pr_is_low_signal(string $text): bool {
    return pr_exactish($text,['هلا','هلا والله','مرحبا','مرحباً','اهلا','أهلا','السلام عليكم','وعليكم السلام','هاي','hi','hello']);
}
function pr_is_confusion(string $text): bool {
    return pr_exactish($text,['مين','مين انت','مين أنت','انت مين','أنت مين','الو','ألو','هلو','انت','أنت','مين حضرتك','انت الي ارسلت','أنت اللي ارسلت','من معي','مين معي']);
}
function pr_field_count(string $text): int {
    $patterns=['المدينة:','نوع العقار:','جهة العمل:','الراتب','الدخل','الالتزامات','البنك','القسط','الدفعة','الميزانية','مبلغ التمويل','العمر:','الحالة الوظيفية'];
    $c=0; foreach($patterns as $p) if(pr_contains($text,[$p])) $c++; return $c;
}
function pr_is_attachment(string $type): bool { return in_array($type,['image','document','video','audio','location','contacts'],true); }

function pr_eval(array $msgs): array {
    $in=[];$out=[];$meaningful=[];$confusions=0;$structured=0;$serviceIntent=false;$nextStep=false;$explicitNegative=false;$explicitConverted=false;$unrelated=false;$staffRequestedDocs=false;$attachmentAfterRequest=false;$requestIndex=null;
    $serviceWords=['عقار','العقار','تمويل','التمويل','رهن','مديونية','قرض','وحدة','شقة','فيلا','تملك','التملك','استشارة','استشاره','شراء','بيت','راتب','سمة','دفعة','ميزانية','بنك'];
    $nextWords=['اتصل','اتصال','كلمني','كلّمني','موعد','احجز','حجز','نبدأ','ابدأ','الخطوة التالية','وش المطلوب','ايش المطلوب','كيف ارسل','كيف أرسل','ارسلك','أرسلك','متى','موقعكم'];
    $negativeWords=['غير مهتم','مش مهتم','ما ابغى','ما أبغى','لا اريد','لا أريد','وقف التواصل','لا تتواصل','الغاء','إلغاء','not interested','do not contact','unsubscribe','cancel'];
    $convertedWords=['تم التعاقد','وقعت العقد','وقّعت العقد','تم توقيع العقد','اشتريت العقار','اشتريت الوحدة','تم شراء العقار','تم شراء الوحدة','contract signed','property purchased','unit purchased'];
    $unrelatedWords=['وظيفه','وظيفة','وظايف','وظائف','توظيف','ابي وظيفة','أبي وظيفة','ابغى وظيفة','أبغى وظيفة'];
    $docRequestWords=['تعريف بالراتب','تقرير سمة','ارسال المستندات','إرسال المستندات','ارسل المستندات','أرسل المستندات','ارسل الأوراق','أرسل الأوراق','المستندات المطلوبة','الأوراق المطلوبة'];
    foreach($msgs as $i=>$m){
        $dir=(string)$m['direction'];$type=(string)$m['type'];$text=(string)$m['text'];
        if($dir==='outbound'){
            $out[]=$m;
            if(pr_contains($text,$docRequestWords)){$staffRequestedDocs=true;$requestIndex=$i;}
            continue;
        }
        $in[]=$m;
        if($text===''||pr_generic_template($text))continue;
        if(pr_is_ack($text)||pr_is_low_signal($text)||pr_is_confusion($text)){if(pr_is_confusion($text))$confusions++;continue;}
        if(pr_is_attachment($type)){ $meaningful[]=$m; if($requestIndex!==null&&$i>$requestIndex)$attachmentAfterRequest=true; continue; }
        $clean=pr_clean($text);
        if($clean===''||!preg_match('/[\p{L}\p{N}]/u',$clean))continue;
        $meaningful[]=$m;
        $structured=max($structured,pr_field_count($clean));
        if(pr_contains($clean,$serviceWords))$serviceIntent=true;
        if(pr_contains($clean,$nextWords))$nextStep=true;
        if(pr_contains($clean,$negativeWords))$explicitNegative=true;
        if(pr_contains($clean,$convertedWords))$explicitConverted=true;
        if(pr_contains($clean,$unrelatedWords))$unrelated=true;
    }
    $meaningfulCount=count($meaningful);$inCount=count($in);$outCount=count($out);
    $hasCustomerAfterStaff=false;$seenStaff=false;
    foreach($msgs as $m){
        if($m['direction']==='outbound')$seenStaff=true;
        elseif($seenStaff&&(string)$m['text']!==''&&!pr_generic_template((string)$m['text'])&&!pr_is_ack((string)$m['text'])&&!pr_is_low_signal((string)$m['text'])){$hasCustomerAfterStaff=true;break;}
    }
    $confusionOnly=$confusions>=2&&$meaningfulCount===0&&$outCount>0;
    $templateOnly=$inCount>0&&$meaningfulCount===0&&$confusions===0;
    $score=0;
    if($serviceIntent)$score+=22;
    if($structured>=2)$score+=18;
    if($structured>=4)$score+=10;
    if($nextStep)$score+=16;
    if($hasCustomerAfterStaff&&$meaningfulCount>0)$score+=12;
    if($meaningfulCount>=2)$score+=8;
    if($staffRequestedDocs)$score+=3;
    if($attachmentAfterRequest)$score+=38;
    if($explicitNegative)$score-=100;
    if($unrelated)$score-=80;
    if($confusionOnly)$score-=70;
    $score=max(0,min(100,$score));

    $stage='new';
    if($explicitConverted){$stage='converted';$score=max($score,98);}
    elseif($explicitNegative||$unrelated||$confusionOnly){$stage='unqualified';}
    elseif($attachmentAfterRequest){$stage='qualified';$score=max($score,88);}
    elseif(($structured>=3&&$serviceIntent)||($serviceIntent&&$nextStep&&$meaningfulCount>=1)||($score>=68&&$meaningfulCount>=2)){$stage='qualified';}
    elseif(($serviceIntent&&$meaningfulCount>=1)||$nextStep||$structured>=1||($hasCustomerAfterStaff&&$meaningfulCount>=2&&$score>=20)){$stage='interested';}
    elseif($templateOnly){$stage='new';$score=0;}
    return ['stage'=>$stage,'quality_score'=>$score];
}
function pr_intent(array $msgs): array {
    $all='';
    foreach($msgs as $m){
        if(($m['direction']??'')!=='inbound')continue;
        $t=(string)($m['text']??'');
        if($t===''||pr_generic_template($t))continue;
        $all.=' '.pr_clean($t);
    }
    $financingProblemWords=[
        'متعثر','متعثره','متعثرين','تعثر','متأخر بالسداد','متاخر بالسداد','مديونية','مديونيه',
        'ديون','دين','سمة','سمه','التزامات','التزام','قرض قائم','قروض','عندي قرض','علي قرض',
        'رفض تمويل','مرفوض تمويل','التمويل مرفوض','البنك رفض','ما وافق البنك','عدم قبول البنك',
        'إيقاف خدمات','ايقاف خدمات','متوقف خدمات','قسط متأخر','اقساط متأخرة','أقساط متأخرة',
        'سداد مديونية','سداد مديونيه','شراء مديونية','شراء مديونيه','إعادة جدولة','اعادة جدولة',
        'تعثر ائتماني','مشكلة تمويل','مشكله تمويل','credit issue','loan problem','debt'
    ];
    $propertyWords=[
        'أبحث عن عقار','ابحث عن عقار','ادور على عقار','أدور على عقار','اختيار العقار',
        'أبحث عن شقة','ابحث عن شقة','ادور على شقة','أدور على شقة',
        'أبحث عن فيلا','ابحث عن فيلا','ادور على فيلا','أدور على فيلا',
        'شراء عقار','ابي عقار','أبي عقار','ابغى عقار','أبغى عقار',
        'شراء شقة','شراء شقه','شراء فيلا','تملك عقار','التملك','وحدة سكنية','وحده سكنيه',
        'عقار مناسب','شقة','شقه','فيلا','دوبلكس','أرض','ارض','عمارة','عقار'
    ];
    $fin = pr_contains($all,$financingProblemWords);
    $property = pr_contains($all,$propertyWords);
    return ['financing_problem'=>$fin,'property_search'=>$property];
}

$clients=pr_json($clientsFile,[]);$connections=pr_json($connectionsFile,[]);
$clientNames=[];$connClient=[];
foreach($clients as $k=>$c) if(is_array($c)){ $id=pr_s($c['id']??(is_string($k)?$k:'')); if($id!=='')$clientNames[$id]=pr_s($c['name']??$id); }
foreach($connections as $k=>$c) if(is_array($c)){ $id=pr_s($c['id']??(is_string($k)?$k:'')); if($id!=='')$connClient[$id]=pr_s($c['client_id']??''); }

$from = new DateTimeImmutable($fromStr.' 00:00:00', new DateTimeZone('Asia/Riyadh'));
$to = new DateTimeImmutable($toStr.' 23:59:59', new DateTimeZone('Asia/Riyadh'));

$convs=[];$rawScanned=0;
if(is_file($rawFile)&&($fh=@fopen($rawFile,'rb'))){
    while(($line=fgets($fh))!==false){
        $rawScanned++;
        $row=json_decode($line,true); if(!is_array($row))continue;
        $payload=is_array($row['payload']??null)?$row['payload']:[];
        $type=pr_s($row['type']??$payload['type']??'');
        $conn=pr_s($row['ycloud_connection_id']??'');
        $cid=$connClient[$conn]??'';
        if($clientFilter!==''&&$cid!==$clientFilter)continue;
        $m=null;$dir='';
        if($type==='whatsapp.inbound_message.received'&&isset($payload['whatsappInboundMessage'])){$m=$payload['whatsappInboundMessage'];$dir='inbound';}
        elseif($type==='whatsapp.smb.message.echoes'&&isset($payload['whatsappMessage'])){$m=$payload['whatsappMessage'];$dir='outbound';}
        elseif($type==='whatsapp.smb.history'&&isset($payload['whatsappInboundMessage'])){$m=$payload['whatsappInboundMessage'];$dir='inbound';}
        elseif($type==='whatsapp.smb.history'&&isset($payload['whatsappMessage'])){$m=$payload['whatsappMessage'];$dir='outbound';}
        if(!is_array($m))continue;
        $waba=pr_s($m['wabaId']??'');
        $customer=pr_s($dir==='inbound'?($m['from']??''):($m['to']??''));
        if($waba===''||$customer==='')continue;
        $id=substr(hash('sha256',$waba.'|'.$customer),0,24);
        $ts=pr_s($m['sendTime']??$row['createTime']??'');
        $msg=['direction'=>$dir,'type'=>pr_s($m['type']??'unknown'),'text'=>pr_text($m),'at'=>$ts,'raw'=>$m];
        if(!isset($convs[$id]))$convs[$id]=['id'=>$id,'client_id'=>$cid,'client_name'=>$clientNames[$cid]??$cid,'messages'=>[],'first_inbound_at'=>'','last_at'=>$ts];
        $convs[$id]['messages'][]=$msg;
        if($dir==='inbound'&&$ts!==''&&($convs[$id]['first_inbound_at']===''||strcmp($ts,$convs[$id]['first_inbound_at'])<0))$convs[$id]['first_inbound_at']=$ts;
        if($ts!==''&&strcmp($ts,$convs[$id]['last_at'])>0)$convs[$id]['last_at']=$ts;
    }
    fclose($fh);
}

$summary=[
    'new_customers'=>0,
    'sources'=>['tiktok'=>0,'google'=>0,'meta'=>0,'snapchat'=>0,'unknown'=>0],
    'quality_stages'=>['converted'=>0,'qualified'=>0,'interested'=>0,'new'=>0,'unqualified'=>0],
    'quality_score_sum'=>0,
    'financing_problem'=>0,
    'property_search'=>0,
    'both_financing_and_property'=>0,
    'neither_detected'=>0,
];
$bySourceQuality=[];

foreach($convs as $c){
    $firstTs=(string)($c['first_inbound_at']??''); if($firstTs==='')continue;
    try{$first=(new DateTimeImmutable($firstTs))->setTimezone(new DateTimeZone('Asia/Riyadh'));}catch(Throwable){continue;}
    if($first<$from||$first>$to)continue;

    usort($c['messages'],fn($a,$b)=>strcmp((string)$a['at'],(string)$b['at']));
    $firstIn=null; foreach($c['messages'] as $m)if($m['direction']==='inbound'){$firstIn=$m;break;}
    if(!$firstIn)continue;

    $src=pr_source($firstIn);
    $q=pr_eval($c['messages']);
    $intent=pr_intent($c['messages']);

    $summary['new_customers']++;
    $summary['sources'][$src['source']] = ($summary['sources'][$src['source']]??0)+1;
    $summary['quality_stages'][$q['stage']] = ($summary['quality_stages'][$q['stage']]??0)+1;
    $summary['quality_score_sum'] += (int)$q['quality_score'];

    if($intent['financing_problem'])$summary['financing_problem']++;
    if($intent['property_search'])$summary['property_search']++;
    if($intent['financing_problem']&&$intent['property_search'])$summary['both_financing_and_property']++;
    if(!$intent['financing_problem']&&!$intent['property_search'])$summary['neither_detected']++;

    if(!isset($bySourceQuality[$src['source']]))$bySourceQuality[$src['source']]=['count'=>0,'score_sum'=>0,'converted'=>0,'qualified'=>0,'interested'=>0,'new'=>0,'unqualified'=>0,'financing_problem'=>0,'property_search'=>0];
    $bs=&$bySourceQuality[$src['source']];
    $bs['count']++;$bs['score_sum']+=(int)$q['quality_score'];$bs[$q['stage']]++;
    if($intent['financing_problem'])$bs['financing_problem']++;
    if($intent['property_search'])$bs['property_search']++;
    unset($bs);
}
$summary['average_quality_score']=$summary['new_customers']>0?round($summary['quality_score_sum']/$summary['new_customers'],1):0;
unset($summary['quality_score_sum']);
foreach($bySourceQuality as &$r){$r['average_quality_score']=$r['count']>0?round($r['score_sum']/$r['count'],1):0;unset($r['score_sum']);}unset($r);

echo json_encode([
    'ok'=>true,
    'version'=>'period-lead-report-v1',
    'client_id'=>$clientFilter?:null,
    'client_name'=>$clientNames[$clientFilter]??null,
    'from'=>$fromStr,
    'to'=>$toStr,
    'timezone'=>'Asia/Riyadh',
    'definition'=>'first inbound WhatsApp message in available synced history falls inside requested period',
    'summary'=>$summary,
    'by_source'=>$bySourceQuality,
    'diagnostics'=>['raw_events_scanned'=>$rawScanned,'conversations_scanned'=>count($convs)]
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";
