#!/bin/sh
set -eu
D="$HOME/domains/hositee.com/public_html/marketing"
mkdir -p "$D" "$D/data"
B="https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/marketing-deploy/marketing-hub-v06"
for F in index.php reports.php lead_report_api.php sheet_feed.php api.php webhook.php wa_click_attribution.php sync_pcare_wa_attribution.php google_conversion_feed.php google_sheet_backfill.php google_sheet_stage_export.php qualified_lead_pool_feed.php reconcile_conversations.php diagnose_bcare_attribution.php repair_recent_attribution.php connect.php init_setup.php flush_outbox.php local_quality_v2.php lead_pool_worker.php lead_pool_dispatcher.php lead_pool_patch_v2.php lead_pool_cutover.php ensure_ycloud_stage_events.php quality_rules_v2.json platform_stage_config.json lead_pool_config.json wa_attribution_clients.json horizons-wa-attribution.js; do
  curl -Lfs "$B/$F" -o "$D/$F"
done
for F in api.php lead_report_api.php webhook.php reconcile_conversations.php wa_click_attribution.php sync_pcare_wa_attribution.php google_conversion_feed.php connect.php init_setup.php flush_outbox.php local_quality_v2.php lead_pool_worker.php lead_pool_dispatcher.php lead_pool_patch_v2.php lead_pool_cutover.php ensure_ycloud_stage_events.php; do
  php -l "$D/$F" >/dev/null
done
php "$D/lead_pool_patch_v2.php" >/dev/null
php "$D/lead_pool_cutover.php" >/dev/null
chmod 600 "$D/init_setup.php" "$D/flush_outbox.php" "$D/local_quality_v2.php" "$D/lead_pool_worker.php" "$D/lead_pool_dispatcher.php" "$D/lead_pool_patch_v2.php" "$D/lead_pool_cutover.php" "$D/ensure_ycloud_stage_events.php" "$D/quality_rules_v2.json" "$D/platform_stage_config.json" "$D/lead_pool_config.json" "$D/wa_attribution_clients.json" || true
printf 'marketing-v18-reconciled-cumulative-google-feeds-deployed\n'
