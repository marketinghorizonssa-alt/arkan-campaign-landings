#!/bin/sh
set -eu

SITE="https://arkan-realestate-solutions.hositee.com"
REF="ARK-AT-LIVETEST20260912A"
DATA="/home/u878466595/domains/hositee.com/public_html/marketing/data/attribution_events.jsonl"
TMP="/tmp/arkan-attr-test-$$"
mkdir -p "$TMP"

curl -fsS "$SITE/?utm_source=google&utm_medium=cpc&utm_campaign=live_test&gclid=TEST-GCLID-LIVE&campaignid=111&adgroupid=222&creative=333&keyword=test&matchtype=e&device=m&network=g" -o "$TMP/page.html"
grep -q 'site.js?v=10' "$TMP/page.html"
curl -fsS "$SITE/assets/site.js?v=10" -o "$TMP/site.js"
grep -q 'ARK-AT-' "$TMP/site.js"
grep -q 'mode=attribution' "$TMP/site.js"
grep -q 'Google Ads' "$TMP/site.js"

RESP=$(curl -fsS -H 'Content-Type: application/json' -H 'Accept: application/json' -d '{"event_type":"attribution_capture","ref_token":"ARK-AT-LIVETEST20260912A","utm_source":"google","utm_medium":"cpc","utm_campaign":"live_test","gclid":"TEST-GCLID-LIVE","campaign_id":"111","ad_group_id":"222","ad_id":"333","keyword":"test","match_type":"e","device":"m","network":"g","landing_page_id":"P0","landing_path":"/","first_landing_url":"https://arkan-realestate-solutions.hositee.com/?utm_source=google"}' "$SITE/api/lead?mode=attribution")
printf '%s\n' "$RESP" | grep -q '"attribution_registered":true'
printf 'SITE_CAPTURE_RESPONSE=%s\n' "$RESP"

grep -q "$REF" "$DATA"
TMPDATA="$DATA.testclean.$$"
grep -v "$REF" "$DATA" > "$TMPDATA" || true
mv "$TMPDATA" "$DATA"
rm -rf "$TMP"
echo 'ARKAN_LIVE_ATTRIBUTION_TEST_OK page_v10=yes js_tracking=yes signed_relay=yes cleanup=yes'
