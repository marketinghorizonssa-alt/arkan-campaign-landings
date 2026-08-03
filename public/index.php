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
    $brandIcons = '<link rel="icon" type="image/webp" href="/assets/logo-360.webp?v=1">'
        . '<link rel="shortcut icon" type="image/webp" href="/assets/logo-360.webp?v=1">'
        . '<link rel="apple-touch-icon" href="/assets/logo-360.webp?v=1">';
    $gtmId = json_encode(GTM_PUBLIC_ID, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $gtm = '<script>window.dataLayer=window.dataLayer||[];(function(w,d,i){var loaded=false;function loadGtm(){if(loaded)return;loaded=true;w.dataLayer.push({"gtm.start":Date.now(),event:"gtm.js"});var f=d.getElementsByTagName("script")[0],j=d.createElement("script");j.async=true;j.src="https://www.googletagmanager.com/gtm.js?id="+encodeURIComponent(i);f.parentNode.insertBefore(j,f)}w.__loadArkanGtm=loadGtm;["pointerdown","keydown","touchstart","scroll"].forEach(function(n){w.addEventListener(n,loadGtm,{once:true,passive:true})});w.setTimeout(loadGtm,12000)})(window,document,' . $gtmId . ');</script>';
    return $verification . $brandIcons . $gtm;
}

function releaseBodyMarkup(): string {
    return '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . e(GTM_PUBLIC_ID) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
}

function renderHtml(string $html): string {
    foreach (['4','5','6','7','8'] as $version) {
        $html = str_replace('/assets/site.js?v=' . $version, '/assets/site.js?v=9', $html);
    }
    $html = str_replace('/assets/logo.webp', '/assets/logo-360.webp', $html);
    $html = preg_replace_callback(
        '/<section class="hero" style="--hero-image:url\(([^)]+)\)">/',
        static function (array $matches): string {
            $hero = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return '<section class="hero">' . heroPictureHtml($hero);
        },
        $html
    ) ?: $html;
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
