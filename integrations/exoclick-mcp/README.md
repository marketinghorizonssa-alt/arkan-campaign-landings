# HORIZONS ExoClick MCP

Private MCP bridge for ExoClick Platform API v2.

## Vercel deployment

- Repository: `marketinghorizonssa-alt/arkan-campaign-landings`
- Branch: `exoclick-mcp-vercel`
- Root Directory: `integrations/exoclick-mcp`
- Framework: Next.js
- MCP endpoint after deploy: `https://<project>.vercel.app/api/mcp`
- Health endpoint: `https://<project>.vercel.app/api/health`

## Authentication

The MCP endpoint accepts the ExoClick long-lived API token as an incoming bearer token:

`Authorization: Bearer <EXOCLICK_API_TOKEN>`

The server exchanges that token against `POST https://api.exoclick.com/v2/login`, caches only the short-lived ExoClick session token in memory, and never commits or stores the ExoClick API token in source control.

## Included MCP tools

- `exoclick_connection_test`
- `exoclick_list_campaigns`
- `exoclick_get_campaign`
- `exoclick_advertiser_statistics`
- `exoclick_api_read`
- `exoclick_pause_campaigns`
- `exoclick_resume_campaigns`
- `exoclick_copy_campaign`
- `exoclick_api_write`

The generic read/write tools provide broad coverage of documented ExoClick API v2 endpoints while the named tools cover common campaign operations.
