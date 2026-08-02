#!/bin/sh
set -eu
COMMIT="${1:?GitHub commit SHA is required}"
ROOT="/home/u878466595/domains/hositee.com/public_html/arkan-realestate-solutions"
REF="/home/u878466595/domains/hositee.com/public_html/arkan-executive/images"
TMP="$ROOT/.deploy-$COMMIT"
BASE="https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/$COMMIT/public"
DRIVE_LOGO="https://drive.google.com/uc?export=download&id=13x_FU4Pr8hce3nMzrsQXPcsM9wZjvDa5"
mkdir -p "$ROOT" "$TMP/app" "$TMP/assets"
for file in index.php .htaccess app/config.php app/helpers.php app/views.php assets/site.css assets/site.js assets/thank-you.js; do
  mkdir -p "$TMP/$(dirname "$file")"
  curl -fsSL "$BASE/$file" -o "$TMP/$file"
done
/opt/alt/php85/usr/bin/php -l "$TMP/index.php"
/opt/alt/php85/usr/bin/php -l "$TMP/app/config.php"
/opt/alt/php85/usr/bin/php -l "$TMP/app/helpers.php"
/opt/alt/php85/usr/bin/php -l "$TMP/app/views.php"
if ! curl -fL --max-time 30 "$DRIVE_LOGO" -o "$TMP/assets/logo.jpg" || ! test -s "$TMP/assets/logo.jpg"; then rm -f "$TMP/assets/logo.jpg"; fi
for asset in logo.webp white-logo.png contact-img.jpg hero.webm; do test -s "$REF/$asset"; install -m 0644 "$REF/$asset" "$TMP/assets/$asset"; done
install -d -m 0755 "$ROOT/app" "$ROOT/assets"
for file in index.php .htaccess app/config.php app/helpers.php app/views.php assets/site.css assets/site.js assets/thank-you.js; do install -m 0644 "$TMP/$file" "$ROOT/$file"; done
for asset in logo.webp white-logo.png contact-img.jpg hero.webm; do install -m 0644 "$TMP/assets/$asset" "$ROOT/assets/$asset"; done
if test -s "$TMP/assets/logo.jpg"; then install -m 0644 "$TMP/assets/logo.jpg" "$ROOT/assets/logo.jpg"; fi
printf 'commit=%s\ndeployed_at=%s\n' "$COMMIT" "$(date -Iseconds)" > "$ROOT/.deployment"
rm -rf "$TMP"
echo "ARKAN_DEPLOY_OK $COMMIT"
