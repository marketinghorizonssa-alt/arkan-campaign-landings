<?php
declare(strict_types=1);
function ag_s(mixed $v): string {return is_scalar($v)?trim((string)$v):'';}
function ag_json(string $p): array {if(!is_file($p))return []; $x=json_decode((string)file_get_contents($p),true);if(!is_array($x))throw new RuntimeException('invalid_json');return $x;}
function ag_save(string $p,array $v): void {$tmp=$p.'.tmp-'.bin2hex(random_bytes(4));if(file_put_contents($tmp,json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('write_failed');chmod($tmp,0600);if(!rename($tmp,$p))throw new RuntimeException('rename_failed');}
function ag_time(string $v): ?DateTimeImmutable {if($v==='')return null;try{return new DateTimeImmutable($v,new DateTimeZone('Asia/Riyadh'));}catch(Throwable){return null;}}
function ag_click(array $r): array {foreach(['gclid','gbraid','wbraid'] as $k){$v=ag_s($r[$k]??$r['google_'.$k]??($r['attribution_params'][$k]??''));if(preg_match('/^[A-Za-z0-9_-]{15,1024}$/D',$v)&&!preg_match('/^(test|local|fake|sample)[_-]|CLICKID|\{\{|__|_CLICKID_/i',$v))return [$k=>$v];}return [];}
function ag_stage(string $v): ?string {return match(strtolower(trim($v))){'contacted','تم التواصل'=>'contacted','interested','مهتم'=>'interested','qualified','مؤهل'=>'qualified','converted','purchased','مغلق - ناجح','تم التعاقد','تم الشراء'=>'converted',default=>null};}
function ag_negative(string $v): bool {return in_array(strtolower(trim($v)),['spam','unqualified','disqualified','غير مؤهل','مغلق - غير ناجح','lost','مكرر'],true);}
function ag_test(array $r): bool {return preg_match('/test|اختبار/i',ag_s($r['processing_status']??'').' '.ag_s($r['lead_status']??'').' '.ag_s($r['full_name']??''))===1;}
function ag_event(string $record,string $stage,array $click,string $time,int $now): ?array {$at=ag_time($time);if(!$at||!$click||$at->getTimestamp()>$now||$at->getTimestamp()<$now-85*86400)return null;return ['eventTimestamp'=>$at->format('Y-m-d\TH:i:sP'),'transactionId'=>'arkg_'.substr(hash('sha256',$record.'|'.$stage),0,40),'eventSource'=>'WEB','adIdentifiers'=>$click];}
function ag_observe(array &$observed,string $id,string $raw,string $explicit,int $now,bool $dry): array {
 $old=$observed[$id]??null;$stage=ag_stage($raw);$at=ag_time($explicit);$explicitValid=$at&&$at->getTimestamp()<=$now&&($old===null||$explicit!==($old['explicit']??''));
 $changed=$old!==null&&ag_s($old['raw'])!==$raw;
 $known=$old['times']??[];$newTime=null;
 // Initial historical statuses have no invented event time. Later changes are recorded at observation time.
 if($stage&&$explicitValid&&!isset($known[$stage]))$known[$stage]=$at->format(DATE_ATOM);
 elseif($stage&&$changed&&!isset($known[$stage])){$known[$stage]=gmdate('c',$now);$newTime=$known[$stage];}
 if(!$dry)$observed[$id]=['raw'=>$raw,'explicit'=>$newTime??$explicit,'times'=>$known,'first_observed_at'=>$old['first_observed_at']??gmdate('c',$now),'last_observed_at'=>gmdate('c',$now)];
 return ['stage'=>$stage,'time'=>$stage?($known[$stage]??null):null,'write_time'=>$newTime];
}
