<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$base=__DIR__.'/data';
$wrong='yc_a9a614a25290d0';
$pcare='cl_0e6efd258397db';
$files=['ycloud_connections.json','conversations.json','conversion_events.jsonl'];
$stamp=gmdate('Ymd_His');
foreach($files as $f){$p=$base.'/'.$f;if(is_file($p))@copy($p,$p.'.bak_'.$stamp);}
$out=['ok'=>true,'connection_updated'=>0,'conversations_updated'=>0,'conversion_events_updated'=>0];

$cf=$base.'/ycloud_connections.json';
$c=json_decode((string)file_get_contents($cf),true)?:[];
if(isset($c[$wrong])){
  $c[$wrong]['client_id']=$pcare;
  $c[$wrong]['label']='P Care YCloud (duplicate disabled)';
  $c[$wrong]['webhook_status']='disabled';
  $c[$wrong]['duplicate_disabled']=true;
  $c[$wrong]['updated_at']=gmdate('c');
  $out['connection_updated']=1;
}
if(isset($c['yc_8280587245a79a'])){
  $c['yc_8280587245a79a']['label']='اتزان للمحاماة YCloud (duplicate disabled)';
  $c['yc_8280587245a79a']['webhook_status']='disabled';
  $c['yc_8280587245a79a']['duplicate_disabled']=true;
  $c['yc_8280587245a79a']['updated_at']=gmdate('c');
}
file_put_contents($cf,json_encode($c,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);

$vf=$base.'/conversations.json';
$v=json_decode((string)file_get_contents($vf),true)?:[];
foreach($v as &$row){
  if(is_array($row)&&(string)($row['ycloud_connection_id']??'')===$wrong){
    if((string)($row['client_id']??'')!==$pcare){$row['client_id']=$pcare;$out['conversations_updated']++;}
  }
}
unset($row);
file_put_contents($vf,json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);

$ef=$base.'/conversion_events.jsonl';
if(is_file($ef)){
  $tmp=$ef.'.tmp_'.$stamp;$in=fopen($ef,'rb');$fh=fopen($tmp,'wb');
  while(($line=fgets($in))!==false){
    $r=json_decode($line,true);
    if(is_array($r)&&(string)($r['ycloud_connection_id']??'')===$wrong){
      if((string)($r['client_id']??'')!==$pcare){$r['client_id']=$pcare;$out['conversion_events_updated']++;}
      fwrite($fh,json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    } else fwrite($fh,$line);
  }
  fclose($in);fclose($fh);rename($tmp,$ef);
}
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
