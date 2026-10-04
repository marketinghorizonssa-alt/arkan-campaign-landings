<?php
declare(strict_types=1);
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header('X-Frame-Options: DENY');header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
$secure='/home/u878466595/.marketing/biyaat';$nonce=is_file($secure.'/setup_token')?trim((string)file_get_contents($secure.'/setup_token')):'';$given=(string)($_GET['setup']??'');
if($nonce===''||!hash_equals($nonce,$given)||time()>(int)@file_get_contents($secure.'/setup_expires')){http_response_code(403);echo 'انتهت صلاحية رابط الربط أو تم استخدامه.';exit;}
$message='';$success=false;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    $token=trim((string)($_POST['access_token']??''));
    if(strlen($token)<20||strlen($token)>4096){$message='أدخل توكن Events API الصحيح الخاص بمصدر Biyaat Website.';}
    else{
        $ch=curl_init('https://business-api.tiktok.com/open_api/v1.3/event/track/');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Access-Token: '.$token,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>'{' . '"event_source":"web","event_source_id":"DAUJ4C3C77U2INVDGUD0","data":[]}',CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false]);$body=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$errno=curl_errno($ch);curl_close($ch);$j=is_string($body)?json_decode($body,true):null;$code=is_array($j)?(int)($j['code']??-1):-1;
        $valid=$errno===0&&$http>=200&&$http<300&&($code===0||($code===40002&&str_contains(strtolower((string)($j['message']??'')),'data')));
        if($valid){$tmp=$secure.'/web_access_token.tmp';file_put_contents($tmp,$token."\n",LOCK_EX);chmod($tmp,0600);rename($tmp,$secure.'/web_access_token');@unlink($secure.'/setup_token');@unlink($secure.'/setup_expires');$success=true;$message='تم ربط توكن أحداث الموقع. التقييم يعمل كل دقيقة، والحملة محفوظة ومتوقفة للمراجعة. اختبار الصلاحية لم يُرسل أي تحويل.';}
        else{$message='تيك توك لم يقبل صلاحية التوكن لهذا البكسل. استخدم توكن مصدر Biyaat Website، وليس CRM أو Offline. كود الاستجابة: '.$code;}
        file_put_contents($secure.'/token_check.json',json_encode(['at'=>gmdate('c'),'ready'=>$valid,'http'=>$http,'api_code'=>$code]),LOCK_EX);chmod($secure.'/token_check.json',0600);
    }
}
header('Content-Type: text/html; charset=utf-8');
?><!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ربط أحداث بيئات</title><style>body{margin:0;background:#f5f8fc;color:#082e70;font:18px/1.8 system-ui,sans-serif}main{max-width:600px;margin:8vh auto;background:white;padding:32px;border-radius:20px}h1{font-size:27px}input,button{box-sizing:border-box;width:100%;padding:14px;border-radius:10px;font:inherit}input{border:1px solid #c4cede;direction:ltr}button{background:#082e70;color:white;border:0;margin-top:16px;cursor:pointer}.result{padding:16px;background:#eef5fa;border-radius:12px}</style><main><h1>ربط أحداث بيئات</h1><p>من TikTok Events Manager، افتح مصدر <b>Biyaat Website</b> ثم إعدادات Events API وأنشئ Access Token. أدخله هنا لإكمال إرسال العملاء Interested من السيستم.</p><p>التوكن يُحفظ على السيرفر فقط. رابط الربط يُستخدم مرة واحدة، ولا يشغّل الحملة.</p><?php if($message!==''):?><p class="result"><?php echo htmlspecialchars($message,ENT_QUOTES,'UTF-8');?></p><?php endif;?><?php if(!$success):?><form method="post"><label for="token">توكن Events API للموقع</label><input id="token" name="access_token" type="password" autocomplete="off" required minlength="20" maxlength="4096"><button type="submit">تحقق واحفظ الربط</button></form><?php endif;?></main></html>
