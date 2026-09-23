<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');

$base=__DIR__.'/data';
$secure=dirname(__DIR__,4).'/.marketing';
$queueFile=$base.'/google_conversion_events.jsonl';
$deliveryFile=$base.'/google_conversion_delivery.jsonl';
$convFile=$base.'/conversations.json';
$tokenFile=$secure.'/google_conversion_feed_token';

if(!is_dir($secure))@mkdir($secure,0700,true);
if(!is_file($tokenFile)){
    @file_put_contents($tokenFile,bin2hex(random_bytes(32))."\n",LOCK_EX);
    @chmod($tokenFile,0600);
}
$expected=trim((string)@file_get_contents($tokenFile));

function gc_s(mixed $v):string{return is_scalar($v)?trim((string)$v):'';}
function gc_json(string $f,array $d=[]):array{
    if(!is_file($f))return$d;
    $v=json_decode((string)@file_get_contents($f),true);
    return is_array($v)?$v:$d;
}
function gc_rows(string $f):array{
    $out=[];if(!is_file($f))return$out;
    $fh=@fopen($f,'rb');if(!$fh)return$out;
    while(($line=fgets($fh))!==false){$r=json_decode($line,true);if(is_array($r))$out[]=$r;}
    fclose($fh);return$out;
}
function gc_append(string $f,array $r):bool{
    return @file_put_contents($f,json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n",FILE_APPEND|LOCK_EX)!==false;
}
function gc_google_time(string $raw):string{
    try{$d=new DateTimeImmutable($raw!==''?$raw:'now');}
    catch(Throwable){$d=new DateTimeImmutable('now',new DateTimeZone('UTC'));}
    return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP');
}

$cli=PHP_SAPI==='cli';
$cliCommand=$cli?strtolower(gc_s($argv[1]??'feed')):'';
if($cli&&$cliCommand==='ack'){
    $ids=array_values(array_filter(array_map('trim',explode(',',gc_s($argv[2]??'')))));
    $written=0;
    foreach($ids as $eventId){
        if(!preg_match('/^gcv_[a-f0-9]{16,64}$/',$eventId))continue;
        if(gc_append($deliveryFile,['event_id'=>$eventId,'success'=>true,'customer_id'=>'','google_request_id'=>'scheduled_mcp','error'=>'','acked_at'=>gmdate('c')]))$written++;
    }
    echo json_encode(['ok'=>true,'acked'=>$written],JSON_UNESCAPED_SLASHES)."\n";exit;
}

$method=$cli?'GET':(string)($_SERVER['REQUEST_METHOD']??'GET');
$token=$cli?$expected:gc_s($_GET['token']??'');
if($method==='POST'){
    $j=json_decode((string)file_get_contents('php://input'),true);
    if(is_array($j)&&$token==='')$token=gc_s($j['token']??'');
}
if($expected===''||$token===''||!hash_equals($expected,$token)){
    http_response_code(403);echo json_encode(['ok'=>false,'error'=>'forbidden']);exit;
}

$clients=[
    'cl_0e6efd258397db'=>[
        'name'=>'Bcare','customer_id'=>'4482394160',
        'actions'=>[
            'message_sent'=>'customers/4482394160/conversionActions/7789160285',
            'interested'=>'customers/4482394160/conversionActions/7789160288',
            'qualified'=>'customers/4482394160/conversionActions/7789160291',
            'converted'=>'customers/4482394160/conversionActions/7789160294',
        ],
        'values'=>['message_sent'=>1.0,'interested'=>2.0,'qualified'=>5.0,'converted'=>10.0]
    ],
    'cl_3ea5ae96e05c6b'=>[
        'name'=>'Almowahid','customer_id'=>'4577472256',
        'actions'=>[
            'message_sent'=>'customers/4577472256/conversionActions/7789308219',
            'interested'=>'customers/4577472256/conversionActions/7789308222',
            'qualified'=>'customers/4577472256/conversionActions/7789308225',
            'converted'=>'customers/4577472256/conversionActions/7789308228',
        ],
        'values'=>['message_sent'=>1.0,'interested'=>2.0,'qualified'=>5.0,'converted'=>10.0]
    ],
    'cl_cbb797950cc8d4'=>[
        'name'=>'Etizan','customer_id'=>'8433542366',
        'actions'=>[
            'message_sent'=>'customers/8433542366/conversionActions/7790177464',
            'interested'=>'customers/8433542366/conversionActions/7790177467',
            'qualified'=>'customers/8433542366/conversionActions/7790177470',
            'converted'=>'customers/8433542366/conversionActions/7790177473',
        ],
        'values'=>['message_sent'=>1.0,'interested'=>2.0,'qualified'=>5.0,'converted'=>10.0]
    ],
];

if($method==='POST'){
    $j=is_array($j??null)?$j:[];
    $rows=is_array($j['results']??null)?$j['results']:[];
    $written=0;
    foreach($rows as $row){
        if(!is_array($row))continue;
        $eventId=gc_s($row['event_id']??'');
        if($eventId==='')continue;
        if(gc_append($deliveryFile,[
            'event_id'=>$eventId,
            'success'=>(bool)($row['success']??false),
            'customer_id'=>gc_s($row['customer_id']??''),
            'google_request_id'=>gc_s($row['google_request_id']??''),
            'error'=>gc_s($row['error']??''),
            'acked_at'=>gmdate('c')
        ]))$written++;
    }
    echo json_encode(['ok'=>true,'acked'=>$written],JSON_UNESCAPED_SLASHES);exit;
}

if($method!=='GET'){http_response_code(405);echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);exit;}

