# ARKAN Campaign Landings

Campaign-first Arabic landing pages for ARKAN Executive real-estate and financial solutions.

## Deployment target

- Public review domain: `https://arkan-realestate-solutions.hositee.com`
- The existing `https://arkan-executive.hositee.com` landing is a factual, brand, contact, and approved-asset reference only. The deployment script reads selected assets without modifying that website.

## Approved brand and contact sources

- Visual identity and logo: client Google Drive folder.
- Approved palette: dark navy, royal blue, and cyan with architectural imagery.
- Phone and WhatsApp: `0500989103` / `+966500989103`.
- Social handles used in the footer: `arkanexecut` and `arkan.execut`.
- There is no public official-website link in the footer.

## Search-aligned routes

- `/حلول-التمويل-العقاري/`
- `/رفض-التمويل-العقاري/`
- `/تمويل-عقاري-مع-التزامات/`
- `/شراء-مديونية-عقارية/`
- `/شراء-عقار-بالتمويل/`
- `/سياسة-الخصوصية/`
- `/تم-استلام-الطلب/`

## Lead system

- The visible form contains name, mobile, city, property type, and employer type.
- The consent checkbox is preselected in the current approved UX.
- Submissions post to the same-origin `/api/lead` endpoint.
- Leads are durably stored in a private SQLite database outside the public web root.
- `lead_form_success` fires only after the server acknowledges the stored row and returns a lead ID.
- A token-protected CSV feed supplies the Google Sheet without putting personal data in URLs or exposing the database publicly.
- The thank-you page offers a WhatsApp follow-up containing the confirmed lead ID.

## Current mode

- The site remains `noindex` while campaign tracking, final legal data, and production QA are incomplete.
- Call and WhatsApp links use the approved contact number.
- The website review banner has been removed from the customer-facing pages.

## Runtime and deployment

- Source of truth: this GitHub repository.
- Default branch: `main`.
- Public runtime: modular PHP 8.5 files under `public/`.
- Hostinger deployment is generated from a recorded GitHub commit by `scripts/hostinger-deploy.sh`.
- The script validates all PHP files before replacing the deployed version.
- It copies only selected approved logo and per-landing hero assets from the read-only reference website into the new deployment target.
- Old English paths redirect permanently to the matching Arabic search-intent routes while preserving query parameters.
- `arkan-v2` is an internal legacy label and must not appear in ads, canonicals, sitemaps, or Search Console.
