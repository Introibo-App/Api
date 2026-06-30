# Introibo API

> Cache-first HTTP API for the traditional Roman liturgy — *Introíbo ad altáre Dei.*

The Introibo **API** is the authoritative, versioned (`/v1`) HTTP service over the Introibo Core
engine. Its responses are a pure function of `(date, system, calendar, office, hour, language)` and
therefore immutable per key — pre-generated as static JSON for hot paths, computed on demand for
cold dates, and fronted by Cloudflare with long, immutable TTLs, ETags, and a data-version stamp.
Corrections bump the data-version and purge the edge cache.

It issues and meters API keys per tenant (quotas + rate limits) and is consumed by the introibo.org
website and admin, the Ordo WordPress plugin, and future apps.

## Status

Pre-release — **v0.1.0 in progress**. See the [roadmap](ROADMAP.md).

## Stack

PHP + MySQL on DreamHost, Cloudflare in front, cache-first. MySQL holds only API keys, tenants,
aggregate usage counters, and the search index — never a row per request. Node is build-time only;
no Python.

## Development

Work branches off `develop`, lands via squash PRs with Conventional-Commit titles, and releases are
cut automatically by release-please.

## Licence

© 2026 Introibo. Licensed under **AGPL-3.0-or-later** (see [LICENSE](LICENSE)). The compiled
calendar dataset is released under **CC0**.
