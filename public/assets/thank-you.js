(()=>{
  const whatsapp=document.getElementById('afterFormWa');
  const call=document.getElementById('afterFormCall');
  let lead={};
  try{lead=JSON.parse(sessionStorage.getItem('arkan_lead_preview')||'{}')}catch{}
  const id=sessionStorage.getItem('arkan_lead_token')||lead.lead_id||'';
  const lines=['مرحبًا أركان التنفيذية، تم إرسال طلبي من الموقع وأرغب في استكماله.'];
  if(lead.full_name)lines.push('الاسم: '+lead.full_name);
  if(lead.phone)lines.push('رقم الجوال: '+lead.phone);
  if(lead.city)lines.push('المدينة: '+lead.city);
  if(lead.property_type)lines.push('نوع العقار: '+lead.property_type);
  if(lead.employer_type)lines.push('جهة العمل: '+lead.employer_type);
  if(lead.landing_page_id)lines.push('مسار الطلب: '+lead.landing_page_id);
  if(id)lines.push('رقم الطلب: '+id);
  window.dataLayer=window.dataLayer||[];
  window.dataLayer.push({event:'thank_you_view',lead_token:id});
  if(whatsapp){
    whatsapp.href='https://wa.me/966500989103?text='+encodeURIComponent(lines.join('\n'));
    whatsapp.addEventListener('click',()=>window.dataLayer.push({event:'whatsapp_after_form',lead_token:id}));
  }
  if(call){
    call.addEventListener('click',()=>window.dataLayer.push({event:'call_after_form',lead_token:id}));
  }
})();
