#!/bin/sh
set -eu
D="$HOME/domains/hositee.com/public_html/marketing"
B="https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/marketing-deploy/marketing-hub-v06"
mkdir -p "$D"
for F in flush_outbox.php tiktok_crm_dispatcher.php tiktok_crm_event_sets.json lead_pool_worker.php lead_pool_dispatcher.php lead_pool_config.json platform_stage_config.json; do
  curl -Lfs "$B/$F" -o "$D/$F"
done
for F in flush_outbox.php tiktok_crm_dispatcher.php lead_pool_worker.php lead_pool_dispatcher.php; do
  php -l "$D/$F" >/dev/null
done
chmod 600 "$D/flush_outbox.php" "$D/tiktok_crm_dispatcher.php" "$D/tiktok_crm_event_sets.json" "$D/lead_pool_worker.php" "$D/lead_pool_dispatcher.php" "$D/lead_pool_config.json" "$D/platform_stage_config.json" || true
printf 'FILES_OK\n'
php "$D/lead_pool_worker.php"
php "$D/tiktok_crm_dispatcher.php"
