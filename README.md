# ARKAN Campaign Landing Pages

Campaign-first Arabic landing application for ARKAN Executive.

## Included routes

- `/` — main campaign landing
- `/solutions/` — solutions and consultation
- `/rejection/` — rejected financing and purchasing power
- `/obligations/` — personal loan and obligations
- `/debt/` — debt purchase, refinancing and mortgage release
- `/property/` — financed property purchase
- `/privacy/` — privacy policy
- `/thank-you/` — post-form confirmation

## Run

```bash
npm start
```

Node.js 20 or newer is recommended. The application binds to `0.0.0.0` and uses the hosting platform `PORT` value.

## Deployment rules

- GitHub `main` is the source of truth.
- Hostinger deployments must be traceable to a GitHub commit.
- The form remains in review mode until contact numbers, lead endpoint, privacy identity, and tracking IDs are approved.
