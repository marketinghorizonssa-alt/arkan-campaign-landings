<?php
declare(strict_types=1);

$secure = dirname(__DIR__, 4) . '/.marketing/tiktok/crm/7686238143054970888';
$keyFile = $secure . '/setup_key';
$tokenFile = $secure . '/access_token';
$key = isset($_GET['k']) && is_scalar($_GET['k']) ? trim((string)$_GET['k']) : '';
$expected = is_file($keyFile) ? trim((string)@file_get_contents($keyFile)) : '';

if ($expected === '' || $key === '' || !hash_equals($expected, $key)) {
    http_response_code(404);
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header('Referrer-Policy: no-referrer');

$error = '';
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['access_token']) && is_scalar($_POST['access_token']) ? trim((string)$_POST['access_token']) : '';
    if (strlen($token) < 20 || strlen($token) > 4096 || preg_match('/\s/', $token)) {
        $error = 'Access token format looks invalid.';
    } else {
        if (!is_dir($secure) && !@mkdir($secure, 0700, true) && !is_dir($secure)) {
            $error = 'Secure directory could not be created.';
        } elseif (@file_put_contents($tokenFile, $token, LOCK_EX) === false) {
            $error = 'Token could not be saved.';
        } else {
            @chmod($tokenFile, 0600);
            @unlink($keyFile);
            $done = true;
        }
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TikTok CRM Connect</title>
<style>body{font-family:system-ui,-apple-system,sans-serif;background:#f5f5f7;color:#111;margin:0;padding:32px}.box{max-width:640px;margin:8vh auto;background:#fff;padding:28px;border-radius:18px;box-shadow:0 8px 30px #0001}h1{margin-top:0}label{display:block;font-weight:700;margin:18px 0 8px}input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #bbb;border-radius:10px;font:inherit}button{margin-top:16px;padding:12px 18px;border:0;border-radius:10px;background:#111;color:#fff;font-weight:700;cursor:pointer}.muted{color:#666;font-size:14px}.err{color:#a00}.ok{color:#075;font-weight:700}</style></head><body><div class="box">
<?php if($done): ?>
<h1>Connected</h1><p class="ok">TikTok CRM Events API token saved securely.</p><p>This one-time connector is now closed.</p>
<?php else: ?>
<h1>Arkan TikTok CRM</h1><p><strong>Event Set:</strong> Arkan Web WhatsApp Quality CRM</p><p><strong>ID:</strong> 7686238143054970888</p><p class="muted">Paste the Events API access token generated for this CRM Event Set. It is stored outside the public website and this page disables itself immediately after saving.</p>
<?php if($error!==''): ?><p class="err"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></p><?php endif; ?>
<form method="post" autocomplete="off"><label for="access_token">Events API access token</label><input id="access_token" name="access_token" type="password" required autocomplete="new-password"><button type="submit">Connect CRM Event Set</button></form>
<?php endif; ?>
</div></body></html>
