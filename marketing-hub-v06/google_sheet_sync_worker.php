<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$syncHome=dirname(__DIR__,4);$syncPrivate=$syncHome.'/.marketing/google_sheet_live';if(!is_dir($syncPrivate))mkdir($syncPrivate,0700,true);
$syncLock=fopen($syncPrivate.'/sheet_sync.lock','c');if(!$syncLock||!flock($syncLock,LOCK_EX|LOCK_NB))exit;
function sheet_sync_status(array $data):void{global $syncPrivate;$data['checked_at']=gmdate('c');file_put_contents($syncPrivate.'/sheet_sync_status.json',json_encode($data,JSON_PRETTY_PRINT),LOCK_EX);chmod($syncPrivate.'/sheet_sync_status.json',0600);echo json_encode($data).PHP_EOL;}
function sheet_sync_request(string $url,array $body,array $headers=[]):array{
 $ch=curl_init($url);$form=str_contains($url,'oauth2.googleapis.com/token');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>45,CURLOPT_HTTPHEADER=>array_merge([$form?'Content-Type: application/x-www-form-urlencoded':'Content-Type: application/json'],$headers),CURLOPT_POSTFIELDS=>$form?http_build_query($body):json_encode($body)]);$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return['status'=>$status,'body'=>json_decode((string)$raw,true)??[]];
}
try{
 $config=json_decode((string)file_get_contents($syncHome.'/.horizons-google-mcp/config.json'),true);$token=json_decode((string)file_get_contents($syncHome.'/.horizons-google-mcp/google-token.json'),true);
 $scopes=preg_split('/\s+/',(string)($token['scope']??''));if(!in_array('https://www.googleapis.com/auth/spreadsheets',$scopes,true)&&!in_array('https://www.googleapis.com/auth/drive',$scopes,true)){sheet_sync_status(['ok'=>false,'state'=>'waiting_for_google_sheets_authorization']);exit;}
 $refresh=sheet_sync_request('https://oauth2.googleapis.com/token',['client_id'=>$config['googleClientId'],'client_secret'=>$config['googleClientSecret'],'refresh_token'=>$token['refresh_token'],'grant_type'=>'refresh_token']);if($refresh['status']!==200||empty($refresh['body']['access_token']))throw new RuntimeException('google_refresh_failed_'.$refresh['status']);
 require_once __DIR__.'/google_sheet_live_core.php';$export=gsl_export();
 $sheets=['cl_0e6efd258397db'=>'1Sdy7EEgBDpDbB9mABzz93HzZ7AKOjtGcl40N4H0HOY8','cl_3ea5ae96e05c6b'=>'1-DLLV4sU3OXOV4d5FkfnNYxYY18droQTbnNaB7Km_0w','cl_cbb797950cc8d4'=>'1QrOBsVLMUUr-twiS770OlQC1oyM0qpqwKUkxccwBU2w'];
 $tabs=['message_started'=>1737674934,'interested'=>1366796299,'qualified'=>305861077,'converted'=>736850939];$results=[];
 foreach($sheets as $cid=>$sheet){
  $requests=[];$counts=[];
  foreach($tabs as $stage=>$sheetId){
   $values=gsl_rows($export,$cid,$stage);$rows=[];$seen=[];
   foreach($values as $ri=>$row){if($ri&&isset($seen[$row[6]]))throw new RuntimeException('duplicate_event');if($ri)$seen[$row[6]]=true;$cells=[];foreach($row as $ci=>$v){$value=['stringValue'=>(string)$v];if($ri&&in_array($ci,[4,10],true)&&is_numeric($v))$value=['numberValue'=>(float)$v];if($ri&&$ci===8)$value=['boolValue'=>true];$cells[]=['userEnteredValue'=>$value];}$rows[]=['values'=>$cells];}
   $size=max(2000,count($rows)+100);$requests[]=['updateSheetProperties'=>['properties'=>['sheetId'=>$sheetId,'gridProperties'=>['rowCount'=>$size]],'fields'=>'gridProperties.rowCount']];$requests[]=['updateCells'=>['range'=>['sheetId'=>$sheetId,'startRowIndex'=>0,'endRowIndex'=>$size,'startColumnIndex'=>0,'endColumnIndex'=>16],'rows'=>$rows,'fields'=>'userEnteredValue']];$counts[$stage]=count($seen);
  }
  $result=sheet_sync_request('https://sheets.googleapis.com/v4/spreadsheets/'.$sheet.':batchUpdate',['requests'=>$requests],['Authorization: Bearer '.$refresh['body']['access_token']]);
  $results[sx_cfg($cid)['client_name']]=['ok'=>$result['status']===200,'status'=>$result['status'],'events'=>$counts];
 }
 $ok=!in_array(false,array_column($results,'ok'),true);sheet_sync_status(['ok'=>$ok,'state'=>$ok?'synced':'partial_failure','clients'=>$results]);if(!$ok)exit(1);
}catch(Throwable $e){sheet_sync_status(['ok'=>false,'state'=>'failed','reason'=>$e->getMessage()]);exit(1);}
