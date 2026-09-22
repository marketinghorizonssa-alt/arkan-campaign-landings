<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

function get(string $url):string{
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>true]);
  $body=curl_exec($ch);curl_close($ch);
  return is_string($body)?$body:'';
}
$base='https://www.ycloud.com/console/static/js/';
$ctx=get($base.'index~8.3b4573df.js');
$runtime=get($base.'index~10.a02b812a.js');
$needle='"./dashboard/accounts/setting/chatLinks/growthTools/createEditTool/index":[';
$p=strpos($ctx,$needle);
if($p===false){echo json_encode(['ok'=>false,'error'=>'context_path_not_found']);exit;}
$s=$p+strlen($needle);$e=strpos($ctx,']',$s);
$raw=substr($ctx,$s,$e-$s);
preg_match_all('/"([0-9]+)"/',$raw,$m);
$ids=$m[1]??[];
$module=array_shift($ids);
$out=['ok'=>true,'module'=>$module,'chunks'=>$ids,'files'=>[],'hits'=>[]];
foreach($ids as $id){
  preg_match_all('/(?<![0-9])'.preg_quote($id,'/').':"([^"]+)"/',$runtime,$mm);
  $vals=array_values(array_unique($mm[1]??[]));
  $name='';$hash='';
  foreach($vals as $v){
    if(preg_match('/^[a-f0-9]{8}$/',$v))$hash=$v;
    elseif($name==='')$name=$v;
  }
  if($hash==='')continue;
  if($name==='')$name=$id;
  $url=$base.$name.'.'.$hash.'.js';
  $body=get($url);
  $hasModule=str_contains($body,$module.':')||str_contains($body,$module.',');
  $out['files'][]=['id'=>$id,'values'=>$vals,'url'=>$url,'bytes'=>strlen($body),'has_module'=>$hasModule];
  if(!$hasModule)continue;
  $patterns=['growthTool','growthTools','chatLink','chatLinks','createGrowth','updateGrowth','/growth','/chat'];
  $snips=[];
  foreach($patterns as $term){
    $offset=0;$count=0;
    while(($pos=stripos($body,$term,$offset))!==false && $count<12){
      $snips[]=substr($body,max(0,$pos-240),600);
      $offset=$pos+strlen($term);$count++;
    }
  }
  preg_match_all('#https?://[^"\' ]+|/[A-Za-z0-9_.~-]+(?:/[A-Za-z0-9_{}.:~-]+){1,8}#',$body,$paths);
  $interesting=[];
  foreach(array_unique($paths[0]??[]) as $x){
    if(preg_match('/growth|chat|tool|link|whatsapp/i',$x))$interesting[]=$x;
    if(count($interesting)>=100)break;
  }
  $out['hits'][]=['chunk_id'=>$id,'url'=>$url,'snippets'=>array_values(array_unique($snips)),'paths'=>$interesting];
}
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
