<?php
declare(strict_types=1);

// Contact clicks are anonymous interactions, separate from submitted leads.
function clickFields(): array {
    return ['event_id','clicked_at','event_type','placement','landing_page_id','landing_path','tracking_token','utm_source','utm_medium','utm_campaign','utm_term','gclid','gbraid','wbraid','campaign_id','ad_group_id','ad_id','keyword'];
}
function clickDb(): PDO {
    $dir = dirname(LEAD_DB_PATH);
    if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new RuntimeException('storage_unavailable');
    $db = new PDO('sqlite:'.$dir.'/arkan-clicks.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA busy_timeout=5000;');
    $columns = array_map(static fn($f)=>'"'.$f.'" TEXT NOT NULL DEFAULT \'\'',clickFields());
    $db->exec('CREATE TABLE IF NOT EXISTS contact_clicks ('.implode(',',$columns).',PRIMARY KEY(event_id))');
    return $db;
}
function handleClickSubmit(): never {
    if (($_SERVER['REQUEST_METHOD']??'GET')==='OPTIONS') { http_response_code(204); exit; }
    if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') jsonResponse(['ok'=>false,'error'=>'method_not_allowed'],405);
    $data = readJsonBody();
    $id = cleanText($data['event_id']??'',100);
    $type = (string)($data['event_type']??'');
    $placement = (string)($data['placement']??'content');
    if (!preg_match('/^[A-Za-z0-9-]{8,100}$/',$id) || !in_array($type,['whatsapp','call'],true) || !in_array($placement,['hero','floating','header','content','after_form'],true)) jsonResponse(['ok'=>false,'error'=>'invalid_click'],422);
    $row = [];
    foreach (clickFields() as $f) $row[$f] = cleanText($data[$f]??'', $f==='landing_path'?500:255);
    $row['event_id']=$id;
    $row['event_type']=$type;
    $row['placement']=$placement;
    $row['clicked_at']=(new DateTimeImmutable('now',new DateTimeZone('Asia/Riyadh')))->format(DateTimeInterface::ATOM);
    $ref=$row['tracking_token'];
    if ($ref!==''&&!preg_match('/^ARK-AT-[A-Z0-9]{12,40}$/',$ref)) jsonResponse(['ok'=>false,'error'=>'invalid_tracking_token'],422);
    $db=clickDb();
    if ($ref!=='') {
        $limit=$db->prepare('SELECT COUNT(*) FROM contact_clicks WHERE tracking_token=? AND clicked_at>=?');
        $limit->execute([$ref,(new DateTimeImmutable('-1 day'))->format(DateTimeInterface::ATOM)]);
        if ((int)$limit->fetchColumn()>=100) jsonResponse(['ok'=>false,'error'=>'rate_limited'],429);
    }
    $fields=clickFields();
    $stmt=$db->prepare('INSERT OR IGNORE INTO contact_clicks ('.implode(',',$fields).') VALUES ('.implode(',',array_fill(0,count($fields),'?')).')');
    $stmt->execute(array_values($row));
    jsonResponse(['ok'=>true,'duplicate'=>$stmt->rowCount()===0]);
}
function handleClickFeed(): never {
    $stored=is_file(LEAD_FEED_TOKEN_PATH)?trim((string)file_get_contents(LEAD_FEED_TOKEN_PATH)):'';
    $provided=(string)($_GET['token']??'');
    if ($stored===''||!hash_equals($stored,$provided)) jsonResponse(['ok'=>false,'error'=>'unauthorized'],403);
    header('Content-Type: text/csv; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex,nofollow');
    $out=fopen('php://output','wb');
    $headers=['معرف النقرة','وقت النقرة','نوع التواصل','مكان الزر','صفحة الهبوط','مسار الصفحة','مرجع الزيارة','مصدر الحملة','الوسيط','الحملة','عبارة البحث','GCLID','GBRAID','WBRAID','Campaign ID','Ad Group ID','Ad ID','Keyword'];
    fputcsv($out,$headers);
    foreach (clickDb()->query('SELECT * FROM contact_clicks ORDER BY clicked_at DESC LIMIT 10000') as $row) {
        $values=array_map(static fn($f)=>(string)$row[$f],clickFields());
        // Never allow visitor-provided cells to become spreadsheet formulas.
        $values=array_map(static fn($v)=>preg_match('/^[=+@-]/',$v)?"'".$v:$v,$values);
        fputcsv($out,$values);
    }
    fclose($out);
    exit;
}
