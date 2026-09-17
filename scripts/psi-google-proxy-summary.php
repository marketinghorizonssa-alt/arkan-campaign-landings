<?php
$url = $argv[1] ?? 'https://cortsexpert.hositee.com/';
$strategy = $argv[2] ?? 'mobile';
$payload = json_encode(['url'=>$url,'strategy'=>$strategy,'categories'=>['performance']]);
$ch = curl_init('https://pagespeedinsights.dev/api/analyze');
curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>120]);
$raw = curl_exec($ch);
if ($raw===false) { fwrite(STDERR,curl_error($ch)); exit(2); }
$d=json_decode($raw,true);
if (!$d || isset($d['error'])) { echo $raw; exit(1); }
$l=$d['lighthouseResult'] ?? [];
$a=$l['audits'] ?? [];
$get=function($id) use ($a){return $a[$id]['displayValue'] ?? ($a[$id]['numericValue'] ?? null);};
$out=[
 'url'=>$url,
 'strategy'=>$strategy,
 'fetchTime'=>$l['fetchTime'] ?? null,
 'lighthouseVersion'=>$l['lighthouseVersion'] ?? null,
 'performance'=>isset($l['categories']['performance']['score'])?round($l['categories']['performance']['score']*100):null,
 'FCP'=>$get('first-contentful-paint'),
 'LCP'=>$get('largest-contentful-paint'),
 'TBT'=>$get('total-blocking-time'),
 'CLS'=>$get('cumulative-layout-shift'),
 'SpeedIndex'=>$get('speed-index'),
 'TTFB'=>$get('server-response-time'),
];
echo json_encode($out,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),"\n";
