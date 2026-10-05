<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';
require_once __DIR__.'/branches.php';
require_once __DIR__.'/blog.php';

function eh(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function etizan_inline_css():string{
    static $css=null;
    if($css!==null)return $css;
    $base=dirname(__DIR__).'/assets/';
    $css='';
    foreach(['site.css','contact-ui.css','perf.css','blog.css','polish.css'] as $file){$path=$base.$file;if(is_readable($path))$css.=(string)file_get_contents($path);}
    return str_ireplace('</style','<\/style',$css);
}
function etizan_contact_tracking():string{return <<<'ETIZAN_TRACKING'
<script>(function(w,d){
  'use strict';
  if(w.__ETIZAN_CONTACT_CAPTURE_V2__)return;
  w.__ETIZAN_CONTACT_CAPTURE_V2__=true;
  var dl=w.dataLayer=w.dataLayer||[],attr={},last={};
  w.gtag=w.gtag||function(){dl.push(arguments)};
  w.gtag('js',new Date());
  w.gtag('config','AW-16995014977');
  var labels={whatsapp:'ZE1TCMD3ze0cEMGy7ac_',call:'K54RCMP3ze0cEMGy7ac_',form:'AJSlCL33ze0cEMGy7ac_'};
  w.etizanAdsConversion=function(kind,data){
    if(!labels[kind])return;
    var p=Object.assign({},data||{});
    p.send_to='AW-16995014977/'+labels[kind];
    w.gtag('event','conversion',p);
  };
  var q=new URLSearchParams(location.search);
  ['gclid','gbraid','wbraid','utm_source','utm_medium','utm_campaign','utm_term','utm_content','campaign_id','adgroup_id','creative_id'].forEach(function(k){if(q.get(k))attr[k]=q.get(k)});
  d.addEventListener('click',function(e){
    var a=e.target&&e.target.closest?e.target.closest('a.track-wa,a.track-call'):null;
    if(!a||!d.body)return;
    var wa=a.classList.contains('track-wa'),href=a.getAttribute('href')||'',now=Date.now(),key=(wa?'wa':'call')+'|'+href;
    if(last[key]&&now-last[key]<800)return;last[key]=now;
    var b=d.body.dataset,p={lead_id:a.dataset.leadId||'',contact_city:a.dataset.contactCity||b.city,contact_phone:a.dataset.contactPhone||b.phone,contact_service:b.service,page_path:location.pathname,click_url:href,click_text:(a.textContent||a.getAttribute('aria-label')||'').trim().slice(0,200),contact_position:a.dataset.contactPosition||(a.closest('.floating')?'floating':a.closest('header')?'header':a.closest('footer')?'footer':'page')};
    Object.keys(attr).forEach(function(k){p[k]=attr[k]});
    w.etizanAdsConversion(wa?'whatsapp':'call',{transaction_id:'ETZ-'+(wa?'WA':'CALL')+'-'+now.toString(36)+'-'+Math.random().toString(36).slice(2,10),contact_city:p.contact_city,contact_phone:p.contact_phone,contact_service:p.contact_service,contact_position:p.contact_position,page_path:p.page_path});
    dl.push(Object.assign({event:a.classList.contains('after-submit-wa')?'whatsapp_after_form':wa?'whatsapp_click':'phone_click'},p));
  },{capture:true,passive:true});
})(window,document);
</script>
ETIZAN_TRACKING;
}
function etizan_landing_labels():array{return[
    'general'=>'الرئيسية','corporate'=>'الشركات والاستثمار','contracts'=>'التجاري والعقود','litigation'=>'القضايا والتقاضي',
    'criminal'=>'القضايا الجنائية','administrative'=>'القضايا الإدارية','real-estate'=>'العقارات','labor'=>'القضايا العمالية',
    'family-inheritance'=>'الأحوال والتركات','enforcement-arbitration'=>'التنفيذ والتحكيم','insurance'=>'التأمين',
    'financial-regulatory'=>'المصرفي والضريبي','bankruptcy'=>'الإفلاس','ip-franchise'=>'الملكية الفكرية والامتياز',
    'cybercrime'=>'الجرائم المعلوماتية','aviation-transport'=>'الطيران والنقل'
];}
function etizan_branch_menu_html():string{
    $html='';
    foreach(['riyadh'=>'الرياض','jeddah'=>'جدة'] as $slug=>$label){
        $links='';
        foreach(etizan_landing_labels() as $key=>$name){
            $href='/'.$slug.'/'.($key==='general'?'':$key.'/');
            $class=$key==='general'?' class="branch-home"':'';
            $links.='<a'.$class.' href="'.eh($href).'">'.eh($name).'</a>';
        }
        $html.='<section class="branch-menu"><h4><a href="/'.$slug.'/">'.eh($label).'</a></h4>'.$links.'</section>';
    }
    return $html;
}
function etizan_page_data(string $path):array{
    $a=array_values(array_filter(explode('/',trim($path,'/')),'strlen'));
    $ce=($a[0]??'')==='jeddah'?'jeddah':'riyadh';
    $city=$ce==='jeddah'?'جدة':'الرياض';
    $pages=etizan_pages();
    $key=isset($a[1],$pages[$a[1]])?$a[1]:'general';
    $p=$pages[$key];
    $r=fn($v)=>str_replace('%CITY%',$city,$v);
    $branch=etizan_branch($ce);
    return[
        'cityEn'=>$ce,'city'=>$city,'key'=>$key,'p'=>$p,
        'title'=>$r($p['title']),'h1'=>$r($p['h1']),'lead'=>$r($p['lead']),
        'keywords'=>array_map($r,$p['keywords']),'wa'=>$branch['phone'],'phone'=>$branch['phone'],
        'phoneDisplay'=>$branch['phone_display'],'branch'=>$branch
    ];
}
function etizan_render(string $path):never{
    $x=etizan_page_data($path);
    $canonical=ETIZAN_BASE.'/'.$x['cityEn'].'/'.($x['key']==='general'?'':$x['key'].'/');
    $desc=mb_substr($x['lead'].' اطلب تواصلًا قانونيًا من شركة إتزان للمحاماة والاستشارات القانونية.',0,160);
    $opts='';foreach(etizan_services() as $s)$opts.='<option value="'.eh($s).'"'.($s===$x['p']['service']?' selected':'').'>'.eh($s).'</option>';
    $chips='';foreach($x['keywords'] as $k)$chips.='<span>'.eh($k).'</span>';
    $cards='';$i=1;foreach($x['p']['cards'] as $c){$cards.='<article><div class="card-icon">0'.$i.'</div><h3>'.eh($c[0]).'</h3><p>'.eh($c[1]).'</p></article>';$i++;}
    $branchMenus=etizan_branch_menu_html();
    $mapEmbed='https://www.google.com/maps?q='.rawurlencode($x['branch']['address']).'&output=embed';
    $wt=rawurlencode('السلام عليكم، أرغب في استشارة قانونية لدى إتزان.');
    $wct=rawurlencode('السلام عليكم، أرغب في استشارة قانونية لدى إتزان - فرع '.$x['city']);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');?>
<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title><?=eh($x['title'])?></title><meta name="description" content="<?=eh($desc)?>"><link rel="canonical" href="<?=eh($canonical)?>"><meta name="theme-color" content="#1b4f7a"><meta property="og:title" content="<?=eh($x['title'])?>"><meta property="og:description" content="<?=eh($desc)?>"><meta property="og:url" content="<?=eh($canonical)?>"><meta property="og:type" content="website"><link rel="preload" href="/assets/fonts/cairo-arabic-v1.woff2" as="font" type="font/woff2" crossorigin><link rel="preload" href="/assets/fonts/cairo-latin-v2.woff2" as="font" type="font/woff2" crossorigin><link rel="icon" href="/assets/favicon.svg" type="image/svg+xml"><link rel="preload" href="/assets/hero-bg-mobile.webp?v=2" as="image" media="(max-width:520px)" fetchpriority="high"><link rel="preload" href="/assets/hero-bg.webp?v=2" as="image" media="(min-width:521px)" fetchpriority="high"><link rel="preconnect" href="https://www.googletagmanager.com"><?=etizan_contact_tracking()?><script async src="https://www.googletagmanager.com/gtag/js?id=AW-16995014977"></script><style id="etizan-inline-css"><?=etizan_inline_css()?></style></head><body data-city="<?=eh($x['city'])?>" data-service="<?=eh($x['p']['service'])?>" data-wa="<?=eh($x['wa'])?>" data-phone="<?=eh($x['phone'])?>">
<div class="top-info"><div class="wrap"><div class="top-contact"><a class="track-call" href="tel:+<?=eh($x['phone'])?>"><bdi dir="ltr"><?=eh($x['phoneDisplay'])?></bdi></a><a href="mailto:info@etizan-law.com">info@etizan-law.com</a></div><span><?=eh($x['branch']['name'])?> · المملكة العربية السعودية</span></div></div>
<header class="topbar"><div class="wrap nav"><a class="brand" href="/<?=eh($x['cityEn'])?>/"><img src="/assets/logo-brand.webp?v=2" width="180" height="63" alt="شركة إتزان للمحاماة والاستشارات القانونية" decoding="async"></a><nav class="nav-links campaign-nav" aria-label="التنقل داخل الصفحة"><a class="current-home" href="/<?=eh($x['cityEn'])?>/">الرئيسية</a><details class="landing-menu"><summary>الأقسام <span aria-hidden="true">⌄</span></summary><div class="landing-mega"><div class="landing-mega-grid"><?=$branchMenus?></div></div></details><a class="journal-nav-link" href="/blog/<?=($x['cityEn']==='jeddah'?'?city=jeddah':'')?>">المدونة</a><a href="#about">من نحن</a><a href="#why">لماذا إتزان</a><a href="#process">آلية العمل</a></nav><div class="nav-actions"><a class="ghost track-call" href="tel:+<?=eh($x['phone'])?>">اتصال</a><a class="solid track-wa" href="https://wa.me/<?=eh($x['wa'])?>?text=<?=$wt?>">تواصل واتساب</a></div></div></header>
<main><section class="hero"><div class="wrap hero-grid"><div class="hero-copy"><div class="eyebrow">شركة إتزان للمحاماة والاستشارات القانونية · <?=eh($x['city'])?></div><h1><?=eh($x['h1'])?></h1><p class="lead"><?=eh($x['lead'])?></p><div class="hero-actions"><a class="solid hero-wa track-wa" data-contact-position="hero" href="https://wa.me/<?=eh($x['wa'])?>?text=<?=$wct?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 0 0-7.7 13.7L3 21l4.4-1.2A9 9 0 1 0 12 3Z"/><path d="M8.2 7.6c.4 3.9 3.3 6.8 7.2 7.2l1.3-1.8-2.6-1.2-.8 1c-1.5-.6-2.7-1.8-3.3-3.3l1-1-1.2-2.5-1.6 1.6Z"/></svg>تواصل واتساب</a><a class="ghost hero-call track-call" data-contact-position="hero" href="tel:+<?=eh($x['phone'])?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.7 15.7 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1.1-.2 1.2.4 2.5.6 3.8.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.8 21 3 13.2 3 3.7c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.6.6 3.8.1.4 0 .8-.2 1.1l-2.3 2.2Z"/></svg>اتصل بفرع <?=eh($x['city'])?></a></div><div class="keyword-row"><?=$chips?></div><div class="hero-proof"><span>فهم قانوني وتجاري</span><span>وضوح وشفافية في نطاق العمل</span><span>خصوصية في التعامل مع البيانات</span></div></div><div class="hero-form"><div class="form-card" id="contact-form"><div class="form-head"><span>ابدأ بخطوة واحدة</span><strong>اطلب تواصلًا قانونيًا من فرع <?=eh($x['city'])?></strong></div><form id="leadForm" novalidate toolname="request_legal_consultation" tooldescription="إرسال طلب تواصل قانوني إلى شركة إتزان للمحاماة والاستشارات القانونية للمتابعة من الفرع المناسب."><input type="hidden" name="service" value="<?=eh($x['p']['service'])?>" toolparamdescription="الخدمة القانونية المرتبطة بصفحة الهبوط"><input type="hidden" name="city" value="<?=eh($x['city'])?>" toolparamdescription="الفرع أو المدينة التي سيتم توجيه الطلب إليها"><input type="hidden" name="consent" value="نعم" toolparamdescription="موافقة المستخدم على استخدام بياناته للتواصل بخصوص الطلب"><input type="hidden" name="consent_version" value="v1" toolparamdescription="إصدار نص الموافقة"><div class="form-grid"><label>الاسم الكامل<input name="name" autocomplete="name" required minlength="2" placeholder="الاسم" toolparamdescription="الاسم الكامل لصاحب الطلب"></label><label>رقم الجوال<input name="phone" inputmode="tel" autocomplete="tel" required placeholder="05xxxxxxxx" toolparamdescription="رقم جوال سعودي صالح للتواصل"></label><label class="full">الخدمة المطلوبة<select name="service_select" id="serviceSelect" toolparamdescription="نوع الخدمة القانونية المطلوبة"><?=$opts?></select></label><label class="full">ملخص مختصر <span>(اختياري)</span><textarea name="message" rows="2" placeholder="اكتب نبذة مختصرة عن الموضوع" toolparamdescription="ملخص اختياري للموضوع القانوني"></textarea></label><label class="consent full"><input type="checkbox" id="consentCheck" checked required><span>أوافق على استخدام بياناتي للتواصل بخصوص الطلب وفق <a href="<?=ETIZAN_PRIVACY?>" target="_blank" rel="noopener">سياسة الخصوصية</a>.</span></label><button type="submit" class="submit full">إرسال الطلب</button><div class="form-status full" id="formStatus" role="status" aria-live="polite"></div></div><p class="form-note">إرسال النموذج لا يعني قبول القضية أو ضمان نتيجة.</p></form></div></div></div></section>
<section class="about" id="about"><div class="wrap about-grid"><div class="about-media"><picture><source media="(max-width:820px)" srcset="/assets/about-image-mobile.webp?v=2"><img src="/assets/about-image.webp?v=2" srcset="/assets/about-image-mobile.webp?v=2 640w, /assets/about-image.webp?v=2 960w" sizes="(max-width:820px) calc(100vw - 36px), 47vw" width="900" height="680" loading="lazy" decoding="async" alt="شركة إتزان للمحاماة والاستشارات القانونية"></picture></div><div><div class="section-kicker">التميز القانوني في كل استشارة</div><h2>ليس مجرد محامٍ — بل شريكك القانوني الاستراتيجي</h2><p>إتزان شركة سعودية تقدم خدمات قانونية متكاملة للشركات والمستثمرين والأفراد، مع ربط الرأي القانوني بواقع القرار التجاري والإجرائي.</p><div class="feature-list"><div><b>01</b><span>فهم قانوني وتجاري للملف</span></div><div><b>02</b><span>حلول عملية مرتبطة بالهدف</span></div><div><b>03</b><span>تخصص وتكامل في فريق العمل</span></div><div><b>04</b><span>وضوح في التواصل والمتابعة</span></div></div></div></div></section>
<section class="services" id="services"><div class="wrap"><div class="section-head"><div class="section-kicker">خدمة مرتبطة مباشرة بهدف بحثك</div><h2>ما الذي يمكن أن نساعدك فيه؟</h2><p>محتوى الصفحة مصمم ليتوافق مع نوع الخدمة التي تبحث عنها، مع توجيه الطلب إلى الفرع المناسب.</p></div><div class="cards"><?=$cards?></div></div></section>
<section class="why" id="why"><div class="wrap"><div class="section-head"><div class="section-kicker">لماذا إتزان</div><h2>معايير عمل واضحة من أول تواصل</h2><p>نحافظ على شخصية إتزان الأصلية: مهنية، وضوح، خصوصية، وفهم عملي للملفات القانونية.</p></div><div class="why-grid"><div class="why-card"><strong>فهم قانوني وتجاري</strong><span>قراءة الملف في سياقه القانوني والعملي قبل تحديد المسار.</span></div><div class="why-card"><strong>حلول عملية</strong><span>تركيز على البدائل والإجراءات القابلة للتنفيذ وفق طبيعة الحالة.</span></div><div class="why-card"><strong>تخصص وتكامل</strong><span>توجيه الطلب إلى نطاق الخدمة الأقرب للموضوع والجهة المختصة.</span></div><div class="why-card"><strong>وضوح وشفافية</strong><span>توضيح نطاق التواصل والخطوة التالية دون وعود غير واقعية.</span></div><div class="why-card"><strong>سرية وخصوصية</strong><span>استخدام البيانات المرسلة لغرض فهم الطلب والتواصل القانوني.</span></div><div class="why-card"><strong>متابعة منظمة</strong><span>تسجيل مصدر الطلب والفرع والخدمة لسهولة المتابعة داخل النظام.</span></div></div></div></section>
<section class="process" id="process"><div class="wrap process-grid"><div><div class="section-kicker">مسار واضح من أول تواصل</div><h2>ابدأ بالمعلومات الأساسية، ثم نحدد الخطوة المناسبة</h2><p>يتم استخدام البيانات لفهم الطلب وتحديد نطاق الخدمة وطريقة المتابعة المناسبة. يمكن أن تكون الخطوة التالية استشارة أو صياغة أو تفاوضًا أو تمثيلًا بحسب طبيعة الحالة.</p></div><ol><li><b>1</b><span><strong>إرسال الطلب</strong><small>الاسم والجوال والخدمة والفرع.</small></span></li><li><b>2</b><span><strong>مراجعة أولية</strong><small>فهم الموضوع والمستندات الأساسية.</small></span></li><li><b>3</b><span><strong>تحديد المسار</strong><small>تحديد طريقة المتابعة المناسبة للحالة.</small></span></li></ol></div></section>
<?=etizan_blog_carousel($x['key'],$x['cityEn'])?>
<section class="cta"><div class="wrap cta-box"><div><span>تحتاج تواصلًا أسرع؟</span><h2>تواصل مع إتزان عبر واتساب — فرع <?=eh($x['city'])?></h2></div><a class="solid large track-wa" href="https://wa.me/<?=eh($x['wa'])?>?text=<?=$wct?>">فتح واتساب</a></div></section></main>
<footer class="footer"><div class="wrap"><div class="footer-grid"><div class="footer-brand"><img src="/assets/logo-brand.webp?v=2" width="170" height="60" alt="إتزان للمحاماة" loading="lazy" decoding="async"><p>شركة إتزان للمحاماة والاستشارات القانونية<br>شريككم القانوني في حماية الأعمال وصناعة القرار.</p></div><div class="footer-contact"><h3>التواصل — <?=eh($x['city'])?></h3><div class="footer-links"><a class="track-call" href="tel:+<?=eh($x['phone'])?>"><bdi dir="ltr"><?=eh($x['phoneDisplay'])?></bdi></a><a class="track-wa" href="https://wa.me/<?=eh($x['wa'])?>?text=<?=$wt?>">واتساب فرع <?=eh($x['city'])?></a><a href="mailto:info@etizan-law.com">info@etizan-law.com</a></div></div><div class="footer-office"><h3><?=eh($x['branch']['name'])?></h3><div class="footer-links"><a class="office-location" href="<?=eh($x['branch']['map'])?>" target="_blank" rel="noopener"><?=eh($x['branch']['address'])?></a><div class="office-map"><iframe title="موقع <?=eh($x['city'])?> على خرائط Google" src="<?=eh($mapEmbed)?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div></div></div></div><div class="footer-bottom"><div class="copyright">© شركة إتزان للمحاماة والاستشارات القانونية</div><nav class="footer-quicklinks" aria-label="روابط أسفل الصفحة"><a href="<?=ETIZAN_PRIVACY?>" target="_blank" rel="noopener">سياسة الخصوصية</a><a href="#contact-form">طلب تواصل قانوني</a><a href="/blog/<?=($x['cityEn']==='jeddah'?'?city=jeddah':'')?>">المدونة</a></nav></div></div></footer>
<div class="floating" aria-label="تواصل سريع"><a class="float-wa track-wa" href="https://wa.me/<?=eh($x['wa'])?>?text=<?=$wt?>" aria-label="واتساب فرع <?=eh($x['city'])?>" title="واتساب"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 0 0-7.7 13.7L3 21l4.4-1.2A9 9 0 1 0 12 3Z"/><path d="M8.2 7.6c.4 3.9 3.3 6.8 7.2 7.2l1.3-1.8-2.6-1.2-.8 1c-1.5-.6-2.7-1.8-3.3-3.3l1-1-1.2-2.5-1.6 1.6Z"/></svg></a><a class="float-call track-call" href="tel:+<?=eh($x['phone'])?>" aria-label="اتصال بفرع <?=eh($x['city'])?>" title="اتصال"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.7 15.7 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1.1-.2 1.2.4 2.5.6 3.8.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.8 21 3 13.2 3 3.7c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.6.6 3.8.1.4 0 .8-.2 1.1l-2.3 2.2Z"/></svg></a></div><script>
window.HORIZONS_WA_ATTR={
  clientId:"cl_cbb797950cc8d4",
  businessNumbers:["+966552491110","+966559451110"],
  businessNumber:"+<?=eh($x['wa'])?>",
  endpoint:"https://marketing.hositee.com/wa_click_attribution.php",
  timeoutMs:2500
};
</script>
<script defer src="/assets/article-wa-attribution.js?v=20261005-2"></script>
<script defer src="/assets/site.js?v=20261003-hero2"></script><script defer src="/assets/blog.js?v=20261003-1"></script></body></html><?php exit;}