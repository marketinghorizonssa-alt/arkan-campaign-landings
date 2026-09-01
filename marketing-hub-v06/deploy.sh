#!/bin/sh
set -eu
D="$HOME/domains/hositee.com/public_html/marketing"
mkdir -p "$D"
B="https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/marketing-deploy/marketing-hub-v06"
curl -Lfs "$B/index.php" -o "$D/index.php"
curl -Lfs "$B/api.php" -o "$D/api.php"
curl -Lfs "$B/webhook.php" -o "$D/webhook.php"
php -l "$D/api.php" >/dev/null
php -l "$D/webhook.php" >/dev/null
printf 'marketing-v06-deployed\n'
