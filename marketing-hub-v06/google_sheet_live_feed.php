<?php
declare(strict_types=1);
header('Content-Type: text/csv; charset=utf-8');header('Cache-Control: no-store');header('X-Robots-Tag: noindex, nofollow, noarchive');
$secure=dirname(__DIR__,4).'/.marketing';$tokens=json_decode((string)@file_get_contents($secure.'/qualified_pool_tokens.json'),true);
$client=is_scalar($_GET['client_id']??null)?trim((string)$_GET['client_id']):'cl_0e6efd258397db';$stage=is_scalar($_GET['stage']??null)?trim((string)$_GET['stage']):'message_started';
$given=is_scalar($_GET['token']??null)?trim((string)$_GET['token']):'';$expected=(string)($tokens[$client]??'');
if($expected===''||$given===''||!hash_equals($expected,$given)){http_response_code(403);echo "forbidden\n";return;}
try{require_once __DIR__.'/google_sheet_live_core.php';$rows=gsl_rows(gsl_export(),$client,$stage);$fh=fopen('php://output','wb');foreach($rows as $i=>$row){if($i)$row[8]='TRUE';fputcsv($fh,$row);}fclose($fh);}catch(Throwable){http_response_code(500);echo "feed_unavailable\n";}
