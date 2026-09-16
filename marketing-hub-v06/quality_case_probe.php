<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$id=trim((string)getenv('Q2_LEAD'));
if($id===''){fwrite(STDERR,"missing Q2_LEAD\n");exit(2);}
$file=__DIR__.'/data/raw_events.jsonl';
function qp_s(mixed $v):string{return is_scalar($v)?trim((string)$v):'';}
function qp_clean(string $s):string{$s=preg_replace('/[\p{Cf}\p{Cc}\p{Cs}]+/u','',$s)??$s;return preg_replace('/\s+/u',' ',trim($s))??trim($s);}
function qp_text(array $m):string{$t=qp_s($m['type']??'');if($t==='text')return qp_clean(qp_s($m['text']['body']??''));foreach(['image','video','document','audio'] as $k)if($t===$k&&isset($m[$k])&&is_array($m[$k])){$c=qp_clean(qp_s($m[$k]['caption']??''));return $c!==''?$c:'['.$k.']';}if($t==='location')return'[location]';if($t==='contacts')return'[contacts]';return'';}
$out=[];
if(($fh=@fopen($file,'rb'))){while(($line=fgets($fh))!==false){$r=json_decode($line,true);if(!is_array($r))continue;$p=is_array($r['payload']??null)?$r['payload']:[];$type=qp_s($r['type']??$p['type']??'');$m=null;$dir='';if($type==='whatsapp.inbound_message.received'&&isset($p['whatsappInboundMessage'])){$m=$p['whatsappInboundMessage'];$dir='inbound';}elseif($type==='whatsapp.smb.message.echoes'&&isset($p['whatsappMessage'])){$m=$p['whatsappMessage'];$dir='outbound';}elseif($type==='whatsapp.smb.history'&&isset($p['whatsappInboundMessage'])){$m=$p['whatsappInboundMessage'];$dir='inbound';}elseif($type==='whatsapp.smb.history'&&isset($p['whatsappMessage'])){$m=$p['whatsappMessage'];$dir='outbound';}if(!is_array($m))continue;$w=qp_s($m['wabaId']??'');$cust=qp_s($dir==='inbound'?($m['from']??''):($m['to']??''));if($w===''||$cust==='')continue;$cid=substr(hash('sha256',$w.'|'.$cust),0,24);if($cid!==$id)continue;$text=qp_text($m);$text=preg_replace('/\+?\d{7,}/','[number]',$text)??$text;$out[]=['direction'=>$dir,'type'=>qp_s($m['type']??''),'text'=>$text,'at'=>qp_s($m['sendTime']??$r['createTime']??'')];}fclose($fh);}
usort($out,fn($a,$b)=>strcmp($a['at'],$b['at']));echo json_encode(['lead_id'=>$id,'messages'=>$out],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";
