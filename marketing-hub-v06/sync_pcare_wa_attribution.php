<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

$feed='https://pcare.sa/pcare-wa-attribution-feed.php?token=HZNpcare_7R4mN2qL9xK6vT3sD8pF5wC1aG0yB4uJ';
$hub='https://marketing.hositee.com/wa_click_attribution.php';
$secure=dirname(__DIR__,4).'/.marketing';
$stateFile=$secure.'/pcare_wa_attr_sync_state.json';
if(!is_dir($secure))@mkdir($secure,0700,true);

function req(string $url,string $method='GET',?array $payload=null):array{
    $ch=curl_init($url);
    $headers=['Accept: application/json'];
    if($payload!==null)$headers[]='Content-Type: application/json';
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false
    ]);
    if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $body=curl_exec($ch);$errno=curl_errno($ch);$err=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    $json=is_string($body)?json_decode($body,true):null;
    return ['ok'=>$errno===0&&$status>=200&&$status<300,'status'=>$status,'error'=>$errno?$err:null,'json'=>is_array($json)?$json:null];
}
function jload(string $f):array{
    if(!is_file($f))return[];
    $v=json_decode((string)file_get_contents($f),true);
    return is_array($v)?$v:[];
}
function jsave(string $f,array $v):bool{
    $tmp=$f.'.tmp';
    if(file_put_contents($tmp,json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX)===false)return false;
    return rename($tmp,$f);
}

$f=req($feed);
if(!$f['ok']||!is_array($f['json'])){echo json_encode(['ok'=>false,'stage'=>'feed','status'=>$f['status'],'error'=>$f['error']]);exit(1);}
$records=is_array($f['json']['records']??null)?$f['json']['records']:[];
$state=jload($stateFile);
$sent=0;$skipped=0;$failed=0;$details=[];
foreach($records as $clickId=>$row){
    if(!is_array($row))continue;
    $row['client_id']='cl_0e6efd258397db';
    $row['click_id']=(string)$clickId;
    $hash=sha1(json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    if(($state[$clickId]['hash']??'')===$hash){$skipped++;continue;}
    $r=req($hub,'POST',$row);
    if($r['ok']){
        $sent++;$state[$clickId]=['hash'=>$hash,'synced_at'=>gmdate('c')];
    }else{
        $failed++;$details[]=['click_id'=>$clickId,'status'=>$r['status'],'error'=>$r['error']];
    }
}
jsave($stateFile,$state);
echo json_encode(['ok'=>$failed===0,'records'=>count($records),'sent'=>$sent,'skipped'=>$skipped,'failed'=>$failed,'details'=>$details],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
