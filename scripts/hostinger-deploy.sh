#!/bin/sh
set -eu

COMMIT="${1:?GitHub commit SHA is required}"
ROOT="/home/u878466595/domains/hositee.com/public_html/arkan-realestate-solutions"
TMP="$ROOT/.deploy-$COMMIT"
BASE="https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/$COMMIT/public"

mkdir -p "$ROOT" "$TMP"
curl -fsSL "$BASE/index.php" -o "$TMP/index.php"
curl -fsSL "$BASE/.htaccess" -o "$TMP/.htaccess"
/opt/alt/php85/usr/bin/php -l "$TMP/index.php"
install -m 0644 "$TMP/index.php" "$ROOT/index.php"
install -m 0644 "$TMP/.htaccess" "$ROOT/.htaccess"
printf 'commit=%s\ndeployed_at=%s\n' "$COMMIT" "$(date -Iseconds)" > "$ROOT/.deployment"
rm -rf "$TMP"
echo "ARKAN_DEPLOY_OK $COMMIT"
