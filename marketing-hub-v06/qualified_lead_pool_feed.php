<?php
declare(strict_types=1);

$base=__DIR__.'/data';
$secure=dirname(__DIR__,4).'/.marketing';
$tokenFile=$secure.'/qualified_pool_tokens.json';
$clients=[
  'cl_0e6efd258397db'=>['name'=>'BCARE'],
  'cl_3ea5ae96e05c6b'=>['name'=>'ALMOWAHID'],
  'cl_cbb797950cc8d4'=>['name'=>'ETIZAN']
];

function lp_json(string $f,array $d=[]):array{
  if(!is_file($f))return$d;
  $v=json_decode((string)@file_get_contents($f),true);
  return is_array($v)?$v:$d;
}
function lp_s(mixed $v):string{return is_scalar($v)?trim((string)$v):'';}
function lp_phone(string $v):string{return preg_replace('/\D+/','',$v)??'';}
function lp_status(array $c):string{
  $s=strtolower(lp_s($c['current_tag']??'message_received'));
  if($s==='purchased')$s='converted';
  return in_array($s,['message_received','interested','qualified','converted','lost'],true)?$s:'message_received';
}
function lp_csv(array $row):void{$fh=fopen('php://output','wb');fputcsv($fh,$row);fclose($fh);}

if(PHP_SAPI==='cli'){
  if(($argv[1]??'')==='init'){
    if(!is_dir($secure))@mkdir($secure,0700,true);
    $t=lp_json($tokenFile,[]);
    foreach(array_keys($clients) as $cid)if(empty($t[$cid]))$t[$cid]=bin2hex(random_bytes(24));
    @file_put_contents($tokenFile,json_encode($t,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n",LOCK_EX);@chmod($tokenFile,0600);
    echo json_encode($t,JSON_UNESCAPED_SLASHES)."\n";exit;
  }
  if(($argv[1]??'')==='export'&&isset($argv[2])){
    $cid=(string)$argv[2];
    if(!isset($clients[$cid])){fwrite(STDERR,"unknown_client\n");exit(2);}
    $headers=['Event ID','Lead Created At','Phone SHA256','Email SHA256','Conversion Value','Currency','Lead ID','Current Status'];
    $rows=[$headers];$convs=lp_json($base.'/conversations.json',[]);
    foreach($convs as $convId=>$c){
      if(!is_array($c)||lp_s($c['client_id']??'')!==$cid)continue;
      $in=(int)($c['valid_inbound_count']??$c['inbound_count']??0);if($in<1)continue;
      $phone=lp_phone(lp_s($c['customer_number']??''));$phoneHash=$phone!==''?hash('sha256',$phone):'';
      $eventId='lead_'.substr(hash('sha256',$cid.'|'.$convId.'|lead'),0,32);
      $rows[]=[$eventId,lp_s($c['first_seen_at']??$c['created_at']??''),$phoneHash,'',1,'SAR',(string)$convId,lp_status($c)];
    }
    usort($rows,function($a,$b){if(($a[0]??'')==='Event ID')return-1;if(($b[0]??'')==='Event ID')return 1;return strcmp((string)($a[1]??''),(string)($b[1]??''));});
    echo json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";exit;
  }
  http_response_code(404);exit;
}

$cid=lp_s($_GET['client']??'');$token=lp_s($_GET['token']??'');
$tokens=lp_json($tokenFile,[]);
if(!isset($clients[$cid])||$token===''||empty($tokens[$cid])||!hash_equals((string)$tokens[$cid],$token)){
  http_response_code(403);header('Content-Type:text/plain; charset=utf-8');echo"forbidden\n";exit;
}
header('Content-Type:text/csv; charset=utf-8');header('Cache-Control:no-store');header('X-Robots-Tag:noindex, nofollow, noarchive');

$headers=['Event ID','Lead Created At','Phone SHA256','Email SHA256','Conversion Value','Currency','Lead ID','Current Status'];
lp_csv($headers);
$convs=lp_json($base.'/conversations.json',[]);
$rows=[];
foreach($convs as $convId=>$c){
  if(!is_array($c)||lp_s($c['client_id']??'')!==$cid)continue;
  $in=(int)($c['valid_inbound_count']??$c['inbound_count']??0);if($in<1)continue;
  $phone=lp_phone(lp_s($c['customer_number']??''));$phoneHash=$phone!==''?hash('sha256',$phone):'';
  $eventId='lead_'.substr(hash('sha256',$cid.'|'.$convId.'|lead'),0,32);
  $rows[]=[$eventId,lp_s($c['first_seen_at']??$c['created_at']??''),$phoneHash,'',1,'SAR',(string)$convId,lp_status($c)];
}
usort($rows,fn($a,$b)=>strcmp((string)$a[1],(string)$b[1]));
foreach($rows as $row)lp_csv($row);
