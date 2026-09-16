import { createMcpHandler } from "mcp-handler";
import { z } from "zod";
import { exoRequest, verifyApiToken } from "../../../lib/exoclick";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";

function extractApiToken(req: Request) {
  const auth = req.headers.get("authorization")?.trim();
  if (auth && /^Bearer\s+/i.test(auth)) return auth.replace(/^Bearer\s+/i, "").trim();
  return (
    req.headers.get("x-exoclick-api-token")?.trim() ||
    req.headers.get("x-api-key")?.trim() ||
    ""
  );
}

function textResult(value: unknown) {
  return {
    content: [{ type: "text" as const, text: JSON.stringify(value, null, 2) }]
  };
}

function errorResult(error: unknown) {
  const message = error instanceof Error ? error.message : String(error);
  return {
    content: [{ type: "text" as const, text: message }],
    isError: true
  };
}

function createHandler(apiToken: string) {
  return createMcpHandler(
    (server) => {
      server.registerTool(
        "exoclick_connection_test",
        {
          title: "Test ExoClick connection",
          description: "Validate the supplied ExoClick API token by logging in to ExoClick API v2.",
          annotations: { readOnlyHint: true, openWorldHint: true }
        },
        async () => {
          try {
            return textResult(await verifyApiToken(apiToken));
          } catch (error) {
            return errorResult(error);
          }
        }
      );

      server.registerTool(
        "exoclick_list_campaigns",
        {
          title: "List ExoClick campaigns",
          description: "List advertiser campaigns. Supports ExoClick API collection filters and pagination.",
          inputSchema: z.object({
            offset: z.number().int().min(0).optional(),
            filters: z.record(z.string(), z.unknown()).optional()
          }),
          annotations: { readOnlyHint: true, openWorldHint: true }
        },
        async ({ offset, filters }) => {
          try {
            return textResult(await exoRequest(apiToken, "GET", "campaigns", { ...(filters ?? {}), ...(offset !== undefined ? { offset } : {}) }));
          } catch (error) {
            return errorResult(error);
          }
        }
      );

      server.registerTool(
        "exoclick_get_campaign",
        {
          title: "Get ExoClick campaign",
          description: "Get one ExoClick campaign by campaign ID.",
          inputSchema: z.object({ campaign_id: z.union([z.string(), z.number()]) }),
          annotations: { readOnlyHint: true, openWorldHint: true }
        },
        async ({ campaign_id }) => {
          try {
            return textResult(await exoRequest(apiToken, "GET", `campaigns/${campaign_id}`));
          } catch (error) {
            return errorResult(error);
          }
        }
      );

      server.registerTool(
        "exoclick_advertiser_statistics",
        {
          title: "Get ExoClick advertiser statistics",
          description: "Read advertiser statistics grouped by campaign, date, hour, country, site, zone, browser, carrier, category, device, language, OS, variation, or another supported API dimension.",
          inputSchema: z.object({
            dimension: z.string().min(1).describe("Example: campaign, date, hour, country, site, zone, browser, device"),
            filters: z.record(z.string(), z.unknown()).optional()
          }),
          annotations: { readOnlyHint: true, openWorldHint: true }
        },
        async ({ dimension, filters }) => {
          try {
            const safeDimension = dimension.replace(/^\/+|\/+$/g, "");
            return textResult(await exoRequest(apiToken, "GET", `statistics/advertiser/${safeDimension}`, filters));
          } catch (error) {
            return errorResult(error);
          }
        }
      );

      server.registerTool(
        "exoclick_api_read",
        {
          title: "ExoClick API read",
          description: "Generic read-only access to any ExoClick Platform API v2 path. The path must be relative to https://api.exoclick.com/v2/.",
          inputSchema: z.object({
            path: z.string().min(1).describe("Relative API path, e.g. campaigns, collections/browsers, user"),
            query: z.record(z.string(), z.unknown()).optional()
          }),
          annotations: { readOnlyHint: true, openWorldHint: true }
        },
        async ({ path, query }) => {
          try {
            return textResult(await exoRequest(apiToken, "GET", path, query));
          } catch (error) {
            return errorResult(error);
          }
        }
      );

      server.registerTool(
        "exoclick_pause_campaigns",
        {
          title: "Pause ExoClick campaigns",
          description: "Pause one or more ExoClick campaigns using campaigns/pause.",
          inputSchema: z.object({ campaign_ids: z.array(z.union([z.string(), z.number()])).min(1) }),
          annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: true }
        },
        async ({ campaign_ids }) => {
          try {
            return textResult(await exoRequest(apiToken, "PUT", "campaigns/pause", undefined, campaign_ids));
          } catch (error) {
            return errorResult(error);
          }
        }
      );

      server.registerTool(
        "exoclick_resume_campaigns",
        {
          title: "Resume ExoClick campaigns",
          description: "Resume/play one or more ExoClick campaigns using campaigns/play.",
          inputSchema: z.object({ campaign_ids: z.array(z.union([z.string(), z.number()])).min(1) }),
          annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: true }
        },
        async ({ campaign_ids }) => {
          try {
            return textResult(await exoRequest(apiToken, "PUT", "campaigns/play", undefined, campaign_ids));
          } catch (error) {
            return errorResult(error);
          }
        }
      );

      server.registerTool(
        "exoclick_copy_campaign",
        {
          title: "Copy ExoClick campaign",
          description: "Create a copy of an existing ExoClick campaign.",
          inputSchema: z.object({ campaign_id: z.union([z.string(), z.number()]) }),
          annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: true }
        },
        async ({ campaign_id }) => {
          try {
            return textResult(await exoRequest(apiToken, "PUT", `campaigns/${campaign_id}/copy`));
          } catch (error) {
            return errorResult(error);
          }
        }
      );

      server.registerTool(
        "exoclick_api_write",
        {
          title: "ExoClick API write",
          description: "Generic write access to ExoClick Platform API v2. Use only for documented ExoClick paths. Supports POST, PUT, and DELETE.",
          inputSchema: z.object({
            method: z.enum(["POST", "PUT", "DELETE"]),
            path: z.string().min(1).describe("Relative API path under /v2, never a full URL"),
            query: z.record(z.string(), z.unknown()).optional(),
            body: z.unknown().optional()
          }),
          annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: true }
        },
        async ({ method, path, query, body }) => {
          try {
            return textResult(await exoRequest(apiToken, method, path, query, body));
          } catch (error) {
            return errorResult(error);
          }
        }
      );
    },
    {
      serverInfo: { name: "HORIZONS ExoClick MCP", version: "1.0.0" },
      instructions: "Private HORIZONS bridge to ExoClick Platform API v2. Read tools are safe lookups. Write tools modify the ExoClick account and should be used intentionally."
    }
  );
}

async function route(req: Request) {
  const apiToken = extractApiToken(req);
  if (!apiToken) {
    return new Response(
      JSON.stringify({ error: "Missing ExoClick API token. Send it as Authorization: Bearer <API_TOKEN>." }),
      {
        status: 401,
        headers: {
          "content-type": "application/json",
          "www-authenticate": "Bearer realm=\"HORIZONS ExoClick MCP\""
        }
      }
    );
  }
  return createHandler(apiToken)(req);
}

export { route as GET, route as POST };

export async function OPTIONS() {
  return new Response(null, {
    status: 204,
    headers: {
      "access-control-allow-origin": "*",
      "access-control-allow-methods": "GET,POST,OPTIONS",
      "access-control-allow-headers": "authorization,content-type,x-exoclick-api-token,x-api-key"
    }
  });
}
