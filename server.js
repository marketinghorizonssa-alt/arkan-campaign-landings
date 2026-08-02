const http = require('http');

const pages = {
  '/': {
    title: 'أركان التنفيذية | حلول عقارية ومالية تساعدك على التملك',
    description: 'حلول واستشارات عقارية ومالية لمشكلات الأهلية والالتزامات ومبلغ التمويل وشراء العقار.',
    eyebrow: 'حلول عقارية ومالية مخصصة',
    h1: 'مشكلة الأهلية أو الالتزامات لا تعني أن حلم التملك انتهى',
    intro: 'نراجع وضعك ونفهم سبب تعثر المسار ونساعدك على اختيار الحل العقاري والمالي الأنسب دون ادعاء منح تمويل أو ضمان موافقة جهة تمويل.',
    pageId: 'P1'
  },
  '/solutions/': {
    title: 'حلول واستشارات التمويل العقاري | أركان التنفيذية',
    description: 'استشارات وحلول عقارية ومالية تساعدك على فهم خياراتك واختيار المسار الأنسب.',
    eyebrow: 'حلول واستشارات',
    h1: 'حلول واستشارات تساعدك على اختيار المسار العقاري والمالي الأنسب',
    intro: 'ندرس وضعك والخيارات المتاحة ونوضح لك المسار المناسب لشراء العقار دون ادعاء منح التمويل أو ضمان الموافقة.',
    pageId: 'P1'
  },
  '/rejection/': {
    title: 'حل رفض التمويل ورفع القدرة التمويلية | أركان التنفيذية',
    description: 'حلول لمشكلة رفض التمويل وانخفاض مبلغ التمويل والدفعة الأولى ونسبة الاستقطاع.',
    eyebrow: 'حل رفض التمويل',
    h1: 'حلول رفض التمويل العقاري ورفع القدرة التمويلية',
    intro: 'نراجع أسباب الرفض ونسبة الاستقطاع ومبلغ التمويل والدفعة الأولى ونحدد المسارات العملية المناسبة للحالة.',
    pageId: 'P2'
  },
  '/obligations/': {
    title: 'تمويل عقاري مع قرض شخصي أو التزامات | أركان التنفيذية',
    description: 'تحليل أثر القرض الشخصي والالتزامات على القدرة التمويلية قبل شراء العقار.',
    eyebrow: 'القرض والالتزامات',
    h1: 'حلول التمويل العقاري مع قرض شخصي أو التزامات قائمة',
    intro: 'نحلل أثر القرض الشخصي والالتزامات على الاستحقاق ونرتب الخيارات الممكنة قبل شراء العقار.',
    pageId: 'P3'
  },
  '/debt/': {
    title: 'شراء المديونية وإعادة التمويل وفك الرهن | أركان التنفيذية',
    description: 'استشارات شراء ونقل المديونية وإعادة التمويل العقاري وفك الرهن وإعادة الجدولة.',
    eyebrow: 'حلول المديونية',
    h1: 'استشارات شراء المديونية وإعادة التمويل العقاري وفك الرهن',
    intro: 'نوضح الخيارات والمتطلبات ونساعدك على اختيار المسار المناسب. أركان ليست بنكًا ولا تمنح قرضًا مباشرًا.',
    pageId: 'P4'
  },
  '/property/': {
    title: 'حلول شراء عقار بالتمويل البنكي | أركان التنفيذية',
    description: 'حلول شراء بيت أو فيلا أو شقة أو أرض بما يتناسب مع قدرتك التمويلية والتزاماتك.',
    eyebrow: 'حلول شراء العقار',
    h1: 'حلول شراء عقار يناسب قدرتك التمويلية',
    intro: 'نساعدك على فهم المتطلبات واختيار العقار والمسار المناسب لمبلغ التمويل والالتزامات الحالية.',
    pageId: 'P5'
  }
};

