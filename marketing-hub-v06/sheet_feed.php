<?php
declare(strict_types=1);

header('Cache-Control: no-store, max-age=0, must-revalidate');
header('Pragma: no-cache');

$secure = dirname(__DIR__, 4) . '/.marketing';
$tokenFile = $secure . '/google_report_feed_token';
$stateFile = $secure . '/google_report_state.json';

function sf_fail(string $message, int $status=400): never {
    http_response_code($status);
    header('Content-Type: text/csv; charset=utf-8');
    echo "\xEF\xBB\xBF";
    $o=fopen('php://output','wb');
    fputcsv($o,['Status','Message']);
    fputcsv($o,['ERROR',$message]);
    fclose($o);
    exit;
}

$expected=is_file($tokenFile)?trim((string)file_get_contents($tokenFile)):'';
$given=trim((string)($_GET['token']??''));
if($expected==='' || $given==='' || !hash_equals($expected,$given)) sf_fail('invalid_feed_token',403);
if(!is_file($stateFile)) sf_fail('No report selected yet. Use Export Google Sheet in Marketing Hub first.',409);

$state=json_decode((string)file_get_contents($stateFile),true);
if(!is_array($state)) sf_fail('invalid_report_state',500);
$client=trim((string)($state['client_id']??''));
$from=trim((string)($state['from']??''));
$to=trim((string)($state['to']??''));
if($client===''||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)) sf_fail('incomplete_report_state',500);

$url='https://marketing.hositee.com/lead_report_api.php?action=csv&client_id='.rawurlencode($client).'&from='.rawurlencode($from).'&to='.rawurlencode($to);
$ch=curl_init($url);
curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CONNECTTIMEOUT=>8,
    CURLOPT_TIMEOUT=>45,
    CURLOPT_FOLLOWLOCATION=>false,
    CURLOPT_HTTPHEADER=>['Accept: text/csv']
]);
$body=curl_exec($ch);
$errno=curl_errno($ch);
$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
curl_close($ch);
if($errno!==0||$status<200||$status>=300||!is_string($body)||$body==='') sf_fail('report_feed_failed',502);

header('Content-Type: text/csv; charset=utf-8');
header('X-Report-Client: '.rawurlencode((string)($state['client_name']??'')));
header('X-Report-From: '.$from);
header('X-Report-To: '.$to);
echo $body;
