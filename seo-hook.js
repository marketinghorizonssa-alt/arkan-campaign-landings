const http = require('http');

const PUBLIC_ORIGIN = 'https://arkan-realestate-solutions.hositee.com';
const REVIEW_MODE = (process.env.SITE_MODE || 'review').toLowerCase() !== 'production';
const LEGACY_ORIGINS = ['https://arkan-v2.hositee.com'];

const publicToInternal = new Map([
  ['/حلول-التمويل-العقاري/', '/solutions/'],
  ['/رفض-التمويل-العقاري/', '/rejection/'],
  ['/تمويل-عقاري-مع-التزامات/', '/obligations/'],
  ['/شراء-مديونية-عقارية/', '/debt/'],
  ['/شراء-عقار-بالتمويل/', '/property/'],
  ['/سياسة-الخصوصية/', '/privacy/'],
  ['/تم-استلام-الطلب/', '/thank-you/']
]);
const internalToPublic = new Map([...publicToInternal].map(([publicPath, internalPath]) => [internalPath, publicPath]));

function splitUrl(rawUrl) {
  const q = rawUrl.indexOf('?');
  return q === -1 ? [rawUrl, ''] : [rawUrl.slice(0, q), rawUrl.slice(q)];
}

function safeDecode(value) {
  try { return decodeURIComponent(value); } catch { return value; }
}

function rewriteText(value) {
  let output = value;
  for (const legacyOrigin of LEGACY_ORIGINS) output = output.split(legacyOrigin).join(PUBLIC_ORIGIN);
  for (const [internalPath, publicPath] of internalToPublic) output = output.split(internalPath).join(publicPath);
  return output;
}

function injectHeadMetadata(html, publicPath) {
  let output = html;
  const canonical = `${PUBLIC_ORIGIN}${publicPath}`;
  if (!/<link\s+rel=["']canonical["']/i.test(output)) {
    output = output.replace(/<head>/i, `<head><link rel="canonical" href="${canonical}">`);
  }
  if (REVIEW_MODE && !/<meta\s+name=["']robots["']/i.test(output)) {
    output = output.replace(/<head>/i, '<head><meta name="robots" content="noindex,nofollow">');
  }
  return output;
}

const originalCreateServer = http.createServer;
http.createServer = function patchedCreateServer(listener) {
  if (typeof listener !== 'function') return originalCreateServer.apply(this, arguments);

  return originalCreateServer.call(this, function seoAwareListener(req, res) {
    const [rawPath, query] = splitUrl(req.url || '/');
    const decodedPath = safeDecode(rawPath);

    if (decodedPath === '/health') {
      res.statusCode = 200;
      res.setHeader('content-type', 'application/json; charset=utf-8');
      res.setHeader('cache-control', 'no-store');
      return res.end(JSON.stringify({ ok: true, mode: REVIEW_MODE ? 'review' : 'production', origin: PUBLIC_ORIGIN }));
    }

    const publicRedirect = internalToPublic.get(decodedPath);
    if (publicRedirect) {
      res.statusCode = 301;
      res.setHeader('Location', encodeURI(publicRedirect) + query);
      res.setHeader('Cache-Control', 'public, max-age=3600');
      return res.end();
    }

    const internalPath = publicToInternal.get(decodedPath);
    const requestedPublicPath = internalPath ? decodedPath : (internalToPublic.get(decodedPath) || decodedPath);
    if (internalPath) req.url = internalPath + query;

    const originalEnd = res.end;
    res.end = function patchedEnd(chunk, encoding, callback) {
      const contentType = String(res.getHeader('content-type') || '');

      if (decodedPath === '/robots.txt' && REVIEW_MODE) {
        res.setHeader('content-type', 'text/plain; charset=utf-8');
        res.setHeader('x-robots-tag', 'noindex, nofollow');
        chunk = 'User-agent: *\nDisallow: /\n';
        res.removeHeader('content-length');
        return originalEnd.call(this, chunk, encoding, callback);
      }

      if (chunk != null && /text|html|javascript|json|xml/.test(contentType)) {
        const wasBuffer = Buffer.isBuffer(chunk);
        const text = wasBuffer ? chunk.toString(encoding || 'utf8') : String(chunk);
        let rewritten = rewriteText(text);

        if (/text\/html/.test(contentType)) {
          const canonicalPath = publicToInternal.has(requestedPublicPath)
            ? requestedPublicPath
            : (internalToPublic.get(requestedPublicPath) || requestedPublicPath || '/');
          rewritten = injectHeadMetadata(rewritten, canonicalPath);
          if (REVIEW_MODE) res.setHeader('x-robots-tag', 'noindex, nofollow');
        }

        res.removeHeader('content-length');
        chunk = wasBuffer ? Buffer.from(rewritten, encoding || 'utf8') : rewritten;
      }

      return originalEnd.call(this, chunk, encoding, callback);
    };

    return listener(req, res);
  });
};