const css = `
:root{--green:#173b35;--gold:#b08a4a;--cream:#f7f3ea;--text:#1d2725;--muted:#66736f}*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font-family:Tahoma,Arial,sans-serif;color:var(--text);background:#fff;line-height:1.75}a{color:inherit;text-decoration:none}.container{width:min(1140px,92%);margin:auto}.review{background:#fff3cd;color:#6d5200;text-align:center;padding:7px;font-size:13px}.header{position:sticky;top:0;background:#ffffffed;backdrop-filter:blur(12px);border-bottom:1px solid #e6ece9;z-index:20}.nav{min-height:76px;display:flex;align-items:center;justify-content:space-between;gap:22px}.brand{font-size:25px;font-weight:800;color:var(--green)}.brand span{color:var(--gold)}.navlinks{display:flex;gap:20px;font-size:14px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:12px;padding:13px 20px;font-weight:700;cursor:pointer}.btn-dark{background:var(--green);color:#fff}.btn-primary{background:var(--gold);color:#fff}.hero{background:linear-gradient(135deg,#f9f6ee,#edf5f1);padding:70px 0}.hero-grid{display:grid;grid-template-columns:1.15fr .85fr;gap:44px;align-items:center}.eyebrow{display:inline-block;background:#e6efe9;color:var(--green);padding:6px 12px;border-radius:999px;font-size:13px;font-weight:700}h1{font-size:clamp(34px,5vw,58px);line-height:1.22;margin:18px 0;color:var(--green)}h2{font-size:32px;color:var(--green);line-height:1.35}.hero p,.lead{font-size:18px;color:var(--muted)}.bullets{display:grid;gap:10px;margin:24px 0}.bullet{background:#fff;border-radius:12px;padding:12px 15px;border:1px solid #e1e8e4}.disclaimer{font-size:13px;color:#6a746f;border-right:3px solid var(--gold);padding-right:12px}.form-card{background:#fff;border-radius:22px;padding:26px;box-shadow:0 18px 60px #173b3520}.form-card h2{margin:0;font-size:27px}.field{margin-top:14px}.field label{display:block;font-size:14px;font-weight:700;margin-bottom:5px}.field input,.field select{width:100%;height:49px;border:1px solid #cfd9d4;border-radius:10px;padding:0 12px;font:inherit;background:#fff}.two{display:grid;grid-template-columns:1fr 1fr;gap:12px}.consent{display:flex;gap:8px;align-items:flex-start;margin-top:15px;font-size:13px}.section{padding:70px 0}.alt{background:var(--cream)}.cards,.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}.card,.step{background:#fff;border:1px solid #e1e7e4;border-radius:17px;padding:22px}.card h3,.step h3{color:var(--green);margin-top:0}.faq details{border-bottom:1px solid #ddd;padding:17px 0}.faq summary{font-weight:700;cursor:pointer}.footer{background:var(--green);color:#dce8e3;padding:40px 0}.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:30px}.floating{position:fixed;bottom:22px;z-index:30}.floating.right{right:20px}.floating.left{left:20px}.float{width:56px;height:56px;border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:700;box-shadow:0 8px 25px #0003}.wa{background:#21a366}.call{background:var(--green)}.thank{min-height:80vh;display:grid;place-items:center;background:var(--cream)}.thank-card{background:#fff;padding:40px;border-radius:20px;text-align:center;max-width:650px}.status{font-size:13px;margin-top:10px}@media(max-width:820px){.navlinks{display:none}.hero{padding:42px 0}.hero-grid{grid-template-columns:1fr}.form-card{order:2}.cards,.steps,.footer-grid{grid-template-columns:1fr}.two{grid-template-columns:1fr}h1{font-size:36px}}
`;

