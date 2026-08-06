'use strict';

const http = require('http');
const https = require('https');
const fs = require('fs');
const path = require('path');

const PORT = Number(process.env.PORT || 3000);
const HOST = '0.0.0.0';
const ROOT = path.join(__dirname, 'static');
const PAGES = path.join(ROOT, 'pages');
const MAX_BODY = 65536;

const routes = new Map([
  ['/', 'home.html'],
  ['/حلول-التمويل-العقاري/', 'finance.html'],
  ['/رفض-التمويل-العقاري/', 'rejection.html'],
  ['/تمويل-عقاري-مع-التزامات/', 'obligations.html'],
  ['/شراء-مديونية-عقارية/', 'debt.html'],
  ['/شراء-عقار-بالتمويل/', 'property.html'],
  ['/سياسة-الخصوصية/', 'privacy.html'],
  ['/تم-استلام-الطلب/', 'thank-you.html']
]);

const legacy = new Map([
  ['/solutions/', '/حلول-التمويل-العقاري/'],
  ['/rejection/', '/رفض-التمويل-العقاري/'],
  ['/obligations/', '/تمويل-عقاري-مع-التزامات/'],
  ['/debt/', '/شراء-مديونية-عقارية/'],
  ['/property/', '/شراء-عقار-بالتمويل/'],
  ['/privacy/', '/سياسة-الخصوصية/'],
  ['/thank-you/', '/تم-استلام-الطلب/']
]);

const mime = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.xml': 'application/xml; charset=utf-8',
  '.txt': 'text/plain; charset=utf-8',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.png': 'image/png',
  '.webp': 'image/webp',
  '.svg': 'image/svg+xml'
};

function baseHeaders() {
  return {
    'X-Content-Type-Options': 'nosniff',
    'Referrer-Policy': 'strict-origin-when-cross-origin',
    'Permissions-Policy': 'camera=(), microphone=(), geolocation=()',
    'X-Powered-By': ''
  };
}

function send(res, status, body, headers = {}) {
  res.writeHead(status, {...baseHeaders(), ...headers});
  if (body === null || body === undefined) return res.end();
  res.end(body);
}

function redirect(res, target, query = '') {
  send(res, 301, '', {'Location': target + (query ? `?${query}` : ''), 'Cache-Control': 'public, max-age=3600'});
}

function readFile(file) {
  return fs.readFileSync(file);
}

function servePage(res, file, status = 200, noindex = false) {
  const headers = {
    'Content-Type': 'text/html; charset=utf-8',
    'Cache-Control': 'no-cache, max-age=0',
    'X-Robots-Tag': noindex
      ? 'noindex, nofollow'
      : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'
  };
  send(res, status, readFile(path.join(PAGES, file)), headers);
}

function safeAssetPath(decodedPath) {
  if (!decodedPath.startsWith('/assets/')) return null;
  const rel = decodedPath.slice('/assets/'.length);
  if (!rel || rel.includes('..') || rel.includes('\\') || rel.includes('\0')) return null;
  const full = path.resolve(ROOT, 'assets', rel);
  const assetsRoot = path.resolve(ROOT, 'assets') + path.sep;
  return full.startsWith(assetsRoot) ? full : null;
}

function serveStatic(res, file, cacheControl) {
  if (!fs.existsSync(file) || !fs.statSync(file).isFile()) return false;
  const ext = path.extname(file).toLowerCase();
  send(res, 200, readFile(file), {
    'Content-Type': mime[ext] || 'application/octet-stream',
    'Cache-Control': cacheControl
  });
  return true;
}

