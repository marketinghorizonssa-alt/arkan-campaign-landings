<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$base=__DIR__.'/data';$file=$base.'/conversations.json';
$targets=['cl_0e6efd258397db'=>1,'cl_3ea5ae96e05c6b'=>1,'cl_cbb797950cc8d4'=>1];

function j(string $f):array{$v=json_decode((string)@file_get_contents($f),true);return is_array($v)?$v:[];}
function savej(string $f,array $v):void{$t=$f.'.tmp';file_put_contents($t,json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);rename($t,$f);}
function good(string $t):bool{return !in_array(strtolower(trim($t)),['','unsupported','reaction','system','unknown','revoke','revoked'],true);}
function countin(array $c):int{
  $n=0;$seen=[];
  foreach((array)($c['recent_messages']??[]) as $m){
    if(!is_array($m)||!str_contains((string)($m['direction']??''),'inbound')||!good((string)($m['type']??'')))continue;
    $id=(string)($m['wamid']??$m['message_id']??$m['source_event_id']??'');
    if($id!==''&&isset($seen[$id]))continue;if($id!=='')$seen[$id]=1;$n++;
  }
  return max($n,(int)($c['valid_inbound_count']??0));
}
function rank(string $s):int{return match($s){'message_received'=>0,'interested'=>1,'qualified'=>2,'converted','purchased'=>3,'lost'=>90,default=>-1};}

$c=j($file);$changed=[];$stats=[];
foreach($c as $id=>$v){
  if(!is_array($v)||!isset($targets[(string)($v['client_id']??'')]))continue;
  $n=countin($v);if($n<1)continue;
  $cur=strtolower((string)($v['current_tag']??''));if($cur==='purchased')$cur='converted';
  $src=strtolower((string)($v['tag_source']??''));
  if($src==='manual'||$cur==='lost'||rank($cur)>=1){$stats[$cur?:'none']=($stats[$cur?:'none']??0)+1;continue;}
  $want=$n>=2?'interested':'message_received';
  if($cur!==$want){
    $now=gmdate('c');$v['current_tag']=$want;$v['tagged_at']=$now;$v['tag_source']='automation';
    $v['auto_label_reason']=$want==='interested'?'two_valid_customer_messages':'first_valid_customer_message';
    $v['auto_label_confidence']=$want==='interested'?0.96:1.0;
    $h=is_array($v['label_history']??null)?$v['label_history']:[];
    $h[]=['tag'=>$want,'source'=>'automation','reason'=>$v['auto_label_reason'],'confidence'=>$v['auto_label_confidence'],'at'=>$now,'repair'=>true];
    if(count($h)>20)$h=array_slice($h,-20);$v['label_history']=$h;$c[$id]=$v;
    $changed[]=['conversation_id'=>$id,'to'=>$want,'valid_inbound'=>$n];
  }
  $stats[$want]=($stats[$want]??0)+1;
}
if($changed)savej($file,$c);
echo json_encode(['ok'=>true,'changed'=>count($changed),'stats'=>$stats,'items'=>$changed],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";
