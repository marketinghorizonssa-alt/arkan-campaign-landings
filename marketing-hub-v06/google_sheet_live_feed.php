<?php
declare(strict_types=1);
header('Content-Type: text/csv; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');
$root=__DIR__;$secure=dirname(__DIR__,4).'/.marketing';
$tokens=json_decode((string)@file_get_contents($secure.'/qualified_pool_tokens.json'),true);
$cid='cl_0e6efd258397db';$given=is_scalar($_GET['token']??null)?trim((string)$_GET['token']):'';
$expected=is_array($tokens)?(string)($tokens[$cid]??''):'';
if($expected===''||$given===''||!hash_equals($expected,$given)){http_response_code(403);echo "forbidden\n";return;}
$code=(string)file_get_contents(__DIR__.'/google_sheet_stage_export.php');
$boundary=strpos($code,"if(PHP_SAPI!=='cli')");
if($boundary===false){http_response_code(500);echo "invalid_exporter\n";return;}
$prefix=str_replace('__DIR__',var_export(__DIR__,true),substr($code,5,$boundary-5));
$export=(static function(string $exportCode){eval($exportCode);return $out;})($prefix);
$current=$export['clients'][$cid]['stages']['message_started']??[];
$ledgerDir=$secure.'/google_sheet_live';
if(!is_dir($ledgerDir)&&!mkdir($ledgerDir,0700,true)&&!is_dir($ledgerDir)){http_response_code(500);echo "ledger_unavailable\n";return;}
$lock=fopen($ledgerDir.'/pcare_message_started.lock','c');
if(!$lock||!flock($lock,LOCK_EX)){http_response_code(503);echo "ledger_busy\n";return;}
$ledgerFile=$ledgerDir.'/pcare_message_started.json';
$ledger=json_decode((string)@file_get_contents($ledgerFile),true);
if(!is_array($ledger))$ledger=json_decode((string)file_get_contents(__DIR__.'/.horizons-mcp-backups/pcare_sheet_seed_20261005.json'),true);
if(!is_array($ledger))$ledger=[];
foreach($current as $r){
    if(empty($r['import_ready'])||($r['source']??'')!=='google')continue;
    if(($r['google_ads_customer_id']??'')!=='4482394160'||($r['conversion_action_id']??'')!=='7789160285')continue;
    $clickCount=(int)!empty($r['gclid'])+(int)!empty($r['gbraid'])+(int)!empty($r['wbraid']);
    if($clickCount!==1||!preg_match('/^gcv_[a-f0-9]{32}$/',(string)($r['order_id']??'')))continue;
    $at=strtotime((string)($r['conversion_time']??''));if(!$at||$at>time())continue;
    $values=[$r['gclid'],$r['gbraid'],$r['wbraid'],$r['conversion_time'],$r['conversion_value'],$r['currency'],$r['order_id'],$r['conversion_action'],true,$r['source'],$r['valid_inbound_count'],$r['conversation_id'],$r['event_id'],$r['google_ads_customer_id'],$r['conversion_action_id'],$r['audit_reason']];
    // Imported event identity and event time are immutable on later customer replies.
    if(!isset($ledger[$r['order_id']]))$ledger[$r['order_id']]=$values;
}
uasort($ledger,fn($a,$b)=>strcmp((string)$a[3],(string)$b[3]));
$tmp=$ledgerFile.'.tmp-'.bin2hex(random_bytes(4));
if(file_put_contents($tmp,json_encode($ledger,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX)===false||!rename($tmp,$ledgerFile)){flock($lock,LOCK_UN);fclose($lock);http_response_code(500);echo "ledger_write_failed\n";return;}
chmod($ledgerFile,0600);flock($lock,LOCK_UN);fclose($lock);
$fh=fopen('php://output','wb');
fputcsv($fh,['GCLID','GBRAID','WBRAID','Conversion Time','Conversion Value','Conversion Currency','Order ID','Conversion Action','Import Ready','Source','Valid Inbound Messages','Conversation ID','Event ID','Google Ads Customer ID','Conversion Action ID','Audit Reason']);
foreach($ledger as $values){$values[8]='TRUE';fputcsv($fh,$values);}
fclose($fh);
