#!/bin/sh
set -eu
D="$HOME/domains/hositee.com/public_html/marketing"
mkdir -p "$D" "$D/data"
B="https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/marketing-deploy/marketing-hub-v06"
for F in index.php api.php webhook.php wa_click_attribution.php sync_pcare_wa_attribution.php google_conversion_feed.php google_sheet_backfill.php google_sheet_stage_export.php qualified_lead_pool_feed.php connect.php init_setup.php flush_outbox.php local_quality_v2.php lead_pool_worker.php lead_pool_dispatcher.php lead_pool_patch_v2.php lead_pool_cutover.php ensure_ycloud_stage_events.php quality_rules_v2.json platform_stage_config.json lead_pool_config.json; do
  curl -Lfs "$B/$F" -o "$D/$F"
done
for F in api.php webhook.php wa_click_attribution.php sync_pcare_wa_attribution.php google_conversion_feed.php connect.php init_setup.php flush_outbox.php local_quality_v2.php lead_pool_worker.php lead_pool_dispatcher.php lead_pool_patch_v2.php lead_pool_cutover.php ensure_ycloud_stage_events.php; do
  php -l "$D/$F" >/dev/null
done
php "$D/lead_pool_patch_v2.php" >/dev/null
php "$D/lead_pool_cutover.php" >/dev/null
chmod 600 "$D/init_setup.php" "$D/flush_outbox.php" "$D/local_quality_v2.php" "$D/lead_pool_worker.php" "$D/lead_pool_dispatcher.php" "$D/lead_pool_patch_v2.php" "$D/lead_pool_cutover.php" "$D/ensure_ycloud_stage_events.php" "$D/quality_rules_v2.json" "$D/platform_stage_config.json" "$D/lead_pool_config.json" || true
printf 'marketing-v14-google-whatsapp-funnel-deployed\n'
