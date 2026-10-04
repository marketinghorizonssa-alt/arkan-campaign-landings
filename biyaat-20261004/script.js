(() => {
  'use strict';
  const PHONE = '966547721414';
  const menuButton = document.querySelector('.menu-toggle');
  const nav = document.querySelector('.main-nav');
  const header = document.querySelector('.site-header');
  const form = document.querySelector('#visit-form');
  const mapShell = document.querySelector('#map-shell');
  const mapButtons = [document.querySelector('#load-map'), document.querySelector('#load-map-inline')].filter(Boolean);

  const query = new URLSearchParams(window.location.search);
  const currentRef = {
    ttclid: query.get('ttclid') || '',
    campaign: query.get('campaign') || query.get('campaign_id') || query.get('utm_campaign') || '',
    adgroup: query.get('adgroup') || query.get('adgroup_id') || '',
    ad: query.get('ad') || query.get('ad_id') || query.get('utm_content') || ''
  };

  try {
    const previous = JSON.parse(sessionStorage.getItem('biyaat_tiktok_ref') || '{}');
    Object.keys(currentRef).forEach((key) => {
      if (!currentRef[key] && previous[key]) currentRef[key] = previous[key];
    });
    if (Object.values(currentRef).some(Boolean)) {
      sessionStorage.setItem('biyaat_tiktok_ref', JSON.stringify(currentRef));
    }
  } catch (_) {}

  const safeRefValue = (value) => String(value || '').replace(/[|\n\r]/g, '').slice(0, 180);
  const refPairs = [
    ['ttclid', currentRef.ttclid],
    ['campaign', currentRef.campaign],
    ['adgroup', currentRef.adgroup],
    ['ad', currentRef.ad]
  ].filter(([, value]) => value);
  const refLine = refPairs.length ? 'REF:' + refPairs.map(([k,v]) => k + '=' + safeRefValue(v)).join('|') : '';

  let websiteRef = '';
  const track = (eventName) => {
    try { window.ttq?.track?.(eventName); } catch (_) {}
  };

  const buildDirectMessage = () => ['السلام عليكم، أرغب بحجز جولة والتعرف على بيئات لطفل المستقبل.', refLine, websiteRef].filter(Boolean).join('\n');
  document.querySelectorAll('.js-whatsapp').forEach((link) => {
    link.href = `https://wa.me/${PHONE}?text=${encodeURIComponent(buildDirectMessage())}`;
    link.addEventListener('click', () => track('WhatsAppClick'));
  });
  document.querySelectorAll('a[href^="tel:"]').forEach((link) => link.addEventListener('click', () => track('PhoneClick')));


  const cookie = (name) => {try{return decodeURIComponent(document.cookie.split('; ').find(c=>c.startsWith(name+'='))?.slice(name.length+1)||'');}catch(_){return '';}};
  const attributionReady = currentRef.ttclid ? fetch('/tiktok-attribution.php', {
    method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',
    body:JSON.stringify({...currentRef,ttp:cookie('_ttp')}),signal:AbortSignal.timeout(1600)
  }).then(r=>r.ok?r.json():null).then(r=>{if(r?.ok&&/^BIA-AT-[A-F0-9]{24}$/.test(r.ref||'')){websiteRef=r.ref;document.querySelectorAll('.js-whatsapp').forEach(link=>{link.href='https://wa.me/'+PHONE+'?text='+encodeURIComponent(buildDirectMessage());});}}).catch(()=>{}) : Promise.resolve();
  document.querySelectorAll('.js-whatsapp').forEach(link=>link.addEventListener('click',async(event)=>{
    if(websiteRef||!currentRef.ttclid)return;
    event.preventDefault();await Promise.race([attributionReady,new Promise(resolve=>setTimeout(resolve,650))]);
    window.location.assign('https://wa.me/'+PHONE+'?text='+encodeURIComponent(buildDirectMessage()));
  }));
  if (menuButton && nav) {
    menuButton.addEventListener('click', () => {
      const open = menuButton.getAttribute('aria-expanded') !== 'true';
      menuButton.setAttribute('aria-expanded', String(open));
      menuButton.setAttribute('aria-label', open ? 'إغلاق القائمة' : 'فتح القائمة');
      nav.classList.toggle('is-open', open);
    });
    nav.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
      nav.classList.remove('is-open');
      menuButton.setAttribute('aria-expanded', 'false');
    }));
  }

  const updateHeader = () => header?.classList.toggle('is-scrolled', window.scrollY > 12);
  updateHeader(); window.addEventListener('scroll', updateHeader, { passive:true });

  const year = document.querySelector('#year'); if (year) year.textContent = String(new Date().getFullYear());

  const revealItems = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const observer = new IntersectionObserver((entries, obs) => entries.forEach((entry) => {
      if (entry.isIntersecting) { entry.target.classList.add('is-visible'); obs.unobserve(entry.target); }
    }), { threshold:.1, rootMargin:'0px 0px -20px 0px' });
    revealItems.forEach((item) => observer.observe(item));
  } else revealItems.forEach((item) => item.classList.add('is-visible'));

  const loadMap = () => {
    if (!mapShell || mapShell.dataset.loaded === 'true') return;
    const src = mapShell.dataset.mapSrc;
    if (!src) return;
    const iframe = document.createElement('iframe');
    iframe.src = src;
    iframe.title = 'موقع بيئات لطفل المستقبل على Google Maps';
    iframe.loading = 'lazy';
    iframe.referrerPolicy = 'no-referrer-when-downgrade';
    iframe.allowFullscreen = true;
    mapShell.replaceChildren(iframe);
    mapShell.dataset.loaded = 'true';
  };
  mapButtons.forEach((button) => button.addEventListener('click', loadMap));

  document.querySelectorAll('.faq-item').forEach((item) => item.addEventListener('toggle', () => {
    if (!item.open) return;
    document.querySelectorAll('.faq-item').forEach((other) => { if (other !== item) other.open = false; });
  }));

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const data = new FormData(form);
    const clean = (key) => String(data.get(key) || '').trim().replace(/[<>]/g,'');
    const phone = clean('phone');
    if (phone.replace(/\D/g,'').length < 9) {
      form.elements.phone.setCustomValidity('يرجى إدخال رقم تواصل صحيح.');
      form.elements.phone.reportValidity();
      form.elements.phone.addEventListener('input', () => form.elements.phone.setCustomValidity(''), { once:true });
      return;
    }
    track('VisitFormPrepared');
    if(currentRef.ttclid&&!websiteRef)await Promise.race([attributionReady,new Promise(resolve=>setTimeout(resolve,650))]);
    const message = [
      'السلام عليكم، أرغب بحجز جولة في بيئات لطفل المستقبل:',
      '',
      'اسم ولي الأمر: ' + clean('parentName'),
      'رقم التواصل: ' + phone,
      'عمر الطفل: ' + clean('childAge'),
      'المرحلة المفضلة: ' + (clean('stage') || 'غير محدد'),
      'الفترة المفضلة: ' + (clean('period') || 'غير محدد'),
      'نوع الاشتراك: ' + (clean('subscription') || 'أرغب بالتعرف على الخيارات'),
      refLine ? '' : null,
      refLine || null,
      websiteRef || null
    ].filter(v => v !== null).join('\n');
    window.location.assign(`https://wa.me/${PHONE}?text=${encodeURIComponent(message)}`);
  });
})();


