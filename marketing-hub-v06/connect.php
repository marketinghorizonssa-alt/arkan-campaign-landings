<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow', true);
header("Content-Security-Policy: default-src 'self'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$base=__DIR__.'/data'; if(!is_dir($base)) @mkdir($base,0775,true);
$secureDir=dirname(__DIR__,4).'/.marketing'; $ycloudSecureDir=$secureDir.'/ycloud'; if(!is_dir($ycloudSecureDir)) @mkdir($ycloudSecureDir,0700,true);
$connectionsFile=$base.'/ycloud_connections.json'; $clientsFile=$base.'/clients.json'; $numbersFile=$base.'/whatsapp_numbers.json';

function esc(string $v): string{return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function load_json(string $file,array $default=[]):array{if(!is_file($file))return $default;$v=json_decode((string)@file_get_contents($file),true);return is_array($v)?$v:$default;}
function save_json(string $file,array $data):bool{$tmp=$file.'.tmp';if(@file_put_contents($tmp,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX)===false)return false;return @rename($tmp,$file);}
function normalize_phone(string $phone):string{$phone=trim($phone);$plus=str_starts_with($phone,'+')?'+':'';$digits=preg_replace('/\D+/','',$phone)??'';return $digits===''?'':$plus.$digits;}
function safe_id(string $id):bool{return (bool)preg_match('/^yc_[a-z0-9_]{4,80}$/',$id);}
function conn_dir(string $root,string $id):string{return $root.'/'.$id;}
function api_call(string $apiKey,string $method,string $path,?array $payload=null):array{
    $ch=curl_init('https://api.ycloud.com'.$path);$headers=['X-API-Key: '.$apiKey,'Accept: application/json'];if($payload!==null)$headers[]='Content-Type: application/json';
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false]);
    if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));$body=curl_exec($ch);$errno=curl_errno($ch);$err=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);$json=is_string($body)?json_decode($body,true):null;
    return ['ok'=>$errno===0&&$status>=200&&$status<300,'status'=>$status,'json'=>is_array($json)?$json:null,'error'=>$errno?$err:null];
}
function extract_list(?array $j):array{if(!$j)return[];if(array_is_list($j))return$j;foreach(['items','list','results']as$k)if(isset($j[$k])&&is_array($j[$k])&&array_is_list($j[$k]))return$j[$k];if(isset($j['data'])&&is_array($j['data'])){if(array_is_list($j['data']))return$j['data'];foreach(['items','list','results']as$k)if(isset($j['data'][$k])&&is_array($j['data'][$k])&&array_is_list($j['data'][$k]))return$j['data'][$k];}return[];}
function unwrap(?array $j):array{if(!$j)return[];if(isset($j['data'])&&is_array($j['data'])&&!array_is_list($j['data']))return$j['data'];return$j;}
function page(string $title,string $body):never{echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc($title).'</title><style>body{margin:0;background:#0b1220;color:#eef3ff;font-family:Arial,sans-serif}.wrap{max-width:700px;margin:7vh auto;padding:24px}.card{background:#151f34;border:1px solid #293652;border-radius:18px;padding:26px}h1{margin-top:0}p{color:#aebbd4;line-height:1.6}input{width:100%;box-sizing:border-box;padding:14px;border-radius:10px;border:1px solid #39496b;background:#0c1528;color:#fff;font-size:16px}button{margin-top:14px;padding:13px 18px;border:0;border-radius:10px;background:#765cff;color:#fff;font-weight:700;cursor:pointer}.ok{color:#54e5a5}.err{color:#ff8d8d}.muted{font-size:13px;color:#8594b2}.endpoint{font:12px ui-monospace,Consolas,monospace;background:#0d1425;padding:10px;border-radius:10px;overflow-wrap:anywhere;color:#d8e1f4}.stat{padding:8px 0;border-bottom:1px solid #293652}.stat:last-child{border:0}</style></head><body><div class="wrap"><div class="card">'.$body.'</div></div></body></html>';exit;}
function upsert_numbers(string $apiKey,string $connectionId,string $clientId,string $numbersFile):int{
    $resp=api_call($apiKey,'GET','/v2/whatsapp/phoneNumbers?limit=100&includeTotal=true');if(!$resp['ok'])return 0;$items=extract_list($resp['json']);$numbers=load_json($numbersFile,[]);$count=0;
    foreach($items as$p){if(!is_array($p))continue;$phone=normalize_phone((string)($p['phoneNumber']??$p['displayPhoneNumber']??''));$waba=(string)($p['wabaId']??'');if($phone===''&&$waba==='')continue;$id='';foreach($numbers as$nid=>$n)if(normalize_phone((string)($n['phone_number']??''))===$phone&&(string)($n['waba_id']??'')===$waba){$id=(string)$nid;break;}if($id==='')$id='num_'.substr(sha1($phone.'|'.$waba),0,12);$now=gmdate('c');$numbers[$id]=['id'=>$id,'client_id'=>$clientId,'ycloud_connection_id'=>$connectionId,'label'=>(string)($p['verifiedName']??$p['displayName']??$p['newName']??($numbers[$id]['label']??'WhatsApp')),'phone_number'=>$phone,'waba_id'=>$waba,'provider'=>'ycloud','status'=>'assigned','remote_status'=>(string)($p['status']??''),'detected'=>true,'created_at'=>(string)($numbers[$id]['created_at']??$now),'updated_at'=>$now];$count++;}
    save_json($numbersFile,$numbers);return$count;
}

