<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$setId='7686238143054970888';
$clientId='cl_76c4e019afc588';
$secure=dirname(__DIR__,4).'/.marketing';
$pool=$secure.'/lead_pools/'.$clientId;
$tokenFile=$secure.'/tiktok/crm/'.$setId.'/access_token';
$token=is_file($tokenFile)?trim((string)file_get_contents($tokenFile)):'';
if($token===''){echo json_encode(['ok'=>false,'error'=>'token_missing'])."\n";exit(1);}
function j(string $f,array $d=[]):array{if(!is_file($f))return$d;$v=json_decode((string)file_get_contents($f),true);return is_array($v)?$v:$d;}
function rows(string $f):array{$o=[];if(!is_file($f))return$o;foreach(file($f,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $l){$r=json_decode($l,true);if(is_array($r))$o[]=$r;}return$o;}
function s(mixed $v):string{return is_scalar($v)?trim((string)$v):'';}
function phone(string $v):string{$d=preg_replace('/\D+/','',$v)??'';if(str_starts_with($d,'00'))$d=substr($d,2);return $d;}
function sendEvent(string $token,string $setId,string $eventName,array $record,array $identity):array{
  $ttclid=s($record['native_ids']['ttclid']??'');$p=phone(s($identity['phone']??''));$contact=s($record['contact_id']??'');
  $user=[];if($p!=='')$user['phone']=hash('sha256','+'.$p);if($contact!=='')$user['external_id']=hash('sha256',$contact);if($ttclid!=='')$user['ttclid']=$ttclid;
  $stage=strtolower(s($record['stage']??''));$occur=s($record['last_seen_at']??'');$ts=$occur!==''?strtotime($occur):time();if($ts===false)$ts=time();
  $eid='stdprobe_'.substr(hash('sha256',$setId.'|'.s($record['lead_id']??'').'|'.$eventName.'|'.$occur),0,32);
  $payload=['event_source'=>'crm','event_source_id'=>$setId,'data'=>[['event'=>$eventName,'event_time'=>$ts,'event_id'=>$eid,'user'=>$user,'properties'=>['lead_stage'=>$stage,'origin_source'=>'tiktok','probe'=>'existing_lead_standard']]]];
  $ch=curl_init('https://business-api.tiktok.com/open_api/v1.3/event/track/');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Access-Token: '.$token,'Content-Type: application/json','Accept: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES),CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false]);$body=curl_exec($ch);$errno=curl_errno($ch);$http=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);$dec=is_string($body)?json_decode($body,true):null;$code=is_array($dec)?(int)($dec['code']??-1):-1;$msg=is_array($dec)?s($dec['message']??''):'';return['ok'=>$errno===0&&$http>=200&&$http<300&&$code===0,'http'=>$http,'code'=>$code,'message'=>$msg];
}
$cur=j($pool.'/current.json',[]);$records=is_array($cur['records']??null)?$cur['records']:[];$ids=[];foreach(rows($pool.'/identity_map.jsonl') as $r){$lid=s($r['lead_id']??'');if($lid!=='')$ids[$lid]=$r;}
$map=['interested'=>'PREFERRED_LEAD','qualified'=>'CLUE_HIGH_INTENTION','unqualified'=>'INVALID_CLUE'];$picked=[];foreach($records as $lid=>$r){if(!is_array($r)||strtolower(s($r['source']??''))!=='tiktok')continue;$stage=strtolower(s($r['stage']??''));if(!isset($map[$stage])||isset($picked[$stage]))continue;$id=$ids[s($r['lead_id']??$lid)]??[];if(phone(s($id['phone']??''))==='')continue;$picked[$stage]=[$r,$id];}
$out=['ok'=>true,'event_set_id'=>$setId,'attempted'=>0,'accepted'=>0,'rejected'=>0,'stages'=>[]];foreach($map as $stage=>$eventName){if(!isset($picked[$stage])){$out['stages'][$stage]=['status'=>'no_eligible_record'];continue;}[$r,$id]=$picked[$stage];$resp=sendEvent($token,$setId,$eventName,$r,$id);$out['attempted']++;if($resp['ok'])$out['accepted']++;else{$out['rejected']++;$out['ok']=false;}$out['stages'][$stage]=['event'=>$eventName,'status'=>$resp['ok']?'accepted':'rejected','http'=>$resp['http'],'api_code'=>$resp['code'],'message'=>$resp['message']];}
echo json_encode($out,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
