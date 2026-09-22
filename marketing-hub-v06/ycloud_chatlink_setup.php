<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

$base=__DIR__.'/data';
$secure=dirname(__DIR__,4).'/.marketing/ycloud';
$connectionsFile=$base.'/ycloud_connections.json';
$contactSourcesFile=$base.'/contact_sources.json';
$targetClient='cl_0e6efd258397db';

function jload(string $f):array{
    $v=is_file($f)?json_decode((string)file_get_contents($f),true):[];
    return is_array($v)?$v:[];
}
function req(string $key,string $method,string $path,?array $payload=null):array{
    $ch=curl_init('https://api.ycloud.com'.$path);
    $headers=['X-API-Key: '.$key,'Accept: application/json'];
    if($payload!==null)$headers[]='Content-Type: application/json';
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>25,CURLOPT_FOLLOWLOCATION=>false]);
    if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $body=curl_exec($ch);$errno=curl_errno($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);
    $json=is_string($body)?json_decode($body,true):null;
    return ['ok'=>$errno===0&&$status>=200&&$status<300,'status'=>$status,'error'=>$errno?$err:null,'json'=>is_array($json)?$json:null];
}
function list_items(?array $j):array{
    if(!$j)return[];
    if(array_is_list($j))return$j;
    foreach(['items','list','results'] as $k)if(isset($j[$k])&&is_array($j[$k])&&array_is_list($j[$k]))return$j[$k];
    if(isset($j['data'])&&is_array($j['data'])){
        if(array_is_list($j['data']))return$j['data'];
        foreach(['items','list','results'] as $k)if(isset($j['data'][$k])&&is_array($j['data'][$k])&&array_is_list($j['data'][$k]))return$j['data'][$k];
    }
    return[];
}

$conns=jload($connectionsFile);
$results=[];
foreach($conns as $id=>$conn){
    if(!is_array($conn)||(string)($conn['client_id']??'')!==$targetClient)continue;
    $id=(string)($conn['id']??$id);
    $keyFile=$secure.'/'.$id.'/api_key';
    $key=is_file($keyFile)?trim((string)file_get_contents($keyFile)):'';
    if($key===''){continue;}
    $ep=(string)($conn['webhook_endpoint_id']??'');
    $entry=['connection_id'=>$id,'label'=>$conn['label']??'','endpoint_id'=>$ep];

    if($ep!==''){
        $g=req($key,'GET','/v2/webhookEndpoints/'.rawurlencode($ep));
        $entry['webhook_get_status']=$g['status'];
        if($g['ok']&&is_array($g['json'])){
            $events=is_array($g['json']['enabledEvents']??null)?$g['json']['enabledEvents']:[];
            $before=$events;
            if(!in_array('contact.created',$events,true))$events[]='contact.created';
            $events=array_values(array_unique(array_map('strval',$events)));
            $patch=req($key,'PATCH','/v2/webhookEndpoints/'.rawurlencode($ep),[
                'enabledEvents'=>$events,
                'status'=>'active'
            ]);
            $entry['webhook_patch_status']=$patch['status'];
            $entry['events_before']=$before;
            $entry['events_after']=$events;
        }
    }

    $counts=[];$growth=0;$examples=[];$allContacts=[];$contactsStatus=200;
    for($page=1;$page<=100;$page++){
        $contacts=req($key,'GET','/v2/contact/contacts?page='.$page.'&limit=100&includeTotal=true');
        $contactsStatus=$contacts['status'];
        if(!$contacts['ok'])break;
        $items=list_items($contacts['json']);
        foreach($items as $ct)if(is_array($ct))$allContacts[]=$ct;
        if(count($items)<100)break;
    }
    $entry['contacts_status']=$contactsStatus;
    $sources=jload($contactSourcesFile);
    foreach($allContacts as $ct){
        $st=strtoupper((string)($ct['sourceType']??'UNKNOWN'));
        $counts[$st]=($counts[$st]??0)+1;
        $phone=preg_replace('/\\D+/','',(string)($ct['phoneNumber']??''))??'';
        $sourceUrl=(string)($ct['sourceUrl']??'');
        $u=strtolower($sourceUrl);
        $tk='unknown';$tl='Unknown';
        if(str_contains($u,'gclid=')||str_contains($u,'gbraid=')||str_contains($u,'wbraid=')||str_contains($u,'utm_source=google')){$tk='google';$tl='Google Ads';}
        elseif(str_contains($u,'tiktok')||str_contains($u,'utm_source=tiktok')){$tk='tiktok';$tl='TikTok Ads';}
        elseif(str_contains($u,'facebook')||str_contains($u,'instagram')||str_contains($u,'utm_source=facebook')||str_contains($u,'utm_source=instagram')){$tk='meta';$tl='Meta Ads';}
        elseif(str_contains($u,'snapchat')||str_contains($u,'utm_source=snapchat')){$tk='snapchat';$tl='Snapchat Ads';}
        elseif($st==='GROWTH_TOOL'){$tk='website';$tl='Website / YCloud Chat Link';}
        elseif($st==='AD'){$tk='ad';$tl='Ad';}
        elseif($st==='WHATSAPP'||$st==='SMB'){$tk='organic';$tl='Organic / Direct';}
        if($phone!==''){
            $sk=$id.'|'.$phone;
            $sources[$sk]=[
                'id'=>(string)($ct['id']??''),'ycloud_connection_id'=>$id,'client_id'=>$targetClient,
                'phone_number'=>'+'.$phone,'source_type'=>$st,'source_id'=>(string)($ct['sourceId']??''),
                'source_url'=>$sourceUrl,'last_connected_number'=>(string)($ct['lastConnectedNumber']??''),
                'traffic_source_key'=>$tk,'traffic_source_label'=>$tl,'traffic_source_confidence'=>'high',
                'traffic_source_reason'=>'ycloud_contact_backfill',
                'created_at'=>(string)($ct['createTime']??gmdate('c')),'updated_at'=>gmdate('c')
            ];
        }
        if($st==='GROWTH_TOOL'){
            $growth++;
            if(count($examples)<5)$examples[]=[
                'sourceId'=>(string)($ct['sourceId']??''),
                'sourceUrl'=>$sourceUrl,
                'lastConnectedNumber'=>(string)($ct['lastConnectedNumber']??'')
            ];
        }
    }
    if($sources)file_put_contents($contactSourcesFile,json_encode($sources,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);
    ksort($counts);
    $entry['contacts_scanned']=count($allContacts);
    $entry['source_type_counts']=$counts;
    $entry['growth_tool_contacts']=$growth;
    $entry['growth_tool_examples']=$examples;

    $probes=[
      '/v2/whatsapp/chatLinks',
      '/v2/whatsapp/chatlinks',
      '/v2/whatsapp/chat-links',
      '/v2/growthTools',
      '/v2/growth-tools',
      '/v2/whatsapp/growthTools',
      '/v2/whatsapp/growthTools/chatLinks'
    ];
    $probeOut=[];
    foreach($probes as $p){$x=req($key,'GET',$p);$probeOut[$p]=$x['status'];}
    $entry['chatlink_api_probe']=$probeOut;

    $results[]=$entry;
}
echo json_encode(['ok'=>true,'target_client'=>$targetClient,'results'=>$results],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
