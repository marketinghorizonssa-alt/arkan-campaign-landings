#!/bin/sh
set -eu
ROOT="$HOME/domains/hositee.com/public_html/arkan-realestate-solutions"
BASE="https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/${1:?commit required}/public"
curl -fsSL "$BASE/google-ads-lead-v2.php" -o "$ROOT/google-ads-lead-v2.php"
/opt/alt/php85/usr/bin/php -l "$ROOT/google-ads-lead-v2.php"
echo ARKAN_GADS_V2_DEPLOY_OK
