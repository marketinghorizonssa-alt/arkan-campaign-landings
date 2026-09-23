<?php
$root='/home/u878466595/domains/hositee.com/public_html/etizan-law';
$view=$root.'/app/view.php';
$css=$root.'/assets/site.css';

if(!is_file($view)||!is_file($css)){fwrite(STDERR,"missing_files\n");exit(1);}

$viewBak=$view.'.bak-hero-actions-20260923';
$cssBak=$css.'.bak-hero-actions-20260923';
if(!is_file($viewBak)) copy($view,$viewBak);
if(!is_file($cssBak)) copy($css,$cssBak);

$v=file_get_contents($view);
$c=file_get_contents($css);

$marker='ETIZAN_HERO_DIRECT_ACTIONS_V1';

if(strpos($v,$marker)===false){
  $old='<div class="hero-proof"><span>فهم قانوني وتجاري</span><span>وضوح وشفافية في نطاق العمل</span><span>خصوصية في التعامل مع البيانات</span></div>';
  $insert=<<<'HTML'
<!-- ETIZAN_HERO_DIRECT_ACTIONS_V1 -->
<div class="hero-direct-actions" aria-label="تواصل مباشر مع فرع <?=eh($x['city'])?>">
  <a class="hero-direct-action wa track-wa" href="https://wa.me/<?=eh($x['wa'])?>?text=<?=$wct?>" aria-label="تواصل عبر واتساب مع فرع <?=eh($x['city'])?>">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 0 0-7.7 13.7L3 21l4.4-1.2A9 9 0 1 0 12 3Z"/><path d="M8.2 7.6c.4 3.9 3.3 6.8 7.2 7.2l1.3-1.8-2.6-1.2-.8 1c-1.5-.6-2.7-1.8-3.3-3.3l1-1-1.2-2.5-1.6 1.6Z"/></svg>
    <span>تواصل عبر واتساب</span>
  </a>
  <a class="hero-direct-action call track-call" href="tel:+<?=eh($x['phone'])?>" aria-label="اتصل بفرع <?=eh($x['city'])?>">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.7 15.7 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1.1-.2 1.2.4 2.5.6 3.8.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.8 21 3 13.2 3 3.7c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.6.6 3.8.1.4 0 .8-.2 1.1l-2.3 2.2Z"/></svg>
    <span>اتصل الآن</span>
  </a>
</div>
HTML;
  if(strpos($v,$old)===false){fwrite(STDERR,"view_anchor_not_found\n");exit(2);}
  $v=str_replace($old,$old.$insert,$v);
  file_put_contents($view,$v);
}

if(strpos($c,$marker)===false){
  $add=<<<'CSS'

/* ETIZAN_HERO_DIRECT_ACTIONS_V1 */
.hero-direct-actions{display:flex;flex-wrap:wrap;gap:11px;margin-top:22px}
.hero-direct-action{min-height:50px;display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:0 19px;border-radius:11px;text-decoration:none;font-weight:900;font-size:14px;line-height:1;box-shadow:0 10px 28px rgba(0,0,0,.18);transition:transform .18s ease,filter .18s ease}
.hero-direct-action:hover{transform:translateY(-2px);filter:brightness(1.04)}
.hero-direct-action svg{width:22px;height:22px;display:block;fill:currentColor}
.hero-direct-action.wa{background:var(--wa);color:#fff;border:1px solid rgba(255,255,255,.18)}
.hero-direct-action.call{background:#fff;color:var(--primary-dark);border:1px solid rgba(255,255,255,.55)}
@media(max-width:820px){.hero-direct-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:18px}.hero-direct-action{width:100%;min-height:50px}}
@media(max-width:380px){.hero-direct-actions{grid-template-columns:1fr}.hero-direct-action{min-height:48px}}
CSS;
  file_put_contents($css,$c.$add);
}

echo json_encode([
  'ok'=>true,
  'view_marker'=>substr_count(file_get_contents($view),$marker),
  'css_marker'=>substr_count(file_get_contents($css),$marker)
],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
?>