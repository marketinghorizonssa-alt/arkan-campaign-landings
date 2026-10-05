<?php
if(PHP_SAPI!=='cli')exit;
$s='/home/u878466595/.marketing/naqel';if(!is_dir($s))mkdir($s,0700,true);$marker=$s.'/setup-installed-20261005';if(is_file($marker)){echo '{"ok":true,"already_done":true}';exit;}
$b=<<<'NAQEL_SETUP'
<?php
declare(strict_types=1);
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header('X-Frame-Options: DENY');header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
$secure='/home/u878466595/.marketing/naqel';$nonce=is_file($secure.'/setup_token')?trim((string)file_get_contents($secure.'/setup_token')):'';$given=(string)($_GET['setup']??'');
if($nonce===''||!hash_equals($nonce,$given)||time()>(int)@file_get_contents($secure.'/setup_expires')){http_response_code(403);echo 'انتهت صلاحية رابط الربط أو تم استخدامه.';exit;}
$message='';$success=false;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    $token=trim((string)($_POST['access_token']??''));
    if(strlen($token)<20||strlen($token)>4096){$message='أدخل توكن Events API الصحيح الخاص بمصدر Naqel Madmoon Website.';}
    else{
        $ch=curl_init('https://business-api.tiktok.com/open_api/v1.3/event/track/');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Access-Token: '.$token,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>'{' . '"event_source":"web","event_source_id":"DAUC8ERC77UD1K9H1N70","data":[]}',CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false]);$body=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$errno=curl_errno($ch);curl_close($ch);$j=is_string($body)?json_decode($body,true):null;$code=is_array($j)?(int)($j['code']??-1):-1;
        $valid=$errno===0&&(($http>=200&&$http<300&&$code===0)||($http===400&&$code===40002&&(str_contains(strtolower((string)($j['message']??'')),'data')||($j['message']??'')==='Number of events must be between 1 and 1000.')));
        if($valid){$tmp=$secure.'/web_access_token.tmp';file_put_contents($tmp,$token."\n",LOCK_EX);chmod($tmp,0600);rename($tmp,$secure.'/web_access_token');@unlink($secure.'/setup_token');@unlink($secure.'/setup_expires');$success=true;$message='تم ربط توكن أحداث الموقع. التقييم يعمل كل دقيقة، والحملة محفوظة ومتوقفة للمراجعة. اختبار الصلاحية لم يُرسل أي تحويل.';}
        else{$message='تيك توك لم يقبل صلاحية التوكن لهذا البكسل. استخدم توكن مصدر Naqel Madmoon Website، وليس CRM أو Offline. كود الاستجابة: '.$code;}
        file_put_contents($secure.'/token_check.json',json_encode(['at'=>gmdate('c'),'ready'=>$valid,'http'=>$http,'api_code'=>$code,'api_message'=>substr((string)($j['message']??''),0,250)]),LOCK_EX);chmod($secure.'/token_check.json',0600);
    }
}
header('Content-Type: text/html; charset=utf-8');
?><!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ربط أحداث الناقل المضمون</title><style>body{margin:0;background:#f5f8fc;color:#082e70;font:18px/1.8 system-ui,sans-serif}main{max-width:600px;margin:8vh auto;background:white;padding:32px;border-radius:20px}h1{font-size:27px}input,button{box-sizing:border-box;width:100%;padding:14px;border-radius:10px;font:inherit}input{border:1px solid #c4cede;direction:ltr}button{background:#082e70;color:white;border:0;margin-top:16px;cursor:pointer}.result{padding:16px;background:#eef5fa;border-radius:12px}</style><main><h1>ربط أحداث الناقل المضمون</h1><p>من TikTok Events Manager، افتح مصدر <b>Naqel Madmoon Website</b> ثم إعدادات Events API وأنشئ Access Token. أدخله هنا لإكمال إرسال العملاء Interested من السيستم.</p><p>التوكن يُحفظ على السيرفر فقط. رابط الربط يُستخدم مرة واحدة، ولا يشغّل الحملة.</p><?php if($message!==''):?><p class="result"><?php echo htmlspecialchars($message,ENT_QUOTES,'UTF-8');?></p><?php endif;?><?php if(!$success):?><form method="post"><label for="token">توكن Events API للموقع</label><input id="token" name="access_token" type="password" autocomplete="off" required minlength="20" maxlength="4096"><button type="submit">تحقق واحفظ الربط</button></form><?php endif;?></main></html>

NAQEL_SETUP;
token_get_all($b,TOKEN_PARSE);
$predicate=trim(explode(';',explode('$valid=',$b,2)[1],2)[0]);
foreach([[400,40002,'Number of events must be between 1 and 1000.',true],[401,40001,'No permission to operate event source id',false],[400,40002,'Invalid access token',false]]as[$http,$code,$message,$expected]){$errno=0;$j=['message'=>$message];if(eval('return '.$predicate.';')!==$expected)throw new RuntimeException('validation_failed');}
$p='/home/u878466595/domains/naqelmadmoon.hositee.com/public_html/web-events-setup.php';if(is_file($p))throw new RuntimeException('setup_exists');file_put_contents($p,$b,LOCK_EX);chmod($p,0644);
$n=bin2hex(random_bytes(32));file_put_contents($s.'/setup_token',$n,LOCK_EX);chmod($s.'/setup_token',0600);file_put_contents($s.'/setup_expires',(string)(time()+86400),LOCK_EX);chmod($s.'/setup_expires',0600);file_put_contents($marker,gmdate('c'));chmod($marker,0600);
echo json_encode(['ok'=>true,'syntax_valid'=>true,'setup_url'=>'https://naqelmadmoon.hositee.com/web-events-setup.php?setup='.$n,'expires_hours'=>24],JSON_UNESCAPED_SLASHES);
