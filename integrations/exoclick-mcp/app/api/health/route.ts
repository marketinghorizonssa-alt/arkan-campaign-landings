export const runtime = "nodejs";

export async function GET() {
  return Response.json({
    ok: true,
    service: "horizons-exoclick-mcp",
    api: "https://api.exoclick.com/v2/",
    auth: "Bearer <ExoClick API token>",
    timestamp: new Date().toISOString()
  });
}
