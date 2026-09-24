<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit;}
$base=__DIR__.'/data';
$f=$base.'/conversations.json';
$c=json_decode((string)@file_get_contents($f),true);
if(!is_array($c))exit(1);
$changed=0;
foreach($c as $id=>$v){
  if(!is_array($v))continue;
  $recent=is_array($v['recent_messages']??null)?$v['recent_messages']:[];
  $seen=[];$valid=0;$validTimes=[];
  foreach($recent as $m){
    if(!is_array($m)||!str_contains((string)($m['direction']??''),'inbound'))continue;
    $t=strtolower(trim((string)($m['type']??'')));
    if(in_array($t,['','unsupported','reaction','system','unknown','revoke','revoked'],true))continue;
    $k=(string)($m['wamid']??$m['message_id']??$m['source_event_id']??'');
    if($k!==''&&isset($seen[$k]))continue;
    if($k!=='')$seen[$k]=true;
    $valid++;$validTimes[]=(string)($m['at']??'');
  }
  $old=(int)($v['valid_inbound_count']??0);
  if($valid>$old){$v['valid_inbound_count']=$valid;$changed++;}
  $tag=strtolower((string)($v['current_tag']??''));
  if($valid>=2&&($tag===''||$tag==='message_received')){
    $v['current_tag']='interested';
    $v['tag_source']='automation';
    $v['auto_label_reason']='two_valid_customer_messages';
    $when=(string)($validTimes[1]??'');if($when==='')$when=gmdate('c');
    $v['tagged_at']=$when;
    $st=is_array($v['stage_times']??null)?$v['stage_times']:[];if(empty($st['message_started']))$st['message_started']=(string)($v['first_seen_at']??$when);if(empty($st['interested']))$st['interested']=$when;$v['stage_times']=$st;
    $changed++;
  }
  $c[$id]=$v;
}
if($changed)file_put_contents($f,json_encode($c,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);
echo json_encode(['ok'=>true,'changed'=>$changed])."\n";
