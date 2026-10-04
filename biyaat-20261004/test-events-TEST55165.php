<?php
if(PHP_SAPI!=='cli')exit;
$s='/home/u878466595/.marketing/biyaat';$done=$s.'/test-TEST55165.done';
if(is_file($done)){echo @file_get_contents($s.'/test-TEST55165-result.json');exit;}
$token=trim((string)file_get_contents($s.'/web_access_token'));if($token==='')throw new RuntimeException('website_token_missing');
require '/home/u878466595/domains/hositee.com/public_html/marketing/biyaat_quality.php';
$payload=['event_source'=>'web','event_source_id'=>'DAUJ4C3C77U2INVDGUD0','test_event_code'=>'TEST55165','data'=>[['event'=>'Contact','event_time'=>time(),'event_id'=>'biyaat_test_TEST55165_20261004','user'=>['external_id'=>hash('sha256','biyaat_test_TEST55165_20261004')],'page'=>['url'=>'https://biyaat.hositee.com/'],'properties'=>['status'=>'interested']]]];
file_put_contents($done,gmdate('c'),LOCK_EX);chmod($done,0600);
$ch=curl_init('https://business-api.tiktok.com/open_api/v1.3/event/track/');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Access-Token: '.$token,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES),CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false]);$body=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_errno($ch);curl_close($ch);
$j=is_string($body)?json_decode($body,true):null;$out=['at'=>gmdate('c'),'test_mode'=>true,'test_event_code'=>'TEST55165','event'=>'Contact','status'=>'interested','event_id'=>$payload['data'][0]['event_id'],'http'=>$http,'curl_error'=>$err,'api'=>$j];
file_put_contents($s.'/test-TEST55165-result.json',json_encode($out,JSON_UNESCAPED_SLASHES),LOCK_EX);chmod($s.'/test-TEST55165-result.json',0600);echo json_encode($out,JSON_UNESCAPED_SLASHES);
