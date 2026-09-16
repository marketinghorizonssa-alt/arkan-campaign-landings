# HORIZONS Google Marketing MCP

Private remote connector for HORIZONS Google Marketing operations.

Endpoint: `https://google-mcp.thehorizons.sa/mcp`

V1 modules: Google Ads + Keyword Planner/Forecast, GA4, GTM, Search Console, Site Verification, PageSpeed, Merchant Center, Business Profile, and a cross-platform client registry.

Secrets live outside `public_html` in `/home/u878466595/.horizons-google-mcp/config.json` and are never committed.

Google OAuth redirect URI: `https://google-mcp.thehorizons.sa/oauth/google/callback`
