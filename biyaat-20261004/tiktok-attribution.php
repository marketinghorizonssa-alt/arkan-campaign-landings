<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);echo '{"ok":false}';exit;}
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');if($origin!=='https://biyaat.hositee.com'){http_response_code(403);echo '{"ok":false}';exit;}
if((int)($_SERVER['CONTENT_LENGTH']??0)>4096){http_response_code(413);exit;}
$b=json_decode((string)file_get_contents('php://input'),true);if(!is_array($b)){http_response_code(400);exit;}
function bat_s(mixed $v,int $max=256):string{return is_scalar($v)?substr(preg_replace('/[\x00-\x1F]/','',trim((string)$v))??'',0,$max):'';}
$ttclid=bat_s($b['ttclid']??'',512);if($ttclid===''||!preg_match('/^[A-Za-z0-9_.~%-]{8,512}$/',$ttclid)){echo '{"ok":true,"ref":""}';exit;}
$secure='/home/u878466595/.marketing/biyaat';if(!is_dir($secure))mkdir($secure,0700,true);
$ip=(string)($_SERVER['REMOTE_ADDR']??'');$bucket=hash('sha256',$ip.'|'.gmdate('YmdH'));$limit=$secure.'/rate-'.$bucket;$n=is_file($limit)?(int)file_get_contents($limit):0;if($n>=120){http_response_code(429);exit;}file_put_contents($limit,(string)($n+1),LOCK_EX);chmod($limit,0600);
$ref='BIA-AT-'.strtoupper(bin2hex(random_bytes(12)));
$campaign=[];foreach(['campaign','adgroup','ad']as$k)$campaign[$k]=bat_s($b[$k]??'',100);
$ttp=bat_s($b['ttp']??'',256);
$row=['ref'=>$ref,'client_id'=>'cl_d554e1c6bfe460','source'=>'tiktok','pixel_code'=>'DAUJ4C3C77U2INVDGUD0','ttclid'=>$ttclid,'ttp'=>$ttp,'campaign'=>$campaign,'page_url'=>'https://biyaat.hositee.com/','ip'=>$ip,'user_agent'=>bat_s($_SERVER['HTTP_USER_AGENT']??'',512),'created_at'=>gmdate('c'),'created_ts'=>time(),'test'=>($b['test']??false)===true];
$p=$secure.'/attribution.jsonl';$fh=fopen($p,'ab');flock($fh,LOCK_EX);fwrite($fh,json_encode($row,JSON_UNESCAPED_SLASHES)."\n");flock($fh,LOCK_UN);fclose($fh);chmod($p,0600);
foreach((array)glob($secure.'/rate-*')as$f)if(filemtime($f)<time()-86400)@unlink($f);
echo json_encode(['ok'=>true,'ref'=>$ref],JSON_UNESCAPED_SLASHES);
