window.HORIZONS_WA_ATTR={clientId:"cl_3ea5ae96e05c6b",businessNumber:"+966537033347",businessNumbers:["+966537033347"],timeoutMs:2500};
(function(w,d){
 'use strict';
 if(w.__HORIZONS_WA_ATTR_V1__)return;w.__HORIZONS_WA_ATTR_V1__=true;
 var cfg=w.HORIZONS_WA_ATTR||{},clientId=String(cfg.clientId||cfg.client_id||'').trim();
 var business=String(cfg.businessNumber||cfg.business_number||'').trim();
 var businesses=cfg.businessNumbers||cfg.business_numbers||[business];
 var endpoint=String(cfg.endpoint||'https://marketing.hositee.com/wa_click_attribution.php');
 if(!clientId||!businesses.length)return;
 var ns='hzn_wa_'+clientId.replace(/[^a-z0-9]/gi,'_')+'_';
 var FIRST=ns+'first_v1',LAST=ns+'last_v1',HIST=ns+'history_v1',QUEUE=ns+'pending_v2',busy=false;
 function read(k){try{return JSON.parse(localStorage.getItem(k)||'null')}catch(e){return null}}
 function write(k,v){try{localStorage.setItem(k,JSON.stringify(v))}catch(e){}}
 function params(url){var out={};try{new URL(url||location.href,location.href).searchParams.forEach(function(v,k){out[k]=v})}catch(e){}return out}
 function touch(){return{url:location.href,path:location.pathname,referrer:d.referrer||'',title:d.title||'',params:params(location.href),at:new Date().toISOString()}}
 function hasCampaign(p){return Object.keys(p||{}).some(function(k){return /^(utm_|gclid$|gbraid$|wbraid$|dclid$|fbclid$|ttclid$|scclid$|ScCid$|msclkid$|li_fat_id$|twclid$|gad_source$|gad_campaignid$)/.test(k)&&p[k]})}
 function externalRef(){try{return !!d.referrer&&new URL(d.referrer).hostname!==location.hostname}catch(e){return false}}
 function touches(){
  var cur=touch(),first=read(FIRST),last=read(LAST),hist=read(HIST),ttl=90*86400000;
  if(first&&Date.now()-Date.parse(first.at)>ttl){first=null;last=null;hist=[]}
  if(last&&Date.now()-Date.parse(last.at)>ttl)last=null;
  if(!first){first=cur;write(FIRST,first)}
  if(!last||hasCampaign(cur.params)||externalRef()){last=cur;write(LAST,last)}
  if(!Array.isArray(hist))hist=[];var prev=hist[hist.length-1];
  if(!prev||prev.url!==cur.url||prev.referrer!==cur.referrer){hist.push(cur);write(HIST,hist.slice(-20))}
  return{first:first,last:last,current:cur,history:hist.slice(-20)};
 }
 function phone(v){var p=String(v||'').replace(/\D/g,'').replace(/^00/,'');if(/^05\d{8}$/.test(p))p='966'+p.slice(1);return p}
 function waUrl(href){try{
  var u=new URL(href,location.href),h=u.hostname.toLowerCase(),p='';
  if(h==='wa.me')p=phone(u.pathname);else if(h==='api.whatsapp.com'||h==='web.whatsapp.com')p=phone(u.searchParams.get('phone'));else return null;
  var allowed=businesses.some(function(n){return phone(n)===p});if(!allowed)return null;
  if(h==='wa.me')u.pathname='/'+p;else u.searchParams.set('phone',p);
  return{url:u,business:'+'+p};
 }catch(e){return null}}
 function encode(s){var a=['\u200B','\u200C','\u200D','\uFEFF'],o='';for(var i=0;i<s.length;i++){var b=s.charCodeAt(i)&255;o+=a[(b>>6)&3]+a[(b>>4)&3]+a[(b>>2)&3]+a[b&3]}return o}
 function embed(text,hidden){text=String(text||'').replace(/[\u200B\u200C\u200D\uFEFF]{12,}/gu,'').trim();if(!text)return hidden;var m=text.match(/[،,\s]/u),i=m&&m.index>0?m.index+1:Math.min(4,text.length);return text.slice(0,i)+hidden+text.slice(i)}
 function interaction(){try{var bytes=new Uint8Array(16);w.crypto.getRandomValues(bytes);return Array.from(bytes).map(function(x){return('0'+x.toString(16)).slice(-2)}).join('')}catch(e){return Date.now().toString(36)+'_'+Math.random().toString(36).slice(2)}}
 function queue(){var a=read(QUEUE);return Array.isArray(a)?a.filter(function(b){return b&&Date.now()-Date.parse(b.browser_time)<86400000}).slice(-20):[]}
 function remember(body){var a=queue().filter(function(b){return b.interaction_id!==body.interaction_id});a.push(body);write(QUEUE,a.slice(-20))}
 function forget(id){write(QUEUE,queue().filter(function(b){return b.interaction_id!==id}))}
 function deliver(body){return fetch(endpoint,{method:'POST',headers:{'Content-Type':'text/plain;charset=UTF-8','Accept':'application/json'},body:JSON.stringify(body),mode:'cors',credentials:'omit',keepalive:true}).then(function(r){if(!r.ok)throw Error('hub_http');return r.json()}).then(function(j){if(!j||!j.ok||!j.click_token)throw Error('hub_token');forget(body.interaction_id);return j})}
 function replay(){queue().forEach(function(b){deliver(b).catch(function(){})})}
 function openTarget(win,url){try{if(win&&!win.closed){win.location.replace(url);return}}catch(e){}location.href=url}
 touches();replay();w.addEventListener('online',replay);w.addEventListener('pageshow',replay);
 d.addEventListener('click',function(ev){
  var a=ev.target&&ev.target.closest?ev.target.closest('a'):null;if(!a)return;
  var target=waUrl(a.getAttribute('href')||a.href||'');if(!target)return;
  ev.preventDefault();if(busy)return;busy=true;
  var u=target.url,original=u.toString(),state=touches(),win=null;
  try{win=w.open('about:blank','_blank');if(win)win.opener=null}catch(e){}
  var body={mint_horizons_token:true,client_id:clientId,business_number:target.business,interaction_id:interaction(),source_url:location.href,page_url:location.href,landing_url:state.first.url,referrer:d.referrer||'',original_href:original,button_text:(a.textContent||a.getAttribute('aria-label')||'WhatsApp').trim().slice(0,500),button_context:location.pathname+'|'+(a.className||''),first_touch:state.first,last_touch:state.last,current_touch:state.current,touch_history:state.history,query_params:params(location.href),browser_time:new Date().toISOString()};
  remember(body);
  var req=deliver(body),timer;
  var deadline=new Promise(function(_,reject){timer=setTimeout(function(){reject(Error('timeout'))},Number(cfg.timeoutMs||2200))});
  Promise.race([req,deadline]).then(function(j){clearTimeout(timer);u.searchParams.set('text',embed(u.searchParams.get('text')||cfg.defaultText||'',encode(String(j.click_token))));openTarget(win,u.toString())}).catch(function(){try{if(w.navigator&&w.navigator.sendBeacon)w.navigator.sendBeacon(endpoint,new Blob([JSON.stringify(body)],{type:'text/plain;charset=UTF-8'}))}catch(e){}openTarget(win,original)}).finally(function(){setTimeout(function(){busy=false},800)});
 },true);
})(window,document);

