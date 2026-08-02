# ARKAN Campaign Landings

Campaign-first Arabic landing pages for ARKAN Executive real-estate and financial solutions.

## Deployment target

- Public review domain: `https://arkan-realestate-solutions.hositee.com`
- The existing `https://arkan-executive.hositee.com` landing is a factual, brand, and contact reference only. Do not deploy over it or modify it as part of this project.

## Search-aligned routes

- `/حلول-التمويل-العقاري/`
- `/رفض-التمويل-العقاري/`
- `/تمويل-عقاري-مع-التزامات/`
- `/شراء-مديونية-عقارية/`
- `/شراء-عقار-بالتمويل/`
- `/سياسة-الخصوصية/`
- `/تم-استلام-الطلب/`

## Current mode

- The default is review mode: `noindex`, no durable lead storage, and no active call or WhatsApp numbers.
- Do not switch to production until the Sheet/CRM endpoint, contact numbers, legal entity data, privacy contact, tracking IDs, and final QA are approved.

## Deployment

- Source of truth: this GitHub repository.
- Default branch: `main`.
- Hostinger deployment must be generated from the recorded GitHub commit.
- Old English paths redirect permanently to the matching Arabic search-intent routes while preserving query parameters.
- `arkan-v2` is an internal legacy label and must not appear in ads, canonicals, sitemaps, or Search Console.
