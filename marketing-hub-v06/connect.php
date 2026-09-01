<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow', true);
header("Content-Security-Policy: default-src 'self'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$secureDir = dirname(__DIR__, 4) . '/.marketing';
$tokenFile = $secureDir . '/setup_token';
$keyFile = $secureDir . '/ycloud_api_key';
if (!is_dir($secureDir)) @mkdir($secureDir, 0700, true);

function esc(string $v): string { return htmlspecialchars($v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function api_call(string $apiKey, string $method, string $path, ?array $payload=null): array {
    $ch = curl_init('https://api.ycloud.com' . $path);
    $headers = ['X-API-Key: ' . $apiKey, 'Accept: application/json'];
    if ($payload !== null) $headers[] = 'Content-Type: application/json';
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $json = is_string($body) ? json_decode($body, true) : null;
    return ['ok'=>$errno===0 && $status>=200 && $status<300, 'status'=>$status, 'json'=>is_array($json)?$json:null, 'error'=>$errno?$err:null];
}
function page(string $title, string $body): never {
    echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc($title).'</title><style>body{margin:0;background:#0b1220;color:#eef3ff;font-family:Arial,sans-serif}.wrap{max-width:620px;margin:7vh auto;padding:24px}.card{background:#151f34;border:1px solid #293652;border-radius:18px;padding:26px}h1{margin-top:0}p{color:#aebbd4;line-height:1.6}input{width:100%;box-sizing:border-box;padding:14px;border-radius:10px;border:1px solid #39496b;background:#0c1528;color:#fff;font-size:16px}button{margin-top:14px;padding:13px 18px;border:0;border-radius:10px;background:#765cff;color:#fff;font-weight:700;cursor:pointer}.ok{color:#54e5a5}.err{color:#ff8d8d}.muted{font-size:13px;color:#8594b2}</style></head><body><div class="wrap"><div class="card">'.$body.'</div></div></body></html>';
    exit;
}

$token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
$storedToken = is_file($tokenFile) ? trim((string)@file_get_contents($tokenFile)) : '';
if ($storedToken === '' || $token === '' || !hash_equals($storedToken, $token)) {
    http_response_code(403);
    page('Setup link expired', '<h1>Setup link expired</h1><p>This one-time setup link is invalid or has already been used.</p>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiKey = trim((string)($_POST['api_key'] ?? ''));
    if ($apiKey === '') page('YCloud Connection', '<h1>YCloud Connection</h1><p class="err">API key is required.</p><a href="?token='.esc($token).'" style="color:#9eaaff">Try again</a>');

    $verify = api_call($apiKey, 'GET', '/v2/balance');
    if (!$verify['ok']) {
        page('YCloud Connection', '<h1>YCloud Connection</h1><p class="err">YCloud rejected this API key (HTTP '.(int)$verify['status'].'). Nothing was saved.</p><a href="?token='.esc($token).'" style="color:#9eaaff">Try again</a>');
    }

    if (@file_put_contents($keyFile, $apiKey . "\n", LOCK_EX) === false) {
        page('YCloud Connection', '<h1>YCloud Connection</h1><p class="err">Could not save the key securely on the server.</p>');
    }
    @chmod($keyFile, 0600);

    $defs = [
        ['name'=>'interested','label'=>'Interested','description'=>'Marketing conversation marked Interested'],
        ['name'=>'qualified','label'=>'Qualified','description'=>'Marketing conversation marked Qualified'],
        ['name'=>'purchased','label'=>'Purchased','description'=>'Marketing conversation marked Purchased'],
    ];
    $errors = [];
    foreach ($defs as $def) {
        $check = api_call($apiKey, 'GET', '/v2/event/definitions/' . rawurlencode($def['name']));
        if ($check['status'] === 200) continue;
        if ($check['status'] !== 404) { $errors[] = $def['name'] . ': lookup HTTP ' . $check['status']; continue; }
        $create = api_call($apiKey, 'POST', '/v2/event/definitions', [
            'name' => $def['name'],
            'label' => $def['label'],
            'description' => $def['description'],
            'objectType' => 'CONTACT',
            'properties' => [],
        ]);
        if (!$create['ok']) $errors[] = $def['name'] . ': create HTTP ' . $create['status'];
    }

    if ($errors) {
        page('YCloud Connected', '<h1>YCloud key connected</h1><p class="ok">The key is valid and stored outside the website root.</p><p class="err">Some event definitions still need a retry: '.esc(implode(', ', $errors)).'</p><p class="muted">The one-time link remains valid so you can retry safely.</p>');
    }

    @unlink($tokenFile);
    page('YCloud Connected', '<h1>Connected</h1><p class="ok">YCloud is connected successfully.</p><p>Custom events <b>Interested</b>, <b>Qualified</b>, and <b>Purchased</b> are ready. Future tag changes will be sent to YCloud in real time.</p><p class="muted">The setup link has now expired automatically.</p>');
}

page('Connect YCloud', '<h1>Connect YCloud</h1><p>Paste the YCloud API key here once. It will be verified with YCloud, stored outside the public website directory, and never written to GitHub.</p><form method="post" autocomplete="off"><input type="hidden" name="token" value="'.esc($token).'"><input type="password" name="api_key" placeholder="YCloud API Key" autocomplete="new-password" required><button type="submit">Verify & Connect</button></form><p class="muted">The key is sent only from your browser to marketing.hositee.com over HTTPS.</p>');
