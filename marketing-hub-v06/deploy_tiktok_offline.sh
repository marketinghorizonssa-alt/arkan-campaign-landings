#!/bin/sh
set -eu
D="$HOME/domains/hositee.com/public_html/marketing"
B="https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/marketing-deploy/marketing-hub-v06"
for F in tiktok_offline_dispatcher.php tiktok_offline_event_sets.json flush_outbox.php; do
  curl -Lfs "$B/$F" -o "$D/$F"
done
php -l "$D/tiktok_offline_dispatcher.php" >/dev/null
php -l "$D/flush_outbox.php" >/dev/null
chmod 600 "$D/tiktok_offline_dispatcher.php" "$D/tiktok_offline_event_sets.json" "$D/flush_outbox.php" || true
printf 'OFFLINE_DEPLOY_OK\n'
php "$D/tiktok_offline_dispatcher.php"
