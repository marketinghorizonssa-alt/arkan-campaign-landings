const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict'),crypto=require('node:crypto').webcrypto;
const targets={form:'AW-18351042794/F7HpCLHk49scEOrhuq5E',whatsapp:'AW-18351042794/XQr5CLTk49scEOrhuq5E',call:'AW-18351042794/x8TACLfk49scEOrhuq5E'};
async function run({ok=true,duplicate=false,legacy=false,ready=false,lp="",initialId="P8"}={}){
 const events=[],requests=[],domEvents=[],docListeners={},formListeners={},mem={};
 const store={getItem:k=>mem[k]||null,setItem:(k,v)=>mem[k]=v,removeItem:k=>delete mem[k]};
 const elements={};for(const k of ['full_name','phone','city','property_type','employer_type','privacy_consent','landing_page_id','landing_path','referrer'])elements[k]={value:'',checked:true};
 Object.assign(elements.full_name,{value:'اختبار'});elements.phone.value='0500000123';elements.city.value='جدة';elements.property_type.value='ready_unit';elements.landing_page_id.value=initialId;
 const button={textContent:'إرسال',disabled:false},status={textContent:''};
 const form={elements,querySelectorAll:()=>[],querySelector:s=>s.includes('button')?button:null,addEventListener:(n,f)=>formListeners[n]=f};
 const doc={referrer:'',head:{appendChild(){}},createElement:()=>({}),querySelector:()=>null,querySelectorAll:()=>[],getElementById:n=>n==='leadForm'?form:n==='formStatus'?status:null,addEventListener:(n,f)=>docListeners[n]=f,dispatchEvent:e=>domEvents.push(e)};
 const w={ARKAN_CONFIG:{endpoint:'https://arkan-realestate-solutions.hositee.com/api/lead'},dataLayer:[],crypto,gtag:(...args)=>events.push(args),setTimeout:()=>{},__arkanGtmListenerInstalled:ready};if(legacy)w.__loadArkanGtm=()=>{};
 const fetch=async(url,init)=>{requests.push({url,body:JSON.parse(init.body)});return {ok,status:ok?200:503,json:async()=>({ok,duplicate,lead_token:'ARK-WEB-LOCAL'})}};
 const sandbox={window:w,document:doc,location:{href:'https://arkan2030.com/shopping/?gclid=local-gclid',pathname:'/shopping/',search:'?gclid=local-gclid'+(lp?'&lp='+lp:'')},localStorage:store,sessionStorage:store,crypto,fetch,URL,URLSearchParams,Uint8Array,Date,CustomEvent:class{constructor(type,options){this.type=type;this.detail=options.detail}},FormData:class{constructor(f){this.f=f}entries(){return Object.entries(this.f.elements).map(([k,v])=>[k,v.value])}},console};
 vm.runInNewContext(fs.readFileSync('public/assets/site.js','utf8'),sandbox);
 for(const kind of ['whatsapp','call']){const link={classList:{contains:c=>c==='track-'+kind},closest:s=>s==='.hero-actions'?{}:null};docListeners.click({target:{closest:()=>link}})}
 assert.equal(requests.filter(r=>r.url.endsWith('/api/click')).length,2);
 assert(requests.filter(r=>r.url.endsWith('/api/click')).every(r=>r.body.gclid==='local-gclid'&&r.body.placement==='hero'));
 if(!(legacy&&ready)){assert.equal(events.filter(e=>e[1]==='conversion'&&e[2].send_to===targets.call).length,1);assert.equal(events.filter(e=>e[1]==='conversion'&&e[2].send_to===targets.whatsapp).length,1)}
 await formListeners.submit({preventDefault(){}});
 if(lp&&legacy)assert.equal(form.elements.landing_page_id.value,lp);
 const n=events.filter(e=>e[1]==='conversion'&&e[2].send_to===targets.form).length;
 assert.equal(n,ok&&!duplicate&&!(legacy&&ready)?1:0);
 assert.equal(domEvents.length,ok&&!duplicate?1:0);
 if(n)assert.equal(events.find(e=>e[2]?.send_to===targets.form)[2].transaction_id,'ARK-WEB-LOCAL');
 return {ok,duplicate,legacy,ready,form_conversions:n};
}
async function thankYou(){
 const events=[],listeners={},mem={},links={afterFormWa:{addEventListener:(n,f)=>listeners.wa=f},afterFormCall:{addEventListener:(n,f)=>listeners.call=f}};
 const store={getItem:k=>mem[k]||null};const w={dataLayer:[],gtag:(...x)=>events.push(x)};
 vm.runInNewContext(fs.readFileSync('public/assets/thank-you.js','utf8'),{window:w,document:{getElementById:n=>links[n]},sessionStorage:store,location:{hostname:'arkan2030.com',pathname:'/thanks/'},crypto,fetch:async()=>({}),URL,Date,encodeURIComponent});
 assert.equal(events.length,0);listeners.wa();listeners.call();assert.equal(events.length,2);assert(events.every(x=>x[2].send_to!==targets.form));return 'no form conversion on thank-you reload';
}
(async()=>console.log(JSON.stringify({ok:true,cases:[await run(),await run({duplicate:true}),await run({ok:false}),await run({legacy:true}),await run({legacy:true,ready:true}),await run({legacy:true,lp:'P6',initialId:'P0'}),await run({legacy:true,lp:'P8',initialId:'P0'}),await run({legacy:true,lp:'P10',initialId:'P0'})],thank_you:await thankYou()})))().catch(e=>{console.error(e);process.exit(1)});
