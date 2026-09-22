<?php
declare(strict_types=1);

$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
$allowedOrigins=[
    'https://pcare.sa',
    'https://www.pcare.sa',
    'https://marketing.hositee.com',
];
if($origin!==''&&in_array($origin,$allowedOrigins,true)){
    header('Access-Control-Allow-Origin: '.$origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json; charset=utf-8');
if(($_SERVER['REQUEST_METHOD']??'')==='OPTIONS'){http_response_code(204);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);exit;}
if($origin!==''&&!in_array($origin,$allowedOrigins,true)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'origin_not_allowed']);exit;}

$base=__DIR__.'/data'; if(!is_dir($base))@mkdir($base,0775,true);
$file=$base.'/chatlink_clicks.json';

function cc_load(string $file):array{
    if(!is_file($file))return[];
    $raw=@file_get_contents($file);
    if($raw===false||trim($raw)==='')return[];
    $v=json_decode($raw,true); return is_array($v)?$v:[];
}
function cc_save(string $file,array $data):bool{
    $tmp=$file.'.tmp';
    $ok=@file_put_contents($tmp,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);
    if($ok===false)return false;
    return @rename($tmp,$file);
}
function cc_decode(string $text):array{
    if(!preg_match('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]{16,}/u',$text,$m))return['click_id'=>'','decoded'=>''];
    $chars=preg_split('//u',$m[0],-1,PREG_SPLIT_NO_EMPTY);
    if(!is_array($chars))return['click_id'=>'','decoded'=>''];
    $map=["\u{200B}"=>0,"\u{200C}"=>1,"\u{200D}"=>2,"\u{FEFF}"=>3];
    $bytes='';$n=count($chars)-count($chars)%4;
    for($i=0;$i<$n;$i+=4){
        if(!isset($map[$chars[$i]],$map[$chars[$i+1]],$map[$chars[$i+2]],$map[$chars[$i+3]]))break;
        $v=($map[$chars[$i]]<<6)|($map[$chars[$i+1]]<<4)|($map[$chars[$i+2]]<<2)|$map[$chars[$i+3]];
        $bytes.=chr($v);
    }
    $click='';
    if(preg_match('/ycloud\.chatlink\.(clk_[A-Za-z0-9_-]{4,120})/',$bytes,$mm))$click=$mm[1];
    return['click_id'=>$click,'decoded'=>$bytes];
}
function cc_classify(string $url):array{
    $key='website';$label='Website / YCloud Chat Link';$reason='ycloud_chatlink_source_url';
    $parts=@parse_url($url);$q=[];
    if(is_array($parts)&&isset($parts['query']))parse_str((string)$parts['query'],$q);
    $utm=strtolower(trim((string)($q['utm_source']??'')));
    $low=strtolower($url);
    if(isset($q['gclid'])||isset($q['gbraid'])||isset($q['wbraid'])||$utm==='google'||str_contains($low,'utm_source=google')){
        $key='google';$label='Google Ads';
    }elseif(isset($q['ttclid'])||$utm==='tiktok'||str_contains($low,'tiktok')){
        $key='tiktok';$label='TikTok Ads';
    }elseif(isset($q['fbclid'])||in_array($utm,['facebook','instagram','meta'],true)){
        $key='meta';$label='Meta Ads';
    }elseif(isset($q['sccid'])||$utm==='snapchat'||str_contains($low,'snapchat')){
        $key='snapchat';$label='Snapchat Ads';
    }
    return[
        'traffic_source_key'=>$key,'traffic_source_label'=>$label,
        'traffic_source_confidence'=>'high','traffic_source_reason'=>$reason,
        'gclid'=>(string)($q['gclid']??''),'gbraid'=>(string)($q['gbraid']??''),'wbraid'=>(string)($q['wbraid']??''),
        'utm_source'=>(string)($q['utm_source']??''),'utm_medium'=>(string)($q['utm_medium']??''),
        'utm_campaign'=>(string)($q['utm_campaign']??'')
    ];
}

$raw=(string)file_get_contents('php://input');
$j=json_decode($raw,true);
if(!is_array($j)){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'invalid_json']);exit;}

$sourceUrl=trim((string)($j['source_url']??''));
$openUrl=trim((string)($j['open_whatsapp_url']??''));
$interactionId=trim((string)($j['interaction_id']??''));
$widgetId=trim((string)($j['widget_id']??''));

if($sourceUrl===''||$openUrl===''){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'missing_source_or_open_url']);exit;}
$sp=@parse_url($sourceUrl);
if(!is_array($sp)||strtolower((string)($sp['scheme']??''))!=='https'){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'invalid_source_url']);exit;}

$op=@parse_url($openUrl);$oq=[];
if(is_array($op)&&isset($op['query']))parse_str((string)$op['query'],$oq);
$text=(string)($oq['text']??'');
$dec=cc_decode($text);
$clickId=(string)$dec['click_id'];
if($clickId===''||!preg_match('/^clk_[A-Za-z0-9_-]{4,120}$/',$clickId)){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'click_id_not_found']);exit;}

$row=array_merge([
    'click_id'=>$clickId,'source_url'=>$sourceUrl,'interaction_id'=>$interactionId,'widget_id'=>$widgetId,
    'captured_at'=>gmdate('c'),'origin'=>$origin,
    'decoded_tracking'=>(string)$dec['decoded']
],cc_classify($sourceUrl));

$all=cc_load($file);$all[$clickId]=$row;
if(count($all)>10000){
    uasort($all,fn($a,$b)=>strcmp((string)($a['captured_at']??''),(string)($b['captured_at']??'')));
    $all=array_slice($all,-8000,null,true);
}
if(!cc_save($file,$all)){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'save_failed']);exit;}

echo json_encode(['ok'=>true,'click_id'=>$clickId,'source'=>$row['traffic_source_key'],'source_url'=>$sourceUrl],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
