<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
$base = __DIR__ . '/data'; if (!is_dir($base)) @mkdir($base, 0775, true);
function load_json(string $file, array $default=[]): array { if(!is_file($file)) return $default; $v=json_decode((string)@file_get_contents($file),true); return is_array($v)?$v:$default; }
function save_json(string $file,array $data): bool { $tmp=$file.'.tmp'; if(@file_put_contents($tmp,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX)===false)return false; return @rename($tmp,$file); }
function append_jsonl(string $file,array $data): bool { $fh=@fopen($file,'ab'); if(!$fh)return false; @flock($fh,LOCK_EX); $ok=fwrite($fh,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n")!==false; @flock($fh,LOCK_UN); fclose($fh); return $ok; }
function read_jsonl(string $file,int $limit=100): array { if(!is_file($file))return []; $lines=@file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES); if(!is_array($lines))return []; $lines=array_slice($lines,-$limit); $out=[]; foreach($lines as $line){$r=json_decode($line,true); if(is_array($r))$out[]=$r;} return array_reverse($out); }
$action=(string)($_GET['action']??'health');
$convFile=$base.'/conversations.json'; $conversionFile=$base.'/conversion_events.jsonl'; $rawFile=$base.'/raw_events.jsonl';
if($action==='health'){ echo json_encode(['ok'=>true,'version'=>'0.6','storage'=>'hostinger-json','time'=>gmdate('c')]); exit; }
if($action==='summary'){
  $c=load_json($convFile,[]); $wabas=[];$numbers=[]; foreach($c as $v){if(!empty($v['waba_id']))$wabas[$v['waba_id']]=1;if(!empty($v['business_number']))$numbers[$v['business_number']]=1;}
  $today=gmdate('Y-m-d'); $count=0; foreach(read_jsonl($conversionFile,1000) as $e){ if(str_starts_with((string)($e['created_at']??''),$today))$count++; }
  echo json_encode(['ok'=>true,'clients'=>count($wabas),'whatsapp_numbers'=>count($numbers),'conversations'=>count($c),'events_today'=>$count]); exit;
}
if($action==='conversations'){
  $c=array_values(load_json($convFile,[])); usort($c,fn($a,$b)=>strcmp((string)($b['last_message_at']??''),(string)($a['last_message_at']??''))); echo json_encode(['ok'=>true,'conversations'=>$c],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}
if($action==='events'){ echo json_encode(['ok'=>true,'events'=>read_jsonl($conversionFile,200)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
if($action==='raw'){ echo json_encode(['ok'=>true,'events'=>read_jsonl($rawFile,50)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
if($action==='tag' && $_SERVER['REQUEST_METHOD']==='POST'){
  $body=json_decode((string)file_get_contents('php://input'),true); if(!is_array($body)){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'invalid_json']);exit;}
  $id=(string)($body['conversation_id']??''); $tag=strtolower(trim((string)($body['tag']??''))); $allowed=['interested','qualified','purchased','lost']; if($id===''||!in_array($tag,$allowed,true)){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'invalid_input']);exit;}
  $c=load_json($convFile,[]); if(!isset($c[$id])){http_response_code(404);echo json_encode(['ok'=>false,'error'=>'conversation_not_found']);exit;}
  $changed=(($c[$id]['current_tag']??null)!==$tag); $c[$id]['current_tag']=$tag; $c[$id]['tagged_at']=gmdate('c'); save_json($convFile,$c);
  if($changed && $tag!=='lost') append_jsonl($conversionFile,['id'=>'mev_'.bin2hex(random_bytes(8)),'event'=>$tag,'conversation_id'=>$id,'waba_id'=>$c[$id]['waba_id']??'','business_number'=>$c[$id]['business_number']??'','customer_number'=>$c[$id]['customer_number']??'','contact_name'=>$c[$id]['contact_name']??'','ctwa_clid'=>$c[$id]['ctwa_clid']??null,'source'=>'manual_tag','created_at'=>gmdate('c')]);
  echo json_encode(['ok'=>true,'changed'=>$changed,'tag'=>$tag]); exit;
}
http_response_code(404); echo json_encode(['ok'=>false,'error'=>'unknown_action']);