(()=> {
  if (window.__almowahidDirectTagInit) return;
  window.__almowahidDirectTagInit = true;
  const dl = window.dataLayer = window.dataLayer || [];
  const qs = new URLSearchParams(location.search);
  const ATTRS = ['utm_source','utm_medium','utm_campaign','utm_term','utm_content','utm_id','utm_source_platform','utm_creative_format','utm_marketing_tactic','gclid','gbraid','wbraid','dclid','ttclid','fbclid','scclid','ScCid','msclkid','li_fat_id','twclid','srsltid','gad_source','gad_campaignid','campaign_id','campaignid','campaign_name','adgroup_id','adgroupid','adgroup_name','ad_id','creative','creative_id','ad_name','keyword','matchtype','network','device','placement','targetid','loc_physical_ms','loc_interest_ms','feeditemid','extensionid','adposition'];
  const ADS = {
    form: 'AW-16848499995/eOdNCLS91_8cEJvq_uE-',
    whatsapp: 'AW-16848499995/4cylCLe91_8cEJvq_uE-',
    call: 'AW-16848499995/mPNJCLq91_8cEJvq_uE-'
  };

  const WA_ATTR_ENDPOINT = 'https://marketing.hositee.com/wa_click_attribution.php';
  const WA_CLIENT_ID = 'cl_3ea5ae96e05c6b';
  const WA_BUSINESS_NUMBER = '+966537033347';
  const WA_DEFAULT_TEXT = 'السلام عليكم، أرغب في الاستفسار عن خدمات الموحد للاستقدام.';
  let firstTouch = null;
  let lastTouch = null;

  function attributionParams() {
    const out = {};
    ATTRS.forEach(key => {
      const value = getSavedAttribution(key);
      if (value) out[key] = value;
    });
    return out;
  }

  function touchSnapshot() {
    return {url:location.href,referrer:document.referrer||'',params:attributionParams(),at:new Date().toISOString()};
  }

  function initTouches() {
    const now=touchSnapshot();
    try { firstTouch=JSON.parse(sessionStorage.getItem('almowahid_first_touch_v1')||'null'); } catch(_){ firstTouch=null; }
    if(!firstTouch||typeof firstTouch!=='object'){
      firstTouch=now;
      try{sessionStorage.setItem('almowahid_first_touch_v1',JSON.stringify(firstTouch));}catch(_){}
    }
    lastTouch=now;
    try{sessionStorage.setItem('almowahid_last_touch_v1',JSON.stringify(lastTouch));}catch(_){}
  }

  function encodeHiddenMarker(value){
    const alphabet=['\u200B','\u200C','\u200D','\uFEFF'];
    let out='';
    for(let i=0;i<value.length;i++){
      const b=value.charCodeAt(i)&255;
      out+=alphabet[(b>>6)&3]+alphabet[(b>>4)&3]+alphabet[(b>>2)&3]+alphabet[b&3];
    }
    return out;
  }

  function embedHiddenToken(visible,token){
    visible=String(visible||'').replace(/[\u200B\u200C\u200D\uFEFF]{12,}/gu,'').trim();
    const hidden=encodeHiddenMarker(token);
    if(!visible)return hidden;
    const m=visible.match(/[،,\s]/u);
    if(m&&typeof m.index==='number'&&m.index>0){
      const i=m.index+1;
      return visible.slice(0,i)+hidden+visible.slice(i);
    }
    const cut=Math.min(4,visible.length);
    return visible.slice(0,cut)+hidden+visible.slice(cut);
  }

  async function buildTrackedWhatsappHref(href,a){
    const u=new URL(href,location.href);
    if(!['wa.me','api.whatsapp.com','web.whatsapp.com'].includes(u.hostname))return href;
    const current=touchSnapshot(); lastTouch=current;
    const payload={
      mint_horizons_token:true,
      client_id:WA_CLIENT_ID,
      business_number:WA_BUSINESS_NUMBER,
      source_url:location.href,
      page_url:location.href,
      landing_url:firstTouch&&firstTouch.url?firstTouch.url:location.href,
      referrer:document.referrer||'',
      original_href:href,
      button_text:(a.textContent||'').trim(),
      button_context:a.getAttribute('aria-label')||a.className||'',
      first_touch:firstTouch||current,
      last_touch:lastTouch,
      current_touch:current,
      query_params:attributionParams(),
      touch_history:[firstTouch||current,current],
      browser_time:new Date().toISOString()
    };
    const res=await fetch(WA_ATTR_ENDPOINT,{
      method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},
      body:JSON.stringify(payload),mode:'cors',credentials:'omit'
    });
    if(!res.ok)throw new Error('attribution_http');
    const j=await res.json();
    if(!j||!j.ok||!j.click_token)throw new Error('attribution_token');
    const visible=u.searchParams.get('text')||WA_DEFAULT_TEXT;
    u.searchParams.set('text',embedHiddenToken(visible,String(j.click_token)));
    return u.toString();
  }

  function pushEvent(name, params={}) {
    // Direct Google tag only. Do not duplicate the same event with a second dataLayer event.
    if (typeof gtag === 'function') {
      gtag('event', name, params);
    }
  }

  function adsConversion(sendTo, params={}) {
    if (typeof gtag === 'function') {
      gtag('event', 'conversion', {send_to:sendTo, ...params});
    }
  }

  function getSavedAttribution(key) {
    return qs.get(key) || sessionStorage.getItem('almowahid_'+key) || '';
  }

  ATTRS.forEach(key => {
    const value = qs.get(key);
    if (value) {
      try { sessionStorage.setItem('almowahid_'+key, value); } catch (_) {}
    }
  });

  initTouches();

  document.querySelectorAll('[data-event]').forEach(a => {
    a.addEventListener('click', async e => {
      const isPostFormWhatsapp=a.hasAttribute('data-whatsapp-complete');
      const rawName=a.dataset.event||'';
      const name=isPostFormWhatsapp&&rawName==='click_whatsapp'?'post_form_whatsapp':rawName;
      const originalHref=a.href||'';
      const isWhatsapp=rawName==='click_whatsapp';

      if(isWhatsapp&&!(window.__HORIZONS_WA_ATTR_V1__&&e.defaultPrevented)){
        e.preventDefault();
        let win=null;
        try{win=window.open('about:blank','_blank');if(win)win.opener=null;}catch(_){}
        try{
          const trackedHref=await buildTrackedWhatsappHref(originalHref,a);
          if(win&&!win.closed)win.location.replace(trackedHref); else location.href=trackedHref;
        }catch(_){
          if(win&&!win.closed)win.location.replace(originalHref); else location.href=originalHref;
        }
      }

      const params={link_url:a.href||originalHref,landing_path:location.pathname};
      pushEvent(name,params);
      if(rawName==='click_whatsapp'&&!isPostFormWhatsapp)adsConversion(ADS.whatsapp,{value:1,currency:'SAR'});
      if(name==='click_call')adsConversion(ADS.call,{value:1,currency:'SAR'});
    });
  });

  document.querySelectorAll('[data-lead-form]').forEach(form => {
    const nameEl = form.querySelector('[name=name]');
    const phoneEl = form.querySelector('[name=phone]');
    const serviceEl = form.querySelector('[name=service]');
    const nationalityEl = form.querySelector('[name=nationality]');
    const saved = (() => {
      try { return JSON.parse(localStorage.getItem('almowahid_identity') || '{}'); }
      catch (_) { return {}; }
    })();

    if (nameEl && !nameEl.value && saved.name) nameEl.value = saved.name;
    if (phoneEl) {
      if (!phoneEl.value && saved.phone) phoneEl.value = saved.phone;
      phoneEl.removeAttribute('pattern');
      phoneEl.removeAttribute('minlength');
      phoneEl.placeholder = 'اكتب رقمك بأي صيغة محلية أو دولية';
    }

    if (serviceEl && qs.get('service')) {
      [...serviceEl.options].some(o => o.value === qs.get('service') && (serviceEl.value = o.value, true));
    }
    if (nationalityEl && qs.get('nationality')) {
      [...nationalityEl.options].some(o => o.value === qs.get('nationality') && (nationalityEl.value = o.value, true));
    }

    form.addEventListener('submit', async e => {
      e.preventDefault();

      const fd = new FormData(form);
      const raw = Object.fromEntries(fd.entries());
      const msg = form.querySelector('.msg');
      const success = form.querySelector('.success-box');
      const wa = form.querySelector('[data-whatsapp-complete]');
      const submit = form.querySelector('button[type=submit]');

      const fullName = String(raw.name || '').trim();
      const phone = String(raw.phone || '').trim();

      if (!fullName || !phone) {
        msg.className = 'msg err';
        msg.textContent = 'من فضلك أدخل الاسم ورقم الجوال.';
        return;
      }

      if (!raw.privacy_consent) {
        msg.className = 'msg err';
        msg.textContent = 'يلزم الموافقة على سياسة الخصوصية.';
        return;
      }

      const submissionId = 'ALMOWAHID-WEB-' + Date.now() + '-' + Math.random().toString(36).slice(2,10).toUpperCase();

      const payload = {
        submission_id: submissionId,
        form_id: 'ALMOWAHID_WEBSITE_FORM_V1',
        full_name: fullName,
        phone,
        service: String(raw.service || '').trim(),
        nationality: String(raw.nationality || '').trim(),
        message: String(raw.message || '').trim(),
        privacy_consent: 'yes',
        page_url: location.href,
        utm_source: getSavedAttribution('utm_source'),
        utm_medium: getSavedAttribution('utm_medium'),
        utm_campaign: getSavedAttribution('utm_campaign'),
        utm_term: getSavedAttribution('utm_term'),
        utm_content: getSavedAttribution('utm_content'),
        gclid: getSavedAttribution('gclid'),
        gbraid: getSavedAttribution('gbraid'),
        wbraid: getSavedAttribution('wbraid'),
        campaign_id: getSavedAttribution('campaign_id') || qs.get('gad_campaignid') || '',
        adgroup_id: getSavedAttribution('adgroup_id'),
        creative_id: getSavedAttribution('creative_id') || getSavedAttribution('ad_id')
      };

      const googlePaid = !!(payload.gclid || payload.gbraid || payload.wbraid || String(payload.utm_source || '').toLowerCase() === 'google');
      const sourceTag = googlePaid
        ? 'المصدر: Google Ads'
        : (payload.utm_source ? 'المصدر: ' + payload.utm_source : 'المصدر: Website');

      const lines = [
        'السلام عليكم، أريد طلب خدمة من موقع الموحد للاستقدام.',
        'الاسم: ' + fullName,
        'رقم الجوال: ' + phone,
        'الخدمة: ' + payload.service,
        payload.nationality ? 'الجنسية: ' + payload.nationality : '',
        payload.message ? 'التفاصيل: ' + payload.message : '',
        sourceTag,
        'الصفحة: ' + location.pathname
      ].filter(Boolean);

      if (success) success.hidden = true;
      msg.className = 'msg';
      msg.textContent = 'جاري إرسال طلبك...';

      const oldText = submit ? submit.textContent : '';
      if (submit) {
        submit.disabled = true;
        submit.textContent = 'جاري الإرسال...';
      }

      try {
        const res = await fetch('/ads/lead.php', {
          method: 'POST',
          headers: {'Content-Type':'application/json', 'Accept':'application/json'},
          body: JSON.stringify(payload),
          credentials: 'same-origin'
        });

        const data = await res.json().catch(() => ({}));

        if (!res.ok || !data.ok) {
          throw new Error(data.message || data.error || 'تعذر إرسال الطلب الآن.');
        }

        try {
          localStorage.setItem('almowahid_identity', JSON.stringify({name:fullName, phone}));
        } catch (_) {}

        if (wa) {
          wa.href = 'https://wa.me/966537033347?text=' + encodeURIComponent(lines.join('\n'));
        }

        msg.className = 'msg ok';
        msg.textContent = 'تم استلام طلبك بنجاح.';
        if (success) {
          success.hidden = false;
          success.scrollIntoView({behavior:'smooth', block:'nearest'});
        }

        const params = {
          form_name: 'almowahid_campaign_lead',
          service: payload.service,
          nationality: payload.nationality,
          landing_path: location.pathname,
          lead_id: data.lead_id || '',
          transaction_id: data.lead_id || submissionId
        };
        pushEvent('lead_form_success', params);
        adsConversion(ADS.form, {
          value:1,
          currency:'SAR',
          transaction_id:data.lead_id || submissionId
        });
      } catch (err) {
        msg.className = 'msg err';
        msg.textContent = err && err.message ? err.message : 'حدث خطأ، حاول مرة أخرى أو تواصل عبر واتساب.';
      } finally {
        if (submit) {
          submit.disabled = false;
          submit.textContent = oldText;
        }
      }
    });
  });

  document.querySelectorAll('.nav details').forEach(d => d.addEventListener('toggle', () => {
    if (d.open) {
      document.querySelectorAll('.nav details').forEach(o => { if (o !== d) o.open = false; });
    }
  }));
})();