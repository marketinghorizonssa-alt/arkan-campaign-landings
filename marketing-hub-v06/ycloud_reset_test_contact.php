<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$base=__DIR__.'/data';
$secure=dirname(__DIR__,4).'/.marketing/ycloud';
$conn='yc_9a6e2357c08902';
$phone='+966574802797';
$keyFile=$secure.'/'.$conn.'/api_key';
$key=is_file($keyFile)?trim((string)file_get_contents($keyFile)):'';
if($key===''){fwrite(STDERR,"missing_api_key\n");exit(2);}
function call_api(string $key,string $method,string $url):array{
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['X-API-Key: '.$key,'Accept: application/json'],CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>25,CURLOPT_FOLLOWLOCATION=>false]);
  $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);
  return ['status'=>$status,'body'=>is_string($body)?$body:'','error'=>$err];
}
$url='https://api.ycloud.com/v2/contact/contacts/'.rawurlencode($phone);
$del=call_api($key,'DELETE',$url);
$get=call_api($key,'GET',$url);
$srcFile=$base.'/contact_sources.json';
$src=is_file($srcFile)?json_decode((string)file_get_contents($srcFile),true):[];
if(!is_array($src))$src=[];
$keyLocal=$conn.'|'.preg_replace('/\D+/','',$phone);
$removed=array_key_exists($keyLocal,$src);
unset($src[$keyLocal]);
file_put_contents($srcFile,json_encode($src,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);
echo json_encode([
  'ok'=>($del['status']===200 && $get['status']===404),
  'delete_status'=>$del['status'],
  'verify_status'=>$get['status'],
  'local_source_removed'=>$removed
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
