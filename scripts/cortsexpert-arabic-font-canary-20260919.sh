#!/bin/sh
set -eu
ROOT=/home/u878466595/domains/hositee.com/public_html/corts-expert
FONT="$ROOT/assets/fonts/beiruti-arabic-v5.woff2"
curl -fsSL https://fonts.gstatic.com/s/beiruti/v5/JTUXjIU69Cmr9FGcSA1t4FZA.woff2 -o "$FONT"
mkdir -p "$ROOT/_arabic-font-canary"
php -r '
$root="/home/u878466595/domains/hositee.com/public_html/corts-expert";
$html=file_get_contents($root."/riyadh-lawyer/index.html");
$html=preg_replace("~<link rel=\"canonical\" href=\"[^\"]+\">~","<meta name=\"robots\" content=\"noindex,nofollow\"><link rel=\"canonical\" href=\"https://cortsexpert.hositee.com/riyadh-lawyer/\">",$html,1);
$html=str_replace("/assets/fonts/beiruti-local-v1.woff2","/assets/fonts/beiruti-arabic-v5.woff2",$html);
file_put_contents($root."/_arabic-font-canary/index.html",$html);
'
printf 'created font=%s\n' "$(wc -c < "$FONT")"
