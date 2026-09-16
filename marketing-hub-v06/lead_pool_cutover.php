<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$base=__DIR__.'/data';$secure=dirname(__DIR__,4).'/.marketing';$rulesFile=$base.'/automation_rules.json';$clientsFile=$base.'/clients.json';$marker=$secure.'/lead_pool_cutover_v1';
if(!is_dir($secure)&&!@mkdir($secure,0700,true)&&!is_dir($secure))throw new RuntimeException('secure_dir_failed');
function lpc_json(string $f,array $d=[]):array{if(!is_file($f))return$d;$v=json_decode((string)@file_get_contents($f),true);return is_array($v)?$v:$d;}
function lpc_save(string $f,array $v):void{$tmp=$f.'.tmp.'.bin2hex(random_bytes(4));$raw=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);if($raw===false||@file_put_contents($tmp,$raw."\n",LOCK_EX)===false)throw new RuntimeException('rules_write_failed');if(!@rename($tmp,$f)){@unlink($tmp);throw new RuntimeException('rules_rename_failed');}}
$rules=lpc_json($rulesFile,[]);if(!is_array($rules['default']??null))$rules['default']=[];$rules['default']['enabled']=false;if(!is_array($rules['clients']??null))$rules['clients']=[];
$clients=lpc_json($clientsFile,[]);$disabled=[];foreach($clients as $k=>$c){if(!is_array($c))continue;$id=trim((string)($c['id']??(is_string($k)?$k:'')));if($id==='')continue;if(!is_array($rules['clients'][$id]??null))$rules['clients'][$id]=[];$rules['clients'][$id]['enabled']=false;$disabled[]=$id;}
lpc_save($rulesFile,$rules);@file_put_contents($marker,gmdate('c')."\n",LOCK_EX);@chmod($marker,0600);
echo json_encode(['ok'=>true,'legacy_auto_classifier_enabled'=>false,'clients_disabled'=>count($disabled)],JSON_UNESCAPED_SLASHES)."\n";
