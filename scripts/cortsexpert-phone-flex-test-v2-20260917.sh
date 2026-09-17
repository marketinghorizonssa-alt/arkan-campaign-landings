#!/bin/sh
set -eu
URL='https://script.google.com/macros/s/AKfycbztk2fHhEAJeUFjIgkzL7na06sHWsrJVkWqpRbxt2CduJvNeyrQHSMpz8EzfNE4UbQv/exec'
curl -sS -L "$URL" \
  --data-urlencode 'source_id=CORTS_WEBSITE_FORM_V1' \
  --data-urlencode 'source=Website Form' \
  --data-urlencode 'submission_id=CORTS-TEST-PHONE-FLEX-V4B-20260917' \
  --data-urlencode 'full_name=TEST PHONE FORMAT V4B' \
  --data-urlencode 'phone=010 1234-5678' \
  --data-urlencode 'phone_raw=010 1234-5678' \
  --data-urlencode 'service=TEST PHONE FORMAT' \
  --data-urlencode 'message=Automated test - safe to delete' \
  --data-urlencode 'page_url=https://cortsexpert.hositee.com/riyadh-lawyer/' \
  --data-urlencode 'privacy_consent=YES' \
  --data-urlencode 'consent_version=v1' \
  --data-urlencode 'city=Riyadh'
