# Edge caching (Cloudflare)

How the service's origin headers are meant to be paired with a Cloudflare edge in
front of the DreamHost origin (#20). The **origin headers are emitted by the code**
(see `Cache\CacheHeaders`); the **edge rules are maintainer configuration** applied
once against the account — nothing here needs a code change to take effect.

## What the origin emits

Every cacheable calendar response (`/v1/day`, `/v1/month`, `/v1/year`, `/v1/meta`)
carries:

| Header | Value | Purpose |
| --- | --- | --- |
| `Cache-Control` | `public, max-age=3600, s-maxage=604800, immutable` | Browser holds 1 h; the edge holds a week; content never changes for a data version. |
| `ETag` | `"<sha256(dataVersion\|key)[:20]>"` | Strong validator for conditional GETs → bodiless `304`. |
| `X-Data-Version` | e.g. `c1.0.0+e0.4.0+d1962-2026-07-02.1` | The build that produced the response, on **every** response. |
| `X-Cache` | `HIT` / `MISS` | Whether the origin served from its own static tier. |

`/v1/health` is deliberately `Cache-Control: no-store` so probes always reach origin.

## Edge configuration (maintainer, once)

1. **Cache Everything** for `/v1/*` (a Cache Rule or Page Rule). The API returns JSON,
   which Cloudflare does not cache by default; this opts it in and lets the origin's
   `Cache-Control` (including `s-maxage`) govern the edge TTL.
2. **Cache key includes the query string.** The response varies by `system`,
   `calendar`, and `lang` (all query parameters), so those must be part of the cache
   key. Include the full query string (or at least those three) in the Cache Key
   settings; otherwise `?calendar=sspx` and the universal calendar would collide.
3. **Serve stale on error / honour origin `Cache-Control`.** Let origin headers drive
   TTL rather than an edge override, so a data-version bump + purge is the single
   lever for freshness.

## Static origin (optional but recommended)

The build-time generator (`bin/generate-static.php`) writes pre-rendered JSON under a
**data-version-namespaced** directory. A web-server rewrite should try the matching
static file first and fall back to `public/index.php`:

```
# try  <root>/<dataVersion>/v1/day/2026-09-03/1962/sspx.json  first, else the app
```

so hot paths never enter PHP. The application's read-through store is the same tier's
fallback and populator, so the two stay byte-identical.

## Corrections & purge

A correction is not an edit to a cached file — it is a **new data version**:

1. Rebuild the corpus / bump the engine → `X-Data-Version` changes; every ETag and
   every static path changes with it.
2. Regenerate the static tier for the new version (new directory).
3. **Purge the edge** (the cache-purge action, #29 / the admin rebuild, #28). Because
   the new version's URLs are distinct at origin and the ETags differ, revalidation
   returns fresh content immediately rather than waiting out a TTL.

The Cloudflare API token needed for programmatic purge is maintainer-provisioned and
injected at deploy time; the code calls it through an edge interface (#29), tested
with a double.
