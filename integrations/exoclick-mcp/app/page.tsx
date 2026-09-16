export default function Home() {
  return (
    <main>
      <h1>HORIZONS ExoClick MCP</h1>
      <p>Private MCP bridge for ExoClick Platform API v2.</p>
      <ul>
        <li>MCP endpoint: <code>/api/mcp</code></li>
        <li>Health endpoint: <code>/api/health</code></li>
        <li>Authentication: ExoClick API token supplied as a Bearer token.</li>
      </ul>
      <p>The server stores no ExoClick long-lived credential.</p>
    </main>
  );
}
