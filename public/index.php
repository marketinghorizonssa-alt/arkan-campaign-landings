<?php
declare(strict_types=1);

require __DIR__ . '/app/config.php';
require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/leads.php';
require __DIR__ . '/app/views.php';

date_default_timezone_set('Asia/Riyadh');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if (REVIEW_MODE) header('X-Robots-Tag: noindex, nofollow, noarchive');

function releaseHeadMarkup(): string {
    $verification = GOOGLE_SITE_VERIFICATION !== ''
        ? '<meta name="google-site-verification" content="' . e(GOOGLE_SITE_VERIFICATION) . '">'
        : '';
    $brandIcons = '<link rel="icon" type="image/webp" href="/assets/logo.webp?v=1">'
        . '<link rel="shortcut icon" type="image/webp" href="/assets/logo.webp?v=1">'
        . '<link rel="apple-touch-icon" href="/assets/logo.webp?v=1">';
    $gtm = '<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({\'gtm.start\':new Date().getTime(),event:\'gtm.js\'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!==\'dataLayer\'?\'&l=\'+l:\'\';j.async=true;j.src=\'https://www.googletagmanager.com/gtm.js?id=\'+i+dl;f.parentNode.insertBefore(j,f);})(window,document,\'script\',\'dataLayer\',\'' . e(GTM_PUBLIC_ID) . '\');</script>';
    return $verification . $brandIcons . $gtm;
}

function releaseBodyMarkup(): string {
    return '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . e(GTM_PUBLIC_ID) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
}

function renderHtml(string $html): string {
    $html = str_replace('/assets/site.js?v=4', '/assets/site.js?v=5', $html);
    $html = str_replace('</head>', releaseHeadMarkup() . '</head>', $html);
    return preg_replace('/<body([^>]*)>/', '<body$1>' . releaseBodyMarkup(), $html, 1) ?: $html;
}

function sitemapLocation(string $urlPath): string {
    if ($urlPath === '/') return ORIGIN . '/';
    $segments = array_values(array_filter(explode('/', trim($urlPath, '/')), static fn(string $segment): bool => $segment !== ''));
    return ORIGIN . '/' . implode('/', array_map('rawurlencode', $segments)) . '/';
}

$path = requestedPath();
if ($path === '/api/lead') handleLeadSubmit();
if ($path === '/lead-feed.csv') handleLeadFeed();
if (isset($legacy[$path])) sendRedirect($legacy[$path]);
if ($path !== '/' && !str_ends_with($path, '/') && !in_array($path, ['/robots.txt','/sitemap.xml','/health'], true)) sendRedirect($path . '/');

if ($path === '/robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo REVIEW_MODE ? "User-agent: *\nDisallow: /\n" : "User-agent: *\nAllow: /\nSitemap: " . ORIGIN . "/sitemap.xml\n";
    exit;
}
if ($path === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $paths = array_keys($pages);
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    foreach ($paths as $urlPath) {
        echo '  <url><loc>' . e(sitemapLocation($urlPath)) . "</loc></url>\n";
    }
    echo "</urlset>\n";
    exit;
}
if ($path === '/health') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>true,'mode'=>REVIEW_MODE?'review':'production','brand'=>'arkan-executive','lead_store'=>'sqlite','gtm'=>GTM_PUBLIC_ID], JSON_UNESCAPED_UNICODE); exit;
}
if ($path === '/سياسة-الخصوصية/') { echo renderHtml(privacyHtml()); exit; }
if ($path === '/تم-استلام-الطلب/') { echo renderHtml(thankYouHtml()); exit; }
if (isset($pages[$path])) { echo renderHtml(pageHtml($path, $pages[$path], $leadEndpoint)); exit; }

http_response_code(404);
echo renderHtml('<!doctype html><html lang="ar" dir="rtl">' . headHtml('الصفحة غير موجودة | أركان التنفيذية','الصفحة غير موجودة.','/') . '<body>' . headerHtml() . '<main class="error-page"><div><h1>الصفحة غير موجودة</h1><a class="btn btn-primary" href="/">العودة للرئيسية</a></div></main>' . footerHtml() . '</body></html>');
