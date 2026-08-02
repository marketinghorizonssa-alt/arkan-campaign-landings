const http = require('http');

const PUBLIC_ORIGIN = 'https://arkan-executive.hositee.com';
const LEGACY_ORIGINS = [
  'https://arkan-v2.hositee.com',
  'https://arkan-realestate-solutions.hositee.com'
];
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

function rewriteText(value) {
  let output = value;
  for (const legacyOrigin of LEGACY_ORIGINS) output = output.split(legacyOrigin).join(PUBLIC_ORIGIN);
  for (const [internalPath, publicPath] of internalToPublic) output = output.split(internalPath).join(publicPath);
  return output;
}

const originalCreateServer = http.createServer;
http.createServer = function patchedCreateServer(listener) {
  if (typeof listener !== 'function') return originalCreateServer.apply(this, arguments);
  return originalCreateServer.call(this, function seoAwareListener(req, res) {
    const [rawPath, query] = splitUrl(req.url || '/');
    let decodedPath = rawPath;
    try { decodedPath = decodeURIComponent(rawPath); } catch {}

    const publicPath = internalToPublic.get(decodedPath);
    if (publicPath) {
      res.statusCode = 301;
      res.setHeader('Location', encodeURI(publicPath) + query);
      res.setHeader('Cache-Control', 'public, max-age=3600');
      return res.end();
    }

    const internalPath = publicToInternal.get(decodedPath);
    if (internalPath) req.url = internalPath + query;

    const originalEnd = res.end;
    res.end = function patchedEnd(chunk, encoding, callback) {
      const contentType = String(res.getHeader('content-type') || '');
      if (chunk != null && /text|html|javascript|json|xml/.test(contentType)) {
        const wasBuffer = Buffer.isBuffer(chunk);
        const text = wasBuffer ? chunk.toString(encoding || 'utf8') : String(chunk);
        const rewritten = rewriteText(text);
        res.removeHeader('content-length');
        chunk = wasBuffer ? Buffer.from(rewritten, encoding || 'utf8') : rewritten;
      }
      return originalEnd.call(this, chunk, encoding, callback);
    };

    return listener(req, res);
  });
};
