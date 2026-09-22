<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

$base=__DIR__.'/data';
$secure=dirname(__DIR__,4).'/.marketing/ycloud';
$connectionsFile=$base.'/ycloud_connections.json';
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

    $contacts=req($key,'GET','/v2/contact/contacts?limit=100&includeTotal=true');
    $entry['contacts_status']=$contacts['status'];
    $counts=[];$growth=0;$examples=[];
    foreach(list_items($contacts['json']) as $ct){
        if(!is_array($ct))continue;
        $st=strtoupper((string)($ct['sourceType']??'UNKNOWN'));
        $counts[$st]=($counts[$st]??0)+1;
        if($st==='GROWTH_TOOL'){
            $growth++;
            if(count($examples)<5)$examples[]=[
                'sourceId'=>(string)($ct['sourceId']??''),
                'sourceUrl'=>(string)($ct['sourceUrl']??''),
                'lastConnectedNumber'=>(string)($ct['lastConnectedNumber']??'')
            ];
        }
    }
    ksort($counts);
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
