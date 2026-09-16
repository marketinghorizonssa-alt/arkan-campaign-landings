<?php
declare(strict_types=1);

$keyFile = '/home/u878466595/.horizons-google-mcp/setup.key';
$configFile = '/home/u878466595/.horizons-google-mcp/config.json';
$expected = is_file($keyFile) ? trim((string)file_get_contents($keyFile)) : '';
$given = (string)($_GET['key'] ?? $_POST['key'] ?? '');

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

if ($expected === '' || !hash_equals($expected, $given)) {
    http_response_code(404);
    exit('Not found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = trim((string)($_POST['client_id'] ?? ''));
    $clientSecret = trim((string)($_POST['client_secret'] ?? ''));
    if ($clientId === '' || $clientSecret === '') {
        http_response_code(400);
        exit('Missing credentials');
    }
    $config = [
        'googleClientId' => $clientId,
        'googleClientSecret' => $clientSecret,
        'googleManagerCustomerId' => '8480913009',
        'googleAdsApiVersion' => 'v25',
        'googleAdsDeveloperToken' => '',
        'googleScopes' => [
            'openid','email','profile',
            'https://www.googleapis.com/auth/adwords',
            'https://www.googleapis.com/auth/analytics.readonly',
            'https://www.googleapis.com/auth/analytics.edit',
            'https://www.googleapis.com/auth/tagmanager.readonly',
            'https://www.googleapis.com/auth/tagmanager.edit.containers',
            'https://www.googleapis.com/auth/tagmanager.edit.containerversions',
            'https://www.googleapis.com/auth/tagmanager.publish',
            'https://www.googleapis.com/auth/webmasters',
            'https://www.googleapis.com/auth/siteverification',
            'https://www.googleapis.com/auth/content',
            'https://www.googleapis.com/auth/business.manage'
        ]
    ];
    if (!is_dir(dirname($configFile))) mkdir(dirname($configFile), 0700, true);
    file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_SLASHES), LOCK_EX);
    chmod($configFile, 0600);
    @unlink($keyFile);
    echo 'CONFIG_OK';
    @unlink(__FILE__);
    exit;
}
?><!doctype html><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>HORIZONS MCP Setup</title><style>body{font-family:system-ui;max-width:720px;margin:60px auto;padding:0 20px}input{display:block;width:100%;padding:12px;margin:8px 0 18px;box-sizing:border-box}button{padding:12px 18px}</style><h1>HORIZONS Google Marketing MCP</h1><p>One-time secure OAuth client setup.</p><form method="post"><input type="hidden" name="key" value="<?=htmlspecialchars($given, ENT_QUOTES)?>"><label>Client ID</label><input name="client_id" autocomplete="off" required><label>Client Secret</label><input name="client_secret" type="password" autocomplete="new-password" required><button type="submit">Save securely</button></form>