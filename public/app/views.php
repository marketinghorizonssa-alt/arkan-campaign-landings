<?php
declare(strict_types=1);

function formHtml(string $pageId): string {
    return '<aside class="form-card" id="form"><h2>ابدأ تقييم حالتك مجانًا</h2><form id="leadForm" novalidate>'
        . '<input type="hidden" name="landing_page_id" value="' . e($pageId) . '"><input type="hidden" name="landing_path"><input type="hidden" name="utm_source"><input type="hidden" name="utm_medium"><input type="hidden" name="utm_campaign"><input type="hidden" name="utm_term"><input type="hidden" name="utm_content"><input type="hidden" name="gclid"><input type="hidden" name="gbraid"><input type="hidden" name="wbraid"><input type="hidden" name="ttclid"><input type="hidden" name="fbclid"><input type="hidden" name="referrer">'
        . '<div class="field"><label for="full_name">الاسم</label><input id="full_name" name="full_name" autocomplete="name" minlength="2" required placeholder="اكتب اسمك"></div>'
        . '<div class="field"><label for="phone">رقم الجوال</label><input id="phone" name="phone" class="ltr-input" type="tel" inputmode="tel" autocomplete="tel" required placeholder="اكتب رقم الجوال" dir="ltr"></div>'
        . '<div class="field"><label for="city">المدينة</label><input id="city" name="city" list="cities" autocomplete="address-level2" required placeholder="اختر أو اكتب المدينة"><datalist id="cities"><option value="جدة"><option value="مكة المكرمة"><option value="الرياض"><option value="المدينة المنورة"><option value="الطائف"><option value="الدمام"><option value="الخبر"></datalist></div>'
        . '<div class="two"><div class="field"><label for="property_type">نوع العقار</label><select id="property_type" name="property_type"><option value="">اختر</option><option>وحدة جاهزة</option><option>بناء ذاتي</option><option>رهن عقاري</option></select></div><div class="field"><label for="employer_type">جهة العمل</label><select id="employer_type" name="employer_type"><option value="">اختر</option><option>حكومي مدني</option><option>حكومي عسكري</option><option>شبه حكومي</option><option>قطاع خاص</option><option>متقاعد</option></select></div></div>'
        . '<label class="consent"><input type="checkbox" name="privacy_consent" value="1" checked required><span>أوافق على <a href="/سياسة-الخصوصية/" target="_blank" rel="noopener">سياسة الخصوصية</a> والتواصل معي بخصوص الطلب.</span></label><button class="btn btn-primary" type="submit">إرسال طلب التقييم</button><div class="form-status" id="formStatus" role="status" aria-live="polite"></div></form></aside>';
}
function trustBar(): string {
    $items = [['chart','تحليل الحالة','الدخل والالتزامات والاستقطاع'],['wallet','حلول مالية','مسارات قابلة للدراسة حسب الحالة'],['home','فرص عقارية','ربط القدرة بخيارات التملك'],['shield','خصوصية البيانات','حفظ آمن ومتابعة واضحة']];
    $html = '<section class="trustbar"><div class="container trustgrid">';
    foreach ($items as [$ic,$title,$sub]) $html .= '<div class="trustitem"><span class="icon">' . icon($ic) . '</span><div><strong>' . e($title) . '</strong><small>' . e($sub) . '</small></div></div>';
    return $html . '</div></section>';
}
function serviceCards(): string {
    $cards = [
        ['/رفض-التمويل-العقاري/','shield','رفض التمويل','مراجعة أسباب الرفض ومبلغ التمويل والدفعة الأولى ونسبة الاستقطاع.'],
        ['/تمويل-عقاري-مع-التزامات/','wallet','الالتزامات والقرض الشخصي','فهم أثر التمويل الشخصي والسيارة والبطاقات على قدرة التملك.'],
        ['/شراء-مديونية-عقارية/','chart','المديونية وإعادة التمويل','توضيح خيارات نقل المديونية وإعادة التمويل وفك الرهن.'],
        ['/شراء-عقار-بالتمويل/','home','شراء العقار','ربط العقار المناسب بالقدرة التمويلية والهدف السكني.'],
    ];
    $html = '<div class="cards">';
    foreach ($cards as [$url,$ic,$title,$text]) $html .= '<article class="card"><span class="icon">' . icon($ic) . '</span><h3>' . e($title) . '</h3><p>' . e($text) . '</p><a href="' . e($url) . '">اعرف التفاصيل ←</a></article>';
    return $html . '</div>';
}
function pageHtml(string $path, array $p, string $leadEndpoint): string {
    $chips = ''; foreach ($p['chips'] as $chip) $chips .= '<span class="chip">' . icon('check') . e($chip) . '</span>';
    $wa = 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . rawurlencode('مرحبًا أركان التنفيذية، أرغب في استشارة بخصوص ' . $p['tag'] . '.');
    $heroStyle = '--hero-image:url(' . e($p['hero']) . ')';
    $body = '<body class="' . e($p['theme']) . '">' . headerHtml()
        . '<main><section class="hero" style="' . $heroStyle . '"><div class="hero-overlay"></div><div class="container hero-grid"><div class="hero-copy"><span class="tag">' . e($p['tag']) . '</span><h1>' . e($p['h1']) . '</h1><p>' . e($p['intro']) . '</p><div class="chips">' . $chips . '</div><div class="hero-note">' . icon('shield') . '<span>أركان التنفيذية تقدم حلولًا واستشارات مالية وعقارية، وليست بنكًا ولا تمنح قرضًا مباشرًا أو تضمن موافقة جهة تمويل.</span></div></div>' . formHtml($p['id']) . '</div></section>'
        . trustBar()
        . '<section class="section section-white"><div class="container"><div class="section-head"><h2>' . e($p['section_title']) . '</h2><p>' . e($p['section_intro']) . '</p></div>' . serviceCards() . '</div></section>'
        . '<section class="section section-ice"><div class="container"><div class="section-head"><span class="tag light-tag">خطوات واضحة</span><h2 class="top-gap">من الطلب إلى الخطوة التالية</h2><p>أرسل بياناتك الأساسية، وسيتواصل معك الفريق لفهم حالتك وتوضيح الخيارات المتاحة.</p></div><div class="steps"><article class="step"><h3>أرسل البيانات الأساسية</h3><p>الاسم والجوال والمدينة، مع اختيار نوع العقار أو جهة العمل على الأقل.</p></article><article class="step"><h3>نراجع حالتك</h3><p>نفهم الهدف والمشكلة ونحدد المعلومات الإضافية المطلوبة عند الحاجة.</p></article><article class="step"><h3>نوضح المسار المناسب</h3><p>نتواصل معك لشرح الخيارات والخطوة التالية بصورة واضحة.</p></article></div></div></section>'
        . '<section class="section brand-band"><div class="container brand-grid"><div><span class="tag">أركان التنفيذية</span><h2 class="top-gap">حل مالي وعقاري تحت سقف واحد</h2><p>نجمع بين فهم القدرة المالية والخبرة العقارية لمساعدتك على اتخاذ خطوة أوضح نحو التملك.</p></div><div class="brand-points"><div class="brand-point"><span class="icon">' . icon('chart') . '</span><div><strong>تحليل الالتزامات والقدرة</strong><br><small>فهم الصورة المالية قبل اختيار المسار.</small></div></div><div class="brand-point"><span class="icon">' . icon('home') . '</span><div><strong>خيارات عقارية مناسبة</strong><br><small>ربط الهدف العقاري بالقدرة الفعلية.</small></div></div><div class="brand-point"><span class="icon">' . icon('shield') . '</span><div><strong>وضوح بلا وعود مضللة</strong><br><small>كل حالة تخضع لمتطلبات الجهات ذات العلاقة.</small></div></div></div></div></section>'
        . '<section class="section section-mist"><div class="container faq"><div class="section-head"><h2>الأسئلة الشائعة</h2><p>إجابات مباشرة قبل إرسال طلب التقييم.</p></div><details><summary>هل أركان بنك أو جهة تمويل؟</summary><p>لا. أركان التنفيذية تقدم حلولًا واستشارات مالية وعقارية ولا تمنح قرضًا مباشرًا.</p></details><details><summary>هل يمكن ضمان قبول التمويل؟</summary><p>لا يمكن ضمان موافقة أي جهة. الهدف هو دراسة الحالة وتوضيح المسارات التي يمكن بحثها وفق المتطلبات.</p></details><details><summary>هل الاستشارة الأولية مجانية؟</summary><p>نعم، التقييم الأولي للبيانات الأساسية مجاني، وقد نحتاج معلومات إضافية لفهم الحالة بصورة أدق.</p></details><details><summary>هل يجب اختيار نوع العقار وجهة العمل معًا؟</summary><p>لا، يكفي اختيار واحد منهما على الأقل لإرسال الطلب، ويمكن اختيار الاثنين إذا كانت البيانات متاحة.</p></details></div></section>'
        . '<section class="contact-band"><div class="container contact-grid"><div><h2>ابدأ تقييم حالتك الآن</h2><p>أرسل البيانات الأساسية أولًا، وبعد تسجيل الطلب ستظهر لك خيارات التواصل المباشر.</p></div><div class="contact-actions"><a class="btn btn-ghost" href="#form">انتقل إلى نموذج التقييم</a></div></div></section></main>'
        . footerHtml() . floatingButtons($wa) . scriptsHtml($leadEndpoint) . '</body>';
    return '<!doctype html><html lang="ar" dir="rtl">' . headHtml($p['title'], $p['description'], $path, $p['hero']) . $body . '</html>';
}
function privacyHtml(): string {
    $body = '<body>' . headerHtml()
        . '<main class="section section-ice"><div class="container privacy"><h1>سياسة الخصوصية</h1><p>توضح هذه السياسة كيفية جمع واستخدام وحماية البيانات التي يرسلها العميل عبر صفحات أركان التنفيذية.</p><h2>البيانات التي نجمعها</h2><p>الاسم، رقم الجوال، المدينة، نوع العقار و/أو جهة العمل، حالة الموافقة، بالإضافة إلى بيانات المصدر والحملة ومعرّفات النقر عند توفرها.</p><h2>الغرض من الاستخدام</h2><p>دراسة الطلب، التواصل مع العميل، تصنيف الحالة، تحسين جودة الخدمة والحملات، ومنع الطلبات المكررة.</p><h2>المشاركة والوصول</h2><p>يقتصر الوصول على الأشخاص والجهات المصرح لها لخدمة الطلب، ولا تُنشر بيانات العملاء للعامة أو تُستخدم خارج الأغراض الموضحة دون أساس نظامي.</p><h2>الاحتفاظ والحماية</h2><p>تُحفظ البيانات للمدة اللازمة لمتابعة الطلب والالتزامات النظامية، مع تطبيق ضوابط وصول مناسبة.</p><h2>حقوق صاحب البيانات</h2><p>يمكن طلب الاستفسار أو التصحيح أو الحذف - وفق ما يسمح به النظام - عبر الاتصال أو واتساب على <a href="tel:' . PHONE_E164 . '">' . phoneHtml() . '</a>.</p><h2>الموافقة والتحديث</h2><p>إرسال النموذج يعني الإقرار بالاطلاع على هذه السياسة والموافقة على التواصل بخصوص الطلب.</p><p><strong>آخر تحديث:</strong> 2 أغسطس 2026.</p></div></main>' . footerHtml() . '</body>';
    return '<!doctype html><html lang="ar" dir="rtl">' . headHtml('سياسة الخصوصية | أركان التنفيذية', 'سياسة جمع واستخدام وحماية بيانات طلبات التواصل لدى أركان التنفيذية.', '/سياسة-الخصوصية/') . $body . '</html>';
}
function thankYouHtml(): string {
    $body = '<body><main class="thank-page"><section class="thank-card">' . logoHtml() . '<h1>تم استلام طلبك</h1><p>تم تسجيل بياناتك بنجاح. اختر الطريقة الأنسب للتواصل المباشر الآن.</p><div class="thank-actions"><a id="afterFormWa" class="btn btn-primary" href="https://wa.me/' . WHATSAPP_NUMBER . '" target="_blank" rel="noopener">' . icon('whatsapp') . 'متابعة عبر واتساب</a><a id="afterFormCall" class="btn btn-ghost" href="tel:' . PHONE_E164 . '">' . icon('phone') . 'اتصال مباشر</a><a class="btn btn-ghost" href="/">العودة للرئيسية</a></div></section></main><script src="/assets/thank-you.js?v=5" defer></script></body>';
    return '<!doctype html><html lang="ar" dir="rtl">' . headHtml('تم استلام طلبك | أركان التنفيذية', 'صفحة تأكيد طلب التواصل لدى أركان التنفيذية.', '/تم-استلام-الطلب/') . $body . '</html>';
}
