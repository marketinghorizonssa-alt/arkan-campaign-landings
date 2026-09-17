#!/bin/sh
set -eu
BASE='https://pagespeedinsights.dev'
HTML="$(curl -fsSL "$BASE")"
printf '%s' "$HTML" | grep -oE '/_nuxt/[A-Za-z0-9._-]+\.js' | sort -u | while read -r p; do
  echo "--- $p"
  curl -fsSL "$BASE$p" | grep -Eo '.{0,120}(\$fetch|fetch\(|/api/|analy[sz]e|pagespeedonline|runPagespeed).{0,220}' || true
done | head -200
