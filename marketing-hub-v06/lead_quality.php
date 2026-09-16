<?php
declare(strict_types=1);

$bootDir = __DIR__;
$secureDir = dirname(__DIR__, 4) . '/.marketing';
$marker = $secureDir . '/lead_pool_bootstrap_v1';
if (!is_dir($secureDir)) @mkdir($secureDir, 0700, true);

function lq_boot_fetch(string $url, string $dest, string $kind): bool {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>8, CURLOPT_TIMEOUT=>20, CURLOPT_FOLLOWLOCATION=>true]);
    $body = curl_exec($ch); $errno = curl_errno($ch); $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    if ($errno !== 0 || $status < 200 || $status >= 300 || !is_string($body) || $body === '') return false;
    if ($kind === 'php' && !str_starts_with(ltrim($body), '<?php')) return false;
    if ($kind === 'json' && !is_array(json_decode($body, true))) return false;
    $tmp = $dest . '.tmp.' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $body, LOCK_EX) === false) return false;
    @chmod($tmp, 0600);
    if (!@rename($tmp, $dest)) { @unlink($tmp); return false; }
    return true;
}

$core = $bootDir . '/lead_quality_core.php';
$needsBootstrap = !is_file($marker) || !is_file($core) || !is_file($bootDir.'/lead_pool_worker.php') || !is_file($bootDir.'/lead_pool_config.json');
if ($needsBootstrap) {
    $rawBase = 'https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/marketing-deploy/marketing-hub-v06';
    $coreUrl = 'https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/6b4f07bb1765128aac513d130624201f4cae9cef/marketing-hub-v06/lead_quality.php';
    $files = [
        [$coreUrl, $core, 'php'],
        [$rawBase.'/local_quality_v2.php', $bootDir.'/local_quality_v2.php', 'php'],
        [$rawBase.'/lead_pool_worker.php', $bootDir.'/lead_pool_worker.php', 'php'],
        [$rawBase.'/flush_outbox.php', $bootDir.'/flush_outbox.php', 'php'],
        [$rawBase.'/lead_pool_config.json', $bootDir.'/lead_pool_config.json', 'json'],
        [$rawBase.'/platform_stage_config.json', $bootDir.'/platform_stage_config.json', 'json'],
        [$rawBase.'/quality_rules_v2.json', $bootDir.'/quality_rules_v2.json', 'json']
    ];
    $ok = true;
    foreach ($files as [$url,$dest,$kind]) if (!lq_boot_fetch($url,$dest,$kind)) $ok = false;
    if ($ok) { @file_put_contents($marker, gmdate('c')."\n", LOCK_EX); @chmod($marker,0600); }
}

if (!is_file($core)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'lead_quality_core_unavailable']);
    exit;
}
require $core;
