<?php
$root='/home/u878466595/domains/hositee.com/public_html/corts-expert';
$src="$root/riyadh-lawyer/index.html";
$dir="$root/_hero-sync-canary";
@mkdir($dir,0755,true);
$html=file_get_contents($src);
$html=preg_replace('/<link rel="canonical" href="[^"]+">/','<meta name="robots" content="noindex,nofollow"><link rel="canonical" href="https://cortsexpert.hositee.com/riyadh-lawyer/">',$html,1);
$html=str_replace('fetchpriority="high" decoding="async"','fetchpriority="high" decoding="sync"',$html);
file_put_contents("$dir/index.html",$html);
echo "created\n";
?>