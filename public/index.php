<?php
declare(strict_types=1);
require __DIR__ . '/app/config.php';
require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/views.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if (REVIEW_MODE) header('X-Robots-Tag: noindex, nofollow, noarchive');

$path = requestedPath();
if (isset($legacy[$path])) sendRedirect($legacy[$path]);
if ($path !== '/' && !str_ends_with($path, '/') && !in_array($path, ['/robots.txt','/sitemap.xml','/health'], true)) sendRedirect($path . '/');

if ($path === '/robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    echo REVIEW_MODE ? "User-agent: *\nDisallow: /\n" : "User-agent: *\nAllow: /\nSitemap: " . ORIGIN . "/sitemap.xml\n";
    exit;
}
if ($path === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=utf-8');
    $paths = array_keys($pages); $paths[] = '/سياسة-الخصوصية/';
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($paths as $urlPath) echo '<url><loc>' . e(canonical($urlPath)) . '</loc></url>';
    echo '</urlset>'; exit;
}
if ($path === '/health') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>true,'mode'=>REVIEW_MODE?'review':'production','brand'=>'arkan-executive'], JSON_UNESCAPED_UNICODE); exit;
}
if ($path === '/سياسة-الخصوصية/') { echo privacyHtml(); exit; }
if ($path === '/تم-استلام-الطلب/') { echo thankYouHtml(); exit; }
if (isset($pages[$path])) { echo pageHtml($path, $pages[$path], $leadEndpoint); exit; }

http_response_code(404);
echo '<!doctype html><html lang="ar" dir="rtl">' . headHtml('الصفحة غير موجودة | أركان التنفيذية','الصفحة المطلوبة غير موجودة.','/') . '<body>' . headerHtml() . '<main class="error-page"><div><h1>الصفحة غير موجودة</h1><p>يمكنك العودة للرئيسية أو التواصل مباشرة.</p><a class="btn btn-primary" href="/">العودة للرئيسية</a></div></main>' . footerHtml() . '</body></html>';
