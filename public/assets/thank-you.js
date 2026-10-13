(()=>{
  const whatsapp=document.getElementById('afterFormWa');
  const call=document.getElementById('afterFormCall');
  let lead={};
  try{lead=JSON.parse(sessionStorage.getItem('arkan_lead_preview')||'{}')}catch{}
  const id=sessionStorage.getItem('arkan_lead_token')||lead.lead_id||'';
  const trackingRef=lead.tracking_token||sessionStorage.getItem('arkan_tracking_token')||'';
  const platform=String(lead.platform_source||'').toLowerCase();
  const sourceLabel=platform==='google'?'Google Ads':platform==='tiktok'?'TikTok Ads':platform==='meta'?'Instagram/Meta Ads':'';
  const lines=['مرحبًا أركان التنفيذية، تم إرسال طلبي من الموقع وأرغب في استكماله.'];
  if(lead.full_name)lines.push('الاسم: '+lead.full_name);
  if(lead.phone)lines.push('رقم الجوال: '+lead.phone);
  if(lead.city)lines.push('المدينة: '+lead.city);
  if(lead.property_type)lines.push('نوع العقار: '+lead.property_type);
  if(lead.employer_type)lines.push('جهة العمل: '+lead.employer_type);
  if(lead.landing_page_id)lines.push('مسار الطلب: '+lead.landing_page_id);
  if(sourceLabel)lines.push('المصدر: '+sourceLabel);
  if(trackingRef)lines.push('مرجع الزيارة: '+trackingRef);
  if(id)lines.push('رقم الطلب: '+id);
  const legacy=typeof window.__loadArkanGtm==='function';
  const ownerReady=legacy&&!!window.__arkanGtmListenerInstalled;
  if(legacy&&!ownerReady){
    window.__arkanGtmListenerInstalled=true;window.dataLayer=window.dataLayer||[];window.gtag=window.gtag||function(){window.dataLayer.push(arguments)};
    const tag=document.createElement('script');tag.async=true;tag.src='https://www.googletagmanager.com/gtag/js?id=AW-18351042794';document.head.appendChild(tag);window.gtag('js',new Date());window.gtag('config','AW-18351042794');
  }
  const track=kind=>{
    if(!ownerReady&&typeof window.gtag==='function')window.gtag('event','conversion',{send_to:kind==='whatsapp'?'AW-18351042794/XQr5CLTk49scEOrhuq5E':'AW-18351042794/x8TACLfk49scEOrhuq5E'});
    const endpoint=location.hostname==='arkan2030.com'||location.hostname==='www.arkan2030.com'?'https://arkan-realestate-solutions.hositee.com/api/click':'/api/click';
    const event_id=crypto.randomUUID?crypto.randomUUID():Date.now()+'-'+Math.random().toString(16).slice(2);
    fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json'},keepalive:true,body:JSON.stringify({event_id,event_type:kind,placement:'after_form',tracking_token:trackingRef,landing_path:location.pathname,landing_page_id:lead.landing_page_id||'',utm_source:lead.utm_source||'',gclid:lead.gclid||'',campaign_id:lead.campaign_id||'',ad_group_id:lead.ad_group_id||''})}).catch(()=>null);
  };
  window.dataLayer=window.dataLayer||[];
  window.dataLayer.push({event:'thank_you_view',lead_token:id});
  if(whatsapp){
    whatsapp.href='https://wa.me/966500989103?text='+encodeURIComponent(lines.join('\n'));
    whatsapp.addEventListener('click',()=>{track('whatsapp');window.dataLayer.push({event:'whatsapp_after_form',lead_token:id,tracking_ref:trackingRef,platform_source:platform});});
  }
  if(call){
    call.addEventListener('click',()=>{track('call');window.dataLayer.push({event:'call_after_form',lead_token:id});});
  }
})();