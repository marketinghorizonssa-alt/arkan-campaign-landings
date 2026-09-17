<?php
$tests=[
 ['https://cortsexpert.hositee.com/','mobile'],
 ['https://cortsexpert.hositee.com/','desktop'],
 ['https://cortsexpert.hositee.com/riyadh-lawyer/','mobile'],
 ['https://cortsexpert.hositee.com/riyadh-lawyer/','desktop'],
];
foreach($tests as [$url,$strategy]){
 $payload=json_encode(['url'=>$url,'strategy'=>$strategy,'categories'=>['performance']]);
 $ch=curl_init('https://pagespeedinsights.dev/api/analyze');
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>120]);
 $raw=curl_exec($ch);
 if($raw===false){echo json_encode(['url'=>$url,'strategy'=>$strategy,'error'=>curl_error($ch)]),"\n";continue;}
 $d=json_decode($raw,true);$l=$d['lighthouseResult']??[];$a=$l['audits']??[];
 $dv=function($id)use($a){return $a[$id]['displayValue']??($a[$id]['numericValue']??null);};
 echo json_encode([
  'url'=>$url,'strategy'=>$strategy,'fetchTime'=>$l['fetchTime']??null,'lighthouseVersion'=>$l['lighthouseVersion']??null,
  'performance'=>isset($l['categories']['performance']['score'])?round($l['categories']['performance']['score']*100):null,
  'FCP'=>$dv('first-contentful-paint'),'LCP'=>$dv('largest-contentful-paint'),'TBT'=>$dv('total-blocking-time'),
  'CLS'=>$dv('cumulative-layout-shift'),'SpeedIndex'=>$dv('speed-index'),'TTFB'=>$dv('server-response-time')
 ],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),"\n";
}
