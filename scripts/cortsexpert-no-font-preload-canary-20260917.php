<?php
$root='/home/u878466595/domains/hositee.com/public_html/corts-expert';$src="$root/riyadh-lawyer/index.html";$dir="$root/_no-font-preload-canary";@mkdir($dir,0755,true);$html=file_get_contents($src);
$html=preg_replace('/<link rel="canonical" href="[^"]+">/','<meta name="robots" content="noindex,nofollow"><link rel="canonical" href="https://cortsexpert.hositee.com/riyadh-lawyer/">',$html,1);
$html=str_replace('<link rel="preload" href="/assets/fonts/beiruti-local-v1.woff2" as="font" type="font/woff2" crossorigin>','',$html);
file_put_contents("$dir/index.html",$html);echo "created\n";
?>