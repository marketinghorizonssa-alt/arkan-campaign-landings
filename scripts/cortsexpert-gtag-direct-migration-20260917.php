<?php
$root='/home/u878466595/domains/hositee.com/public_html/corts-expert';
$gtag=<<<'HTML'
<script async src="https://www.googletagmanager.com/gtag/js?id=AW-18435697489"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','AW-18435697489');</script>
HTML;
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
$changed=0;
foreach($it as $f){
  if(!$f->isFile() || strtolower($f->getExtension())!=='html') continue;
  $path=$f->getPathname();
  if(strpos($path,'/_gtag-direct-canary/')!==false) continue;
  $s=file_get_contents($path); $orig=$s;
  $s=preg_replace("~<script>\\(function\\(w,d,s,l,i\\)\\{.*?googletagmanager\\.com/gtm\\.js\\?id=.*?</script>~s",$gtag,$s,1);
  $s=preg_replace("~<noscript><iframe[^>]*googletagmanager\\.com/ns\\.html\\?id=GTM-M9ZK36MB.*?</iframe></noscript>~s",'',$s);
  $s=preg_replace('~/assets/app\\.js\\?v=[^\"\']+~','/assets/app.js?v=gtag-direct-20260917',$s);
  if($s!==$orig){file_put_contents($path,$s);$changed++;}
}
$app=<<<'JS'
(()=>{
const ENDPOINT='https://script.google.com/macros/s/AKfycbztk2fHhEAJeUFjIgkzL7na06sHWsrJVkWqpRbxt2CduJvNeyrQHSMpz8EzfNE4UbQv/exec';
const dl=window.dataLayer=window.dataLayer||[];
const GADS={
  lead_form_success:'AW-18435697489/T1E7CNieyvAcENHW6dZE',
  click_whatsapp:'AW-18435697489/dgnOCNueyvAcENHW6dZE',
  click_call:'AW-18435697489/ppiUCN6eyvAcENHW6dZE'
};
const hasGtag=()=>typeof window.gtag==='function';
const emitCustom=(name,params={})=>{
  dl.push(Object.assign({event:name},params));
  if(hasGtag()) window.gtag('event',name,params);
};
const sendConversion=(name,params={},callback)=>{
  const dest=GADS[name];
  if(!dest){if(callback)callback();return}
  if(!hasGtag()){if(callback)callback();return}
  const payload=Object.assign({send_to:dest},params);
  if(callback) payload.event_callback=callback;
  window.gtag('event','conversion',payload);
};

document.addEventListener('click',e=>{
  const a=e.target&&e.target.closest?e.target.closest('a[href]'):null;
  if(!a)return;
  const raw=(a.getAttribute('href')||'').trim();
  let name='';
  if(/^tel:/i.test(raw)) name='click_call';
  else if(/^(?:https?:\/\/)?(?:wa\.me|api\.whatsapp\.com|web\.whatsapp\.com)\//i.test(raw)||/^whatsapp:/i.test(raw)) name='click_whatsapp';
  if(!name)return;
  const params={click_url:raw,page_location:location.href,page_path:location.pathname};
  emitCustom(name,params);
  const normalClick=(typeof e.button==='undefined'||e.button===0)&&!e.metaKey&&!e.ctrlKey&&!e.shiftKey&&!e.altKey&&a.target!=='_blank';
  if(!normalClick){sendConversion(name,params);return}
  e.preventDefault();
  const href=a.href; let moved=false;
  const go=()=>{if(moved)return;moved=true;window.location.href=href};
  sendConversion(name,params,go);
  setTimeout(go,700);
},true);

const qs=new URLSearchParams(location.search);
const attrs=['gclid','gbraid','wbraid','utm_source','utm_medium','utm_campaign','utm_term','utm_content','campaign_id','adgroup_id','creative_id'];
const ar='٠١٢٣٤٥٦٧٨٩';
const fa='۰۱۲۳۴۵۶۷۸۹';
const toEn=s=>String(s||'').replace(/[٠-٩]/g,d=>String(ar.indexOf(d))).replace(/[۰-۹]/g,d=>String(fa.indexOf(d)));
const normPhone=v=>{let p=toEn(v).replace(/[^\d+]/g,'');if(p.startsWith('00966'))p='+'+p.slice(2);else if(p.startsWith('966'))p='+'+p;else if(/^05\d{8}$/.test(p))p='+966'+p.slice(1);else if(/^5\d{8}$/.test(p))p='+966'+p;return p};
const makeId=()=>`CORTS-WEB-${Date.now()}-${Math.random().toString(36).slice(2,10).toUpperCase()}`;
document.querySelectorAll('form[data-lead-form]').forEach(form=>form.addEventListener('submit',async e=>{
 e.preventDefault();
 const btn=form.querySelector('button[type=submit]'),msg=form.querySelector('.msg'),fd=new FormData(form);
 const phone=normPhone(fd.get('phone'));
 if(!/^\+9665\d{8}$/.test(phone)){msg.className='msg err';msg.textContent='أدخل رقم جوال سعودي صحيح';return}
 if(!fd.get('privacy_consent')){msg.className='msg err';msg.textContent='يلزم الموافقة على سياسة الخصوصية';return}
 const submission_id=makeId();
 const p={source_id:'CORTS_WEBSITE_FORM_V1',source:'Website Form',submission_id,full_name:String(fd.get('name')||'').trim(),phone,service:String(fd.get('service')||'').trim(),message:String(fd.get('message')||'').trim(),page_url:location.href.split('#')[0],referrer:document.referrer||'',privacy_consent:'YES',consent_version:'v1',city:'Riyadh'};
 attrs.forEach(k=>p[k]=qs.get(k)||'');
 btn.disabled=true;const old=btn.textContent;btn.textContent='جاري الإرسال...';
 try{
   const ctl=new AbortController();const t=setTimeout(()=>ctl.abort(),12000);
   const r=await fetch(ENDPOINT,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:new URLSearchParams(p),signal:ctl.signal});clearTimeout(t);
   const txt=await r.text();
   if(!r.ok)throw new Error('receiver_http_'+r.status);
   let ok=true;try{const j=JSON.parse(txt);if(j&&Object.prototype.hasOwnProperty.call(j,'success'))ok=String(j.success)==='true'}catch{}
   if(!ok)throw new Error('receiver_rejected');
   form.reset();const c=form.querySelector('[name=privacy_consent]');if(c)c.checked=true;
   msg.className='msg ok';msg.textContent='تم استلام طلبك بنجاح.';
   const meta={form_name:'CORTS_WEBSITE_FORM_V1',submission_id,service:p.service,landing_path:location.pathname};
   emitCustom('lead_form_success',meta);
   sendConversion('lead_form_success',{});
 }catch(err){msg.className='msg err';msg.textContent='تعذر تأكيد استلام الطلب الآن. يمكنك التواصل مباشرة عبر واتساب أو الاتصال.'}
 finally{btn.disabled=false;btn.textContent=old}
}));
})();
JS;
file_put_contents($root.'/assets/app.js',$app);
file_put_contents($root.'/.tracking-version',"GTAG_DIRECT_V1_20260917\n");
echo "changed_html=$changed\n";
echo "app_bytes=".strlen($app)."\n";
?>