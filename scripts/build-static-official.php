<?php
declare(strict_types=1);

$sourceOrigin = 'https://arkan-realestate-solutions.hositee.com';
$targetOrigin = 'https://arkan2030.com';
$backendEndpoint = $sourceOrigin . '/api/lead';
$root = '/home/u878466595/domains/hositee.com/public_html/arkan-realestate-solutions';
$out = $root . '/.arkan2030-static';
$zipPath = $root . '/arkan2030-static.zip';

$pages = [
    '/' => 'index.html',
    '/حلول-التمويل-العقاري/' => 'حلول-التمويل-العقاري/index.html',
    '/رفض-التمويل-العقاري/' => 'رفض-التمويل-العقاري/index.html',
    '/تمويل-عقاري-مع-التزامات/' => 'تمويل-عقاري-مع-التزامات/index.html',
    '/شراء-مديونية-عقارية/' => 'شراء-مديونية-عقارية/index.html',
    '/شراء-عقار-بالتمويل/' => 'شراء-عقار-بالتمويل/index.html',
    '/سياسة-الخصوصية/' => 'سياسة-الخصوصية/index.html',
    '/تم-استلام-الطلب/' => 'تم-استلام-الطلب/index.html',
];

function deleteTree(string $path): void {
    if (!is_dir($path)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

function encodedUrl(string $origin, string $path): string {
    if ($path === '/') return $origin . '/';
    $parts = array_values(array_filter(explode('/', trim($path, '/')), static fn(string $part): bool => $part !== ''));
    return $origin . '/' . implode('/', array_map('rawurlencode', $parts)) . '/';
}

function fetchUrl(string $url): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => ['Cache-Control: no-cache'],
        CURLOPT_USERAGENT => 'ARKAN-Official-Static-Exporter/1.0',
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if (!is_string($body) || $status !== 200) {
        throw new RuntimeException("Fetch failed: {$url} status={$status} error={$error}");
    }
    return $body;
}

function writeFileSafe(string $path, string $content): void {
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException("Cannot create directory: {$dir}");
    }
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException("Cannot write file: {$path}");
    }
}

function copyTree(string $source, string $target): void {
    if (!is_dir($source)) throw new RuntimeException("Missing source directory: {$source}");
    if (!is_dir($target)) mkdir($target, 0755, true);
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($items as $item) {
        $dest = $target . '/' . $items->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($dest)) mkdir($dest, 0755, true);
        } else {
            copy($item->getPathname(), $dest);
        }
    }
}

function zipTree(string $source, string $zipPath): void {
    if (is_file($zipPath)) unlink($zipPath);
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Cannot create zip archive');
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($items as $item) {
        if (!$item->isFile()) continue;
        $local = str_replace('\\', '/', substr($item->getPathname(), strlen($source) + 1));
        $zip->addFile($item->getPathname(), $local);
    }
    $zip->close();
}

deleteTree($out);
mkdir($out, 0755, true);

foreach ($pages as $path => $relative) {
    $html = fetchUrl(encodedUrl($sourceOrigin, $path));
    $html = str_replace($sourceOrigin, $targetOrigin, $html);
    $html = str_replace('"endpoint":"/api/lead"', '"endpoint":"' . $backendEndpoint . '"', $html);
    $html = str_replace("'endpoint':'/api/lead'", "'endpoint':'{$backendEndpoint}'", $html);
    writeFileSafe($out . '/' . $relative, $html);
}

copyTree($root . '/assets', $out . '/assets');

$robots = "User-agent: Googlebot\nAllow: /\n\nUser-agent: *\nAllow: /\n\nSitemap: {$targetOrigin}/sitemap.xml\n";
writeFileSafe($out . '/robots.txt', $robots);

$urls = [
    '/',
    '/حلول-التمويل-العقاري/',
    '/رفض-التمويل-العقاري/',
    '/تمويل-عقاري-مع-التزامات/',
    '/شراء-مديونية-عقارية/',
    '/شراء-عقار-بالتمويل/',
];
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $path) {
    $loc = htmlspecialchars(encodedUrl($targetOrigin, $path), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $priority = $path === '/' ? '1.0' : '0.8';
    $xml .= "  <url><loc>{$loc}</loc><changefreq>weekly</changefreq><priority>{$priority}</priority></url>\n";
}
$xml .= '</urlset>' . "\n";
writeFileSafe($out . '/sitemap.xml', $xml);

$htaccess = <<<'HTACCESS'
Options -Indexes
DirectoryIndex index.html

<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{HTTPS} !=on [OR]
RewriteCond %{HTTP_HOST} ^www\.arkan2030\.com$ [NC]
RewriteRule ^ https://arkan2030.com%{REQUEST_URI} [R=301,L]

RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
</IfModule>

<IfModule mod_headers.c>
Header always set X-Content-Type-Options "nosniff"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
<FilesMatch "\.(css|js|webp|webm|png|jpg|jpeg|svg)$">
Header set Cache-Control "public, max-age=2592000, immutable"
</FilesMatch>
<FilesMatch "\.(html|xml|txt)$">
Header set Cache-Control "public, max-age=300"
</FilesMatch>
</IfModule>

<IfModule mod_deflate.c>
AddOutputFilterByType DEFLATE text/html text/plain text/css application/javascript application/json application/xml
</IfModule>
HTACCESS;
writeFileSafe($out . '/.htaccess', $htaccess . "\n");

$notFound = '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>الصفحة غير موجودة | أركان التنفيذية</title></head><body><main style="min-height:80vh;display:grid;place-items:center;font-family:Arial;text-align:center"><div><h1>الصفحة غير موجودة</h1><p><a href="/">العودة للرئيسية</a></p></div></main></body></html>';
writeFileSafe($out . '/404.html', $notFound);

zipTree($out, $zipPath);
chmod($zipPath, 0644);

echo json_encode([
    'ok' => true,
    'zip' => $zipPath,
    'bytes' => filesize($zipPath),
    'pages' => count($pages),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
