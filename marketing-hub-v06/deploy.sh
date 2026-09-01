#!/bin/sh
set -eu
D="$HOME/domains/hositee.com/public_html/marketing"
mkdir -p "$D" "$D/data"
B="https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/marketing-deploy/marketing-hub-v06"
for F in index.php api.php webhook.php connect.php init_setup.php flush_outbox.php; do
  curl -Lfs "$B/$F" -o "$D/$F"
done
for F in api.php webhook.php connect.php init_setup.php flush_outbox.php; do
  php -l "$D/$F" >/dev/null
done
chmod 600 "$D/init_setup.php" "$D/flush_outbox.php" || true
printf 'marketing-v09-deployed\n'
