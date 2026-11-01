(function(root,factory){var api=factory();if(typeof module==='object'&&module.exports)module.exports=api;root.ArkanAttribution=api;})(typeof window==='object'?window:globalThis,function(){
 'use strict';
 var fields=['utm_source','utm_medium','utm_campaign','utm_term','utm_content','utm_source_platform','gclid','gbraid','wbraid','dclid','ttclid','fbclid','scclid','ScCid','msclkid','campaign_id','campaign_name','ad_group_id','ad_group_name','ad_id','keyword','match_type','device','network'];
 var aliases={campaign_id:['campaign_id','campaignid'],ad_group_id:['ad_group_id','adgroup_id','adgroupid'],ad_id:['ad_id','creative'],match_type:['match_type','matchtype']};
 function source(p){var u=String(p.utm_source||p.utm_source_platform||'').toLowerCase();if(p.gclid||p.gbraid||p.wbraid||p.dclid||/google|adwords/.test(u))return'google';if(p.ttclid||/tiktok/.test(u))return'tiktok';if(p.fbclid||/facebook|instagram|meta/.test(u))return'meta';if(p.scclid||p.ScCid||/snap/.test(u))return'snapchat';if(p.msclkid)return'microsoft_ads';return u||'website';}
 function capture(previous,query,url,now){
  previous=previous&&typeof previous==='object'?previous:{};var p=new URLSearchParams(query||''),fresh={};fields.forEach(function(k){(aliases[k]||[k]).some(function(a){var v=p.get(a);if(v){fresh[k]=v;return true;}return false;});});
  var hasFresh=Object.keys(fresh).some(function(k){return /^(utm_|gclid|gbraid|wbraid|dclid|ttclid|fbclid|scclid|ScCid|msclkid)/.test(k);});
  var age=now-Date.parse(previous.captured_at||'');if(!Number.isFinite(age)||age>90*86400000||age< -60000)previous={};
  var current=hasFresh?fresh:previous;var out={};fields.forEach(function(k){if(current[k])out[k]=String(current[k]);});
  out.captured_at=hasFresh||!previous.captured_at?new Date(now).toISOString():previous.captured_at;out.first_landing_url=hasFresh||!previous.first_landing_url?url:previous.first_landing_url;out.platform_source=source(out);return out;
 }
 function storage(store){var memory={};return{get:function(k){try{return store.getItem(k)||memory[k]||'';}catch(e){return memory[k]||'';}},set:function(k,v){memory[k]=v;try{store.setItem(k,v);}catch(e){}},remove:function(k){delete memory[k];try{store.removeItem(k);}catch(e){}}};}
 return{fields:fields,source:source,capture:capture,storage:storage};
});
