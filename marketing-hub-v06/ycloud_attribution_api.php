<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if($origin==='https://pcare.sa'||$origin==='https://www.pcare.sa'){
    header('Access-Control-Allow-Origin: '.$origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if(($_SERVER['REQUEST_METHOD']??'GET')==='OPTIONS'){http_response_code(204);exit;}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);echo json_encode(['ok'=>false]);exit;}

$raw=(string)file_get_contents('php://input');
$j=json_decode($raw,true);
if(!is_array($j)){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'invalid_json']);exit;}

$clickToken=trim((string)($j['click_token']??''));
$clickId=trim((string)($j['click_id']??''));
if($clickToken===''||$clickId===''){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'missing_click']);exit;}
if(!preg_match('/^ycloud\.chatlink\.(clk_[A-Za-z0-9._-]+)$/',$clickToken,$m)){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'invalid_token']);exit;}
$clickId=$m[1];

$base=__DIR__.'/data';
if(!is_dir($base))@mkdir($base,0755,true);
$file=$base.'/ycloud_click_attribution.json';

function load_map(string $f):array{
    $v=is_file($f)?json_decode((string)file_get_contents($f),true):[];
    return is_array($v)?$v:[];
}
function clean_scalar($v,int $max=1000):string{
    if(is_array($v)||is_object($v))return '';
    $s=trim((string)$v);
    if(strlen($s)>$max)$s=substr($s,0,$max);
    return $s;
}
function clean_map($v):array{
    $out=[];
    if(!is_array($v))return $out;
    foreach($v as $k=>$x){
        $k=preg_replace('/[^A-Za-z0-9_.:-]/','',(string)$k);
        if($k==='')continue;
        $out[$k]=clean_scalar($x,500);
    }
    return $out;
}

$allParams=clean_map($j['params']??[]);
$utm=[];
foreach($allParams as $k=>$v){
    if(str_starts_with(strtolower($k),'utm_'))$utm[$k]=$v;
}
$row=[
    'click_id'=>$clickId,
    'click_token'=>$clickToken,
    'client_id'=>'cl_0e6efd258397db',
    'business_number'=>'+966505952042',
    'interaction_id'=>clean_scalar($j['interaction_id']??'',200),
    'page_url'=>clean_scalar($j['page_url']??'',2000),
    'landing_path'=>clean_scalar($j['landing_path']??'',500),
    'referrer'=>clean_scalar($j['referrer']??'',2000),
    'gclid'=>clean_scalar($allParams['gclid']??'',500),
    'gbraid'=>clean_scalar($allParams['gbraid']??'',500),
    'wbraid'=>clean_scalar($allParams['wbraid']??'',500),
    'dclid'=>clean_scalar($allParams['dclid']??'',500),
    'msclkid'=>clean_scalar($allParams['msclkid']??'',500),
    'fbclid'=>clean_scalar($allParams['fbclid']??'',500),
    'ttclid'=>clean_scalar($allParams['ttclid']??'',500),
    'sccid'=>clean_scalar($allParams['sccid']??'',500),
    'utm'=>$utm,
    'params'=>$allParams,
    'captured_at'=>gmdate('c'),
    'user_agent'=>clean_scalar($_SERVER['HTTP_USER_AGENT']??'',700)
];
$map=load_map($file);
$map[$clickId]=$row;
if(count($map)>20000){
    uasort($map,fn($a,$b)=>strcmp((string)($a['captured_at']??''),(string)($b['captured_at']??'')));
    $map=array_slice($map,-15000,null,true);
}
file_put_contents($file,json_encode($map,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);

echo json_encode(['ok'=>true,'click_id'=>$clickId],JSON_UNESCAPED_SLASHES);
