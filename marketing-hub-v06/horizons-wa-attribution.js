(function(w,d){
  'use strict';
  if(w.__HORIZONS_WA_ATTR_V1__) return;
  w.__HORIZONS_WA_ATTR_V1__=true;
  var cfg=w.HORIZONS_WA_ATTR||{};
  var clientId=String(cfg.clientId||cfg.client_id||'').trim();
  var business=String(cfg.businessNumber||cfg.business_number||'').trim();
  var endpoint=String(cfg.endpoint||'https://marketing.hositee.com/wa_click_attribution.php');
  if(!clientId||!business)return;

  var ns='hzn_wa_'+clientId.replace(/[^a-z0-9]/gi,'_')+'_';
  var FIRST=ns+'first_v1',LAST=ns+'last_v1',HIST=ns+'history_v1';
  var busy=false;

  function params(url){
    var out={};
    try{new URL(url||location.href,location.href).searchParams.forEach(function(v,k){
      if(Object.prototype.hasOwnProperty.call(out,k)){if(!Array.isArray(out[k]))out[k]=[out[k]];out[k].push(v)}else out[k]=v;
    })}catch(e){}
    return out;
  }
  function touch(){
    return {url:location.href,path:location.pathname,referrer:d.referrer||'',title:d.title||'',params:params(location.href),at:new Date().toISOString()};
  }
  function read(k){try{var x=JSON.parse(localStorage.getItem(k)||'null');return x&&typeof x==='object'?x:null}catch(e){return null}}
  function write(k,v){try{localStorage.setItem(k,JSON.stringify(v))}catch(e){}}
  function hasCampaign(p){
    if(!p||typeof p!=='object')return false;
    var strong=['gclid','gbraid','wbraid','dclid','fbclid','ttclid','scclid','ScCid','msclkid','li_fat_id','twclid','gad_source','gad_campaignid'];
    for(var i=0;i<strong.length;i++)if(p[strong[i]])return true;
    return Object.keys(p).some(function(k){return /^utm_/i.test(k)});
  }
  function externalRef(){try{return !!d.referrer&&new URL(d.referrer).hostname!==location.hostname}catch(e){return false}}
  function touches(){
    var cur=touch(),first=read(FIRST),last=read(LAST),hist=read(HIST),ttl=90*86400000;
    if(first&&first.at&&Date.now()-Date.parse(first.at)>ttl){first=null;last=null;hist=[]}
    if(!first){first=cur;write(FIRST,first)}
    if(!last||hasCampaign(cur.params)||externalRef()){last=cur;write(LAST,last)}
    if(!Array.isArray(hist))hist=[];
    var prev=hist.length?hist[hist.length-1]:null;
    if(!prev||prev.url!==cur.url||prev.referrer!==cur.referrer){hist.push(cur);if(hist.length>20)hist=hist.slice(-20);write(HIST,hist)}
    return {first:first,last:last,current:cur,history:hist};
  }
  touches();

  function digits(v){return String(v||'').replace(/\D+/g,'')}
  function waUrl(href){
    try{
      var u=new URL(href,location.href),h=u.hostname.toLowerCase();
      if(h!=='wa.me'&&h!=='api.whatsapp.com'&&h!=='web.whatsapp.com')return null;
      var p='';
      if(h==='wa.me')p=digits(u.pathname);
      else p=digits(u.searchParams.get('phone')||'');
      var expected=digits(business);
      if(expected&&p&&p!==expected)return null;
      return u;
    }catch(e){return null}
  }
  function stripHidden(s){return String(s||'').replace(/[\u200B\u200C\u200D\uFEFF]{12,}/gu,'').trim()}
  function encodeAscii(s){
    var a=['\u200B','\u200C','\u200D','\uFEFF'],o='';
    for(var i=0;i<s.length;i++){var b=s.charCodeAt(i)&255;o+=a[(b>>6)&3]+a[(b>>4)&3]+a[(b>>2)&3]+a[b&3]}
    return o;
  }
  function embed(text,hidden){
    text=stripHidden(text);
    if(!text)return hidden;
    var m=text.match(/[،,\s]/u);
    if(m&&typeof m.index==='number'&&m.index>0){
      var i=m.index+1;return text.slice(0,i)+hidden+text.slice(i);
    }
    var cut=Math.min(4,text.length);return text.slice(0,cut)+hidden+text.slice(cut);
  }
  function openTarget(win,url){
    try{if(win&&!win.closed){win.location.replace(url);return}}catch(e){}
    location.href=url;
  }
  function timeout(ms){return new Promise(function(_,rej){setTimeout(function(){rej(new Error('timeout'))},ms)})}

  d.addEventListener('click',function(ev){
    var a=ev.target&&ev.target.closest?ev.target.closest('a'):null;if(!a)return;
    var u=waUrl(a.getAttribute('href')||a.href||'');if(!u)return;
    if(busy){ev.preventDefault();return}
    ev.preventDefault();
    busy=true;

    var original=u.toString(),state=touches(),win=null;
    try{win=w.open('about:blank','_blank');if(win)win.opener=null}catch(e){}
    var body={
      mint_horizons_token:true,
      client_id:clientId,
      business_number:business,
      source_url:location.href,
      page_url:location.href,
      landing_url:(state.first&&state.first.url)||location.href,
      referrer:d.referrer||'',
      original_href:original,
      button_text:(a.textContent||a.getAttribute('aria-label')||'WhatsApp').trim().slice(0,500),
      button_context:location.pathname+'|'+(a.className||''),
      first_touch:state.first||{},
      last_touch:state.last||{},
      current_touch:state.current||{},
      touch_history:state.history||[],
      query_params:params(location.href),
      browser_time:new Date().toISOString()
    };
    var req=fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(body),mode:'cors',credentials:'omit'});
    Promise.race([req,timeout(Number(cfg.timeoutMs||2200))])
      .then(function(r){if(!r||!r.ok)throw new Error('hub_http');return r.json()})
      .then(function(j){
        if(!j||!j.ok||!j.click_token)throw new Error('hub_token');
        var visible=u.searchParams.get('text')||String(cfg.defaultText||'');
        u.searchParams.set('text',embed(visible,encodeAscii(String(j.click_token))));
        openTarget(win,u.toString());
      })
      .catch(function(){openTarget(win,original)})
      .finally(function(){setTimeout(function(){busy=false},800)});
  },true);
})(window,document);