;(async()=>{try{
  const mc=document.modelContext;
  if(!mc?.registerTool) return;
  const tools=[
    {name:'get_biyaat_program_details',title:'Biyaat program details',description:'Return the key education and visit details for Biyaat Future Child Center.',inputSchema:{type:'object',properties:{}},annotations:{readOnlyHint:true},execute:async()=>({name:'بيئات لطفل المستقبل',ages:'2.5 to 6.5 years',stages:['Nursery','KG1','KG2','KG3'],periods:['Morning','Evening'],offer:'Opening discount up to 40%',city:'Jeddah',district:'Al Khalidiyah'})},
    {name:'get_biyaat_age_and_stages',title:'Biyaat ages and stages',description:'Return the accepted child age range and available nursery/KG stages.',inputSchema:{type:'object',properties:{}},annotations:{readOnlyHint:true},execute:async()=>({ages:'2.5 to 6.5 years',stages:['Nursery','KG1','KG2','KG3']})},
    {name:'get_biyaat_subscription_options',title:'Biyaat subscription options',description:'Return available subscription durations and attendance periods.',inputSchema:{type:'object',properties:{}},annotations:{readOnlyHint:true},execute:async()=>({subscriptions:['Daily','Weekly','Monthly','Annual'],periods:['Morning','Evening']})},
    {name:'get_biyaat_contact_details',title:'Biyaat contact details',description:'Return the official phone and WhatsApp contact used on this landing page.',inputSchema:{type:'object',properties:{}},annotations:{readOnlyHint:true},execute:async()=>({phone:'+966547721414',whatsapp:'https://wa.me/966547721414'})},
    {name:'get_biyaat_location_details',title:'Biyaat location',description:'Return the center location and Google Maps search link without loading the map iframe.',inputSchema:{type:'object',properties:{}},annotations:{readOnlyHint:true},execute:async()=>({city:'Jeddah',district:'Al Khalidiyah',maps:'https://www.google.com/maps/search/?api=1&query='+encodeURIComponent('بيئات لطفل المستقبل حي الخالدية جدة')})},
    {name:'focus_biyaat_booking_form',title:'Focus booking form',description:'Scroll the page to the visible Biyaat visit booking form so the user can review and complete it.',inputSchema:{type:'object',properties:{}},annotations:{readOnlyHint:false},execute:async()=>{document.querySelector('#hero-form')?.scrollIntoView({behavior:'smooth',block:'center'});document.querySelector('#visit-form input')?.focus({preventScroll:true});return 'Booking form focused for user review.';}}
  ];
  await Promise.allSettled(tools.map(t=>mc.registerTool(t)));
}catch(_){}})();