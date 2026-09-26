<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');
$secure=dirname(__DIR__,4).'/.marketing';
$tokenFile=$secure.'/tmp_identity_export_token';
$expected=is_file($tokenFile)?trim((string)file_get_contents($tokenFile)):'';
$given=is_scalar($_GET['token']??null)?trim((string)$_GET['token']):'';
if($expected===''||$given===''||!hash_equals($expected,$given)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'forbidden']);exit;}
$allowed=['cl_0e6efd258397db'=>true,'cl_3ea5ae96e05c6b'=>true,'cl_cbb797950cc8d4'=>true];
$cid=is_scalar($_GET['client']??null)?trim((string)$_GET['client']):'';
if(!isset($allowed[$cid])){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'bad_client']);exit;}
$file=$secure.'/lead_pools/'.preg_replace('/[^A-Za-z0-9_.-]/','_',$cid).'/identity_map.jsonl';
$out=[];
if(is_file($file)&&($fh=fopen($file,'rb'))){
  while(($line=fgets($fh))!==false){
    $r=json_decode($line,true); if(!is_array($r))continue;
    $lead=(string)($r['lead_id']??''); if($lead==='')continue;
    $phone=preg_replace('/\D+/','',(string)($r['phone']??''))??'';
    $out[]=[
      'lead_id'=>$lead,
      'phone'=>$phone!==''?('+'.$phone):'',
      'phone_sha256'=>$phone!==''?hash('sha256',$phone):'',
      'conversation_id'=>(string)($r['conversation_id']??'')
    ];
  }
  fclose($fh);
}
echo json_encode(['ok'=>true,'client_id'=>$cid,'count'=>count($out),'rows'=>$out],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
