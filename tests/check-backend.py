import json, urllib.request, urllib.error, sqlite3, pathlib, secrets, subprocess, os, time, atexit, shutil
base='http://127.0.0.1:8135'
private=pathlib.Path(__file__).resolve().parents[2]/'test-private'
shutil.rmtree(private,ignore_errors=True)
private.mkdir(exist_ok=True)
runtime=pathlib.Path(__file__).resolve().parents[2]/'php-runtime'
server=subprocess.Popen([str(runtime/'usr/bin/php8.3'),'-n','-d','extension='+str(runtime/'usr/lib/php/20230831/pdo.so'),'-d','extension='+str(runtime/'usr/lib/php/20230831/pdo_sqlite.so'),'-S','127.0.0.1:8135','-t','public','public/index.php'],env={**os.environ,'ARKAN_PRIVATE_DIR':str(private)},stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
atexit.register(server.terminate)
time.sleep(.3)
(private/'arkan-feed-token').write_text('local-test-feed-only')
def req(path,data=None):
 r=urllib.request.Request(base+path,data=None if data is None else json.dumps(data).encode(),headers={'Content-Type':'application/json'})
 try:
  with urllib.request.urlopen(r) as x:return x.status,x.read()
 except urllib.error.HTTPError as x:return x.code,x.read()
assert req('/api/lead',{})[0]==422
lead={'full_name':'اختبار محلي','phone':'0500000123','city':'جدة','property_type':'ready_unit','employer_type':'private','privacy_consent':'1','landing_page_id':'P8','gclid':'local-test-click','campaign_id':'local-campaign'}
x=json.loads(req('/api/lead',lead)[1]);assert x['ok'] and not x['duplicate']
y=json.loads(req('/api/lead',lead)[1]);assert y['duplicate']
db=sqlite3.connect(private/'arkan-leads.sqlite');assert db.execute('select count(*) from leads where gclid=?',('local-test-click',)).fetchone()[0]==2
assert req('/api/click',{'event_id':'local-test-0000','event_type':'invalid'})[0]==422
click={'event_id':'local-test-0001','event_type':'whatsapp','placement':'hero','landing_page_id':'P8','tracking_token':'ARK-AT-123456789012ABCD','utm_campaign':'=UNSAFE()'}
x=json.loads(req('/api/click',click)[1]);assert x['ok'] and not x['duplicate']
x=json.loads(req('/api/click',click)[1]);assert x['duplicate']
assert req('/click-feed.csv')[0]==403
status,feed=req('/click-feed.csv?token=local-test-feed-only');assert status==200 and b"'=UNSAFE()" in feed
assert sqlite3.connect(private/'arkan-clicks.sqlite').execute('select count(*) from contact_clicks').fetchone()[0]==1
pages=json.loads((pathlib.Path(__file__).resolve().parents[2]/'routes-ready.json').read_text())+['/سياسة-الخصوصية/','/تم-استلام-الطلب/']
checks=[]
for page in pages:
 status,html=req(urllib.parse.quote(page));s=html.decode();assert status==200 and s.count('<h1>')==1 and 'gtag/js?id=AW-18351042794' in s and 'gtm.js' not in s
 if page in pages[:11]:assert 'hero-actions' in s and 'id="leadForm"' in s
 checks.append({'path':page,'status':status,'bytes':len(html)})
print(json.dumps({'ok':True,'checks':['validation','durable storage','duplicate detection','click idempotency','feed authorization','formula injection prevention','13 page routes'],'pages':checks},ensure_ascii=False))