$delivered=[];
foreach(gc_rows($deliveryFile) as $d){
    if(!empty($d['success'])&&gc_s($d['event_id']??'')!=='')$delivered[gc_s($d['event_id'])]=true;
}
$convs=gc_json($convFile,[]);
$out=[];$pendingNoClick=0;$notGoogle=0;$unknownConversation=0;
foreach(gc_rows($queueFile) as $e){
    $eventId=gc_s($e['event_id']??'');
    if($eventId===''||isset($delivered[$eventId]))continue;
    $clientId=gc_s($e['client_id']??'');$stage=gc_s($e['stage']??'');$convId=gc_s($e['conversation_id']??'');
    $cfg=$clients[$clientId]??null;
    if(!is_array($cfg)||!isset($cfg['actions'][$stage]))continue;
    $conv=is_array($convs[$convId]??null)?$convs[$convId]:null;
    if(!$conv){$unknownConversation++;continue;}
    if(strtolower(gc_s($conv['traffic_source_key']??''))!=='google'){$notGoogle++;continue;}

    $gclid=gc_s($conv['google_gclid']??'');
    $gbraid=gc_s($conv['google_gbraid']??'');
    $wbraid=gc_s($conv['google_wbraid']??'');
    if($gclid===''&&$gbraid===''&&$wbraid===''){$pendingNoClick++;continue;}

    $row=[
        'event_id'=>$eventId,
        'client_id'=>$clientId,
        'client_name'=>$cfg['name'],
        'customer_id'=>$cfg['customer_id'],
        'stage'=>$stage,
        'conversion_action'=>$cfg['actions'][$stage],
        'conversion_date_time'=>gc_google_time(gc_s($e['occurred_at']??'')),
        'conversion_value'=>(float)($cfg['values'][$stage]??1),
        'currency_code'=>'SAR',
        'order_id'=>$eventId
    ];
    if($gclid!=='')$row['gclid']=$gclid;
    elseif($gbraid!=='')$row['gbraid']=$gbraid;
    else$row['wbraid']=$wbraid;
    $out[]=$row;
    if(count($out)>=100)break;
}
echo json_encode([
    'ok'=>true,'generated_at'=>gmdate('c'),'events'=>$out,
    'diagnostics'=>['ready'=>count($out),'pending_no_click_id'=>$pendingNoClick,'non_google_source'=>$notGoogle,'missing_conversation'=>$unknownConversation]
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
