<?php
$root='/home/u878466595/domains/hositee.com/public_html/corts-expert';
$files=[
  $root.'/index.html',
  $root.'/riyadh-lawyer/index.html',
  $root.'/legal-consultation/index.html',
  $root.'/labor-law/index.html',
  $root.'/debt-collection-execution/index.html',
  $root.'/family-inheritance/index.html',
  $root.'/inheritance-estates/index.html',
  $root.'/business-commercial-law/index.html',
  $root.'/company-law/index.html',
  $root.'/trademark-intellectual-property/index.html',
  $root.'/real-estate-law/index.html',
  $root.'/criminal-specialized/index.html'
];

$css=<<<'CSS'

/* HERO_DIRECT_ACTIONS_V1 */
.hero-direct-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:22px}
.hero-direct-action{min-height:52px;display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:0 20px;border-radius:14px;text-decoration:none;font-weight:900;font-size:15px;line-height:1;box-shadow:0 10px 26px rgba(0,0,0,.16);transition:transform .18s ease,filter .18s ease,border-color .18s ease}
.hero-direct-action:hover{transform:translateY(-2px);filter:brightness(1.04)}
.hero-direct-action.wa{background:#14864f;color:#fff;border:1px solid rgba(255,255,255,.2)}
.hero-direct-action.wa img{width:23px;height:23px;display:block;filter:brightness(0) invert(1)}
.hero-direct-action.call{background:#d6b77f;color:#102445;border:1px solid #e6c990}
.hero-direct-action.call .hero-call-icon{font-size:22px;line-height:1}
@media(max-width:760px){.hero-direct-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:18px}.hero-direct-action{width:100%;min-height:50px;padding:0 12px;font-size:14px}}
@media(max-width:380px){.hero-direct-actions{grid-template-columns:1fr}.hero-direct-action{min-height:48px}}
CSS;

$html=<<<'HTML'
<!-- HERO_DIRECT_ACTIONS_V1 -->
<div class="hero-direct-actions" aria-label="تواصل مباشر">
  <a class="hero-direct-action wa" data-event="click_whatsapp" href="https://wa.me/966556044425?text=%D8%A7%D9%84%D8%B3%D9%84%D8%A7%D9%85%20%D8%B9%D9%84%D9%8A%D9%83%D9%85%D8%8C%20%D8%A3%D8%B1%D8%BA%D8%A8%20%D9%81%D9%8A%20%D8%A7%D9%84%D8%AA%D9%88%D8%A7%D8%B5%D9%84%20%D9%85%D8%B9%20%D9%83%D9%88%D8%B1%D8%AA%20%D8%A5%D9%83%D8%B3%D8%A8%D8%B1%D8%AA%20%D8%A8%D8%AE%D8%B5%D9%88%D8%B5%20%D8%AE%D8%AF%D9%85%D8%A9%20%D9%82%D8%A7%D9%86%D9%88%D9%86%D9%8A%D8%A9." aria-label="تواصل مع كورت إكسبرت عبر واتساب">
    <img src="/assets/whatsapp-brand-v1.svg" width="23" height="23" alt="" aria-hidden="true">
    <span>تواصل عبر واتساب</span>
  </a>
  <a class="hero-direct-action call" data-event="click_call" href="tel:+966556044425" aria-label="اتصل بكورت إكسبرت">
    <span class="hero-call-icon" aria-hidden="true">☎</span>
    <span>اتصل الآن</span>
  </a>
</div>
HTML;

$changed=0;$skipped=0;$failed=[];
foreach($files as $p){
  if(!file_exists($p)){ $failed[]=$p.' missing'; continue; }
  $s=file_get_contents($p);
  if($s===false){ $failed[]=$p.' unreadable'; continue; }
  if(strpos($s,'HERO_DIRECT_ACTIONS_V1')!==false){$skipped++;continue;}
  if(strpos($s,'class="hero-points"')===false){$failed[]=$p.' hero-points-not-found';continue;}
  if(strpos($s,'</style><script type="application/ld+json">')===false){$failed[]=$p.' style-anchor-not-found';continue;}

  $bak=$p.'.bak-hero-actions-20260923';
  if(!file_exists($bak)) copy($p,$bak);

  $s=str_replace('</style><script type="application/ld+json">',$css.'</style><script type="application/ld+json">',$s);

  $n=0;
  $s=preg_replace(
    '~(<div class="hero-points">.*?</div>)~s',
    '$1'.$html,
    $s,
    1,
    $n
  );
  if($n!==1){$failed[]=$p.' html-insert-failed';continue;}
  file_put_contents($p,$s);
  $changed++;
}
echo json_encode(['changed'=>$changed,'skipped'=>$skipped,'failed'=>$failed],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
?>