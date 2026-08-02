# ARKAN Campaign Landings

Campaign-first Arabic landing pages for ARKAN Executive real-estate and financial solutions.

## Deployment target

- Public review domain: `https://arkan-realestate-solutions.hositee.com`
- The existing `https://arkan-executive.hositee.com` landing is a factual, brand, contact, and approved-asset reference only. The deployment script reads selected assets without modifying that website.

## Approved brand and contact sources

- Visual identity and logo: client Google Drive folder.
- Approved palette: dark navy, royal blue, and cyan with architectural imagery.
- Phone and WhatsApp: `0500989103` / `+966500989103`.
- Official website reference: `https://www.arkan2030.com`.
- Social handles used in the footer: `arkanexecut` and `arkan.execut`.

## Search-aligned routes

- `/حلول-التمويل-العقاري/`
- `/رفض-التمويل-العقاري/`
- `/تمويل-عقاري-مع-التزامات/`
- `/شراء-مديونية-عقارية/`
- `/شراء-عقار-بالتمويل/`
- `/سياسة-الخصوصية/`
- `/تم-استلام-الطلب/`

## Current mode

- The default remains review mode: `noindex` and no durable lead storage.
- Call and WhatsApp links use the approved contact number.
- Form values are retained locally for review and passed to the post-form WhatsApp handoff without placing personal data in the URL of the thank-you page.
- `lead_form_success` is reserved for a durable Sheet/CRM acknowledgement after the lead endpoint is connected.
- Do not switch to production until the Sheet/CRM endpoint, legal entity data, privacy contact, tracking IDs, and final QA are approved.

## Runtime and deployment

- Source of truth: this GitHub repository.
- Default branch: `main`.
- Public runtime: modular PHP 8.5 files under `public/`.
- Hostinger deployment is generated from a recorded GitHub commit by `scripts/hostinger-deploy.sh`.
- The script validates all PHP files before replacing the deployed version.
- It copies only selected approved logo, hero, and contact assets from the read-only reference website into the new deployment target.
- Old English paths redirect permanently to the matching Arabic search-intent routes while preserving query parameters.
- `arkan-v2` is an internal legacy label and must not appear in ads, canonicals, sitemaps, or Search Console.
