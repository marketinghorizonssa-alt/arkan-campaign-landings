<?php
declare(strict_types=1);

function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function requestedPath(): string { return rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'); }
function canonical(string $path): string { return ORIGIN . $path; }
function phoneHtml(): string { return '<bdi class="phone-number" dir="ltr">' . e(PHONE_DISPLAY) . '</bdi>'; }
function sendRedirect(string $target, int $status = 301): never {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . $target . ($query !== '' ? '?' . $query : ''), true, $status);
    exit;
}
function icon(string $name): string {
    $icons = [
        'check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>',
        'chart' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V9m6 10V5m6 14v-7m4 7H2"/></svg>',
        'home' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 11 9-8 9 8v9H6v-9m4 9v-6h4v6"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 5 3 8 7 10 4-2 7-5 7-10V6l-7-3Zm-3 9 2 2 4-5"/></svg>',
        'phone' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h3l2 5-2 2c1 3 3 5 6 6l2-2 5 2v3c0 1-1 2-2 2C11 21 3 13 3 5c0-1 1-2 3-2Z"/></svg>',
        'whatsapp' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11.5a8 8 0 0 1-11.8 7L4 20l1.4-4.1A8 8 0 1 1 20 11.5Z"/><path d="M9 8c.5 3 2 4.5 5 5l1-1.5 2 .8c-.3 2-1.5 3-3.5 2.7-3.8-.6-6-2.8-6.6-6.6C6.6 6.5 7.7 5.3 9.7 5l.8 2L9 8Z"/></svg>',
        'wallet' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h15v13H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h13v3m2 5h3v4h-3a2 2 0 1 1 0-4Z"/></svg>',
        'instagram' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.8" r="1" fill="currentColor" stroke="none"/></svg>',
        'x' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4 19 20M19 4 5 20"/></svg>',
        'tiktok' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4v10.2a4.2 4.2 0 1 1-3.2-4.1M14 4c.6 3 2.2 4.6 5 5"/></svg>',
    ];
    return $icons[$name] ?? $icons['check'];
}
function logoHtml(string $class = ''): string {
    $classAttr = $class !== '' ? ' class="' . e($class) . '"' : '';
    return '<picture><source srcset="/assets/logo.webp" type="image/webp"><img' . $classAttr . ' src="/assets/logo.jpg" alt="شعار أركان التنفيذية" width="720" height="280"></picture>';
}
function headHtml(string $title, string $description, string $path, string $heroImage = '/assets/hero-home.jpg'): string {
    $robots = REVIEW_MODE ? 'noindex,nofollow,noarchive' : 'index,follow,max-image-preview:large';
    $url = canonical($path);
    $schema = [
        '@context' => 'https://schema.org', '@type' => 'ProfessionalService', 'name' => 'أركان التنفيذية',
        'url' => ORIGIN, 'telephone' => PHONE_E164,
        'description' => 'حلول مالية وعقارية متكاملة تساعد العملاء على فهم الأهلية والالتزامات واختيار مسار التملك المناسب.',
        'areaServed' => ['@type' => 'Country', 'name' => 'Saudi Arabia'],
        'sameAs' => ['https://www.instagram.com/arkanexecut/', 'https://x.com/arkanexecut', 'https://www.tiktok.com/@arkan.execut'],
    ];
    return '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">'
        . '<title>' . e($title) . '</title><meta name="description" content="' . e($description) . '">'
        . '<meta name="robots" content="' . e($robots) . '"><link rel="canonical" href="' . e($url) . '">'
        . '<meta property="og:locale" content="ar_SA"><meta property="og:type" content="website">'
        . '<meta property="og:title" content="' . e($title) . '"><meta property="og:description" content="' . e($description) . '">'
        . '<meta property="og:url" content="' . e($url) . '"><meta property="og:image" content="' . ORIGIN . e($heroImage) . '">'
        . '<meta name="twitter:card" content="summary_large_image"><meta name="theme-color" content="#071434">'
        . '<link rel="preload" href="' . e($heroImage) . '" as="image">'
        . '<link rel="preload" href="/assets/logo.webp" as="image" type="image/webp">'
        . '<link rel="stylesheet" href="/assets/site.css?v=5">'
        . '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script></head>';
}
function navHtml(): string {
    return '<a href="/حلول-التمويل-العقاري/">الحلول</a><a href="/رفض-التمويل-العقاري/">رفض التمويل</a><a href="/تمويل-عقاري-مع-التزامات/">الالتزامات</a><a href="/شراء-مديونية-عقارية/">المديونية</a><a href="/شراء-عقار-بالتمويل/">شراء العقار</a>';
}
function headerHtml(): string {
    return '<header class="header"><div class="container nav"><a class="brand" href="/" aria-label="أركان التنفيذية">' . logoHtml() . '</a><nav class="navlinks" aria-label="التنقل الرئيسي">' . navHtml() . '</nav><div class="nav-actions"><a class="btn btn-ghost track-call" href="tel:' . PHONE_E164 . '">' . icon('phone') . '<span>' . phoneHtml() . '</span></a><a class="btn btn-primary" href="#form">ابدأ التقييم</a></div></div></header>';
}
function socialLink(string $name, string $iconName, string $url, string $label): string {
    return '<a class="social-link" href="' . e($url) . '" target="_blank" rel="noopener" aria-label="' . e($label) . '"><span class="social-icon">' . icon($iconName) . '</span><span>' . e($name) . '</span></a>';
}
function footerHtml(): string {
    return '<footer class="footer"><div class="container footergrid"><div class="footer-brand"><div class="footer-logo-wrap">' . logoHtml('footer-logo') . '</div><p>حلول مالية وعقارية متكاملة تربط قدرتك المالية بفرص التملك المناسبة.</p><small>أركان ليست جهة تمويل مباشر، وكل حالة تخضع لمتطلبات الجهات ذات العلاقة.</small></div><div><h3>روابط مهمة</h3><a href="/سياسة-الخصوصية/">سياسة الخصوصية</a></div><div><h3>تابع أركان</h3><div class="social-list">' . socialLink('Instagram', 'instagram', 'https://www.instagram.com/arkanexecut/', 'إنستجرام أركان التنفيذية') . socialLink('X', 'x', 'https://x.com/arkanexecut', 'حساب أركان على X') . socialLink('TikTok', 'tiktok', 'https://www.tiktok.com/@arkan.execut', 'تيك توك أركان التنفيذية') . '</div><small class="copyright">© 2026 أركان التنفيذية</small></div></div></footer>';
}
function floatingButtons(string $wa): string {
    return '<div class="floating-stack"><a class="float wa track-whatsapp" href="' . e($wa) . '" target="_blank" rel="noopener" aria-label="تواصل واتساب">' . icon('whatsapp') . '</a><a class="float call track-call" href="tel:' . PHONE_E164 . '" aria-label="اتصل بأركان">' . icon('phone') . '</a></div>';
}
function scriptsHtml(string $leadEndpoint): string {
    $config = json_encode(['review' => REVIEW_MODE, 'endpoint' => $leadEndpoint, 'thankYou' => '/تم-استلام-الطلب/', 'whatsapp' => WHATSAPP_NUMBER, 'privacyVersion' => PRIVACY_VERSION], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return '<script>window.ARKAN_CONFIG=' . $config . ';</script><script src="/assets/site.js?v=6" defer></script>';
}
