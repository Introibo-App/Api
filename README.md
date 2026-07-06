# Directorium API

> Cache-first HTTP API for the traditional Roman liturgy — *Introíbo ad altáre Dei.*

The Directorium **API** is the authoritative, versioned (`/v1`) HTTP service over the Directorium Core
engine. Its responses are a pure function of `(date, system, calendar, office, hour, language)` and
therefore immutable per key — pre-generated as static JSON for hot paths, computed on demand for
cold dates, and fronted by Cloudflare with long, immutable TTLs, ETags, and a data-version stamp.
Corrections bump the data-version and purge the edge cache.

It issues and meters API keys per tenant (quotas + rate limits) and is consumed by the directorium.app
website and admin, the Ordo WordPress plugin, and future apps.

## Status

Pre-release — **v0.1.0 in progress**. See the [roadmap](ROADMAP.md).

## Stack

PHP + MySQL on DreamHost, Cloudflare in front, cache-first. MySQL holds only API keys, tenants,
aggregate usage counters, and the search index — never a row per request. Node is build-time only;
no Python.

## Development

Work branches off `develop`, lands via squash PRs with Conventional-Commit titles, and releases are
cut automatically by release-please. The engine is consumed as the `directorium/core` Composer
dependency (pinned by `composer.lock`); the API adds no liturgical logic of its own. The
architecture is documented in [docs/design/api-architecture.md](docs/design/api-architecture.md).

```sh
composer install            # pulls in directorium/core
composer check              # PSR-12 lint + PHPStan + PHPUnit — the CI gate
php -S 127.0.0.1:8080 -t public public/index.php
#   → GET /v1/day/2026-09-03?calendar=sspx
#   → GET /v1/month/2026-09
#   → GET /v1/year/2026
#   → GET /v1/meta      (supported systems, calendars, languages, range)
#   → GET /v1/health

# Pre-generate the static tier for a year range (data-version-namespaced):
php bin/generate-static.php 2024 2030 ./build/static
```

Access control (API keys, quotas, rate limits) is **off until a key store is
configured** — set `DIRECTORIUM_DB_DSN` (schema in [`sql/schema.sql`](sql/schema.sql))
and manage tenants/keys with `php bin/api-key.php` (`tenant` / `issue` / `rotate` /
`revoke`). Health, discovery, and the policies (`/v1/aup`, `/v1/terms`) stay public.
Admin actions (`POST /v1/admin/purge`, `/rebuild`) exist only when
`DIRECTORIUM_ADMIN_TOKEN` is set; edge purge targets Cloudflare (`DIRECTORIUM_CF_ZONE` /
`DIRECTORIUM_CF_TOKEN`), a no-op otherwise.

## Licence

© 2026 Directorium. Licensed under **AGPL-3.0-or-later** (see [LICENSE](LICENSE)). The compiled
calendar dataset is released under **CC0**.
