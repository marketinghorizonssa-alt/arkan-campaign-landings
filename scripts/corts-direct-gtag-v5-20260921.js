(()=>{ 
const ENDPOINT='https://script.google.com/macros/s/AKfycbztk2fHhEAJeUFjIgkzL7na06sHWsrJVkWqpRbxt2CduJvNeyrQHSMpz8EzfNE4UbQv/exec';
const GADS={
  lead_form_success:'AW-18435697489/T1E7CNieyvAcENHW6dZE',
  click_whatsapp:'AW-18435697489/dgnOCNueyvAcENHW6dZE',
  click_call:'AW-18435697489/ppiUCN6eyvAcENHW6dZE'
};
const qs=new URLSearchParams(location.search);
const gtagOk=()=>typeof window.gtag==='function';
const token=(prefix='CORTS-EVT')=>`${prefix}-${Date.now()}-${Math.random().toString(36).slice(2,10).toUpperCase()}`;
const attrs=['gclid','gbraid','wbraid','utm_source','utm_medium','utm_campaign','utm_term','utm_content','campaign_id','adgroup_id','creative_id'];

const memoryOnce=new Set();
const once=(key,persist=false)=>{
  if(memoryOnce.has(key))return false;
  if(persist){
    try{
      if(sessionStorage.getItem(key)==='1')return false;
      sessionStorage.setItem(key,'1');
    }catch{}
  }
  memoryOnce.add(key);
  return true;
};

const emitCustomOnce=(name,params={},key=name)=>{
  if(!gtagOk()||!once('corts_custom_'+key))return false;
  window.gtag('event',name,Object.assign({transport_type:'beacon'},params));
  return true;
};

const sendConversionOnce=(name,params={},callback,key=name,persist=false)=>{
  const dest=GADS[name];
  if(!dest||!gtagOk()||!once('corts_conv_'+key,persist)){
    if(callback)callback();
    return false;
  }
  const payload=Object.assign({
    send_to:dest,
    transport_type:'beacon',
    event_timeout:1000
  },params);
  if(callback)payload.event_callback=callback;
  window.gtag('event','conversion',payload);
  return true;
};

document.addEventListener('click',e=>{
  const a=e.target&&e.target.closest?e.target.closest('a[href]'):null;
  if(!a)return;
  const raw=(a.getAttribute('href')||'').trim();
  let name='';
  if(/^tel:/i.test(raw))name='click_call';
  else if(/^(?:https?:\/\/)?(?:wa\.me|api\.whatsapp\.com|web\.whatsapp\.com)\//i.test(raw)||/^whatsapp:/i.test(raw))name='click_whatsapp';
  if(!name)return;

  const actionKey=name;
  if(!once('corts_action_'+actionKey,true))return;

  const action_id=token(name==='click_call'?'CORTS-CALL':'CORTS-WA');
  const params={
    click_url:raw,
    page_location:location.href,
    page_path:location.pathname,
    event_id:action_id
  };
  emitCustomOnce(name,params,actionKey);

  const normalClick=(typeof e.button==='undefined'||e.button===0)&&!e.metaKey&&!e.ctrlKey&&!e.shiftKey&&!e.altKey&&a.target!=='_blank';
  if(!normalClick){
    sendConversionOnce(name,{transaction_id:action_id},null,actionKey,true);
    return;
  }
  e.preventDefault();
  const href=a.href;
  let moved=false;
  const go=()=>{if(moved)return;moved=true;window.location.href=href};
  sendConversionOnce(name,{transaction_id:action_id},go,actionKey,true);
  setTimeout(go,1100);
},true);

const ar='٠١٢٣٤٥٦٧٨٩',fa='۰۱۲۳۴۵۶۷۸۹';
const toEn=s=>String(s||'').replace(/[٠-٩]/g,d=>String(ar.indexOf(d))).replace(/[۰-۹]/g,d=>String(fa.indexOf(d)));
const phoneInfo=v=>{
  const raw=String(v||'').trim();
  let p=toEn(raw).trim();
  const plus=p.startsWith('+');
  p=p.replace(/[^\d]/g,'');
  if(p.startsWith('00'))p='+'+p.slice(2);
  else if(plus)p='+'+p;
  else if(/^05\d{8}$/.test(p))p='+966'+p.slice(1);
  else if(/^5\d{8}$/.test(p))p='+966'+p;
  else if(/^9665\d{8}$/.test(p))p='+'+p;
  return{raw,normalized:p,digits:p.replace(/\D/g,'')};
};

const waUrl=data=>{
  const lines=[
    'السلام عليكم، أرسلت طلبًا عبر موقع كورت إكسبرت وأرغب بمتابعته عبر واتساب.',
    '',
    `الاسم: ${data.full_name||'-'}`,
    `رقم الجوال: ${data.phone_raw||data.phone||'-'}`,
    `الخدمة: ${data.service||'-'}`
  ];
  if(data.message)lines.push(`ملخص الطلب: ${data.message}`);
  lines.push(`رقم الطلب: ${data.submission_id}`);
  return'https://wa.me/966556044425?text='+encodeURIComponent(lines.join('\n'));
};

const showSuccess=(msg,p)=>{
  msg.className='msg ok';
  msg.textContent='تم استلام طلبك بنجاح. يمكنك متابعة الطلب مباشرة عبر واتساب.';
  const a=document.createElement('a');
  a.href=waUrl(p);
  a.target='_blank';
  a.rel='noopener';
  a.className='corts-wa-after-submit';
  a.textContent='متابعة الطلب على واتساب';
  a.style.cssText='display:flex;align-items:center;justify-content:center;min-height:48px;margin-top:10px;padding:10px 16px;border-radius:11px;background:#14864f;color:#fff;text-decoration:none;font-weight:900;text-align:center';
  msg.appendChild(a);
};

document.querySelectorAll('form[data-lead-form]').forEach(form=>form.addEventListener('submit',async e=>{
  e.preventDefault();
  if(form.dataset.submitting==='1')return;

  const btn=form.querySelector('button[type=submit]');
  const msg=form.querySelector('.msg');
  const fd=new FormData(form);
  const pi=phoneInfo(fd.get('phone'));

  if(pi.digits.length<6||pi.digits.length>20){
    msg.className='msg err';
    msg.textContent='أدخل رقم هاتف صحيح بأي صيغة مناسبة';
    return;
  }
  if(!fd.get('privacy_consent')){
    msg.className='msg err';
    msg.textContent='يلزم الموافقة على سياسة الخصوصية';
    return;
  }

  const submission_id=token('CORTS-WEB');
  const p={
    source_id:'CORTS_WEBSITE_FORM_V1',
    source:'Website Form',
    submission_id,
    full_name:String(fd.get('name')||'').trim(),
    phone:pi.normalized||pi.raw,
    phone_raw:pi.raw,
    service:String(fd.get('service')||'').trim(),
    message:String(fd.get('message')||'').trim(),
    page_url:location.href.split('#')[0],
    referrer:document.referrer||'',
    privacy_consent:'YES',
    consent_version:'v1',
    city:'Riyadh'
  };
  attrs.forEach(k=>p[k]=qs.get(k)||'');

  form.dataset.submitting='1';
  btn.disabled=true;
  const old=btn.textContent;
  btn.textContent='جاري الإرسال...';

  try{
    const ctl=new AbortController();
    const t=setTimeout(()=>ctl.abort(),12000);
    const r=await fetch(ENDPOINT,{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
      body:new URLSearchParams(p),
      signal:ctl.signal
    });
    clearTimeout(t);
    const txt=await r.text();
    if(!r.ok)throw new Error('receiver_http_'+r.status);

    let ok=true;
    try{
      const j=JSON.parse(txt);
      if(j&&Object.prototype.hasOwnProperty.call(j,'success'))ok=String(j.success)==='true';
    }catch{}
    if(!ok)throw new Error('receiver_rejected');

    if(once('corts_form_success_'+submission_id)){
      const meta={
        form_name:'CORTS_WEBSITE_FORM_V1',
        submission_id,
        service:p.service,
        landing_path:location.pathname,
        event_id:submission_id
      };
      emitCustomOnce('lead_form_success',meta,'form_'+submission_id);
      sendConversionOnce('lead_form_success',{transaction_id:submission_id},null,'form_'+submission_id,false);
    }

    form.reset();
    const c=form.querySelector('[name=privacy_consent]');
    if(c)c.checked=true;
    showSuccess(msg,p);
  }catch(err){
    msg.className='msg err';
    msg.textContent='تعذر تأكيد استلام الطلب الآن. يمكنك التواصل مباشرة عبر واتساب أو الاتصال.';
  }finally{
    form.dataset.submitting='0';
    btn.disabled=false;
    btn.textContent=old;
  }
}));
})();