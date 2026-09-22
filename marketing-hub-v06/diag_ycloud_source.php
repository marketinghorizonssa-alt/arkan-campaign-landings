<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

$base=__DIR__.'/data';
$connectionId='yc_9a6e2357c08902';
$business='+966505952042';
$since='2026-09-22T13:45:00Z';

function jload(string $f):array{
    $v=is_file($f)?json_decode((string)file_get_contents($f),true):[];
    return is_array($v)?$v:[];
}
function mask_phone(string $p):string{
    $d=preg_replace('/\D+/','',$p)??'';
    if(strlen($d)<4)return '***';
    return '+***'.substr($d,-4);
}
function decode_chatlink(string $text):array{
    if(!preg_match('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]{16,}/u',$text,$m))return ['click_id'=>'','decoded'=>''];
    $chars=preg_split('//u',$m[0],-1,PREG_SPLIT_NO_EMPTY);
    $map=["\u{200B}"=>0,"\u{200C}"=>1,"\u{200D}"=>2,"\u{FEFF}"=>3];
    $bytes='';$n=count($chars)-count($chars)%4;
    for($i=0;$i<$n;$i+=4){
        if(!isset($map[$chars[$i]],$map[$chars[$i+1]],$map[$chars[$i+2]],$map[$chars[$i+3]]))break;
        $v=($map[$chars[$i]]<<6)|($map[$chars[$i+1]]<<4)|($map[$chars[$i+2]]<<2)|$map[$chars[$i+3]];
        $bytes.=chr($v);
    }
    $id=''; if(preg_match('/ycloud\.chatlink\.(clk_[A-Za-z0-9._-]+)/',$bytes,$mm))$id=$mm[1];
    return ['click_id'=>$id,'decoded'=>$bytes];
}
function source_like(array $a,string $prefix=''):array{
    $out=[];
    foreach($a as $k=>$v){
        $key=$prefix===''?(string)$k:$prefix.'.'.$k;
        $lk=strtolower((string)$k);
        if(is_array($v)){
            $out+=source_like($v,$key);
        }elseif(
            str_contains($lk,'source')||str_contains($lk,'referr')||str_contains($lk,'click')||
            str_contains($lk,'interaction')||str_contains($lk,'campaign')||str_contains($lk,'gclid')||
            str_contains($lk,'utm')||str_contains($lk,'growth')
        ){
            $out[$key]=$v;
        }
    }
    return $out;
}
function api(string $key,string $method,string $path,?array $payload=null):array{
    $ch=curl_init('https://api.ycloud.com'.$path);
    $h=['X-API-Key: '.$key,'Accept: application/json'];
    if($payload!==null)$h[]='Content-Type: application/json';
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$h,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>25,CURLOPT_FOLLOWLOCATION=>false]);
    if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);
    $j=is_string($body)?json_decode($body,true):null;
    return ['status'=>$status,'error'=>$err,'json'=>is_array($j)?$j:null];
}

$events=[];
$lastTrackedCustomer='';
$rawFile=$base.'/raw_events.jsonl';
if(is_file($rawFile)){
    $fh=fopen($rawFile,'rb');
    while(($line=fgets($fh))!==false){
        $r=json_decode($line,true);
        if(!is_array($r)||(string)($r['ycloud_connection_id']??'')!==$connectionId)continue;
        $at=(string)($r['createTime']??'');
        if($at!=='' && strcmp($at,$since)<0)continue;
        $payload=is_array($r['payload']??null)?$r['payload']:[];
        $type=(string)($r['type']??'');
        $row=['type'=>$type,'at'=>$at,'event_id'=>(string)($r['id']??'')];

        if($type==='whatsapp.inbound_message.received'){
            $m=is_array($payload['whatsappInboundMessage']??null)?$payload['whatsappInboundMessage']:[];
            if((string)($m['to']??'')!==$business)continue;
            $body=(string)($m['text']['body']??'');
            $dec=decode_chatlink($body);
            $row['customer']=mask_phone((string)($m['from']??''));
            $row['message_keys']=array_keys($m);
            $row['source_like_fields']=source_like($m);
            $row['referral']=$m['referral']??null;
            $row['chatlink_click_id']=$dec['click_id'];
            if($dec['click_id']!=='')$lastTrackedCustomer=(string)($m['from']??'');
        }elseif($type==='contact.created'){
            $c=is_array($payload['contactCreated']??null)?$payload['contactCreated']:[];
            if((string)($c['lastConnectedNumber']??'')!==$business)continue;
            $row['customer']=mask_phone((string)($c['phoneNumber']??''));
            $row['contact_source']=[
                'sourceType'=>$c['sourceType']??null,'sourceId'=>$c['sourceId']??null,'sourceUrl'=>$c['sourceUrl']??null,
                'createTime'=>$c['createTime']??null,'updateTime'=>$c['updateTime']??null
            ];
            $row['source_like_fields']=source_like($c);
        }elseif($type==='contact.attributes_changed'){
            $x=is_array($payload['contactAttributesChanged']??null)?$payload['contactAttributesChanged']:[];
            $row['contact_id']=$x['id']??null;
            $row['changedAttributes']=$x['changedAttributes']??[];
        }else{
            continue;
        }
        $events[]=$row;
    }
    fclose($fh);
}

$conns=jload($base.'/ycloud_connections.json');
$conn=is_array($conns[$connectionId]??null)?$conns[$connectionId]:[];
$keyFile=dirname(__DIR__,4).'/.marketing/ycloud/'.$connectionId.'/api_key';
$key=is_file($keyFile)?trim((string)file_get_contents($keyFile)):'';
$contactNow=null;$endpointNow=null;
if($key!=='' && $lastTrackedCustomer!==''){
    $r=api($key,'GET','/v2/contact/contacts/'.rawurlencode($lastTrackedCustomer));
    $c=$r['json'];
    $contactNow=[
        'http'=>$r['status'],
        'sourceType'=>$c['sourceType']??null,'sourceId'=>$c['sourceId']??null,'sourceUrl'=>$c['sourceUrl']??null,
        'lastConnectedNumber'=>$c['lastConnectedNumber']??null,'createTime'=>$c['createTime']??null,'updateTime'=>$c['updateTime']??null,
        'id'=>$c['id']??null
    ];
}
$ep=(string)($conn['webhook_endpoint_id']??$conn['last_endpoint_id']??'');
if($key!==''&&$ep!==''){
    $r=api($key,'GET','/v2/webhookEndpoints/'.rawurlencode($ep));
    $e=$r['json'];
    $endpointNow=[
        'http'=>$r['status'],'id'=>$e['id']??$ep,'url'=>$e['url']??null,'status'=>$e['status']??null,
        'enabledEvents'=>$e['enabledEvents']??null,'eventProperties'=>$e['eventProperties']??null
    ];
}

echo json_encode([
    'ok'=>true,'since'=>$since,'business'=>$business,'event_count'=>count($events),
    'events'=>$events,'last_tracked_customer'=>mask_phone($lastTrackedCustomer),
    'contact_now'=>$contactNow,'webhook_endpoint'=>$endpointNow
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
