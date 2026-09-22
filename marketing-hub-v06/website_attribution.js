(function(){
  'use strict';
  var MARKER='source:web';
  function isWhatsApp(u){
    try{
      var x=new URL(u,location.href);
      var h=x.hostname.toLowerCase();
      return h==='wa.me'||h.endsWith('.wa.me')||h==='api.whatsapp.com'||h==='web.whatsapp.com'||h==='whatsapp.com'||h.endsWith('.whatsapp.com');
    }catch(e){return false;}
  }
  function tagUrl(u){
    try{
      var x=new URL(u,location.href);
      if(!isWhatsApp(x.href)) return u;
      var t=x.searchParams.get('text')||'';
      if(t.toLowerCase().indexOf(MARKER)<0){
        t=(t?t.replace(/\s+$/,'')+'\n\n':'')+MARKER;
        x.searchParams.set('text',t);
      }
      return x.href;
    }catch(e){return u;}
  }
  function tag(a){
    if(!a||!a.getAttribute)return;
    var h=a.getAttribute('href')||'';
    if(h&&isWhatsApp(h))a.setAttribute('href',tagUrl(h));
  }
  function scan(root){
    (root||document).querySelectorAll('a[href]').forEach(tag);
  }
  document.addEventListener('DOMContentLoaded',function(){scan(document);});
  document.addEventListener('click',function(e){
    var a=e.target&&e.target.closest?e.target.closest('a[href]'):null;
    if(a)tag(a);
  },true);
  window.HorizonsWhatsAppAttribution={tagUrl:tagUrl,scan:scan,marker:MARKER};
})();