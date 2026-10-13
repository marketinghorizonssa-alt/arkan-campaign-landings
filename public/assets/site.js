(()=>{
  const cfg=window.ARKAN_CONFIG||{};
  // Preserve the existing container as owner when running on a legacy static export.
  const legacyTracking=typeof window.__loadArkanGtm==='function';
  const legacyOwnerReady=legacyTracking&&!!window.__arkanGtmListenerInstalled;
  if(legacyTracking&&!legacyOwnerReady){
    window.__arkanGtmListenerInstalled=true;window.dataLayer=window.dataLayer||[];window.gtag=window.gtag||function(){window.dataLayer.push(arguments)};
    const tag=document.createElement('script');tag.async=true;tag.src='https://www.googletagmanager.com/gtag/js?id=AW-18351042794';document.head.appendChild(tag);
    window.gtag('js',new Date());window.gtag('config','AW-18351042794');
  }
  const form=document.getElementById('leadForm');
  const saveClick=(kind,link,extra={})=>{
    const endpoint=new URL(cfg.endpoint||'/api/lead',location.href);endpoint.pathname=endpoint.pathname.replace(/\/api\/lead$/,'/api/click');endpoint.search='';
    const placement=link.closest?.('.hero-actions')?'hero':link.closest?.('.floating-stack')?'floating':link.closest?.('.header')?'header':'content';
    const event_id=crypto.randomUUID?crypto.randomUUID():Date.now()+'-'+Math.random().toString(16).slice(2);
    fetch(endpoint.toString(),{method:'POST',headers:{'Content-Type':'application/json'},keepalive:true,body:JSON.stringify({event_id,event_type:kind,placement,landing_path:location.pathname,landing_page_id:form?.elements.landing_page_id?.value||'',...extra})}).catch(()=>null);
  };
  if(!form){
    document.addEventListener('click',e=>{const a=e.target.closest?.('a');if(!a)return;const kind=a.classList.contains('track-whatsapp')?'whatsapp':a.classList.contains('track-call')?'call':'';if(!kind)return;saveClick(kind,a);if(!legacyOwnerReady&&typeof window.gtag==='function')window.gtag('event','conversion',{send_to:kind==='whatsapp'?'AW-18351042794/XQr5CLTk49scEOrhuq5E':'AW-18351042794/x8TACLfk49scEOrhuq5E'});},{passive:true});return;
  }
  if(legacyTracking){
    const profiles={"P0":{"id":"P0","title":"أركان التنفيذية | حلول مالية وعقارية متكاملة","description":"حلول واستشارات مالية وعقارية لمشكلات الأهلية ونسبة الاستقطاع والالتزامات وشراء العقار في السعودية.","tag":"حلول مالية وعقارية متكاملة","h1":"عقار يناسب ميزانيتك، وخطوة تمويل أوضح","intro":"نساعدك في اختيار شقة أو فيلا أو أرض تناسب ميزانيتك، ونراجع خيارات التمويل والالتزامات مع الجهات التمويلية حتى تتضح لك الخطوة المناسبة للتملك.","chips":["خيارات حسب ميزانيتك","تنسيق مع الجهات التمويلية","متابعة طلبك"],"section_title":"ابدأ من احتياجك: عقار مناسب أو حلول للتمويل","section_intro":"اختر مسار البحث عن عقار، أو مسار مراجعة التمويل والمديونية. لكل خدمة صفحة وطلب مخصصان.","hero":"/assets/hero-home.jpg","theme":"theme-home","path":"/"},"P1":{"id":"P1","title":"حلول التمويل العقاري والاستشارات | أركان التنفيذية","description":"حلول واستشارات تساعدك على فهم مشكلة التمويل العقاري واختيار المسار الأنسب لحالتك.","tag":"حلول التمويل العقاري","h1":"حلول واستشارات تساعدك على اختيار المسار العقاري والمالي الأنسب","intro":"نراجع الهدف العقاري والدخل والالتزامات الحالية، ثم نوضح الخيارات العملية التي يمكن دراستها دون وعود غير واقعية.","chips":["فهم الأهلية","مراجعة الالتزامات","اختيار المسار"],"section_title":"ما الذي نراجعه في التقييم الأولي؟","section_intro":"نركز على العناصر التي تؤثر مباشرة في القدرة التمويلية وفرص التملك المناسبة.","hero":"/assets/hero-finance.jpg","theme":"theme-finance","path":"/حلول-التمويل-العقاري/"},"P2":{"id":"P2","title":"حل رفض التمويل العقاري ورفع القدرة | أركان التنفيذية","description":"دراسة أسباب رفض التمويل العقاري وانخفاض مبلغ التمويل والدفعة الأولى ونسبة الاستقطاع.","tag":"رفض التمويل والقدرة","h1":"رفض التمويل لا يعني أن فرصة التملك انتهت","intro":"نراجع سبب الرفض ونسبة الاستقطاع ومبلغ التمويل والدفعة الأولى، ونحدد المسارات التي يمكن دراستها وفق حالتك.","chips":["سبب الرفض","نسبة الاستقطاع","الدفعة الأولى"],"section_title":"أسباب شائعة تحتاج مراجعة أدق","section_intro":"الهدف ليس تقديم وعد بالموافقة، بل فهم السبب وتحديد الخيارات الواقعية المتاحة.","hero":"/assets/hero-rejection.jpg","theme":"theme-rejection","path":"/رفض-التمويل-العقاري/"},"P3":{"id":"P3","title":"تمويل عقاري مع قرض شخصي أو التزامات | أركان التنفيذية","description":"تحليل أثر القرض الشخصي والالتزامات على التمويل العقاري واختيار المسار المناسب.","tag":"القرض الشخصي والالتزامات","h1":"حلول التمويل العقاري مع قرض شخصي أو التزامات قائمة","intro":"نحلل أثر التمويل الشخصي والسيارة والبطاقات والالتزامات الأخرى على قدرتك، ونرتب الخيارات قبل اختيار العقار أو التقديم.","chips":["تحليل الالتزامات","مراجعة الاستقطاع","ترتيب الأولويات"],"section_title":"رتّب التزاماتك قبل قرار التملك","section_intro":"صورة الالتزامات الكاملة تساعد على تجنب قرارات متسرعة واختيار عقار يناسب القدرة الفعلية.","hero":"/assets/hero-obligations.jpg","theme":"theme-obligations","path":"/تمويل-عقاري-مع-التزامات/"},"P4":{"id":"P4","title":"شراء المديونية وإعادة التمويل وفك الرهن | أركان التنفيذية","description":"استشارات شراء ونقل المديونية وإعادة التمويل العقاري وفك الرهن وإعادة الجدولة.","tag":"المديونية وإعادة التمويل","h1":"شراء المديونية ونقلها وإعادة التمويل العقاري","intro":"نراجع مديونيتك العقارية والشخصية ونوضح خيارات نقل المديونية وإعادة التمويل وفك الرهن لدى الجهات التمويلية، وفق متطلبات كل جهة وحالتك الفعلية.","chips":["نقل المديونية","إعادة التمويل","فك الرهن"],"section_title":"افهم الخيارات قبل اتخاذ القرار","section_intro":"تختلف المتطلبات حسب الحالة والجهة، لذلك تبدأ الخطوة الصحيحة بمراجعة المعلومات الأساسية.","hero":"/assets/hero-debt.jpg","theme":"theme-debt","path":"/شراء-مديونية-عقارية/"},"P5":{"id":"P5","title":"عقارات تناسب ميزانيتك: شقق وفلل وأراضٍ | أركان التنفيذية","description":"حلول شراء بيت أو فيلا أو شقة أو أرض بما يتناسب مع القدرة التمويلية والالتزامات.","tag":"اختيار العقار والمسار","h1":"شقق وفلل وأراضٍ تناسب ميزانيتك","intro":"نساعدك في البحث عن شقق تمليك وفلل وأراضٍ وفق ميزانيتك والمدينة المطلوبة، مع مراجعة خيارات الشراء نقدًا أو عن طريق البنك بحسب حالتك وتوافر العقارات.","chips":["وحدات جاهزة","بناء ذاتي","رهن عقاري"],"section_title":"عقار مناسب يبدأ بحسبة مناسبة","section_intro":"نربط بين الهدف العقاري والقدرة المالية بدل اختيار وحدة لا تتناسب مع الوضع الحالي.","hero":"/assets/hero-property.jpg","theme":"theme-property","path":"/شراء-عقار-بالتمويل/"},"P6":{"id":"P6","title":"تسهيل إجراءات التمويل الشخصي للتملك | أركان التنفيذية","description":"نراجع التمويل الشخصي مع الالتزامات الحالية، وننسق مع الجهات التمويلية لدراسة الخيارات المرتبطة بشراء العقار. التمويل يصدر من الجهة التمويلية وفق متطلباتها.","tag":"تسهيل إجراءات التمويل الشخصي للتملك","h1":"نوضح دور التمويل الشخصي في خطة شراء العقار","intro":"نراجع التمويل الشخصي مع الالتزامات الحالية، وننسق مع الجهات التمويلية لدراسة الخيارات المرتبطة بشراء العقار. التمويل يصدر من الجهة التمويلية وفق متطلباتها.","section_title":"خطوة أوضح تبدأ بطلبك","section_intro":"نراجع الاحتياج والميزانية والمعلومات الأساسية، ثم نوضح الخيارات والخطوة المناسبة.","hero":"/assets/hero-obligations.jpg","theme":"theme-obligations","chips":["حسب حالتك","خيارات واضحة","متابعة من الفريق"],"path":"/تسهيلات-التمويل-الشخصي-للعقار/"},"P7":{"id":"P7","title":"حلول التعثر والمديونية العقارية | أركان التنفيذية","description":"نراجع وضع المديونية والالتزامات ونوضح مسارات السداد وإعادة الجدولة أو إعادة التمويل التي يمكن دراستها مع الجهات ذات العلاقة بحسب حالتك.","tag":"حلول التعثر والمديونية العقارية","h1":"مديونية عقارية تعطل خطتك؟ ابدأ بمراجعة حالتك","intro":"نراجع وضع المديونية والالتزامات ونوضح مسارات السداد وإعادة الجدولة أو إعادة التمويل التي يمكن دراستها مع الجهات ذات العلاقة بحسب حالتك.","section_title":"خطوة أوضح تبدأ بطلبك","section_intro":"نراجع الاحتياج والميزانية والمعلومات الأساسية، ثم نوضح الخيارات والخطوة المناسبة.","hero":"/assets/hero-debt.jpg","theme":"theme-debt","chips":["حسب حالتك","خيارات واضحة","متابعة من الفريق"],"path":"/حلول-التعثر-والمديونية-العقارية/"},"P8":{"id":"P8","title":"شقق تمليك جدة | أركان التنفيذية","description":"نساعدك في اختيار شقة مناسبة للموقع والميزانية واحتياج أسرتك، مع توضيح خيارات الشراء والتملك بحسب العقارات المتاحة.","tag":"شقق تمليك جدة","h1":"ابحث عن شقة تمليك في جدة تناسب ميزانيتك","intro":"نساعدك في اختيار شقة مناسبة للموقع والميزانية واحتياج أسرتك، مع توضيح خيارات الشراء والتملك بحسب العقارات المتاحة.","section_title":"خطوة أوضح تبدأ بطلبك","section_intro":"نراجع الاحتياج والميزانية والمعلومات الأساسية، ثم نوضح الخيارات والخطوة المناسبة.","hero":"/assets/hero-property.jpg","theme":"theme-property","chips":["حسب حالتك","خيارات واضحة","متابعة من الفريق"],"path":"/شقق-تمليك-جدة/"},"P9":{"id":"P9","title":"فلل للبيع جدة | أركان التنفيذية","description":"حدد المدينة والميزانية وأرسل طلبك، وسيساعدك فريق أركان في البحث عن خيارات الفلل المناسبة والتنسيق لاستكمال إجراءات الشراء.","tag":"فلل للبيع جدة","h1":"فيلا في جدة تناسب ميزانيتك واحتياجك","intro":"حدد المدينة والميزانية وأرسل طلبك، وسيساعدك فريق أركان في البحث عن خيارات الفلل المناسبة والتنسيق لاستكمال إجراءات الشراء.","section_title":"خطوة أوضح تبدأ بطلبك","section_intro":"نراجع الاحتياج والميزانية والمعلومات الأساسية، ثم نوضح الخيارات والخطوة المناسبة.","hero":"/assets/hero-property.jpg","theme":"theme-property","chips":["حسب حالتك","خيارات واضحة","متابعة من الفريق"],"path":"/فلل-للبيع-جدة/"},"P10":{"id":"P10","title":"أراضٍ للبيع جدة | أركان التنفيذية","description":"نساعدك في مراجعة خيارات الأراضي بحسب الموقع والميزانية وهدف الشراء، ونوضح متطلبات الخطوة التالية وفق الخيارات المتاحة.","tag":"أراضٍ للبيع جدة","h1":"ابحث عن أرض في جدة وفق ميزانيتك وهدفك","intro":"نساعدك في مراجعة خيارات الأراضي بحسب الموقع والميزانية وهدف الشراء، ونوضح متطلبات الخطوة التالية وفق الخيارات المتاحة.","section_title":"خطوة أوضح تبدأ بطلبك","section_intro":"نراجع الاحتياج والميزانية والمعلومات الأساسية، ثم نوضح الخيارات والخطوة المناسبة.","hero":"/assets/hero-property.jpg","theme":"theme-property","chips":["حسب حالتك","خيارات واضحة","متابعة من الفريق"],"path":"/أراضي-للبيع-جدة/"}};
    const publicLandingUrl=id=>{const bases={P6:'P1',P7:'P4',P8:'P5',P9:'P5',P10:'P5'};const base=profiles[bases[id]||id]?.path||'/';return Number(id.slice(1))>=6?base+'?lp='+id:base;};
    const requested=new URLSearchParams(location.search).get('lp');
    const id=Object.hasOwn(profiles,requested||'')?requested:(form.elements.landing_page_id?.value||'P0'),profile=profiles[id],hero=document.querySelector('.hero-copy');
    if(form.elements.landing_page_id)form.elements.landing_page_id.value=id;
    if(profile&&hero){
      hero.querySelector('h1').textContent=profile.h1;hero.querySelector('p').textContent=profile.intro;
      const tag=hero.querySelector('.tag');if(tag)tag.textContent=profile.tag;
      if(document.body){document.body.className=document.body.className.replace(/theme-[a-z]+/g,'').trim()+' '+profile.theme;}
      const image=document.querySelector('.hero-picture img'),source=document.querySelector('.hero-picture source');
      const asset='https://arkan-realestate-solutions.hositee.com'+profile.hero.replace(/\.jpg$/,'');
      if(!source?.getAttribute('srcset')?.includes(asset+'-768.webp')){if(image)image.src=asset+'.jpg';if(source)source.srcset=asset+'-768.webp 768w, '+asset+'.webp 1280w';}
      const chips=hero.querySelectorAll('.chip');chips.forEach((chip,i)=>{if(profile.chips[i])chip.textContent='✓ '+profile.chips[i];});
      const description=document.querySelector('meta[name="description"]');if(description)description.content=profile.description;
      document.title=profile.title;
      if(requested&&Object.hasOwn(profiles,requested)){const canonical=document.querySelector('link[rel=canonical]');if(canonical)canonical.href=location.origin+publicLandingUrl(id);}
      if(!hero.querySelector('.hero-actions')){
        const group=document.createElement('div');group.className='hero-actions';
        const wa=document.querySelector('.float.wa');const call=document.querySelector('.float.call');
        if(wa){const a=wa.cloneNode(true);a.className='btn btn-wa track-whatsapp';a.appendChild(document.createTextNode(' تواصل واتساب'));group.appendChild(a);}
        if(call){const a=call.cloneNode(true);a.className='btn btn-outline track-call';a.appendChild(document.createTextNode(' اتصل الآن'));group.appendChild(a);}
        hero.querySelector('p').insertAdjacentElement('afterend',group);
      }
      hero.querySelectorAll('.chip').forEach(chip=>{if(chip.textContent.includes('مجاني'))chip.textContent='خيارات حسب حالتك';});
    }
    const title=document.querySelector('.form-card h2');if(title)title.textContent='أرسل طلبك إلى أركان';
    const grid=document.querySelector('.section .cards');
    if(grid){
      const choices=[['P8','شقق تمليك جدة','ابحث عن شقة تناسب ميزانيتك والحي المطلوب.'],['P9','فلل للبيع جدة','خيارات فلل حسب الميزانية واحتياجات الأسرة.'],['P10','أراضي للبيع جدة','دراسة خيارات الأرض بحسب الموقع والميزانية.'],['P1','تسهيلات التمويل العقاري','نراجع الخيارات وإجراءات التقديم مع الجهات التمويلية.'],['P4','المديونية وإعادة التمويل','نوضح خيارات نقل المديونية وفك الرهن بحسب الحالة.'],['P7','حلول التعثر والالتزامات','نساعدك في دراسة مسارات السداد وإعادة الجدولة.']];
      const template=grid.querySelector('.card');
      if(template){grid.replaceChildren(...choices.map(([pageId,heading,copy])=>{const card=template.cloneNode(true);card.querySelector('h3').textContent=heading;card.querySelector('p').textContent=copy;card.querySelector('a').href=publicLandingUrl(pageId);return card;}));}
      const section=grid.closest('section'),heading=section?.querySelector('.section-head h2'),copy=section?.querySelector('.section-head p');if(heading)heading.textContent=profile?.section_title||'عقار مناسب أو حلول لتمويل التملك';if(copy)copy.textContent=profile?.section_intro||'اختر المسار الذي يناسب احتياجك.';
    }
    document.querySelectorAll('a[href]').forEach(a=>{try{const u=new URL(a.getAttribute('href'),location.href),route=decodeURIComponent(u.pathname),entry=Object.values(profiles).find(v=>v.path===route);if(entry&&Number(entry.id.slice(1))>=6&&u.origin===location.origin)a.href=publicLandingUrl(entry.id);}catch{}});

  }
  const status=document.getElementById('formStatus');
  const visible=['full_name','phone','city','property_type','employer_type'];
  const params=new URLSearchParams(location.search);
  const attribution=['utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid','gbraid','wbraid','ttclid','fbclid'];
  const campaignFields=['campaign_id','campaign_name','ad_group_id','ad_group_name','ad_id','keyword','match_type','device','network'];
  const paramAliases={campaign_id:['campaign_id','campaignid'],campaign_name:['campaign_name'],ad_group_id:['ad_group_id','adgroup_id','adgroupid'],ad_group_name:['ad_group_name'],ad_id:['ad_id','creative'],keyword:['keyword'],match_type:['match_type','matchtype'],device:['device'],network:['network']};
  const pushEvent=(event,detail={})=>{
    window.dataLayer=window.dataLayer||[];
    window.dataLayer.push({event,...detail});
  };
  const adsConversion=(kind,id='')=>{
    const targets={form:'AW-18351042794/F7HpCLHk49scEOrhuq5E',whatsapp:'AW-18351042794/XQr5CLTk49scEOrhuq5E',call:'AW-18351042794/x8TACLfk49scEOrhuq5E'};
    if(legacyOwnerReady||typeof window.gtag!=='function')return;
    const body={send_to:targets[kind],event_timeout:1200};if(id)body.transaction_id=id;
    window.gtag('event','conversion',body);
  };
  const firstParam=keys=>{for(const k of keys){const v=params.get(k);if(v)return v}return''};
  const makeToken=()=>{
    const bytes=new Uint8Array(8);
    if(window.crypto?.getRandomValues)crypto.getRandomValues(bytes);else for(let i=0;i<bytes.length;i++)bytes[i]=Math.floor(Math.random()*256);
    return 'ARK-AT-'+Array.from(bytes,b=>b.toString(16).padStart(2,'0')).join('').toUpperCase();
  };
  let firstLanding=localStorage.getItem('arkan_first_landing');
  if(!firstLanding){firstLanding=location.href;localStorage.setItem('arkan_first_landing',firstLanding);}
  let sessionId=sessionStorage.getItem('arkan_session_id');
  if(!sessionId){sessionId=(crypto.randomUUID?crypto.randomUUID():Date.now()+'-'+Math.random().toString(16).slice(2));sessionStorage.setItem('arkan_session_id',sessionId);}
  let trackingToken=sessionStorage.getItem('arkan_tracking_token');
  if(!trackingToken){trackingToken=makeToken();sessionStorage.setItem('arkan_tracking_token',trackingToken);}
  let storedAttribution={};
  try{storedAttribution=JSON.parse(localStorage.getItem('arkan_attribution_v1')||'{}')||{}}catch{}
  const fresh={};
  attribution.forEach(k=>{const v=params.get(k);if(v)fresh[k]=v;});
  campaignFields.forEach(k=>{const v=firstParam(paramAliases[k]||[k]);if(v)fresh[k]=v;});
  const hasFresh=Object.keys(fresh).length>0;
  if(hasFresh){
    storedAttribution={...storedAttribution,...fresh,captured_at:new Date().toISOString(),first_landing_url:firstLanding,landing_path:location.pathname};
    localStorage.setItem('arkan_attribution_v1',JSON.stringify(storedAttribution));
  }else if(!storedAttribution.first_landing_url){
    storedAttribution={...storedAttribution,first_landing_url:firstLanding,landing_path:location.pathname};
  }
  attribution.forEach(k=>{const el=form.elements[k];if(el)el.value=storedAttribution[k]||'';});
  form.elements.landing_path.value=location.pathname;
  form.elements.referrer.value=document.referrer||'';
  if(form.elements.privacy_consent)form.elements.privacy_consent.checked=true;

  const sourceFromAttribution=a=>{
    const utm=String(a.utm_source||'').toLowerCase();
    if(a.gclid||a.wbraid||a.gbraid||utm.includes('google')||utm.includes('adwords'))return'google';
    if(a.ttclid||utm.includes('tiktok'))return'tiktok';
    if(a.fbclid||utm.includes('facebook')||utm.includes('instagram')||utm.includes('meta'))return'meta';
    return utm||'website';
  };
  const platformSource=sourceFromAttribution(storedAttribution);
  const registrationPayload=()=>({
    event_type:'attribution_capture',ref_token:trackingToken,captured_at:new Date().toISOString(),
    landing_page_id:form.elements.landing_page_id?.value||'',landing_path:location.pathname,first_landing_url:firstLanding,
    ...Object.fromEntries(attribution.map(k=>[k,storedAttribution[k]||''])),
    ...Object.fromEntries(campaignFields.map(k=>[k,storedAttribution[k]||'']))
  });
  const registerAttribution=(keepalive=false)=>fetch((cfg.endpoint||'/api/lead')+'?mode=attribution',{
    method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},credentials:'same-origin',keepalive,body:JSON.stringify(registrationPayload())
  }).catch(()=>null);
  registerAttribution(false);

  const sourceLabel=platformSource==='google'?'Google Ads':platformSource==='tiktok'?'TikTok Ads':platformSource==='meta'?'Instagram/Meta Ads':'';
  document.querySelectorAll('a.track-whatsapp').forEach(link=>{
    try{
      const u=new URL(link.href,location.href);
      const text=u.searchParams.get('text')||'مرحبًا أركان التنفيذية، أرغب في معرفة المزيد.';
      const lines=[text];
      if(sourceLabel&&!text.includes('المصدر:'))lines.push('المصدر: '+sourceLabel);
      if(!text.includes('ARK-AT-'))lines.push('مرجع الزيارة: '+trackingToken);
      u.searchParams.set('text',lines.join('\n'));
      link.href=u.toString();
    }catch{}
  });

  document.addEventListener('click',e=>{
    const link=e.target.closest?.('a');
    if(!link)return;
    if(link.classList.contains('track-call')){pushEvent('arkan_call_click',{page_path:location.pathname});adsConversion('call');saveClick('call',link,{tracking_token:trackingToken,...storedAttribution});}
    if(link.classList.contains('track-whatsapp')){
      registerAttribution(true);
      pushEvent('arkan_whatsapp_click',{page_path:location.pathname,tracking_ref:trackingToken,platform_source:platformSource});adsConversion('whatsapp');saveClick('whatsapp',link,{tracking_token:trackingToken,...storedAttribution});
    }
  },{passive:true});

  const fieldLabels={
    full_name:'الاسم',phone:'رقم الجوال',city:'المدينة',property_type:'نوع العقار',employer_type:'جهة العمل',classification:'نوع العقار أو جهة العمل',privacy_consent:'الموافقة على سياسة الخصوصية'
  };
  const errorStyle='margin-top:6px;color:#b45309;font-size:13px;font-weight:700;';
  const resetElement=el=>{if(!el)return;el.removeAttribute('aria-invalid');el.style.borderColor='';el.style.boxShadow='';el.style.background='';};
  const clearErrors=()=>{form.querySelectorAll('[data-field-error]').forEach(node=>node.remove());form.querySelectorAll('[aria-invalid="true"]').forEach(resetElement);if(status)status.textContent='';};
  const errorContainer=(el,field)=>{if(field==='classification')return form.querySelector('.two')||el.parentElement;if(field==='privacy_consent')return el.closest('.consent')||el.parentElement;return el.closest('.field')||el.parentElement;};
  const showFieldError=(field,message)=>{
    clearErrors();
    let el=field==='classification'?form.elements.property_type:form.elements[field];if(!el)el=form.elements.full_name;
    const container=errorContainer(el,field);
    const mark=target=>{if(!target)return;target.setAttribute('aria-invalid','true');if(target.type!=='checkbox'){target.style.borderColor='#b45309';target.style.boxShadow='0 0 0 3px rgba(180,83,9,.13)';target.style.background='#fffaf5';}};
    if(field==='classification'){mark(form.elements.property_type);mark(form.elements.employer_type);}else mark(el);
    const note=document.createElement('div');note.dataset.fieldError=field;note.setAttribute('role','alert');note.style.cssText=errorStyle;note.textContent=message||('من فضلك أدخل '+(fieldLabels[field]||'البيان المطلوب')+'.');container.appendChild(note);
    if(status)status.textContent=note.textContent;
    window.setTimeout(()=>{try{el.focus({preventScroll:true});}catch(_){el.focus();}container.scrollIntoView({behavior:'smooth',block:'center'});},30);
  };
  const validate=()=>{
    const name=(form.elements.full_name?.value||'').trim();if(name.length<2){showFieldError('full_name','من فضلك أدخل الاسم.');return false;}
    const phone=(form.elements.phone?.value||'').replace(/\D/g,'');if(phone.length<7||phone.length>15){showFieldError('phone','من فضلك أدخل رقم الجوال بشكل صحيح.');return false;}
    const city=(form.elements.city?.value||'').trim();if(!city){showFieldError('city','من فضلك أدخل المدينة.');return false;}
    const property=(form.elements.property_type?.value||'').trim();const employer=(form.elements.employer_type?.value||'').trim();if(!property&&!employer){showFieldError('classification','من فضلك اختر نوع العقار أو جهة العمل على الأقل.');return false;}
    if(!form.elements.privacy_consent?.checked){showFieldError('privacy_consent','من فضلك وافق على سياسة الخصوصية لإرسال الطلب.');return false;}
    return true;
  };

  let saved={};try{saved=JSON.parse(localStorage.getItem('arkan_lead_draft_v2')||'{}')}catch{}
  visible.forEach(k=>{if(saved[k]&&form.elements[k])form.elements[k].value=saved[k];});
  form.addEventListener('input',e=>{const draft={};visible.forEach(k=>draft[k]=form.elements[k]?.value||'');localStorage.setItem('arkan_lead_draft_v2',JSON.stringify(draft));if(e.target?.matches('input,select'))clearErrors();});
  form.addEventListener('change',e=>{if(e.target?.matches('input,select'))clearErrors();});
  form.addEventListener('submit',async e=>{
    e.preventDefault();clearErrors();if(!validate())return;
    
    const data=Object.fromEntries(new FormData(form).entries());
    attribution.forEach(k=>data[k]=storedAttribution[k]||data[k]||'');
    data.privacy_consent='1';data.privacy_version=cfg.privacyVersion||'';data.consent_at=new Date().toISOString();data.submitted_at_client=new Date().toISOString();data.page_url=location.href;data.first_landing_url=firstLanding;data.session_id=sessionId;data.tracking_token=trackingToken;data.platform_source=platformSource;data.form_id='arkan_landing_form_v1';
    data.campaign_id=storedAttribution.campaign_id||'';data.campaign_name=storedAttribution.campaign_name||'';data.ad_group_id=storedAttribution.ad_group_id||'';data.ad_group_name=storedAttribution.ad_group_name||'';data.ad_id=storedAttribution.ad_id||'';data.keyword=storedAttribution.keyword||data.utm_term||'';data.match_type=storedAttribution.match_type||'';data.device=storedAttribution.device||'';data.network=storedAttribution.network||'';
    await registerAttribution(false);
    const button=form.querySelector('button[type=submit]');const original=button.textContent;button.disabled=true;button.textContent='جاري إرسال الطلب...';
    try{
      const response=await fetch(cfg.endpoint||'/api/lead',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},credentials:'same-origin',body:JSON.stringify(data)});
      const result=await response.json().catch(()=>({}));
      if(!response.ok||!result.ok){button.disabled=false;button.textContent=original;if(response.status===422&&result.field){showFieldError(result.field,result.message||'من فضلك أدخل البيان المطلوب.');return;}throw new Error(result.error||'submit_failed');}
      localStorage.removeItem('arkan_lead_draft_v2');
      const eventDetail={landing_page_id:data.landing_page_id||'',lead_token:result.lead_token||'',duplicate:!!result.duplicate};
      sessionStorage.setItem('arkan_lead_preview',JSON.stringify({...data,lead_id:result.lead_token||''}));sessionStorage.setItem('arkan_lead_token',result.lead_token||'');
      if(!result.duplicate){adsConversion('form',result.lead_token||'');pushEvent('arkan:lead-success',eventDetail);document.dispatchEvent(new CustomEvent('arkan:lead-success',{detail:eventDetail}));}
      window.setTimeout(()=>{location.href=cfg.thankYou||'/تم-استلام-الطلب/';},600);
    }catch(err){if(status)status.textContent='تعذر إرسال الطلب الآن. جرّب مرة أخرى بعد قليل.';button.disabled=false;button.textContent=original;}
  });
})();