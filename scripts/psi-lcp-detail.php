<?php
$url=$argv[1]??'';
$strategy=$argv[2]??'mobile';
$payload=json_encode(['url'=>$url,'strategy'=>$strategy,'categories'=>['performance']]);
$ch=curl_init('https://pagespeedinsights.dev/api/analyze');
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['content-type: application/json'],CURLOPT_POSTFIELDS=>$payload,CURLOPT_TIMEOUT=>120]);
$raw=curl_exec($ch);curl_close($ch);
$j=json_decode($raw,true);
$a=$j['lighthouseResult']['audits']??[];
$out=[];
foreach($a as $id=>$v){
  if(stripos($id,'lcp')!==false||stripos($id,'largest-contentful')!==false){
    $out[$id]=['title'=>$v['title']??null,'score'=>$v['score']??null,'displayValue'=>$v['displayValue']??null,'numericValue'=>$v['numericValue']??null,'details'=>$v['details']??null];
  }
}
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),"\n";
?>