function form(pageId){return `<div class="form-card" id="form"><h2>ابدأ تقييم حالتك</h2><p>خمسة بيانات فقط، ونراجع لك المسار المناسب.</p><form id="leadForm"><input type="hidden" name="landing_page_id" value="${pageId}"><div class="field"><label>الاسم</label><input name="full_name" autocomplete="name" required minlength="2"></div><div class="field"><label>رقم الجوال</label><input name="phone" type="tel" inputmode="tel" autocomplete="tel" required placeholder="05xxxxxxxx"></div><div class="field"><label>المدينة</label><input name="city" autocomplete="address-level2" required></div><div class="two"><div class="field"><label>نوع العقار</label><select name="property_type" required><option value="">اختر</option><option>وحدة جاهزة</option><option>بناء ذاتي</option><option>رهن عقاري</option></select></div><div class="field"><label>جهة العمل</label><select name="employer_type" required><option value="">اختر</option><option>حكومي مدني</option><option>حكومي عسكري</option><option>شبه حكومي</option><option>قطاع خاص</option><option>متقاعد</option></select></div></div><label class="consent"><input type="checkbox" required> أوافق على <a href="/privacy/" target="_blank" style="text-decoration:underline">سياسة الخصوصية</a> والتواصل بشأن طلبي.</label><button class="btn btn-primary" type="submit" style="width:100%;margin-top:15px">أرسل طلب التقييم</button><div class="status" id="status"></div></form></div>`}

function layout(p){return `<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${p.title}</title><meta name="description" content="${p.description}"><link rel="canonical" href="https://arkan-v2.hositee.com${p.path||'/'}"><style>${css}</style></head><body><div class="review">نسخة مراجعة — استقبال الطلبات غير مفعّل حتى اعتماد الأرقام والربط</div><header class="header"><div class="container nav"><a class="brand" href="/">أركان <span>التنفيذية</span></a><nav class="navlinks"><a href="/solutions/">الحلول</a><a href="/rejection/">رفض التمويل</a><a href="/obligations/">الالتزامات</a><a href="/debt/">المديونية</a><a href="/property/">شراء العقار</a></nav><a class="btn btn-dark" href="#form">ابدأ تقييم حالتك</a></div></header><main><section class="hero"><div class="container hero-grid"><div><span class="eyebrow">${p.eyebrow}</span><h1>${p.h1}</h1><p>${p.intro}</p><div class="bullets"><div class="bullet">دراسة المشكلة قبل اقتراح المسار</div><div class="bullet">نموذج مختصر يركز على جودة العميل</div><div class="bullet">حلول عقارية ومالية تحت سقف واحد</div></div><div class="disclaimer">أركان التنفيذية ليست بنكًا ولا تمنح قرضًا مباشرًا. كل حالة تخضع لمتطلبات الجهات ذات العلاقة.</div></div>${form(p.pageId)}</div></section><section class="section"><div class="container"><h2>كيف نساعدك؟</h2><div class="cards"><div class="card"><h3>نفهم الحالة</h3><p>نحدد سبب المشكلة والهدف العقاري المطلوب.</p></div><div class="card"><h3>نراجع الخيارات</h3><p>نقارن المسارات المتاحة بما يناسب الالتزامات والقدرة.</p></div><div class="card"><h3>نوضح الخطوة التالية</h3><p>تحصل على توجيه واضح دون وعود غير واقعية.</p></div></div></div></section><section class="section alt"><div class="container faq"><h2>الأسئلة الشائعة</h2><details><summary>هل أركان بنك أو جهة تمويل؟</summary><p>لا، أركان تقدم حلولًا واستشارات عقارية ومالية ولا تمنح قرضًا مباشرًا.</p></details><details><summary>هل الموافقة مضمونة؟</summary><p>لا يمكن ضمان موافقة أي جهة؛ كل حالة تخضع للسياسات والمتطلبات.</p></details><details><summary>لماذا النموذج مختصر؟</summary><p>نطلب فقط البيانات الأساسية لتوجيه الحالة ثم نستكمل التفاصيل عند الحاجة.</p></details></div></section></main><footer class="footer"><div class="container footer-grid"><div><strong>أركان التنفيذية</strong><p>حلول عقارية ومالية تساعدك على اتخاذ خطوة أوضح نحو التملك.</p></div><div><a href="/privacy/">سياسة الخصوصية</a></div><div>© 2026 أركان التنفيذية</div></div></footer><div class="floating right"><a class="float wa" href="#form">وات</a></div><div class="floating left"><a class="float call" href="#form">اتصل</a></div><script>const f=document.getElementById('leadForm');if(f){f.addEventListener('submit',e=>{e.preventDefault();document.getElementById('status').textContent='النموذج في وضع المراجعة ولم يتم تفعيل الإرسال بعد.'})}</script></body></html>`}

