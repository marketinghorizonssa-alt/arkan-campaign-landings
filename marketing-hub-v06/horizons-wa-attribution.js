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
