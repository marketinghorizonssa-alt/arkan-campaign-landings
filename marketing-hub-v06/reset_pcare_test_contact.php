<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$base=__DIR__.'/data';
$client='cl_0e6efd258397db';
$phone='966574802797';
function jload(string $f):array{$v=is_file($f)?json_decode((string)file_get_contents($f),true):[];return is_array($v)?$v:[];}
function jsave(string $f,array $v):void{$tmp=$f.'.tmp';file_put_contents($tmp,json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);rename($tmp,$f);}
function digits(string $v):string{return preg_replace('/\D+/','',$v)??'';}
function filter_jsonl(string $file,string $client,array $ids,string $phone,array &$removed):void{
  if(!is_file($file))return;
  $in=fopen($file,'rb');$tmp=$file.'.tmp';$out=fopen($tmp,'wb');
  while(($line=fgets($in))!==false){
    $r=json_decode($line,true);$drop=false;
    if(is_array($r)){
      $cid=(string)($r['client_id']??'');
      $conv=(string)($r['conversation_id']??'');
      $cust=digits((string)($r['customer_number']??''));
      $drop=$cid===$client&&(in_array($conv,$ids,true)||($cust!==''&&$cust===$phone));
    }
    if($drop)$removed[]=['file'=>basename($file),'row'=>$r];else fwrite($out,$line);
  }
  fclose($in);fclose($out);rename($tmp,$file);
}
$convs=jload($base.'/conversations.json');$ids=[];$backup=['at'=>gmdate('c'),'client_id'=>$client,'phone'=>$phone,'conversations'=>[],'contact_sources'=>[],'jsonl'=>[]];
foreach($convs as $id=>$c){
  if(!is_array($c))continue;
  if((string)($c['client_id']??'')===$client&&digits((string)($c['customer_number']??''))===$phone){
    $ids[]=(string)$id;$backup['conversations'][$id]=$c;unset($convs[$id]);
  }
}
jsave($base.'/conversations.json',$convs);
$sources=jload($base.'/contact_sources.json');
foreach($sources as $k=>$r){
  if(!is_array($r))continue;
  if((string)($r['client_id']??'')===$client&&digits((string)($r['phone_number']??''))===$phone){
    $backup['contact_sources'][$k]=$r;unset($sources[$k]);
  }
}
jsave($base.'/contact_sources.json',$sources);
foreach(['google_conversion_events.jsonl','conversion_events.jsonl','platform_delivery.jsonl'] as $name)filter_jsonl($base.'/'.$name,$client,$ids,$phone,$backup['jsonl']);
$backupFile=$base.'/test_reset_'.$phone.'_'.gmdate('Ymd_His').'.json';
file_put_contents($backupFile,json_encode($backup,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);
echo json_encode(['ok'=>true,'client_id'=>$client,'phone'=>$phone,'conversation_ids'=>$ids,'removed_conversations'=>count($backup['conversations']),'removed_contact_sources'=>count($backup['contact_sources']),'removed_derived_rows'=>count($backup['jsonl']),'backup'=>basename($backupFile)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";
