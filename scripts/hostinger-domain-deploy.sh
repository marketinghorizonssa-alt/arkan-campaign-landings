#!/bin/sh
set -eu

COMMIT="${1:?Git commit is required}"
DOMAIN="${2:?Target domain is required}"
SOURCE_ORIGIN="${3:-https://arkan-realestate-solutions.hositee.com}"
ROOT="$HOME/domains/$DOMAIN/public_html"
TMP="$ROOT/.deploy-$COMMIT"
BASE="https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/$COMMIT/public"
OLD_ORIGIN="https://arkan-realestate-solutions.hositee.com"
NEW_ORIGIN="https://$DOMAIN"

PHP_BIN=""
for CANDIDATE in /opt/alt/php85/usr/bin/php /opt/alt/php84/usr/bin/php /opt/alt/php83/usr/bin/php /opt/alt/php82/usr/bin/php /usr/bin/php; do
  if [ -x "$CANDIDATE" ]; then PHP_BIN="$CANDIDATE"; break; fi
done
if [ -z "$PHP_BIN" ]; then
  PHP_BIN="$(command -v php || true)"
fi
if [ -z "$PHP_BIN" ]; then
  echo "PHP binary was not found" >&2
  exit 1
fi

rm -rf "$TMP"
mkdir -p "$ROOT" "$TMP/app" "$TMP/assets" "$HOME/private"
chmod 700 "$HOME/private"

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
  curl -fsSL "$SOURCE_ORIGIN/assets/$ASSET" -o "$TMP/assets/$ASSET"
done

"$PHP_BIN" -l "$TMP/index.php"
"$PHP_BIN" -l "$TMP/app/config.php"
"$PHP_BIN" -l "$TMP/app/helpers.php"
"$PHP_BIN" -l "$TMP/app/leads.php"
"$PHP_BIN" -l "$TMP/app/views.php"

"$PHP_BIN" -r '$xml = simplexml_load_file($argv[1]); if ($xml === false || count($xml->url) !== 6) { fwrite(STDERR, "Invalid sitemap\n"); exit(1); }' "$TMP/sitemap.xml"
grep -q "$NEW_ORIGIN/" "$TMP/sitemap.xml"
grep -q "Sitemap: $NEW_ORIGIN/sitemap.xml" "$TMP/robots.txt"

mkdir -p "$ROOT/app" "$ROOT/assets"
rm -f "$ROOT/index.html" "$ROOT/default.php"
install -m 0644 "$TMP/index.php" "$ROOT/index.php"
install -m 0644 "$TMP/.htaccess" "$ROOT/.htaccess"
install -m 0644 "$TMP/robots.txt" "$ROOT/robots.txt"
install -m 0644 "$TMP/sitemap.xml" "$ROOT/sitemap.xml"
install -m 0644 "$TMP/app/config.php" "$ROOT/app/config.php"
install -m 0644 "$TMP/app/helpers.php" "$ROOT/app/helpers.php"
install -m 0644 "$TMP/app/leads.php" "$ROOT/app/leads.php"
install -m 0644 "$TMP/app/views.php" "$ROOT/app/views.php"
for FILE in "$TMP"/assets/*; do
  [ -f "$FILE" ] && install -m 0644 "$FILE" "$ROOT/assets/$(basename "$FILE")"
done

printf 'commit=%s\ndomain=%s\ndeployed_at=%s\nlead_mode=relay-to-existing-crm\n' "$COMMIT" "$DOMAIN" "$(date -Iseconds)" > "$ROOT/.deployment"
rm -rf "$TMP"
echo "ARKAN_DOMAIN_DEPLOY_OK $DOMAIN $COMMIT"
