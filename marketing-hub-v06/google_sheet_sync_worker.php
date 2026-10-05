<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$syncHome=dirname(__DIR__,4);$syncPrivate=$syncHome.'/.marketing/google_sheet_live';
if(!is_dir($syncPrivate))mkdir($syncPrivate,0700,true);
$syncLock=fopen($syncPrivate.'/sheet_sync.lock','c');
if(!$syncLock||!flock($syncLock,LOCK_EX|LOCK_NB))exit;
function sheet_sync_status(array $data):void{
    global $syncPrivate;
    $data['checked_at']=gmdate('c');
    file_put_contents($syncPrivate.'/sheet_sync_status.json',json_encode($data,JSON_PRETTY_PRINT),LOCK_EX);
    chmod($syncPrivate.'/sheet_sync_status.json',0600);
    echo json_encode($data).PHP_EOL;
}
function sheet_sync_request(string $url,array $body,array $headers=[]):array{
    $ch=curl_init($url);$form=str_contains($url,'oauth2.googleapis.com/token');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>45,
        CURLOPT_HTTPHEADER=>array_merge([$form?'Content-Type: application/x-www-form-urlencoded':'Content-Type: application/json'],$headers),
        CURLOPT_POSTFIELDS=>$form?http_build_query($body):json_encode($body)]);
    $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    return ['status'=>$status,'body'=>json_decode((string)$raw,true)??[]];
}
try{
    $syncConfig=json_decode((string)file_get_contents($syncHome.'/.horizons-google-mcp/config.json'),true);
    $syncToken=json_decode((string)file_get_contents($syncHome.'/.horizons-google-mcp/google-token.json'),true);
    $syncScope=preg_split('/\s+/',(string)($syncToken['scope']??''));
    if(!in_array('https://www.googleapis.com/auth/spreadsheets',$syncScope,true)&&!in_array('https://www.googleapis.com/auth/drive',$syncScope,true)){
        sheet_sync_status(['ok'=>false,'state'=>'waiting_for_google_sheets_authorization']);exit;
    }
    $refresh=sheet_sync_request('https://oauth2.googleapis.com/token',[
        'client_id'=>$syncConfig['googleClientId'],'client_secret'=>$syncConfig['googleClientSecret'],
        'refresh_token'=>$syncToken['refresh_token'],'grant_type'=>'refresh_token']);
    if($refresh['status']!==200||empty($refresh['body']['access_token']))throw new RuntimeException('google_refresh_failed_'.$refresh['status']);
    $syncTokens=json_decode((string)file_get_contents($syncHome.'/.marketing/qualified_pool_tokens.json'),true);
    $syncUrl='https://marketing.hositee.com/google_sheet_live_feed.php?token='.rawurlencode((string)($syncTokens['cl_0e6efd258397db']??''));
    $ch=curl_init($syncUrl);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>45]);
    $csv=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($http!==200)throw new RuntimeException('feed_failed_'.$http);
    $stream=fopen('php://temp','w+');fwrite($stream,(string)$csv);rewind($stream);$values=[];
    while(($row=fgetcsv($stream))!==false)$values[]=$row;fclose($stream);
    if(count($values)<2||count($values[0])!==16||$values[0][0]!=='GCLID')throw new RuntimeException('invalid_feed');
    $ids=[];foreach(array_slice($values,1) as $row){
        if(count($row)!==16||$row[13]!=='4482394160'||$row[14]!=='7789160285'||isset($ids[$row[6]]))throw new RuntimeException('invalid_or_duplicate_event');
        $ids[$row[6]]=true;
    }
    $rows=[];foreach($values as $ri=>$row){$cells=[];foreach($row as $ci=>$v){
        $value=['stringValue'=>(string)$v];
        if($ri>0&&in_array($ci,[4,10],true)&&is_numeric($v))$value=['numberValue'=>(float)$v];
        if($ri>0&&$ci===8)$value=['boolValue'=>true];
        $cells[]=['userEnteredValue'=>$value];
    }$rows[]=['values'=>$cells];}
    // Updating values over the full tab removes the temporary import formula and preserves formatting.
    $requests=[['updateCells'=>['range'=>['sheetId'=>1737674934,'startRowIndex'=>0,'endRowIndex'=>max(2000,count($rows)+100),'startColumnIndex'=>0,'endColumnIndex'=>16],'rows'=>$rows,'fields'=>'userEnteredValue']]];
    if(count($rows)>1900)array_unshift($requests,['updateSheetProperties'=>['properties'=>['sheetId'=>1737674934,'gridProperties'=>['rowCount'=>count($rows)+100]],'fields'=>'gridProperties.rowCount']]);
    $result=sheet_sync_request('https://sheets.googleapis.com/v4/spreadsheets/1Sdy7EEgBDpDbB9mABzz93HzZ7AKOjtGcl40N4H0HOY8:batchUpdate',['requests'=>$requests],['Authorization: Bearer '.$refresh['body']['access_token']]);
    if($result['status']!==200)throw new RuntimeException('sheet_write_failed_'.$result['status']);
    sheet_sync_status(['ok'=>true,'state'=>'synced','events'=>count($ids),'latest_event_time'=>end($values)[3]]);
}catch(Throwable $e){sheet_sync_status(['ok'=>false,'state'=>'failed','reason'=>$e->getMessage()]);exit(1);}
