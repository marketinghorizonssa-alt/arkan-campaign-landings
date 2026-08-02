(()=>{
  const cfg=window.ARKAN_CONFIG||{};
  const form=document.getElementById('leadForm');
  if(!form)return;
  const visible=['full_name','phone','city','property_type','employer_type'];
  const params=new URLSearchParams(location.search);
  const attribution=['utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid','gbraid','wbraid','ttclid','fbclid'];
  attribution.forEach(k=>{const el=form.elements[k];if(el)el.value=params.get(k)||'';});
  form.elements.landing_path.value=location.pathname;
  form.elements.referrer.value=document.referrer||'';
  if(form.elements.privacy_consent)form.elements.privacy_consent.checked=true;
  let firstLanding=localStorage.getItem('arkan_first_landing');
  if(!firstLanding){firstLanding=location.href;localStorage.setItem('arkan_first_landing',firstLanding);}
  let sessionId=sessionStorage.getItem('arkan_session_id');
  if(!sessionId){sessionId=(crypto.randomUUID?crypto.randomUUID():Date.now()+'-'+Math.random().toString(16).slice(2));sessionStorage.setItem('arkan_session_id',sessionId);}
  let saved={};
  try{saved=JSON.parse(localStorage.getItem('arkan_lead_draft_v2')||'{}')}catch{}
  visible.forEach(k=>{if(saved[k]&&form.elements[k])form.elements[k].value=saved[k];});
  form.addEventListener('input',()=>{
    const draft={};visible.forEach(k=>draft[k]=form.elements[k]?.value||'');
    localStorage.setItem('arkan_lead_draft_v2',JSON.stringify(draft));
  });
  form.addEventListener('submit',async e=>{
    e.preventDefault();
    const status=document.getElementById('formStatus');
    if(!form.reportValidity()){status.textContent='راجع الحقول المطلوبة قبل المتابعة.';return;}
    const data=Object.fromEntries(new FormData(form).entries());
    data.privacy_consent='1';
    data.privacy_version=cfg.privacyVersion||'';
    data.consent_at=new Date().toISOString();
    data.submitted_at_client=new Date().toISOString();
    data.page_url=location.href;
    data.first_landing_url=firstLanding;
    data.session_id=sessionId;
    data.platform_source=data.utm_source||'website';
    data.form_id='arkan_landing_form_v1';
    data.campaign_id=params.get('campaign_id')||params.get('campaignid')||'';
    data.campaign_name=params.get('campaign_name')||'';
    data.ad_group_id=params.get('adgroup_id')||params.get('adgroupid')||'';
    data.ad_group_name=params.get('adgroup_name')||'';
    data.ad_id=params.get('ad_id')||params.get('creative')||'';
    data.keyword=params.get('keyword')||data.utm_term||'';
    data.match_type=params.get('matchtype')||'';
    data.device=params.get('device')||'';
    data.network=params.get('network')||'';
    const button=form.querySelector('button[type=submit]');
    const original=button.textContent;
    button.disabled=true;button.textContent='جاري إرسال الطلب...';status.textContent='';
    try{
      const response=await fetch(cfg.endpoint||'/api/lead',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},credentials:'same-origin',body:JSON.stringify(data)});
      const result=await response.json().catch(()=>({}));
      if(!response.ok||!result.ok)throw new Error(result.error||'submit_failed');
      localStorage.removeItem('arkan_lead_draft_v2');
      sessionStorage.setItem('arkan_lead_preview',JSON.stringify({...data,lead_id:result.lead_token||''}));
      sessionStorage.setItem('arkan_lead_token',result.lead_token||'');
      document.dispatchEvent(new CustomEvent('arkan:lead-success',{detail:{landing_page_id:data.landing_page_id||'',lead_token:result.lead_token||'',duplicate:!!result.duplicate}}));
      window.setTimeout(()=>{location.href=cfg.thankYou||'/تم-استلام-الطلب/';},600);
    }catch(err){
      status.textContent='تعذر إرسال الطلب الآن. جرّب مرة أخرى أو تواصل معنا عبر واتساب.';
      button.disabled=false;button.textContent=original;
    }
  });
})();
