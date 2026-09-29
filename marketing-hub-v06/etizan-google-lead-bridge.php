<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
  echo json_encode(['ok'=>true,'service'=>'ETIZAN Google Lead Bridge','version'=>'1.0.0'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
  exit;
}

require_once __DIR__ . '/app/config.php';

$raw=file_get_contents('php://input') ?: '';
$p=json_decode($raw,true);
if(!is_array($p)){
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'invalid_json']);
  exit;
}

$id=trim((string)($p['lead_id']??''));
$ts=trim((string)($p['lead_submit_time']??''));
if($id===''||$ts===''){
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'missing_submission_identity']);
  exit;
}

function etizan_canon($v){
  if(!is_array($v)) return $v;
  if(array_is_list($v)){
    $items=array_map('etizan_canon',$v);
    usort($items,static function($a,$b){
      return strcmp(
        json_encode($a,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        json_encode($b,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
      );
    });
    return $items;
  }
  ksort($v);
  foreach($v as $k=>$x)$v[$k]=etizan_canon($x);
  return $v;
}

$form=(string)($p['form_id']??'');
$city=$form==='421403129488'?'الرياض':($form==='419656396233'?'جدة':'');

$fields=is_array($p['user_column_data']??null)?$p['user_column_data']:[];
$hasCity=false;
foreach($fields as &$f){
  if(!is_array($f)) continue;
  $cid=strtoupper(trim((string)($f['column_id']??'')));
  if($cid==='CITY'&&$city!==''){
    $f['column_name']='CITY';
    $f['string_value']=$city;
    $hasCity=true;
  }
}
unset($f);
if($city!==''&&!$hasCity){
  $fields[]=['column_name'=>'CITY','string_value'=>$city,'column_id'=>'CITY'];
}
$p['user_column_data']=$fields;

$identity=[
  'lead_id'=>$id,
  'lead_submit_time'=>$ts,
  'form_id'=>$form,
  'campaign_id'=>(string)($p['campaign_id']??''),
  'adgroup_id'=>(string)($p['adgroup_id']??$p['ad_group_id']??''),
  'creative_id'=>(string)($p['creative_id']??''),
  'user_column_data'=>etizan_canon($fields)
];

$fp=strtoupper(substr(hash(
  'sha256',
  json_encode($identity,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
),0,16));

$p['original_lead_id']=$id;
$p['submission_fingerprint']=$fp;
$p['lead_id']=$id.'~'.$fp;

$j=json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

$ch=curl_init(ETIZAN_ROUTER);
curl_setopt_array($ch,[
  CURLOPT_POST=>true,
  CURLOPT_POSTFIELDS=>$j,
  CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_FOLLOWLOCATION=>true,
  CURLOPT_CONNECTTIMEOUT_MS=>1500,
  CURLOPT_TIMEOUT_MS=>12000
]);
$b=curl_exec($ch);
$s=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
$e=curl_error($ch);
curl_close($ch);

if($b===false||$e!==''||$s<200||$s>=300){
  http_response_code(502);
  echo json_encode(['ok'=>false,'error'=>'upstream_failed','upstream_http'=>$s,'fingerprint'=>$fp]);
  exit;
}

echo json_encode(['ok'=>true,'fingerprint'=>$fp],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