$connId=(string)($_POST['c']??$_GET['c']??'');$token=(string)($_POST['token']??$_GET['token']??'');$connections=load_json($connectionsFile,[]);
if(!safe_id($connId)||!isset($connections[$connId])||$connId==='yc_legacy'){http_response_code(404);page('Connection not found','<h1>Connection not found</h1><p class="err">This YCloud connection does not exist.</p>');}
$connDir=conn_dir($ycloudSecureDir,$connId);if(!is_dir($connDir))@mkdir($connDir,0700,true);$tokenFile=$connDir.'/setup_token';$keyFile=$connDir.'/api_key';$secretFile=$connDir.'/webhook_secret';$storedToken=is_file($tokenFile)?trim((string)@file_get_contents($tokenFile)):'';
if($storedToken===''||$token===''||!hash_equals($storedToken,$token)){http_response_code(403);page('Setup link expired','<h1>Setup link expired</h1><p>This one-time setup link is invalid or has already been used. Generate a new one from Marketing.</p>');}
$clients=load_json($clientsFile,[]);$clientId=(string)($connections[$connId]['client_id']??'');$clientName=(string)($clients[$clientId]['name']??'Client');$webhookUrl='https://marketing.hositee.com/webhook.php?connection='.rawurlencode($connId);

if($_SERVER['REQUEST_METHOD']==='POST'){
    $apiKey=trim((string)($_POST['api_key']??''));if($apiKey==='')page('YCloud Connection','<h1>Connect '.$clientName.'</h1><p class="err">API key is required.</p><a href="?c='.esc($connId).'&token='.esc($token).'" style="color:#9eaaff">Try again</a>');
    $verify=api_call($apiKey,'GET','/v2/balance');if(!$verify['ok'])page('YCloud Connection','<h1>Connect '.$clientName.'</h1><p class="err">YCloud rejected this API key (HTTP '.(int)$verify['status'].'). Nothing was saved.</p><a href="?c='.esc($connId).'&token='.esc($token).'" style="color:#9eaaff">Try again</a>');
    if(@file_put_contents($keyFile,$apiKey."\n",LOCK_EX)===false)page('YCloud Connection','<h1>Connection error</h1><p class="err">Could not save the key securely.</p>');@chmod($keyFile,0600);

    $defs=[['name'=>'interested','label'=>'Interested','description'=>'Marketing conversation marked Interested'],['name'=>'qualified','label'=>'Qualified','description'=>'Marketing conversation marked Qualified'],['name'=>'purchased','label'=>'Purchased','description'=>'Marketing conversation marked Purchased']];$definitionErrors=[];
    foreach($defs as$def){$check=api_call($apiKey,'GET','/v2/event/definitions/'.rawurlencode($def['name']));if($check['status']===200)continue;if($check['status']!==404){$definitionErrors[]=$def['name'].' lookup '.$check['status'];continue;}$create=api_call($apiKey,'POST','/v2/event/definitions',['name'=>$def['name'],'label'=>$def['label'],'description'=>$def['description'],'objectType'=>'CONTACT','properties'=>[]]);if(!$create['ok'])$definitionErrors[]=$def['name'].' create '.$create['status'];}

    $endpointId='';$endpointSecret='';$endpointStatus='';$endpointError='';$list=api_call($apiKey,'GET','/v2/webhookEndpoints?limit=100&includeTotal=true');
    if($list['ok']){foreach(extract_list($list['json']) as$ep){if(is_array($ep)&&(string)($ep['url']??'')===$webhookUrl){$endpointId=(string)($ep['id']??'');$endpointStatus=(string)($ep['status']??'');break;}}}
    if($endpointId===''){
        $created=api_call($apiKey,'POST','/v2/webhookEndpoints',['url'=>$webhookUrl,'description'=>'Marketing - '.$clientName,'enabledEvents'=>['whatsapp.inbound_message.received','whatsapp.smb.message.echoes','whatsapp.smb.history'],'status'=>'active']);
        if($created['ok']){$obj=unwrap($created['json']);$endpointId=(string)($obj['id']??'');$endpointSecret=(string)($obj['secret']??'');$endpointStatus=(string)($obj['status']??'active');}else{$endpointError='create HTTP '.$created['status'];}
    }
    if($endpointId!==''&&$endpointSecret===''){$get=api_call($apiKey,'GET','/v2/webhookEndpoints/'.rawurlencode($endpointId));if($get['ok']){$obj=unwrap($get['json']);$endpointSecret=(string)($obj['secret']??'');$endpointStatus=(string)($obj['status']??$endpointStatus);}}
    if($endpointSecret!==''){@file_put_contents($secretFile,$endpointSecret."\n",LOCK_EX);@chmod($secretFile,0600);}

    $phoneCount=upsert_numbers($apiKey,$connId,$clientId,$numbersFile);$wabas=api_call($apiKey,'GET','/v2/whatsapp/businessAccounts?limit=100&includeTotal=true');$wabaItems=$wabas['ok']?extract_list($wabas['json']):[];$accountName='';if($wabaItems&&is_array($wabaItems[0]??null))$accountName=(string)($wabaItems[0]['businessName']??$wabaItems[0]['name']??'');
    $connections=load_json($connectionsFile,[]);$now=gmdate('c');$connections[$connId]=array_merge($connections[$connId]??[],['id'=>$connId,'client_id'=>$clientId,'status'=>'connected','account_name'=>$accountName,'webhook_url'=>$webhookUrl,'webhook_endpoint_id'=>$endpointId,'webhook_status'=>$endpointStatus,'webhook_events'=>['whatsapp.inbound_message.received','whatsapp.smb.message.echoes','whatsapp.smb.history'],'connected_at'=>$now,'last_sync_at'=>$now,'updated_at'=>$now]);save_json($connectionsFile,$connections);

    if($endpointId===''||$endpointSecret===''){
        page('YCloud partially connected','<h1>YCloud key connected</h1><p class="ok">The API key is valid and stored separately for <b>'.esc($clientName).'</b>.</p><p class="err">Webhook automation needs attention: '.esc($endpointError!==''?$endpointError:'endpoint secret not returned').'</p><p class="muted">The setup link remains valid so this can be retried.</p>');
    }
    if($definitionErrors)page('YCloud partially connected','<h1>YCloud connected</h1><p class="ok">Webhook and account connection are ready.</p><p class="err">Some custom event definitions need a retry: '.esc(implode(', ',$definitionErrors)).'</p><p class="muted">The setup link remains valid so this can be retried.</p>');
    @unlink($tokenFile);
    page('YCloud Connected','<h1>Connected</h1><p class="ok"><b>'.esc($clientName).'</b> now has its own YCloud connection.</p><div class="stat">Webhook: <b>Active + signed</b></div><div class="stat">WhatsApp numbers synced: <b>'.(int)$phoneCount.'</b></div><div class="stat">Custom events: <b>Interested, Qualified, Purchased</b></div><p class="muted">You do not need to create the webhook manually. Marketing created it through the YCloud API and stored its signing secret outside the public website.</p>');
}
page('Connect YCloud','<h1>Connect YCloud for '.esc($clientName).'</h1><p>Paste the API key from this client\'s YCloud account. Marketing will verify it, create the required custom events, create a signed webhook automatically, and sync the WhatsApp numbers in that YCloud account.</p><form method="post" autocomplete="off"><input type="hidden" name="c" value="'.esc($connId).'"><input type="hidden" name="token" value="'.esc($token).'"><input type="password" name="api_key" placeholder="YCloud API Key" autocomplete="new-password" required><button type="submit">Verify & Connect Client</button></form><p class="muted">The API key is sent only to marketing.hositee.com over HTTPS and stored outside public_html.</p>');
