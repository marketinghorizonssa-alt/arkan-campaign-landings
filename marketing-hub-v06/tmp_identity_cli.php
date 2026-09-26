<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$secure=dirname(__DIR__,4).'/.marketing';
$allowed=['cl_0e6efd258397db'=>true,'cl_3ea5ae96e05c6b'=>true,'cl_cbb797950cc8d4'=>true];
$cid=trim((string)($argv[1]??''));
if(!isset($allowed[$cid])){fwrite(STDERR,"bad_client\n");exit(2);}
$file=$secure.'/lead_pools/'.preg_replace('/[^A-Za-z0-9_.-]/','_',$cid).'/identity_map.jsonl';
$out=[];
if(is_file($file)&&($fh=fopen($file,'rb'))){
  while(($line=fgets($fh))!==false){
    $r=json_decode($line,true); if(!is_array($r))continue;
    $lead=(string)($r['lead_id']??''); if($lead==='')continue;
    $phone=preg_replace('/\D+/','',(string)($r['phone']??''))??'';
    $out[]=['lead_id'=>$lead,'phone'=>$phone!==''?('+'.$phone):'','phone_sha256'=>$phone!==''?hash('sha256',$phone):''];
  }
  fclose($fh);
}
echo json_encode(['ok'=>true,'client_id'=>$cid,'count'=>count($out),'rows'=>$out],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
