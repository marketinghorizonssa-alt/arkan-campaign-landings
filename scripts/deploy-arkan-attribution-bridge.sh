#!/bin/sh
set -eu

HOME_DIR="/home/u878466595"
MARKETING_ROOT="$HOME_DIR/domains/hositee.com/public_html/marketing"
ARKAN_ROOT="$HOME_DIR/domains/hositee.com/public_html/arkan-realestate-solutions"
SECURE_DIR="$HOME_DIR/.marketing"
TMP="/tmp/arkan-attribution-deploy-$$"
PHP="/opt/alt/php85/usr/bin/php"
HUB_COMMIT="f4a1dc280ef3c28c73baad0653ad28316edf2a85"
SITE_COMMIT="b0d2304905ea2a287e63fbed1c85756af541775d"
REPO="https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings"

mkdir -p "$TMP/marketing" "$TMP/site/app" "$TMP/site/assets" "$MARKETING_ROOT" "$ARKAN_ROOT/app" "$ARKAN_ROOT/assets" "$SECURE_DIR"
chmod 700 "$SECURE_DIR"

if [ ! -s "$SECURE_DIR/arkan_attribution_secret" ]; then
  umask 077
  openssl rand -hex 32 > "$SECURE_DIR/arkan_attribution_secret"
fi
chmod 600 "$SECURE_DIR/arkan_attribution_secret"

curl -fsSL "$REPO/$HUB_COMMIT/marketing-hub-v06/attribution.php" -o "$TMP/marketing/attribution.php"
curl -fsSL "$REPO/$HUB_COMMIT/marketing-hub-v06/lead_quality_enriched.php" -o "$TMP/marketing/lead_quality_enriched.php"
curl -fsSL "$REPO/$SITE_COMMIT/public/app/helpers.php" -o "$TMP/site/app/helpers.php"
curl -fsSL "$REPO/$SITE_COMMIT/public/app/leads.php" -o "$TMP/site/app/leads.php"
curl -fsSL "$REPO/$SITE_COMMIT/public/assets/site.js" -o "$TMP/site/assets/site.js"
curl -fsSL "$REPO/$SITE_COMMIT/public/assets/thank-you.js" -o "$TMP/site/assets/thank-you.js"

"$PHP" -l "$TMP/marketing/attribution.php"
"$PHP" -l "$TMP/marketing/lead_quality_enriched.php"
"$PHP" -l "$TMP/site/app/helpers.php"
"$PHP" -l "$TMP/site/app/leads.php"

grep -q 'ARK-AT-' "$TMP/site/assets/site.js"
grep -q 'mode=attribution' "$TMP/site/assets/site.js"
grep -q 'X-Arkan-Signature' "$TMP/site/app/leads.php"
grep -q 'site.js?v=10' "$TMP/site/app/helpers.php"
grep -q 'attribution_events.jsonl' "$TMP/marketing/attribution.php"
grep -q 'server_attribution_' "$TMP/marketing/lead_quality_enriched.php"

install -m 0644 "$TMP/marketing/attribution.php" "$MARKETING_ROOT/attribution.php"
install -m 0644 "$TMP/marketing/lead_quality_enriched.php" "$MARKETING_ROOT/lead_quality_enriched.php"
install -m 0644 "$TMP/site/app/helpers.php" "$ARKAN_ROOT/app/helpers.php"
install -m 0644 "$TMP/site/app/leads.php" "$ARKAN_ROOT/app/leads.php"
install -m 0644 "$TMP/site/assets/site.js" "$ARKAN_ROOT/assets/site.js"
install -m 0644 "$TMP/site/assets/thank-you.js" "$ARKAN_ROOT/assets/thank-you.js"

"$PHP" -l "$ARKAN_ROOT/app/helpers.php"
"$PHP" -l "$ARKAN_ROOT/app/leads.php"
"$PHP" -l "$MARKETING_ROOT/attribution.php"
"$PHP" -l "$MARKETING_ROOT/lead_quality_enriched.php"

rm -rf "$TMP"
echo "ARKAN_ATTRIBUTION_DEPLOY_OK HUB=$HUB_COMMIT SITE=$SITE_COMMIT"
