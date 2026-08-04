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

function releaseMobileMenuMarkup(): string {
    return '<div class="mobile-menu-backdrop" data-mobile-menu-close aria-hidden="true"></div>'
        . '<aside class="mobile-menu" id="mobileMenu" aria-hidden="true" aria-label="قائمة التنقل">'
        . '<div class="mobile-menu-head"><strong>القائمة</strong><button class="mobile-menu-close" type="button" data-mobile-menu-close aria-label="إغلاق القائمة">×</button></div>'
        . '<nav class="mobile-menu-links" aria-label="التنقل على الموبايل"><a href="/">الرئيسية</a>' . navHtml() . '</nav>'
        . '<div class="mobile-menu-actions"><a class="btn btn-primary" href="/#form">ابدأ التقييم</a><a class="btn btn-ghost track-call" href="tel:' . PHONE_E164 . '">' . icon('phone') . '<span>' . phoneHtml() . '</span></a></div>'
        . '</aside>';
}

function releaseHeadMarkup(): string {
    $verification = GOOGLE_SITE_VERIFICATION !== ''
        ? '<meta name="google-site-verification" content="' . e(GOOGLE_SITE_VERIFICATION) . '">'
        : '';
    $brandIcons = '<link rel="icon" type="image/webp" href="/assets/logo-360.webp?v=1">'
        . '<link rel="shortcut icon" type="image/webp" href="/assets/logo-360.webp?v=1">'
        . '<link rel="apple-touch-icon" href="/assets/logo-360.webp?v=1">';
    $mobileMenuCss = '<style>'
        . '.mobile-menu-toggle,.mobile-menu,.mobile-menu-backdrop{display:none}'
        . '@media(max-width:1050px){'
        . '.nav{position:relative;gap:12px}.mobile-menu-toggle{display:inline-flex;width:44px;height:44px;flex:0 0 44px;align-items:center;justify-content:center;flex-direction:column;gap:5px;border:1px solid #d5e0ec;border-radius:12px;background:#fff;color:#071434;cursor:pointer;padding:0;box-shadow:0 5px 16px rgba(7,20,52,.08)}'
        . '.mobile-menu-toggle span{display:block;width:22px;height:2px;border-radius:4px;background:currentColor;transition:transform .2s ease,opacity .2s ease}.mobile-menu-toggle[aria-expanded="true"] span:nth-child(1){transform:translateY(7px) rotate(45deg)}.mobile-menu-toggle[aria-expanded="true"] span:nth-child(2){opacity:0}.mobile-menu-toggle[aria-expanded="true"] span:nth-child(3){transform:translateY(-7px) rotate(-45deg)}'
        . '.mobile-menu-backdrop{display:block;position:fixed;inset:0;z-index:90;background:rgba(2,10,28,.58);opacity:0;visibility:hidden;pointer-events:none;transition:opacity .22s ease,visibility .22s ease;backdrop-filter:blur(2px)}'
        . '.mobile-menu{display:flex;position:fixed;top:0;right:0;z-index:91;width:min(360px,88vw);height:100vh;height:100dvh;flex-direction:column;background:#fff;color:#12213a;box-shadow:-18px 0 50px rgba(2,10,28,.24);transform:translateX(105%);visibility:hidden;transition:transform .25s ease,visibility .25s ease;padding:max(18px,env(safe-area-inset-top)) 20px max(22px,env(safe-area-inset-bottom));overflow-y:auto}'
        . 'body.mobile-menu-open{overflow:hidden}body.mobile-menu-open .mobile-menu{transform:translateX(0);visibility:visible}body.mobile-menu-open .mobile-menu-backdrop{opacity:1;visibility:visible;pointer-events:auto}'
        . '.mobile-menu-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-bottom:14px;border-bottom:1px solid #dce6f2}.mobile-menu-head strong{font-size:21px;color:#071434}.mobile-menu-close{width:42px;height:42px;border:1px solid #d5e0ec;border-radius:11px;background:#f5f8fc;color:#071434;font-size:30px;line-height:1;cursor:pointer}'
        . '.mobile-menu-links{display:grid;margin-top:10px}.mobile-menu-links a{display:flex;align-items:center;min-height:52px;padding:11px 4px;border-bottom:1px solid #e6edf5;color:#142b4d;font-size:15px;font-weight:800}.mobile-menu-links a:hover,.mobile-menu-links a:focus-visible{color:#1d4a90;padding-right:9px}'
        . '.mobile-menu-actions{display:grid;gap:10px;margin-top:auto;padding-top:22px}.mobile-menu-actions .btn{width:100%}'
        . '}'
        . '@media(max-width:780px){.nav-actions .btn-primary{padding:10px 12px;font-size:12px;white-space:nowrap}.brand img{width:150px}}'
        . '@media(max-width:390px){.brand img{width:135px}.nav{gap:8px}.mobile-menu-toggle{width:42px;height:42px;flex-basis:42px}.nav-actions .btn-primary{padding:9px 10px;font-size:11.5px}}'
        . '@media(prefers-reduced-motion:reduce){.mobile-menu,.mobile-menu-backdrop,.mobile-menu-toggle span{transition:none!important}}'
        . '</style>';
    $mobileMenuJs = '<script>document.addEventListener("DOMContentLoaded",function(){var t=document.querySelector(".mobile-menu-toggle"),m=document.getElementById("mobileMenu"),b=document.querySelector(".mobile-menu-backdrop");if(!t||!m||!b)return;var c=m.querySelector(".mobile-menu-close"),last=null;function setOpen(open,restore){document.body.classList.toggle("mobile-menu-open",open);t.setAttribute("aria-expanded",open?"true":"false");t.setAttribute("aria-label",open?"إغلاق القائمة":"فتح القائمة");m.setAttribute("aria-hidden",open?"false":"true");b.setAttribute("aria-hidden",open?"false":"true");if(open){last=document.activeElement;window.setTimeout(function(){if(c)c.focus()},20)}else if(restore!==false&&last&&typeof last.focus==="function"){last.focus()}}t.addEventListener("click",function(){setOpen(!document.body.classList.contains("mobile-menu-open"))});document.querySelectorAll("[data-mobile-menu-close]").forEach(function(el){el.addEventListener("click",function(){setOpen(false)})});m.addEventListener("click",function(e){if(e.target.closest("a"))setOpen(false,false)});document.addEventListener("keydown",function(e){if(e.key==="Escape"&&document.body.classList.contains("mobile-menu-open"))setOpen(false)});var mq=window.matchMedia("(min-width:1051px)");function desktopClose(e){if(e.matches)setOpen(false,false)}if(mq.addEventListener)mq.addEventListener("change",desktopClose);else if(mq.addListener)mq.addListener(desktopClose)});</script>';
    $gtmId = json_encode(GTM_PUBLIC_ID, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $gtm = '<script>window.dataLayer=window.dataLayer||[];(function(w,d,i){var loaded=false;function loadGtm(){if(loaded)return;loaded=true;w.dataLayer.push({"gtm.start":Date.now(),event:"gtm.js"});var f=d.getElementsByTagName("script")[0],j=d.createElement("script");j.async=true;j.src="https://www.googletagmanager.com/gtm.js?id="+encodeURIComponent(i);f.parentNode.insertBefore(j,f)}w.__loadArkanGtm=loadGtm;["pointerdown","keydown","touchstart","scroll"].forEach(function(n){w.addEventListener(n,loadGtm,{once:true,passive:true})});w.setTimeout(loadGtm,12000)})(window,document,' . $gtmId . ');</script>';
    return $verification . $brandIcons . $mobileMenuCss . $mobileMenuJs . $gtm;
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
    $menuButton = '<button class="mobile-menu-toggle" type="button" aria-expanded="false" aria-controls="mobileMenu" aria-label="فتح القائمة"><span></span><span></span><span></span></button>';
    $html = str_replace('<header class="header"><div class="container nav">', '<header class="header"><div class="container nav">' . $menuButton, $html);
    $html = preg_replace('/<\/header>/', releaseMobileMenuMarkup() . '</header>', $html, 1) ?: $html;
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