function privacy(){return `<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>سياسة الخصوصية | أركان التنفيذية</title><style>${css}</style></head><body><header class="header"><div class="container nav"><a class="brand" href="/">أركان <span>التنفيذية</span></a></div></header><main class="section"><div class="container"><h1>سياسة الخصوصية</h1><p>توضح هذه الصفحة آلية جمع واستخدام بيانات طلبات التواصل الخاصة بأركان التنفيذية.</p><h2>البيانات التي نجمعها</h2><p>الاسم ورقم الجوال والمدينة ونوع العقار وجهة العمل، بالإضافة إلى بيانات المصدر والحملة ومعرّفات النقر عند توفرها.</p><h2>الغرض من المعالجة</h2><p>دراسة الطلب والتواصل مع صاحبه وتحسين جودة الحملات والخدمة ومنع الطلبات المكررة.</p><h2>المشاركة والحماية</h2><p>تُقيد صلاحية الوصول للجهات المصرح لها، ولا تُنشر بيانات العملاء للعامة. سيتم اعتماد الاسم القانوني ووسيلة طلبات الخصوصية قبل الإطلاق الإنتاجي.</p><h2>الموافقة والتحديث</h2><p>إرسال النموذج يعني الموافقة على استخدام البيانات لغرض متابعة الطلب. قد تُحدث هذه السياسة عند تغير آلية المعالجة.</p></div></main></body></html>`}

function thankYou(){return `<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تم استلام طلبك | أركان التنفيذية</title><style>${css}</style></head><body><main class="thank"><div class="thank-card"><div class="brand">أركان <span>التنفيذية</span></div><h1>تم استلام طلبك</h1><p>سيتم مراجعة بياناتك والتواصل معك وفق آلية الاستقبال المعتمدة.</p><a class="btn btn-dark" href="/">العودة للرئيسية</a></div></main></body></html>`}

const server=http.createServer((req,res)=>{const url=new URL(req.url,'http://localhost');let path=url.pathname;if(path==='/privacy/'||path==='/privacy'){res.setHeader('content-type','text/html; charset=utf-8');return res.end(privacy())}if(path==='/thank-you/'||path==='/thank-you'){res.setHeader('content-type','text/html; charset=utf-8');return res.end(thankYou())}if(path==='/robots.txt'){res.setHeader('content-type','text/plain');return res.end('User-agent: *\nAllow: /\nSitemap: https://arkan-v2.hositee.com/sitemap.xml\n')}if(path==='/sitemap.xml'){res.setHeader('content-type','application/xml');return res.end('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'+Object.keys(pages).map(x=>'<url><loc>https://arkan-v2.hositee.com'+x+'</loc></url>').join('')+'<url><loc>https://arkan-v2.hositee.com/privacy/</loc></url></urlset>')}const p=pages[path.endsWith('/')?path:path+'/']||pages[path];if(!p){res.statusCode=404;res.setHeader('content-type','text/html; charset=utf-8');return res.end('<h1>الصفحة غير موجودة</h1><a href="/">الرئيسية</a>')}p.path=path;res.setHeader('content-type','text/html; charset=utf-8');res.setHeader('x-content-type-options','nosniff');res.setHeader('referrer-policy','strict-origin-when-cross-origin');res.end(layout(p))});
server.listen(process.env.PORT||3000,'0.0.0.0');
