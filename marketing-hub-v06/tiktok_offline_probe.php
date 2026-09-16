<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$client='cl_76c4e019afc588';
$offlineSet='7686264082239897620';
$crmSet='7686238143054970888';
$secure=dirname(__DIR__,4).'/.marketing';
$tokenFile=$secure.'/tiktok/crm/'.$crmSet.'/access_token';
$pool=$secure.'/lead_pools/'.$client;
function rows(string $f):array{$o=[];if(!is_file($f))return$o;foreach(file($f,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $l){$r=json_decode($l,true);if(is_array($r))$o[]=$r;}return$o;}
function s(mixed $v):string{return is_scalar($v)?trim((string)$v):'';}
function phone(string $v):string{$d=preg_replace('/\D+/','',$v)??'';if(str_starts_with($d,'00'))$d=substr($d,2);return $d;}
$token=is_file($tokenFile)?trim((string)file_get_contents($tokenFile)):'';
if($token===''){echo json_encode(['ok'=>false,'error'=>'token_missing'])."\n";exit(1);}
$cur=json_decode((string)file_get_contents($pool.'/current.json'),true);$records=is_array($cur['records']??null)?$cur['records']:[];
$ids=[];foreach(rows($pool.'/identity_map.jsonl') as $r){$lid=s($r['lead_id']??'');if($lid!=='')$ids[$lid]=$r;}
$pick=null;$id=[];foreach($records as $lid=>$r){if(!is_array($r))continue;if(strtolower(s($r['source']??''))!=='tiktok')continue;if(strtolower(s($r['stage']??''))!=='qualified')continue;$lead=s($r['lead_id']??$lid);$x=$ids[$lead]??[];$p=phone(s($x['phone']??''));if($p==='')continue;$pick=$r;$id=$x;break;}
if(!$pick){echo json_encode(['ok'=>false,'error'=>'no_qualified_tiktok_lead'])."\n";exit(2);}
$p=phone(s($id['phone']??''));$lead=s($pick['lead_id']??'');$when=s($pick['last_seen_at']??'');$ts=$when!==''?gmdate('c',strtotime($when)?:time()):gmdate('c');$eventId='offprobe_'.substr(hash('sha256',$offlineSet.'|'.$lead.'|qualified'),0,30);
$payload=['event_set_id'=>$offlineSet,'event'=>'Lead','event_id'=>$eventId,'timestamp'=>$ts,'context'=>['user'=>['phone_numbers'=>[hash('sha256','+'.$p)]]],'properties'=>['event_channel'=>'other','lead_stage'=>'qualified','source'=>'tiktok']];
$ch=curl_init('https://business-api.tiktok.com/open_api/v1.3/offline/track/');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Access-Token: '.$token,'Content-Type: application/json','Accept: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES),CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false]);$body=curl_exec($ch);$errno=curl_errno($ch);$err=curl_error($ch);$http=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);$dec=is_string($body)?json_decode($body,true):null;$code=is_array($dec)?($dec['code']??null):null;$msg=is_array($dec)?($dec['message']??null):null;$ok=$errno===0&&$http>=200&&$http<300&&(int)$code===0;echo json_encode(['ok'=>$ok,'http'=>$http,'api_code'=>$code,'message'=>$msg,'curl_error'=>$errno?$err:null,'event_set_id'=>$offlineSet,'event'=>'Lead'],JSON_UNESCAPED_SLASHES)."\n";