function proxyLead(req, res) {
  let size = 0;
  const chunks = [];
  let finished = false;

  req.on('data', (chunk) => {
    size += chunk.length;
    if (size > MAX_BODY) {
      finished = true;
      send(res, 413, JSON.stringify({ok: false, error: 'payload_too_large'}), {
        'Content-Type': 'application/json; charset=utf-8',
        'Cache-Control': 'no-store'
      });
      req.destroy();
      return;
    }
    chunks.push(chunk);
  });

  req.on('end', () => {
    if (finished) return;
    const body = Buffer.concat(chunks);
    const upstream = https.request({
      hostname: 'arkan-realestate-solutions.hositee.com',
      port: 443,
      path: '/api/lead',
      method: 'POST',
      headers: {
        'Content-Type': req.headers['content-type'] || 'application/json',
        'Accept': 'application/json',
        'Content-Length': body.length,
        'X-Arkan-Relay': 'official-domain-node'
      },
      timeout: 18000
    }, (upstreamRes) => {
      const responseChunks = [];
      upstreamRes.on('data', (chunk) => responseChunks.push(chunk));
      upstreamRes.on('end', () => {
        send(res, upstreamRes.statusCode || 502, Buffer.concat(responseChunks), {
          'Content-Type': upstreamRes.headers['content-type'] || 'application/json; charset=utf-8',
          'Cache-Control': 'no-store'
        });
      });
    });
    upstream.on('timeout', () => upstream.destroy(new Error('upstream_timeout')));
    upstream.on('error', () => {
      send(res, 502, JSON.stringify({ok: false, error: 'relay_unavailable'}), {
        'Content-Type': 'application/json; charset=utf-8',
        'Cache-Control': 'no-store'
      });
    });
    upstream.end(body);
  });

  req.on('error', () => {
    if (!res.headersSent) {
      send(res, 400, JSON.stringify({ok: false, error: 'invalid_request'}), {
        'Content-Type': 'application/json; charset=utf-8',
        'Cache-Control': 'no-store'
      });
    }
  });
}

const server = http.createServer((req, res) => {
  const method = req.method || 'GET';
  let url;
  try {
    url = new URL(req.url || '/', 'https://arkan2030.com');
  } catch {
    return servePage(res, '404.html', 404, true);
  }

  let pathname;
  try {
    pathname = decodeURIComponent(url.pathname);
  } catch {
    return servePage(res, '404.html', 404, true);
  }

  if (pathname === '/api/lead') {
    if (method !== 'POST') {
      return send(res, 405, JSON.stringify({ok: false, error: 'method_not_allowed'}), {
        'Allow': 'POST',
        'Content-Type': 'application/json; charset=utf-8',
        'Cache-Control': 'no-store'
      });
    }
    return proxyLead(req, res);
  }

  if (method !== 'GET' && method !== 'HEAD') {
    return send(res, 405, '', {'Allow': 'GET, HEAD'});
  }

  if (pathname === '/health') {
    const body = JSON.stringify({
      ok: true,
      mode: 'production',
      brand: 'arkan-executive',
      lead_store: 'relay-to-existing-crm',
      gtm: 'GTM-P5J6D6ND',
      seo: 'static-production',
      runtime: 'node'
    });
    return send(res, 200, method === 'HEAD' ? '' : body, {
      'Content-Type': 'application/json; charset=utf-8',
      'Cache-Control': 'no-store',
      'X-Robots-Tag': 'noindex, nofollow'
    });
  }

  if (legacy.has(pathname)) return redirect(res, legacy.get(pathname), url.searchParams.toString());

  if (routes.has(pathname)) {
    const noindex = pathname === '/تم-استلام-الطلب/';
    return servePage(res, routes.get(pathname), 200, noindex);
  }

  if (pathname !== '/' && !pathname.endsWith('/') && routes.has(`${pathname}/`)) {
    return redirect(res, `${pathname}/`, url.searchParams.toString());
  }

  if (pathname === '/robots.txt') {
    return serveStatic(res, path.join(ROOT, 'robots.txt'), 'no-cache, max-age=0');
  }
  if (pathname === '/sitemap.xml') {
    return serveStatic(res, path.join(ROOT, 'sitemap.xml'), 'no-cache, max-age=0');
  }
  if (pathname === '/googlebff965ed4f5bbb83.html') {
    return serveStatic(res, path.join(ROOT, 'googlebff965ed4f5bbb83.html'), 'public, max-age=86400');
  }

  const asset = safeAssetPath(pathname);
  if (asset && serveStatic(res, asset, 'public, max-age=604800')) return;

  return servePage(res, '404.html', 404, true);
});

server.listen(PORT, HOST, () => {
  console.log(`ARKAN_NODE_READY ${HOST}:${PORT}`);
});
