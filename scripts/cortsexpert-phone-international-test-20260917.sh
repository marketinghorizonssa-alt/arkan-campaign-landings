#!/bin/sh
set -eu
URL='https://script.google.com/macros/s/AKfycbztk2fHhEAJeUFjIgkzL7na06sHWsrJVkWqpRbxt2CduJvNeyrQHSMpz8EzfNE4UbQv/exec'
curl -sS -L "$URL" \
  --data-urlencode 'source_id=CORTS_WEBSITE_FORM_V1' \
  --data-urlencode 'source=Website Form' \
  --data-urlencode 'submission_id=CORTS-TEST-PHONE-INTL-V4-20260917' \
  --data-urlencode 'full_name=TEST INTERNATIONAL PHONE V4' \
  --data-urlencode 'phone=+201012345678' \
  --data-urlencode 'phone_raw=+20 10 1234 5678' \
  --data-urlencode 'service=TEST PHONE FORMAT' \
  --data-urlencode 'message=Automated international phone test - safe to delete' \
  --data-urlencode 'page_url=https://cortsexpert.hositee.com/riyadh-lawyer/' \
  --data-urlencode 'privacy_consent=YES' \
  --data-urlencode 'consent_version=v1' \
  --data-urlencode 'city=Riyadh'
