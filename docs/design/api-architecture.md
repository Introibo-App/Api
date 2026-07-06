# The Directorium API — architecture

The versioned, cache-first HTTP service that exposes the Core liturgical engine to
the Ordo plugin, the Site, and third-party clients. This document is the decision
record the rest of the v0.1 build works against; downstream repos build on the
contract described here.

## What the service is (and is not)

- **A thin layer over Core.** Every piece of liturgical truth comes from
  `directorium/core` through **one** boundary, `Engine\CoreGateway` (#6). No handler,
  parser, or cache reimplements a rubric or reshapes the day payload. Core is a
  Composer dependency, pinned by `composer.lock`; when Core moves, the API updates
  deliberately (`composer update directorium/core`) and re-runs its gates.
- **Cache-first.** The overwhelmingly common read — one date under one calendar —
  is deterministic and immutable for a given data version, so it is served as a
  pre-generated static file at the edge and only *computed* on a cold miss (#13,
  #17). The PHP application is the origin of truth and the cold path, not the hot
  path.
- **Stateless per request.** A response depends only on `(date, system, calendar,
  language)` and the current data version — never on wall-clock, geolocation, or
  session. That is what makes it cacheable and reproducible.

## The public contract

A client depends on three things — the **URL shape**, the **envelope**, and the
**error shape**. All three are versioned under `/v1` and only ever grow additively.

### URLs

The **temporal resource** lives in the path (it maps 1:1 to a static file); the
**resolution parameters** live in the query with sensible defaults:

```
GET  /v1/day/{date}      ?system=1962 &calendar=universal &lang=la
GET  /v1/month/{year-month}
GET  /v1/year/{year}
GET  /v1/meta            (discovery: supported systems, calendars, languages)
GET  /v1/aup             (acceptable-use policy)   GET /v1/terms
GET  /v1/health          (liveness)
POST /v1/admin/purge     POST /v1/admin/rebuild     (admin-token only)
```

- `date` is an ISO `YYYY-MM-DD`; `year-month` is `YYYY-MM`; `year` is `YYYY`.
- `system` — the rubric system. `1962` today (Core's `roman:rubricae-1960`);
  `1954`/`1955` join as Core gains them. Unsupported → `422 unsupported_system`.
- `calendar` — `universal` (the 1962 base, the default) or a particular-calendar
  slug (`sspx`). Unknown → `422 unknown_calendar`.
- `lang` — a corpus language; `la` today. Unsupported → `422 unsupported_language`.
- **Supported date range: 1583–2200**, the proven range of Core's golden fixture
  (#365). Outside it → `422 date_out_of_range`. A non-date → `400 malformed_date`.

### The success envelope

```jsonc
{
  "data": { /* the Core DayContract (#52), verbatim, for /day; a list for month/year */ },
  "meta": {
    "dataVersion": "c1.0.0+e0.4.0+d1962-2026-07-02.1",
    "request": { "date": "2026-09-03", "system": "1962", "calendar": "sspx", "language": "la" }
  }
}
```

- `data` is Core's frozen output contract, passed through untouched. The per-day
  version stamps (`contractVersion`, `corpusVersion`, `engineVersion`) live inside
  it and remain authoritative.
- `meta.dataVersion` is the **service-wide** stamp — a compact, opaque token that
  moves when *any* axis that can change output moves (contract shape `c`, engine
  `e`, corpus data `d`). Clients key a cache on it; the caching layer (#17) derives
  ETags and static-file versions from it.
- `meta.request` echoes the normalised parameters the service actually resolved.
- **Collections** (`/month`, `/year`) return `data` as a list of day contracts
  ordered ascending by date — each self-describing via its own `date` field — and
  add `meta.count`. The whole civil year is resolved once, so a range costs a
  single resolution.
- **Additive-only.** New `meta` fields and new `data` keys (as Core's contract
  grows) never break a client. A removal or repurposing would be a `/v2`.

### The error shape

One shape for every failure, so a client parses errors exactly one way:

```json
{ "error": { "code": "unknown_calendar", "message": "…", "status": 422 } }
```

`code` is a stable machine string (`Contract\ErrorCode`) a client may switch on;
it is never renamed, only added to. Any unexpected internal fault becomes an opaque
`500 internal_error` — Core stack detail never leaks.

## REST posture, and the migration path

The path-date + query-parameter shape is already REST-shaped: `/v1/day/2026-09-03`
is a dated resource, and `?calendar=` / `?lang=` are representation/filter
modifiers on it — exactly how mature APIs model those dimensions. It is also the
most cache- and static-generation-friendly layout, because the date maps directly
to a file and the parameters are a small, bounded, enumerable set.

A more **resource-oriented** hierarchy (e.g. `/v1/calendars/{calendar}/days/{date}`)
can be added later **additively, with no client breakage**, because:

- **Handlers key off the resolved `CalendarQuery`, never the URL shape.** A new
  route is one extra line in the router pointing at the *same* handler.
- **The payload contract is independent of the URL.** Reshaping URLs never touches
  `data` or `meta`.
- **`/v1` + cacheable 308 redirects** migrate any deprecated path without a break;
  a genuinely different contract becomes `/v2` while `/v1` still serves.

So the pivot is a routing-table change, not a rewrite. The disciplines that keep it
that way: handlers stay URL-shape-agnostic, and the envelope stays additive-only.

## Static generation & the cold path

The calendar reads are deterministic and immutable for a given data version, so they
are served from a **static tier** and only *computed* on a cold miss (#13):

- **`Cache\StaticStore`** — a filesystem store of pre-rendered response bodies, one
  JSON file per request keyed by its canonical path (`v1/day/2026-09-03/1962/sspx`).
  The whole tree is **namespaced by the data version** (a sanitised `dataVersion`
  directory), so a corpus/engine/contract bump lands in a fresh directory and a
  stale file can never be served; the rebuild-and-purge action (#28) simply drops
  the old version's directory. No root configured (the dev/test default) → the store
  is disabled and the service runs identically without it.
- **`Cache\ResponseCache`** + **`Cache\CacheHeaders`** — read-through: a hit returns
  the stored bytes untouched; a miss computes, **writes back** to the store, and
  returns. Every served response carries a strong `ETag` (a hash of `dataVersion|key`
  — it changes exactly when content would, without hashing the body), an immutable
  `Cache-Control` (`public, max-age=3600, s-maxage=604800, immutable`), and honours
  conditional GETs with a bodiless `304`. `X-Data-Version` is stamped on **every**
  response; `/v1/health` is `no-store`. Edge pairing is documented in
  [docs/deploy/cloudflare.md](../deploy/cloudflare.md).
- **`bin/generate-static.php`** — warms the store for a year range by replaying every
  hot request (year, months, days × systems × calendars) through the **real kernel**
  (#14). Because the kernel writes read-through, generation reuses the exact response
  shape the live endpoints serve — there is no second serialiser to drift — and the
  civil year is resolved once per (year, calendar).

In production the web server serves an existing static file directly (a rewrite:
try the file, else `public/index.php`), so the hot path never enters PHP; the
read-through store is the origin's own fallback and the write-through populator.

## Access control (keys, quotas, rate limits)

The metered calendar endpoints (`/v1/day`, `/v1/month`, `/v1/year`) sit behind an
access gate (#21); `/v1/health` and `/v1/meta` stay public. The gate is **open when
no key store is configured** (the dev/test default and the R2-launch default), so
enabling metering is a deploy-time switch, not a code change.

- **`Auth\AccessControl`** — authenticates a request to an `ApiKey` (via
  `Authorization: Bearer <key>` or `X-API-Key`), scopes it to that key's `Tenant`,
  enforces the key's per-minute **rate limit** and the tenant's monthly **quota**,
  and returns the `X-RateLimit-*` headers to echo. A failure is the canonical error:
  `401 unauthenticated`, or `429 rate_limited` / `429 quota_exceeded` (the rate 429
  carries `Retry-After`). Wrapped around handlers by `Auth\GuardedHandler`.
- **Keys are hashes.** Only `sha256(secret)` is ever stored; the plaintext is shown
  once at issue (`KeyIssuer::issue`) and never again. `rotate` deactivates the old
  key and mints a new one; `revoke` deactivates without deleting.
- **`Auth\KeyStore`** is the one storage seam (#22): `InMemoryKeyStore` (the tested
  double) and `PdoKeyStore` (MySQL, `sql/schema.sql`). Counters are **aggregate** —
  one upserted row per (tenant, month) and per (key, minute), never a row per
  request. `bin/api-key.php` is the maintainer CLI for tenants and keys.

The MySQL database is the maintainer-provisioned half; the logic is proven against
the in-memory store, and `AccessControl::fromEnvironment()` wires MySQL when
`INTROIBO_DB_DSN` is set.

## Admin actions & policies

- **`GET /v1/aup`, `GET /v1/terms`** (#30) — the acceptable-use policy and terms of
  use, served as versioned data (`Legal\Policies`). Public and cacheable.
- **`POST /v1/admin/purge`** (#29) and **`POST /v1/admin/rebuild`** (#28) — admin
  actions behind `Admin\AdminGate` (a shared token via `X-Admin-Token` or bearer).
  Purge invalidates the edge (`Edge\EdgeCache` → `CloudflareEdge`); rebuild reports
  the current data version and purges, so a new build is served at once (the static
  tier is regenerated out of band with `bin/generate-static.php`). **The admin routes
  are only registered when `INTROIBO_ADMIN_TOKEN` is set** — an unconfigured service
  exposes no admin surface. When no CDN is configured the edge is `NullEdge` and a
  purge is a no-op.

## Framework posture

**No framework.** A hand-rolled front controller (`public/index.php`) → `Kernel` →
`Http\Router` → `Http\Handler`, with immutable `Http\Request`/`Http\Response`
value objects. Runtime dependencies are `directorium/core` and `ext-json` only. This
mirrors Core's dependency-free clean-room ethos, keeps the PHP 8.2+ surface small,
and fits a service whose hot path is static files the application never touches.
Dev tooling matches Core: PHP_CodeSniffer (PSR-12), PHPStan, PHPUnit.

## Layout

```
public/index.php          Front controller (two-line bootstrap).
src/Kernel.php            Wires the gateway + routes; renders the error envelope.
src/Http/                 Request, Response, Router, Handler.
src/Contract/             Envelope, ErrorCode, ApiError, ApiException, Json.
src/Query/                CalendarQuery (the validated request) + QueryParser (#7).
src/Engine/CoreGateway.php  The single boundary to Core (#6).
src/Handler/              One class per endpoint.
```

## The infrastructure boundary

The service is built to run, but taking it *live* on 3mi.org needs infrastructure a
maintainer provisions. The split:

| Built in the repo (code + tests) | Maintainer infrastructure |
| --- | --- |
| The service, endpoints, envelope, validation | The DreamHost origin + a URL-rewrite to `public/index.php` |
| Static generation + cold dynamic path (#13) | A writable static directory / object store |
| ETag + `Cache-Control` + data-version stamp (#17) | A **Cloudflare** account with cache-everything rules |
| API keys/quotas/rate-limits coded against a storage interface, tested with a double (#21) | A **MySQL** database + credentials |
| Cache-purge + admin rebuild coded against an edge interface, tested with a double (#27) | Cloudflare API token for edge purge |

The application never embeds a credential; infrastructure is wired through
environment/config at deploy time. Everything above the line is unit-tested with no
network and no external services.

## Versioning & stability

- The URL prefix is `/v1`. Breaking the envelope, an error code, or a URL contract
  means `/v2`, served alongside `/v1`.
- The envelope and error shape grow additively; open enums (new error codes, new
  systems/calendars/languages) are minor, non-breaking additions.
- `meta.dataVersion` is the client's cache key; when it changes, cached responses
  may differ and should be refetched.
