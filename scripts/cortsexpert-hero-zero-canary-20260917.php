<?php
$root='/home/u878466595/domains/hositee.com/public_html/corts-expert';
$src="$root/riyadh-lawyer/index.html";
$dir="$root/_hero-zero-canary";
@mkdir($dir,0755,true);
$html=file_get_contents($src);
$html=preg_replace('/<link rel="canonical" href="[^"]+">/','<meta name="robots" content="noindex,nofollow"><link rel="canonical" href="https://cortsexpert.hositee.com/riyadh-lawyer/">',$html,1);
$html=str_replace('<link rel="preload" href="/assets/hero-mobile.jpg" as="image" media="(max-width:760px)" fetchpriority="high">','',$html);
$html=str_replace('<source media="(max-width:760px)" srcset="/assets/hero-mobile.jpg">','<source media="(max-width:760px)" srcset="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=">',$html);
$html=str_replace('</style>','@media(max-width:760px){.hero-media{display:none!important}.hero:before{background:linear-gradient(160deg,#102445 0%,#1b3461 58%,#294978 100%)!important}}</style>',$html);
file_put_contents("$dir/index.html",$html);
echo "created\n";
?>