#!/bin/sh
set -eu

COMMIT="${1:?Git commit is required}"
DOMAIN="${2:-arkan2030.com}"
SOURCE_ROOT="${3:-$HOME/domains/hositee.com/public_html/arkan-realestate-solutions}"
TMP="$SOURCE_ROOT/.package-$COMMIT"
OUT="$SOURCE_ROOT/arkan2030-php.zip"
BASE="https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/$COMMIT/public"
OLD_ORIGIN="https://arkan-realestate-solutions.hositee.com"
NEW_ORIGIN="https://$DOMAIN"

rm -rf "$TMP" "$OUT"
mkdir -p "$TMP/app" "$TMP/assets"

for FILE in index.php .htaccess robots.txt sitemap.xml app/config.php app/helpers.php app/leads.php app/views.php assets/site.css assets/site.js assets/thank-you.js; do
  curl -fsSL "$BASE/$FILE" -o "$TMP/$FILE"
done
sed -i "s|$OLD_ORIGIN|$NEW_ORIGIN|g" "$TMP/robots.txt" "$TMP/sitemap.xml"

for ASSET in \
  logo.jpg logo.webp logo-360.webp white-logo.png \
  hero-home.jpg hero-home.webp hero-home-768.webp \
  hero-finance.jpg hero-finance.webp hero-finance-768.webp \
  hero-rejection.jpg hero-rejection.webp hero-rejection-768.webp \
  hero-obligations.jpg hero-obligations.webp hero-obligations-768.webp \
  hero-debt.jpg hero-debt.webp hero-debt-768.webp \
  hero-property.jpg hero-property.webp hero-property-768.webp; do
  install -m 0644 "$SOURCE_ROOT/assets/$ASSET" "$TMP/assets/$ASSET"
done

PHP_BIN="/opt/alt/php85/usr/bin/php"
[ -x "$PHP_BIN" ] || PHP_BIN="$(command -v php)"
"$PHP_BIN" -l "$TMP/index.php"
"$PHP_BIN" -l "$TMP/app/config.php"
"$PHP_BIN" -l "$TMP/app/helpers.php"
"$PHP_BIN" -l "$TMP/app/leads.php"
"$PHP_BIN" -l "$TMP/app/views.php"

(
  cd "$TMP"
  zip -qr "$OUT" .
)
rm -rf "$TMP"
echo "ARKAN_PHP_PACKAGE_OK $OUT $(wc -c < "$OUT")"
