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
header('X-Robots-Tag: index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1');

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
    $html = str_replace('/assets/site.js?v=4', '/assets/site.js?v=7', $html);
    $html = str_replace('/assets/site.js?v=5', '/assets/site.js?v=7', $html);
    $html = str_replace('/assets/site.js?v=6', '/assets/site.js?v=7', $html);
    $html = str_replace('</head>', releaseHeadMarkup() . '</head>', $html);
    return preg_replace('/<body([^>]*)>/', '<body$1>' . releaseBodyMarkup(), $html, 1) ?: $html;
}

$path = requestedPath();
if ($path === '/api/lead') handleLeadSubmit();
if ($path === '/lead-feed.csv') handleLeadFeed();
if (isset($legacy[$path])) sendRedirect($legacy[$path]);
if ($path !== '/' && !str_ends_with($path, '/') && $path !== '/health') sendRedirect($path . '/');

if ($path === '/health') {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    echo json_encode(['ok'=>true,'mode'=>'production','brand'=>'arkan-executive','lead_store'=>'sqlite','gtm'=>GTM_PUBLIC_ID,'seo'=>'static-production'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($path === '/سياسة-الخصوصية/') {
    header('Link: <' . canonical($path) . '>; rel="canonical"');
    echo renderHtml(privacyHtml());
    exit;
}
if ($path === '/تم-استلام-الطلب/') {
    header('X-Robots-Tag: noindex, nofollow');
    echo renderHtml(thankYouHtml());
    exit;
}
if (isset($pages[$path])) {
    header('Link: <' . canonical($path) . '>; rel="canonical"');
    echo renderHtml(pageHtml($path, $pages[$path], $leadEndpoint));
    exit;
}

http_response_code(404);
header('X-Robots-Tag: noindex, nofollow');
echo renderHtml('<!doctype html><html lang="ar-SA" dir="rtl">' . headHtml('الصفحة غير موجودة | أركان التنفيذية','الصفحة غير موجودة.','/') . '<body>' . headerHtml() . '<main class="error-page"><div><h1>الصفحة غير موجودة</h1><a class="btn btn-primary" href="/">العودة للرئيسية</a></div></main>' . footerHtml() . '</body></html>');
