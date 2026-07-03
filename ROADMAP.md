# Roadmap

_A plain-language overview of where the Introibo API is headed. Each version links to its tracking
milestone and the issues that make it up (issue links are added once the backlog is imported)._

_Last updated: 2026-07-03_

## Release train
- **R1 — Groundwork.** The service skeleton and the pre-launch contract decisions surfaced over the
  API (open-season vocabulary, textAvailability, edition governance, lectionary indicators).
- **R2 — 3mi.org.** The calendar endpoints with the **SSPX** preset first — v0.1.0 as the release
  vehicle behind the Ordo plugin on 3mi.org.
- **R3+.** Everything else, in the build order below.

## ✅ Code-complete on `develop` — [v0.1.0](https://github.com/Introibo-App/Api/milestone/1)
**The platform comes online.** A versioned `/v1` service over Core (`introibo/core`, no liturgical logic
of its own): the calendar endpoints (`/day`, `/month`, `/year`, `/meta`) with the **SSPX** calendar via
`?calendar=sspx`, cache-first with static generation, ETags + immutable `Cache-Control` + an
`X-Data-Version` stamp, API keys with per-tenant quotas and per-key rate limits, admin rebuild/cache-purge,
and the AUP + terms endpoints. All six epics merged; architecture in
[`docs/design/api-architecture.md`](docs/design/api-architecture.md). **Remaining for the R2 launch is
infrastructure, not code:** provision the MySQL database (`sql/schema.sql`), a Cloudflare zone, and the
DreamHost deploy target, then cut the v0.1.0 release.

## 🗓️ Next — [v0.2.0](https://github.com/Introibo-App/Api/milestone/3)
**Particular-calendar presets.** FSSP, ICKSP, and the Cum sanctissima toggle (the SSPX overlay already
ships in v0.1 via Core). FSSP/ICKSP land as Core adds their overlays.

## 🔭 Future
- **[v0.3.0](https://github.com/Introibo-App/Api/milestone/4) — Outputs & integrations.** iCal feed +
  printable monthly Ordo (PDF).
- **[v0.4.0](https://github.com/Introibo-App/Api/milestone/2) — 1954/1955 rubric-system parameters.**
  Endpoints for the 1954 and 1955 rubric systems as Core gains them.
- **[v1.0.0](https://github.com/Introibo-App/Api/milestone/5) — Platform launch.** Cut together with
  Core, Site, and Ordo — **OpenAPI**, generated **JS/PHP/Python SDKs**, a **docs portal**, and a
  versioning policy.
- **[v1.1.0](https://github.com/Introibo-App/Api/milestone/6) — Text infrastructure over the API.** The
  Little Office of the BVM, **provenance & explainability** (`/v1/sources`, `?explain` resolution trace),
  and **CalDAV, webhooks & shareable presets**.
- **[v1.2.0](https://github.com/Introibo-App/Api/milestone/7) — Mass/Missal API.** Ordinary + Proper,
  plus the **lectionary endpoint**.
- **[v1.3.0](https://github.com/Introibo-App/Api/milestone/8) — Breviary/Office API.** Canonical hours,
  psalter, propers.
- **[v1.4.0](https://github.com/Introibo-App/Api/milestone/10) — Novus Ordo & LOTH delivery.**
  `/v1/editions` discovery with **capability + textAvailability flags**, LOTH hour params, and NO
  lectionary cycle params.
- **[v2.0.0](https://github.com/Introibo-App/Api/milestone/9) — Comparison endpoints.**
  `/v1/compare/calendar`, `/compare/rite`, `/compare/office` over the shared diff engine, plus
  `/v1/rites` discovery.
- **[v2.1.0](https://github.com/Introibo-App/Api/milestone/11) — Supporting-corpora API.** Martyrology,
  chant, devotions, fasting.

## ✅ Released
_None yet._
