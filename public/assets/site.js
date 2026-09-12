(()=>{
  const cfg=window.ARKAN_CONFIG||{};
  const form=document.getElementById('leadForm');
  if(!form)return;
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
  const registerAttribution=(keepalive=false)=>fetch('/api/lead?mode=attribution',{
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
    if(link.classList.contains('track-call'))pushEvent('arkan_call_click',{page_path:location.pathname});
    if(link.classList.contains('track-whatsapp')){
      registerAttribution(true);
      pushEvent('arkan_whatsapp_click',{page_path:location.pathname,tracking_ref:trackingToken,platform_source:platformSource});
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
    if(typeof window.__loadArkanGtm==='function')window.__loadArkanGtm();
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
      pushEvent('arkan:lead-success',eventDetail);document.dispatchEvent(new CustomEvent('arkan:lead-success',{detail:eventDetail}));
      window.setTimeout(()=>{location.href=cfg.thankYou||'/تم-استلام-الطلب/';},600);
    }catch(err){if(status)status.textContent='تعذر إرسال الطلب الآن. جرّب مرة أخرى بعد قليل.';button.disabled=false;button.textContent=original;}
  });
})();