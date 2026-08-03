#!/bin/sh
set -eu

COMMIT="${1:?GitHub commit SHA is required}"
ROOT="/home/u878466595/domains/hositee.com/public_html/arkan-realestate-solutions"
PRIVATE="/home/u878466595/private"
REFERENCE="/home/u878466595/domains/hositee.com/public_html/arkan-executive/images"
TMP="$ROOT/.deploy-$COMMIT"
BASE="https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/$COMMIT/public"
ORIGIN="https://arkan-realestate-solutions.hositee.com"
PHP="/opt/alt/php85/usr/bin/php"
INSPECTION_UA="Mozilla/5.0 (compatible; Google-InspectionTool/1.0)"

mkdir -p "$ROOT" "$TMP/app" "$TMP/assets" "$PRIVATE"
chmod 700 "$PRIVATE"

for FILE in index.php .htaccess robots.txt sitemap.xml googlebff965ed4f5bbb83.html app/config.php app/helpers.php app/leads.php app/views.php assets/site.css assets/site.js assets/thank-you.js; do
  curl -fsSL "$BASE/$FILE" -o "$TMP/$FILE"
done

"$PHP" -l "$TMP/index.php"
"$PHP" -l "$TMP/app/config.php"
"$PHP" -l "$TMP/app/helpers.php"
"$PHP" -l "$TMP/app/leads.php"
"$PHP" -l "$TMP/app/views.php"

grep -qx 'google-site-verification: googlebff965ed4f5bbb83.html' "$TMP/googlebff965ed4f5bbb83.html"
grep -qx 'User-agent: Google-InspectionTool' "$TMP/robots.txt"
grep -qx 'User-agent: Googlebot' "$TMP/robots.txt"
grep -qx 'User-agent: \*' "$TMP/robots.txt"
grep -qx 'Disallow:' "$TMP/robots.txt"
grep -qx "Sitemap: $ORIGIN/sitemap.xml" "$TMP/robots.txt"
if grep -Eq '^Disallow:[[:space:]]*[^[:space:]]' "$TMP/robots.txt"; then
  echo 'Deployment blocked: static robots.txt contains a non-empty Disallow rule.' >&2
  exit 1
fi

"$PHP" -r '$xml = simplexml_load_file($argv[1]); if ($xml === false || count($xml->url) !== 6) { fwrite(STDERR, "Invalid sitemap or unexpected URL count\n"); exit(1); }' "$TMP/sitemap.xml"
grep -q "$ORIGIN/" "$TMP/sitemap.xml"
grep -q "X-Robots-Tag: index, follow" "$TMP/index.php"
if grep -q "path === '/robots.txt'" "$TMP/index.php" || grep -q "path === '/sitemap.xml'" "$TMP/index.php"; then
  echo 'Deployment blocked: crawl-control endpoints must be static.' >&2
  exit 1
fi

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
install -m 0644 "$TMP/robots.txt" "$ROOT/robots.txt"
install -m 0644 "$TMP/sitemap.xml" "$ROOT/sitemap.xml"
install -m 0644 "$TMP/googlebff965ed4f5bbb83.html" "$ROOT/googlebff965ed4f5bbb83.html"
install -m 0644 "$TMP/app/config.php" "$ROOT/app/config.php"
install -m 0644 "$TMP/app/helpers.php" "$ROOT/app/helpers.php"
install -m 0644 "$TMP/app/leads.php" "$ROOT/app/leads.php"
install -m 0644 "$TMP/app/views.php" "$ROOT/app/views.php"
for FILE in "$TMP"/assets/*; do [ -f "$FILE" ] && install -m 0644 "$FILE" "$ROOT/assets/$(basename "$FILE")"; done

# Validate the exact Search Console inspection user agent after installation.
curl -fsS -A "$INSPECTION_UA" -H 'Cache-Control: no-cache' "$ORIGIN/robots.txt?deploy=$COMMIT" -o "$TMP/robots.live"
grep -qx 'User-agent: Google-InspectionTool' "$TMP/robots.live"
grep -qx 'User-agent: Googlebot' "$TMP/robots.live"
grep -qx 'User-agent: \*' "$TMP/robots.live"
grep -qx 'Disallow:' "$TMP/robots.live"
grep -qx "Sitemap: $ORIGIN/sitemap.xml" "$TMP/robots.live"
if grep -Eq '^Disallow:[[:space:]]*[^[:space:]]' "$TMP/robots.live"; then
  echo 'Deployment blocked: live inspection-tool robots response contains a non-empty Disallow rule.' >&2
  exit 1
fi

curl -fsSI -A "$INSPECTION_UA" -H 'Cache-Control: no-cache' "$ORIGIN/sitemap.xml?deploy=$COMMIT" -o "$TMP/sitemap.headers"
grep -Eqi '^HTTP/[0-9.]+ 200' "$TMP/sitemap.headers"
grep -Eqi '^content-type:[[:space:]]*application/xml' "$TMP/sitemap.headers"
curl -fsS -A "$INSPECTION_UA" -H 'Cache-Control: no-cache' "$ORIGIN/sitemap.xml?deploy=$COMMIT" -o "$TMP/sitemap.live"
"$PHP" -r '$xml = simplexml_load_file($argv[1]); if ($xml === false || count($xml->url) !== 6) { fwrite(STDERR, "Live sitemap invalid\n"); exit(1); }' "$TMP/sitemap.live"

printf 'commit=%s\ndeployed_at=%s\nseo_mode=static-production\ninspection_tool=explicitly-allowed\n' "$COMMIT" "$(date -Iseconds)" > "$ROOT/.deployment"
rm -rf "$TMP"
echo "ARKAN_DEPLOY_OK $COMMIT INSPECTION_TOOL_ROBOTS_OK"
