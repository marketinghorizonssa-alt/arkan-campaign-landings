# ARKAN CRM → Google Ads

Deploy `core.php` as `marketing/arkan_google_offline_core.php` and `worker.php` as `marketing/arkan_google_offline_worker.php` on the existing Horizons hosting account. Both files reject HTTP execution. The worker uses the existing authenticated Horizons Google MCP; no credentials are packaged here.

Run every five minutes with a **relative** Hostinger cron command:

```sh
php -r 'require("domains/hositee.com/public_html/marketing/arkan_google_offline_worker.php");' 2>&1
```

Run with `$argv=["--dry-run"]` to validate without importing. `$argv=["--status"]` prints the last aggregate result.

Sources: the existing Arkan form SQLite database, the existing CRM worksheet joined by exact Lead ID, and the Arkan WhatsApp client in the hub. Only real click identifiers are sent. Unknown attribution, tests, duplicates and missing historical stage timestamps are withheld. Website submissions map to Lead Received; they are never called Message Started. Contacted does not imply Interested. Negative classifications suppress quality events.

CRM columns A:R remain unchanged. S stores the genuine evaluation timestamp when known, or the time a new status change was observed by the five-minute worker; T explains mapping status. The initial historical CRM classification snapshot never receives an invented timestamp. Fill S with an actual known ISO 8601 time to allow a historical evaluation import. Newly observed transitions receive a stable recorded time. Changes that occur and revert entirely between polls cannot be recovered.

All six conversion actions are Secondary initially. Existing website Form Success stays Primary. Campaign creation and bidding goals are separate work.

Private state under `.marketing/arkan_google_offline` stores an event ledger, CRM status observations, upload request IDs and processing diagnostics. Transaction IDs are stable per record and stage. Accepted requests are marked PROCESSING until Google destination diagnostics verify record counts. Partial failures are retained for review rather than blindly retried. Rejected requests are retried after a backoff. Aggregate cron output contains no phone numbers or names.

The worker does not transmit hashed customer data because the account's enhanced-conversion customer data terms were not enabled at inspection. It uses GCLID/GBRAID/WBRAID.
