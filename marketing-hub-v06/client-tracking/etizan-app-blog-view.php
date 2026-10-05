<?php
declare(strict_types=1);
function etizan_blog_render(?string $slug = null): never {
    $cityEn = (($_GET['city'] ?? '') === 'jeddah') ? 'jeddah' : 'riyadh';
    $posts = etizan_blog_posts();
    $post = $slug === null ? null : ($posts[$slug] ?? null);
    if ($slug !== null && $post === null) {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="ar" dir="rtl"><meta name="robots" content="noindex"><meta charset="utf-8"><title>المقال غير موجود | إتزان</title><h1>المقال غير موجود</h1><p><a href="/blog/">العودة إلى المدونة</a></p></html>';
        exit;
    }
    $serviceKey = $post['primaryService'] ?? 'general';
    $serviceUrl = '/' . $cityEn . '/' . ($serviceKey === 'general' ? '' : $serviceKey . '/');
    $x = etizan_page_data($serviceUrl);
    $title = $post ? $post['title'] . ' | مدونة إتزان' : 'المدونة القانونية | إتزان للمحاماة في الرياض وجدة';
    $desc = $post['description'] ?? 'مقالات عملية عن الاستشارات القانونية والقضايا العمالية والعقود وتحصيل الديون والتركات والملكية الفكرية، مع روابط لخدمات إتزان في الرياض وجدة.';
    $canonical = ETIZAN_BASE . '/blog/' . ($post ? $post['slug'] . '/' : '');
    $branchMenus = etizan_branch_menu_html();
    $mapEmbed = 'https://www.google.com/maps?q=' . rawurlencode($x['branch']['address']) . '&output=embed';
    $contactTopic = $post ? $post['category'] : 'الخدمات القانونية';
    $contactMessage = $post
        ? 'السلام عليكم، قرأت مقال «' . $post['title'] . '» وأرغب في طلب خدمة في مجال ' . $contactTopic . ' لدى إتزان - فرع ' . $x['city'] . '.'
        : 'السلام عليكم، أرغب في طلب خدمة قانونية لدى إتزان - فرع ' . $x['city'] . '.';
    $wt = rawurlencode($contactMessage);
    $image = ETIZAN_BASE . '/assets/about-image.webp?v=2';
    $breadcrumbs = [['@type'=>'ListItem','position'=>1,'name'=>'إتزان','item'=>ETIZAN_BASE.'/riyadh/'],['@type'=>'ListItem','position'=>2,'name'=>'المدونة','item'=>ETIZAN_BASE.'/blog/']];
    if ($post) $breadcrumbs[] = ['@type'=>'ListItem','position'=>3,'name'=>$post['title'],'item'=>$canonical];
    $organization = ['@type'=>'Organization','@id'=>ETIZAN_BASE.'/#organization','name'=>'شركة إتزان للمحاماة والاستشارات القانونية','url'=>ETIZAN_BASE.'/','logo'=>['@type'=>'ImageObject','url'=>ETIZAN_BASE.'/assets/logo-brand.webp?v=2']];
    $graph = [$organization,['@type'=>'BreadcrumbList','itemListElement'=>$breadcrumbs]];
    if ($post) {
        $graph[] = ['@type'=>'BlogPosting','@id'=>$canonical.'#article','headline'=>$post['title'],'description'=>$desc,'image'=>[$image],'datePublished'=>$post['published'],'dateModified'=>$post['modified'],'inLanguage'=>'ar-SA','articleSection'=>$post['category'],'mainEntityOfPage'=>['@type'=>'WebPage','@id'=>$canonical],'author'=>['@type'=>'Organization','name'=>$post['author'],'url'=>ETIZAN_BASE.'/blog/'],'publisher'=>['@id'=>ETIZAN_BASE.'/#organization']];
    } else {
        $items=[]; foreach(array_values($posts) as $i=>$p) $items[]=['@type'=>'ListItem','position'=>$i+1,'url'=>ETIZAN_BASE.'/blog/'.$p['slug'].'/','name'=>$p['title']];
        $graph[]=['@type'=>'Blog','@id'=>$canonical.'#blog','name'=>'مدونة إتزان القانونية','url'=>$canonical,'inLanguage'=>'ar-SA','publisher'=>['@id'=>ETIZAN_BASE.'/#organization']];
        $graph[]=['@type'=>'ItemList','itemListElement'=>$items];
    }
    $schema=json_encode(['@context'=>'https://schema.org','@graph'=>$graph],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
?>
<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title><?=eh($title)?></title><meta name="description" content="<?=eh($desc)?>"><link rel="canonical" href="<?=eh($canonical)?>"><meta name="theme-color" content="#1b4f7a"><meta property="og:title" content="<?=eh($title)?>"><meta property="og:description" content="<?=eh($desc)?>"><meta property="og:url" content="<?=eh($canonical)?>"><meta property="og:type" content="<?=$post?'article':'website'?>"><meta property="og:image" content="<?=eh($image)?>"><meta property="og:locale" content="ar_SA"><meta name="twitter:card" content="summary_large_image"><link rel="preload" href="/assets/fonts/cairo-arabic-v1.woff2" as="font" type="font/woff2" crossorigin><link rel="preload" href="/assets/fonts/cairo-latin-v2.woff2" as="font" type="font/woff2" crossorigin><link rel="icon" href="/assets/favicon.svg" type="image/svg+xml"><link rel="alternate" type="application/rss+xml" title="مدونة إتزان" href="<?=ETIZAN_BASE?>/blog/feed.xml"><style id="etizan-inline-css"><?=etizan_inline_css()?></style><script type="application/ld+json"><?=$schema?></script><?=etizan_contact_tracking()?><script async src="https://www.googletagmanager.com/gtag/js?id=AW-16995014977"></script></head><body data-city="<?=eh($x['city'])?>" data-service="<?=eh($x['p']['service'])?>" data-wa="<?=eh($x['wa'])?>" data-phone="<?=eh($x['phone'])?>">
<div class="top-info"><div class="wrap"><div class="top-contact"><a class="track-call" href="tel:+<?=eh($x['phone'])?>"><bdi dir="ltr"><?=eh($x['phoneDisplay'])?></bdi></a><a href="mailto:info@etizan-law.com">info@etizan-law.com</a></div><span><?=eh($x['branch']['name'])?> · المملكة العربية السعودية</span></div></div>
<header class="topbar"><div class="wrap nav"><a class="brand" href="/<?=eh($x['cityEn'])?>/"><img src="/assets/logo-brand.webp?v=2" width="180" height="63" alt="شركة إتزان للمحاماة والاستشارات القانونية" decoding="async"></a><nav class="nav-links campaign-nav" aria-label="التنقل داخل الصفحة"><a href="/<?=eh($x['cityEn'])?>/">الرئيسية</a><details class="landing-menu"><summary>الأقسام <span aria-hidden="true">⌄</span></summary><div class="landing-mega"><div class="landing-mega-grid"><?=$branchMenus?></div></div></details><a class="current-home" href="/blog/<?=($x['cityEn']==='jeddah'?'?city=jeddah':'')?>">المدونة</a><a href="/<?=eh($x['cityEn'])?>/#about">من نحن</a><a href="/<?=eh($x['cityEn'])?>/#why">لماذا إتزان</a><a href="/<?=eh($x['cityEn'])?>/#process">آلية العمل</a></nav><div class="nav-actions"><a class="ghost track-call" href="tel:+<?=eh($x['phone'])?>">اتصال</a><a class="solid track-wa" href="https://wa.me/<?=eh($x['wa'])?>?text=<?=$wt?>">تواصل واتساب</a></div></div></header>

<main id="main">
<section class="journal-hero"><div class="wrap"><nav class="journal-breadcrumbs" aria-label="مسار الصفحة"><a href="/<?=eh($cityEn)?>/">إتزان — <?=eh($x['city'])?></a><span aria-hidden="true">/</span><?php if($post):?><a href="/blog/<?=$cityEn==='jeddah'?'?city=jeddah':''?>">المدونة</a><span aria-hidden="true">/</span><span><?=eh($post['category'])?></span><?php else:?><span>المدونة</span><?php endif;?></nav><div class="section-kicker"><?=$post?eh($post['category']):'معرفة قانونية تدعم قرارك'?></div><h1><?=$post?eh($post['title']):'مدونة إتزان القانونية'?></h1><?php if($post):?><div class="journal-article-meta"><span><?=eh($post['author'])?></span><time datetime="<?=eh($post['published'])?>"><?=eh(etizan_blog_date($post['published']))?></time><span><?=etizan_blog_minutes($post)?> دقائق قراءة</span></div><?php else:?><p>أدلة عملية تساعدك على فهم موضوعك، وتجهيز مستنداتك، واختيار الخدمة القانونية المناسبة في الرياض وجدة.</p><?php endif;?></div></section>
<?php if(!$post):
    $topic = is_string($_GET['topic']??null) ? $_GET['topic'] : '';
    if(!isset(etizan_blog_topics()[$topic])) $topic='';
    $query = is_string($_GET['q']??null) ? mb_substr(trim($_GET['q']),0,100) : '';
    $visible=array_filter($posts, static function(array $p) use($topic,$query):bool {
        if($topic!=='' && $p['topic']!==$topic)return false;
        return $query==='' || mb_stripos($p['title'].' '.$p['intro'].' '.implode(' ',$p['keywords']),$query)!==false;
    });
?>
<section class="journal-list"><div class="wrap"><form class="journal-filter" action="/blog/" method="get" role="search"><div><label for="journal-q">ابحث في المدونة</label><input id="journal-q" type="search" name="q" value="<?=eh($query)?>" placeholder="مثل: التركة، عقد العمل، العلامة التجارية"></div><div><label for="journal-topic">الموضوع</label><select id="journal-topic" name="topic"><option value="">جميع الموضوعات</option><?php foreach(etizan_blog_topics() as $key=>$label):?><option value="<?=eh($key)?>" <?=$key===$topic?'selected':''?>><?=eh($label)?></option><?php endforeach;?></select></div><?php if($cityEn==='jeddah'):?><input type="hidden" name="city" value="jeddah"><?php endif;?><button class="solid" type="submit">عرض المقالات</button></form><div class="journal-results" role="status"><?=count($visible)?> مقالات<?php if($query!==''||$topic!==''):?> · <a href="/blog/<?=$cityEn==='jeddah'?'?city=jeddah':''?>">عرض الكل</a><?php endif;?></div><?php if(!$visible):?><div class="journal-empty"><h2>لم نعثر على مقال بهذا البحث</h2><p>جرب اسم الخدمة أو تصفح جميع الموضوعات.</p></div><?php else:?><div class="journal-grid"><?php foreach($visible as $p)echo etizan_blog_card($p,$cityEn);?></div><?php endif;?></div></section>
<?php else:?>
<div class="wrap journal-article-layout"><aside class="journal-toc" aria-label="فهرس المقال"><div class="journal-toc-inner"><strong>في هذا المقال</strong><nav><?php foreach($post['sections'] as $section):?><a href="#<?=eh($section['id'])?>"><?=eh($section['title'])?></a><?php endforeach;?><?php if(!empty($post['faq'])):?><a href="#article-faq">أسئلة شائعة</a><?php endif;?><?php if(!empty($post['sources'])):?><a href="#article-sources">المصادر الرسمية</a><?php endif;?><a href="#article-contact">الاستشارات والحجز</a></nav><div class="journal-contact-actions journal-sidebar-contact"><a class="solid track-wa" href="https://wa.me/<?=eh($x['wa'])?>?text=<?=$wt?>">اطلب الخدمة عبر واتساب</a><a class="ghost track-call" href="tel:+<?=eh($x['phone'])?>">اتصل بفرع <?=eh($x['city'])?></a></div></div></aside><article class="journal-article"><p class="journal-intro"><?=eh($post['intro'])?></p><?=etizan_blog_article_contacts($post)?><?php foreach($post['sections'] as $section):?><section id="<?=eh($section['id'])?>"><h2><?=eh($section['title'])?></h2><?php foreach($section['paragraphs']??[] as $paragraph):?><p><?=eh($paragraph)?></p><?php endforeach;?><?php if(!empty($section['list'])):?><ul><?php foreach($section['list'] as $item):?><li><?=eh($item)?></li><?php endforeach;?></ul><?php endif;?><?php foreach($section['subsections']??[] as $subsection):?><div class="journal-subsection"><h3><?=eh($subsection['title'])?></h3><?php foreach($subsection['paragraphs']??[] as $paragraph):?><p><?=eh($paragraph)?></p><?php endforeach;?></div><?php endforeach;?><?php foreach($section['closing']??[] as $paragraph):?><p><?=eh($paragraph)?></p><?php endforeach;?></section><?php endforeach;?>
<?php if(!empty($post['faq'])):?><section id="article-faq" class="journal-faq"><h2><?=eh($post['faqTitle']??'أسئلة شائعة')?></h2><?php foreach($post['faq'] as $faq):?><details><summary><?=eh($faq['q'])?></summary><p><?=eh($faq['a'])?></p></details><?php endforeach;?></section><?php endif;?>
<section class="journal-service-links"><h2>خدمات مرتبطة بهذا الموضوع</h2><p>اختر الخدمة والفرع لعرض التفاصيل وطلب التواصل.</p><div class="journal-service-grid"><?php foreach($post['services'] as $key):if($key==='general')continue;$label=etizan_landing_labels()[$key]??(etizan_pages()[$key]['h1']??$key);if($key==='financial-regulatory')$label='المصرفي والضريبي والأوراق المالية';?><div><strong><?=eh($label)?></strong><a href="/riyadh/<?=eh($key)?>/">الرياض</a><a href="/jeddah/<?=eh($key)?>/">جدة</a></div><?php endforeach;?><div><strong>الاستشارات القانونية</strong><a href="/riyadh/">الرياض</a><a href="/jeddah/">جدة</a></div></div></section>
<?php if(!empty($post['sources'])):?><section id="article-sources" class="journal-sources"><h2>مصادر رسمية للاطلاع</h2><ul><?php foreach($post['sources'] as $source):?><li><a href="<?=eh($source['url'])?>" target="_blank" rel="noopener noreferrer"><?=eh($source['label'])?> ↗</a></li><?php endforeach;?></ul><p class="journal-note">محتوى توعوي عام، ويُحدد الرأي القانوني بعد دراسة وقائع الملف ومستنداته. راجع النصوص والمتطلبات الرسمية عند اتخاذ الإجراء.</p></section><?php endif;?></article></div>
<?=etizan_blog_carousel($serviceKey,$cityEn,$post['slug'])?>
<?php endif;?>
<section class="cta"><div class="wrap cta-box"><div><span>من المعرفة إلى الخطوة المناسبة</span><h2><?=$post?'هل تحتاج إلى خدمة في مجال '.eh($contactTopic).'؟':'تواصل مع فرع إتزان في '.eh($x['city'])?></h2><p>اطلب الخدمة من فرع <?=eh($x['city'])?> عبر واتساب أو اتصال مباشر.</p></div><div class="journal-contact-actions"><a class="solid large track-wa" href="https://wa.me/<?=eh($x['wa'])?>?text=<?=$wt?>">اطلب الخدمة عبر واتساب</a><a class="ghost large track-call" href="tel:+<?=eh($x['phone'])?>">اتصل الآن</a></div></div></section></main>
<footer class="footer"><div class="wrap"><div class="footer-grid"><div class="footer-brand"><img src="/assets/logo-brand.webp?v=2" width="170" height="60" alt="إتزان للمحاماة" loading="lazy" decoding="async"><p>شركة إتزان للمحاماة والاستشارات القانونية<br>شريككم القانوني في حماية الأعمال وصناعة القرار.</p></div><div class="footer-contact"><h3>التواصل — <?=eh($x['city'])?></h3><div class="footer-links"><a class="track-call" href="tel:+<?=eh($x['phone'])?>"><bdi dir="ltr"><?=eh($x['phoneDisplay'])?></bdi></a><a class="track-wa" href="https://wa.me/<?=eh($x['wa'])?>?text=<?=$wt?>">واتساب فرع <?=eh($x['city'])?></a><a href="mailto:info@etizan-law.com">info@etizan-law.com</a></div></div><div class="footer-office"><h3><?=eh($x['branch']['name'])?></h3><div class="footer-links"><a class="office-location" href="<?=eh($x['branch']['map'])?>" target="_blank" rel="noopener"><?=eh($x['branch']['address'])?></a><div class="office-map"><iframe title="موقع <?=eh($x['city'])?> على خرائط Google" src="<?=eh($mapEmbed)?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div></div></div></div><div class="footer-bottom"><div class="copyright">© شركة إتزان للمحاماة والاستشارات القانونية</div><nav class="footer-quicklinks" aria-label="روابط أسفل الصفحة"><a href="<?=ETIZAN_PRIVACY?>" target="_blank" rel="noopener">سياسة الخصوصية</a><a href="<?=eh($serviceUrl)?>#contact-form">طلب تواصل قانوني</a><a href="/blog/<?=($x['cityEn']==='jeddah'?'?city=jeddah':'')?>">المدونة</a><a href="/blog/feed.xml">تحديثات المقالات RSS</a></nav></div></div></footer>
<div class="floating" aria-label="تواصل سريع"><a class="float-wa track-wa" href="https://wa.me/<?=eh($x['wa'])?>?text=<?=$wt?>" aria-label="واتساب فرع <?=eh($x['city'])?>" title="واتساب"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 0 0-7.7 13.7L3 21l4.4-1.2A9 9 0 1 0 12 3Z"/><path d="M8.2 7.6c.4 3.9 3.3 6.8 7.2 7.2l1.3-1.8-2.6-1.2-.8 1c-1.5-.6-2.7-1.8-3.3-3.3l1-1-1.2-2.5-1.6 1.6Z"/></svg></a><a class="float-call track-call" href="tel:+<?=eh($x['phone'])?>" aria-label="اتصال بفرع <?=eh($x['city'])?>" title="اتصال"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.7 15.7 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1.1-.2 1.2.4 2.5.6 3.8.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.8 21 3 13.2 3 3.7c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.6.6 3.8.1.4 0 .8-.2 1.1l-2.3 2.2Z"/></svg></a></div><script>
window.HORIZONS_WA_ATTR={
  clientId:"cl_cbb797950cc8d4",
  businessNumbers:["+966552491110","+966559451110"],
  businessNumber:"+<?=eh($x['wa'])?>",
  endpoint:"https://marketing.hositee.com/wa_click_attribution.php",
  <?php if($post):?>businessNumbers:["+966552491110","+966559451110","+966555329032","+966550563737"],<?php endif;?>
  timeoutMs:2500
};
</script>
<script defer src="/assets/article-wa-attribution.js?v=20261005-2"></script>
<script defer src="/assets/site.js?v=20261003-hero2"></script><script defer src="/assets/blog.js?v=20261003-1"></script></body></html>
<?php exit; }
