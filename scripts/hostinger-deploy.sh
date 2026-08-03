#!/bin/sh
set -eu

COMMIT="${1:?GitHub commit SHA is required}"
ROOT="/home/u878466595/domains/hositee.com/public_html/arkan-realestate-solutions"
PRIVATE="/home/u878466595/private"
REFERENCE="/home/u878466595/domains/hositee.com/public_html/arkan-executive/images"
TMP="$ROOT/.deploy-$COMMIT"
BASE="https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/$COMMIT/public"

mkdir -p "$ROOT" "$TMP/app" "$TMP/assets" "$PRIVATE"
chmod 700 "$PRIVATE"

for FILE in index.php .htaccess googlebff965ed4f5bbb83.html app/config.php app/helpers.php app/leads.php app/views.php assets/site.css assets/site.js assets/thank-you.js; do
  curl -fsSL "$BASE/$FILE" -o "$TMP/$FILE"
done

/opt/alt/php85/usr/bin/php -l "$TMP/index.php"
/opt/alt/php85/usr/bin/php -l "$TMP/app/config.php"
/opt/alt/php85/usr/bin/php -l "$TMP/app/helpers.php"
/opt/alt/php85/usr/bin/php -l "$TMP/app/leads.php"
/opt/alt/php85/usr/bin/php -l "$TMP/app/views.php"

grep -qx 'google-site-verification: googlebff965ed4f5bbb83.html' "$TMP/googlebff965ed4f5bbb83.html"

copy_asset() {
  SOURCE="$1"
  TARGET="$2"
  if [ -f "$REFERENCE/$SOURCE" ]; then install -m 0644 "$REFERENCE/$SOURCE" "$TMP/assets/$TARGET"; fi
}
copy_asset logo.webp logo.webp
copy_asset white-logo.png white-logo.png
copy_asset contact-img.jpg hero-home.jpg
copy_asset service-finance.jpg hero-finance.jpg
copy_asset service-sme.jpg hero-rejection.jpg
copy_asset service-debt.jpg hero-obligations.jpg
copy_asset service-brokerage.jpg hero-debt.jpg
copy_asset service-construction.jpg hero-property.jpg

if [ ! -f "$TMP/assets/logo.webp" ]; then
  curl -fsSL "https://drive.google.com/uc?export=download&id=13x_FU4Pr8hce3nMzrsQXPcsM9wZjvDa5" -o "$TMP/assets/logo.jpg"
else
  cp "$TMP/assets/logo.webp" "$TMP/assets/logo.jpg"
fi

mkdir -p "$ROOT/app" "$ROOT/assets"
install -m 0644 "$TMP/index.php" "$ROOT/index.php"
install -m 0644 "$TMP/.htaccess" "$ROOT/.htaccess"
install -m 0644 "$TMP/googlebff965ed4f5bbb83.html" "$ROOT/googlebff965ed4f5bbb83.html"
install -m 0644 "$TMP/app/config.php" "$ROOT/app/config.php"
install -m 0644 "$TMP/app/helpers.php" "$ROOT/app/helpers.php"
install -m 0644 "$TMP/app/leads.php" "$ROOT/app/leads.php"
install -m 0644 "$TMP/app/views.php" "$ROOT/app/views.php"
for FILE in "$TMP"/assets/*; do [ -f "$FILE" ] && install -m 0644 "$FILE" "$ROOT/assets/$(basename "$FILE")"; done

printf 'commit=%s\ndeployed_at=%s\n' "$COMMIT" "$(date -Iseconds)" > "$ROOT/.deployment"
rm -rf "$TMP"
echo "ARKAN_DEPLOY_OK $COMMIT"
