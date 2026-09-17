<?php
$root='/home/u878466595/domains/hositee.com/public_html/corts-expert';$src="$root/riyadh-lawyer/index.html";$dir="$root/_hero-accent-canary";@mkdir($dir,0755,true);$html=file_get_contents($src);
$html=preg_replace('/<link rel="canonical" href="[^"]+">/','<meta name="robots" content="noindex,nofollow"><link rel="canonical" href="https://cortsexpert.hositee.com/riyadh-lawyer/">',$html,1);
$css='/* HERO_ACCENT_CANARY */@media(max-width:760px){.hero-media{width:100%!important;height:60px!important;inset:0 0 auto 0!important}.hero-media img{opacity:.45!important;object-position:center 42%!important}.hero:before{background:linear-gradient(180deg,rgba(16,36,69,.72),rgba(16,36,69,.98) 34%,#102445 100%)!important}}';
$html=str_replace('</style>',$css.'</style>',$html);file_put_contents("$dir/index.html",$html);echo "created\n";
?>