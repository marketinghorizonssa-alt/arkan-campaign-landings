<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>تقارير إحالات واتساب</title>
<style>
:root{--bg:#0a0f1f;--side:#0f172a;--card:#151e33;--card2:#111a2e;--line:#2a3654;--text:#fff;--muted:#9fb0cc;--ok:#2ee6a6;--warn:#ffc21a;--accent:#8190ff;--danger:#ff6f87}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:Tahoma,Arial,sans-serif}.shell{display:grid;grid-template-columns:240px 1fr;min-height:100vh}.side{padding:28px 22px;border-left:1px solid var(--line);background:var(--side);position:sticky;top:0;height:100vh}.brand{font-size:28px;font-weight:800;margin-bottom:32px}.brand i{font-style:normal;color:var(--accent)}nav a{display:block;color:#c7d1e5;text-decoration:none;font-size:15px;margin:0 0 18px}.active{color:#fff!important;font-weight:700}main{padding:34px 28px;min-width:0;max-width:1600px;width:100%}.head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start}.head h1{margin:0 0 10px;font-size:32px}.muted{color:var(--muted)}.card{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:18px;margin:16px 0}.filters{display:grid;grid-template-columns:1.2fr .9fr .9fr auto;gap:10px;align-items:end}.field label{display:block;font-size:12px;color:var(--muted);margin:0 0 6px}.input,.select,.btn{background:#0d1425;border:1px solid #344366;color:#fff;border-radius:10px;padding:10px 12px;min-height:42px}.input,.select{width:100%}.btn{cursor:pointer}.btn.primary{background:#253566;border-color:#8291ff}.btn.ok{background:#173d35;border-color:#275c50;color:var(--ok)}.btn.warn{background:#3a3012;border-color:#6e5a1a;color:var(--warn)}.presets{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.grid{display:grid;grid-template-columns:repeat(6,minmax(130px,1fr));gap:10px;margin:16px 0}.metric{font-size:28px;font-weight:800;margin-top:7px}.metric-card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:16px}.section-title{display:flex;justify-content:space-between;gap:12px;align-items:center}.badge{display:inline-block;border-radius:999px;padding:6px 9px;font-size:12px;background:#202b44;color:#c7d1e5;margin:3px}.meta{background:#24345b;color:#bfcaff}.tiktok{background:#392047;color:#f0c7ff}.google{background:#163a2e;color:#a7ffd9}.snapchat{background:#544d12;color:#fff38d}.organic{background:#30303a;color:#ddd}.qualified{background:#173d35;color:var(--ok)}.interested{background:#193957;color:#9bd0ff}.converted{background:#4a3b14;color:#ffd76d}.unqualified{background:#49202a;color:#ffb0bd}.new{background:#292f42;color:#d0d7e8}table{width:100%;border-collapse:collapse;font-size:13px}th,td{padding:11px 9px;border-bottom:1px solid #26324e;text-align:right;vertical-align:top}th{color:#91a1bf;font-weight:600}.phone{direction:ltr;text-align:right;white-space:nowrap}.leadrow{cursor:pointer}.leadrow:hover{background:#111a2e}.details{display:none;background:#0f1727}.details.open{display:table-row}.conv{padding:12px 0;display:grid;gap:8px}.msg{max-width:78%;padding:10px 12px;border-radius:12px;background:#1a263d;border:1px solid #2d3c5b;white-space:pre-wrap;line-height:1.5}.msg.outbound{margin-right:auto;background:#1b332c}.msg.inbound{margin-left:auto}.msgmeta{font-size:11px;color:#7789aa;margin-top:5px}.empty{padding:30px;text-align:center;color:var(--muted)}.toolbar{display:flex;gap:8px;flex-wrap:wrap}.status{font-size:12px;color:var(--muted)}.notice{padding:12px;border:1px solid #344366;border-radius:12px;background:#10192c;color:#b8c6de;font-size:13px}.toast{position:fixed;left:22px;bottom:22px;background:#111a2e;border:1px solid var(--line);padding:12px 14px;border-radius:12px;display:none;max-width:360px;z-index:10}.toast.show{display:block}@media(max-width:1100px){.shell{grid-template-columns:1fr}.side{display:none}.grid{grid-template-columns:repeat(3,1fr)}.filters{grid-template-columns:1fr 1fr}}@media(max-width:650px){main{padding:18px 12px}.grid{grid-template-columns:1fr 1fr}.filters{grid-template-columns:1fr}.head{display:block}.msg{max-width:95%}}
</style>
</head>
<body>
<div class="shell">
<aside class="side"><div class="brand">Marketing<i>.</i></div><nav>
<a href="index.php">Dashboard</a>
<a href="reports.php" class="active">التقارير</a>
<a href="index.php#clients">Clients</a>
<a href="index.php#conversations">Conversations</a>
<a href="index.php#platforms">Platforms</a>
</nav></aside>
<main>
<div class="head"><div><h1>تقارير إحالات واتساب</h1><div class="muted">العملاء الجدد لأول مرة فقط — مصدر الإحالة، تقييم الجودة، والمحادثة كاملة داخل الفترة المختارة.</div></div><div class="status" id="generated"></div></div>

<section class="card">
<div class="filters">
<div class="field"><label>العميل</label><select class="select" id="client"><option value="">اختر العميل</option></select></div>
<div class="field"><label>من</label><input class="input" type="date" id="from"></div>
<div class="field"><label>إلى</label><input class="input" type="date" id="to"></div>
<button class="btn primary" id="runBtn" onclick="runReport()">إظهار التقرير</button>
</div>
<div class="presets">
<button class="btn" onclick="preset('today')">النهاردة</button>
<button class="btn" onclick="preset('yesterday')">امبارح</button>
<button class="btn" onclick="preset('two')">النهاردة + امبارح</button>
</div>
</section>

<div class="grid">
<div class="metric-card"><div class="muted">عملاء جدد لأول مرة</div><div class="metric" id="mNew">—</div></div>
<div class="metric-card"><div class="muted">من إعلانات</div><div class="metric" id="mPaid">—</div></div>
<div class="metric-card"><div class="muted">Organic / Direct</div><div class="metric" id="mOrganic">—</div></div>
<div class="metric-card"><div class="muted">Qualified</div><div class="metric" id="mQualified">—</div></div>
<div class="metric-card"><div class="muted">Interested</div><div class="metric" id="mInterested">—</div></div>
<div class="metric-card"><div class="muted">Unqualified</div><div class="metric" id="mUnqualified">—</div></div>
</div>

<section class="card">
<div class="section-title"><div><b>مصادر الإحالات</b><div class="muted" style="font-size:12px;margin-top:4px">لو مفيش attribution إعلاني متتبع، بيتعرض Organic / Direct مع confidence منخفض.</div></div><div id="sources"></div></div>
</section>

<section class="card">
<div class="section-title">
<div><b>تفاصيل العملاء الجدد</b><div class="muted" id="definition" style="font-size:12px;margin-top:4px"></div></div>
<div class="toolbar">
<button class="btn ok" onclick="downloadCsv()">Download CSV</button>
<button class="btn warn" onclick="exportDrive()">Export Google Sheet</button>
</div>
</div>
<div class="notice" style="margin-top:12px">اضغط على أي صف لفتح المحادثة. التصدير يضع كل رسالة في صف مستقل مع رقم العميل، المصدر، التقييم، وقت الرسالة واتجاهها.</div>
<div id="tableWrap"><div class="empty">اختر العميل والتاريخ واضغط إظهار التقرير.</div></div>
</section>
</main>
</div>
<div class="toast" id="toast"></div>
<script>
const $=id=>document.getElementById(id);let R=null;
const sourceClass=k=>({meta:'meta',tiktok:'tiktok',google:'google',snapchat:'snapchat',organic:'organic'}[k]||'');
const stageClass=k=>({qualified:'qualified',interested:'interested',converted:'converted',unqualified:'unqualified',new:'new'}[k]||'new');
function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
function iso(d){const y=d.getFullYear(),m=String(d.getMonth()+1).padStart(2,'0'),x=String(d.getDate()).padStart(2,'0');return y+'-'+m+'-'+x}
function preset(k){const n=new Date(),a=new Date(n);if(k==='yesterday'){a.setDate(n.getDate()-1);$('from').value=$('to').value=iso(a)}else if(k==='two'){a.setDate(n.getDate()-1);$('from').value=iso(a);$('to').value=iso(n)}else{$('from').value=$('to').value=iso(n)}}
function toast(t){$('toast').textContent=t;$('toast').classList.add('show');setTimeout(()=>$('toast').classList.remove('show'),4200)}
async function j(u,o){const r=await fetch(u,o);const x=await r.json();if(!r.ok)throw new Error(x.error||'request_failed');return x}
async function loadClients(){const x=await j('api.php?action=clients');$('client').innerHTML='<option value="">اختر العميل</option>'+(x.clients||[]).map(c=>'<option value="'+esc(c.id)+'">'+esc(c.name)+'</option>').join('');const saved=localStorage.getItem('report_client');if(saved)$('client').value=saved}
async function runReport(){const c=$('client').value,f=$('from').value,t=$('to').value;if(!c){toast('اختار العميل الأول');return}if(!f||!t){toast('اختار التاريخ');return}$('runBtn').disabled=true;$('runBtn').textContent='جاري التحميل...';try{R=await j('lead_report_api.php?client_id='+encodeURIComponent(c)+'&from='+encodeURIComponent(f)+'&to='+encodeURIComponent(t));localStorage.setItem('report_client',c);render()}catch(e){toast('تعذر تحميل التقرير: '+e.message)}finally{$('runBtn').disabled=false;$('runBtn').textContent='إظهار التقرير'}}
function render(){const s=R.summary||{},q=s.quality||{};$('mNew').textContent=s.new_customers??0;$('mPaid').textContent=s.paid_ads??0;$('mOrganic').textContent=s.organic??0;$('mQualified').textContent=q.qualified??0;$('mInterested').textContent=q.interested??0;$('mUnqualified').textContent=q.unqualified??0;$('generated').textContent='آخر تحديث: '+(R.generated_at||'');$('definition').textContent=R.definition||'';
const labels={meta:'Meta Ads',tiktok:'TikTok Ads',google:'Google Ads',snapchat:'Snapchat Ads',linkedin:'LinkedIn Ads',x:'X Ads',organic:'Organic / Direct'};
$('sources').innerHTML=Object.entries(s.sources||{}).map(([k,v])=>'<span class="badge '+sourceClass(k)+'">'+esc(labels[k]||k)+' '+v+'</span>').join('')||'<span class="muted">لا توجد إحالات</span>';
if(!(R.leads||[]).length){$('tableWrap').innerHTML='<div class="empty">مفيش عملاء جدد لأول مرة في الفترة دي.</div>';return}
let h='<div style="overflow:auto"><table><thead><tr><th>#</th><th>الرقم</th><th>أول تواصل</th><th>المصدر</th><th>التقييم</th><th>Score</th><th>الرسائل</th><th>ملخص</th></tr></thead><tbody>';
R.leads.forEach((l,i)=>{h+='<tr class="leadrow" onclick="toggleDetail('+i+')"><td>'+(i+1)+'</td><td class="phone">'+esc(l.customer_phone)+'</td><td>'+esc(l.first_contact_at)+'</td><td><span class="badge '+sourceClass(l.source.key)+'">'+esc(l.source.label)+'</span><div class="muted" style="font-size:11px">'+esc(l.source.confidence)+'</div></td><td><span class="badge '+stageClass(l.quality.key)+'">'+esc(l.quality.label)+'</span></td><td>'+esc(l.quality.score)+'</td><td>'+esc(l.message_count)+'</td><td>'+esc(l.quality.summary||l.quality.reason||'—')+'</td></tr>';
h+='<tr class="details" id="d'+i+'"><td colspan="8"><div class="conv">'+(l.messages||[]).map(m=>'<div class="msg '+esc(m.direction)+'"><div>'+esc(m.text||('['+m.type+']'))+'</div><div class="msgmeta">'+esc(m.at)+' • '+esc(m.direction)+' • '+esc(m.type)+'</div></div>').join('')+'</div></td></tr>'});
h+='</tbody></table></div>';$('tableWrap').innerHTML=h}
function toggleDetail(i){const x=$('d'+i);if(x)x.classList.toggle('open')}
function downloadCsv(){if(!R){toast('شغّل التقرير الأول');return}const c=$('client').value,f=$('from').value,t=$('to').value;location.href='lead_report_api.php?action=csv&client_id='+encodeURIComponent(c)+'&from='+encodeURIComponent(f)+'&to='+encodeURIComponent(t)}
async function exportDrive(){if(!R){toast('شغّل التقرير الأول');return}const c=$('client').value,f=$('from').value,t=$('to').value;try{const x=await j('lead_report_api.php?action=drive_export&client_id='+encodeURIComponent(c)+'&from='+encodeURIComponent(f)+'&to='+encodeURIComponent(t),{method:'POST'});toast('تم إنشاء Google Sheet');if(x.url)window.open(x.url,'_blank')}catch(e){if(e.message==='drive_not_configured')toast('ربط Google Drive المباشر غير مفعّل على السيرفر لسه. Download CSV شغال الآن.');else toast('فشل التصدير إلى Drive: '+e.message)}}
preset('today');loadClients().catch(e=>toast(e.message));
</script>
</body></html>