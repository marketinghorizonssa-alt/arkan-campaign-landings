<?php
declare(strict_types=1);
function gsl_header():array{return ['GCLID','GBRAID','WBRAID','Conversion Time','Conversion Value','Conversion Currency','Order ID','Conversion Action','Import Ready','Source','Valid Inbound Messages','Conversation ID','Event ID','Google Ads Customer ID','Conversion Action ID','Audit Reason'];}
function gsl_export():array{
 $code=(string)file_get_contents(__DIR__.'/google_sheet_stage_export.php');$boundary=strpos($code,"if(PHP_SAPI!=='cli')");
 if($boundary===false)throw new RuntimeException('invalid_exporter');
 $prefix=str_replace('__DIR__',var_export(__DIR__,true),substr($code,5,$boundary-5));
 return (static function(string $exportCode){eval($exportCode);return $out;})($prefix);
}
function gsl_rows(array $export,string $cid,string $stage):array{
 $cfg=sx_cfg($cid);if(!$cfg||!isset($cfg['actions'][$stage]))throw new RuntimeException('invalid_stage');
 $secure=dirname(__DIR__,4).'/.marketing/google_sheet_live';if(!is_dir($secure))mkdir($secure,0700,true);
 $label=['cl_0e6efd258397db'=>'pcare','cl_3ea5ae96e05c6b'=>'almowahid','cl_cbb797950cc8d4'=>'etizan'][$cid];
 $stem=$secure.'/'.$label.'_'.$stage;$lock=fopen($stem.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('ledger_busy');
 try{
  $ledger=sx_json($stem.'.json',[]);$seeds=sx_json(__DIR__.'/.horizons-mcp-backups/sheet_followup_seed_20261005.json',[]);
  foreach(($seeds[strtoupper($label)][$stage]??[]) as $id=>$values){if(!isset($ledger[$id]))$ledger[$id]=$values;}
  foreach(($export['clients'][$cid]['stages'][$stage]??[]) as $r){
   if(empty($r['import_ready'])||($r['source']??'')!=='google'||$r['google_ads_customer_id']!==$cfg['customer_id']||$r['conversion_action_id']!==$cfg['actions'][$stage]['id'])continue;
   $n=(int)!empty($r['gclid'])+(int)!empty($r['gbraid'])+(int)!empty($r['wbraid']);$at=strtotime($r['conversion_time']);
   if($n!==1||!preg_match('/^gcv_[a-f0-9]{32}$/',$r['order_id'])||!$at||$at>time())continue;
   if(!isset($ledger[$r['order_id']]))$ledger[$r['order_id']]=[$r['gclid'],$r['gbraid'],$r['wbraid'],$r['conversion_time'],$r['conversion_value'],$r['currency'],$r['order_id'],$r['conversion_action'],true,$r['source'],$r['valid_inbound_count'],$r['conversation_id'],$r['event_id'],$r['google_ads_customer_id'],$r['conversion_action_id'],$r['audit_reason']];
  }
  uasort($ledger,fn($a,$b)=>strcmp((string)$a[3],(string)$b[3]));
  $tmp=$stem.'.tmp-'.bin2hex(random_bytes(4));if(file_put_contents($tmp,json_encode($ledger,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX)===false||!rename($tmp,$stem.'.json'))throw new RuntimeException('ledger_write_failed');chmod($stem.'.json',0600);
  $convs=sx_json(__DIR__.'/data/conversations.json',[]);$rows=[gsl_header()];
  foreach($ledger as $v){
   if(count($v)!==16||$v[13]!==$cfg['customer_id']||$v[14]!==$cfg['actions'][$stage]['id'])throw new RuntimeException('ledger_account_mismatch');
   $conv=$convs[$v[11]]??[];$manual=($conv['tag_source']??'')==='manual'||!empty($conv['manual_override']);
   // Keep the first received message; manual downgrades suppress pending higher stages.
   if($stage!=='message_started'&&$manual&&sx_rank((string)($conv['current_tag']??''))<sx_stage_rank($stage))continue;
   $rows[]=$v;
  }
  return $rows;
 }finally{flock($lock,LOCK_UN);fclose($lock);}
}
