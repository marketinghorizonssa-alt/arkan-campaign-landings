# ARKAN official-domain Node runtime

This runtime serves the reviewed Arabic landing pages generated from the PHP source, static assets, robots, sitemap, verification file and a same-origin `/api/lead` relay to the existing unified lead backend.

Production requirements:

- Node 20+
- Bind to `0.0.0.0` and the platform `PORT`
- Keep `GTM-P5J6D6ND`
- Keep the canonical production origin `https://arkan2030.com`
- Preserve the eight approved page routes, legacy redirects, real 404, robots, six-URL sitemap and Search Console verification file
- Generate the static page files from the reviewed PHP source with `HTTP_HOST=arkan2030.com`
- Copy only the approved ARKAN image assets
- Test `/health`, all landing routes, `/api/lead`, robots, sitemap and 404 after deployment

The first production package was generated from GitHub Actions artifact `arkan2030-deploy-package`, digest `sha256:5cf897e2332e433ba9dbe9becb594d4ed1cc810c07629fa708e3eaf588a9eb12`, then wrapped with this reviewed Node runtime and locally validated before Hostinger deployment.
