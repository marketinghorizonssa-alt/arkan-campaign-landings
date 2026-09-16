import { createHash } from "node:crypto";

const EXO_BASE = "https://api.exoclick.com/v2/";
const sessionCache = new Map<string, { authorization: string; expiresAt: number }>();

function tokenKey(apiToken: string) {
  return createHash("sha256").update(apiToken).digest("hex");
}

function normalizePath(path: string) {
  const value = path.trim().replace(/^\/+/, "");
  if (!value || value === "login") throw new Error("A non-login ExoClick API path is required.");
  if (/^https?:\/\//i.test(value) || value.includes("..") || value.includes("\\")) {
    throw new Error("Invalid ExoClick API path.");
  }
  return value;
}

function toUrl(path: string, query?: Record<string, unknown>) {
  const url = new URL(normalizePath(path), EXO_BASE);
  if (query) {
    for (const [key, raw] of Object.entries(query)) {
      if (raw === undefined || raw === null || raw === "") continue;
      if (Array.isArray(raw)) {
        for (const item of raw) url.searchParams.append(key, String(item));
      } else if (typeof raw === "object") {
        url.searchParams.set(key, JSON.stringify(raw));
      } else {
        url.searchParams.set(key, String(raw));
      }
    }
  }
  return url;
}

async function readResponse(res: Response) {
  const text = await res.text();
  if (!text) return null;
  try {
    return JSON.parse(text);
  } catch {
    return text;
  }
}

async function login(apiToken: string) {
  const res = await fetch(`${EXO_BASE}login`, {
    method: "POST",
    headers: { "content-type": "application/json", accept: "application/json" },
    body: JSON.stringify({ api_token: apiToken }),
    cache: "no-store"
  });

  const data = await readResponse(res);
  if (!res.ok) {
    throw new Error(`ExoClick login failed (HTTP ${res.status}). ${typeof data === "string" ? data.slice(0, 500) : JSON.stringify(data).slice(0, 500)}`);
  }

  if (!data || typeof data !== "object" || !("token" in data)) {
    throw new Error("ExoClick login returned an unexpected response.");
  }

  const token = String((data as Record<string, unknown>).token ?? "");
  const type = String((data as Record<string, unknown>).type ?? "Bearer");
  if (!token) throw new Error("ExoClick login did not return a session token.");

  return `${type} ${token}`;
}

async function getAuthorization(apiToken: string, forceRefresh = false) {
  const key = tokenKey(apiToken);
  const cached = sessionCache.get(key);
  if (!forceRefresh && cached && cached.expiresAt > Date.now()) return cached.authorization;

  const authorization = await login(apiToken);
  sessionCache.set(key, { authorization, expiresAt: Date.now() + 4 * 60 * 1000 });
  return authorization;
}

export async function verifyApiToken(apiToken: string) {
  await getAuthorization(apiToken, true);
  return { ok: true, accountAuthenticated: true };
}

export async function exoRequest(
  apiToken: string,
  method: "GET" | "POST" | "PUT" | "DELETE",
  path: string,
  query?: Record<string, unknown>,
  body?: unknown
) {
  const url = toUrl(path, query);
  let authorization = await getAuthorization(apiToken);

  const execute = async () => {
    const headers: Record<string, string> = {
      authorization,
      accept: "application/json"
    };
    const init: RequestInit = { method, headers, cache: "no-store" };
    if (method !== "GET" && body !== undefined) {
      headers["content-type"] = "application/json";
      init.body = JSON.stringify(body);
    }
    return fetch(url, init);
  };

  let res = await execute();
  if (res.status === 401 || res.status === 403) {
    authorization = await getAuthorization(apiToken, true);
    res = await execute();
  }

  const data = await readResponse(res);
  if (!res.ok) {
    const detail = typeof data === "string" ? data.slice(0, 1500) : JSON.stringify(data).slice(0, 1500);
    throw new Error(`ExoClick API ${method} /${normalizePath(path)} failed (HTTP ${res.status}). ${detail}`);
  }

  return {
    ok: true,
    status: res.status,
    method,
    path: `/${normalizePath(path)}`,
    data
  };
}
