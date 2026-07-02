# Roadmap

_A plain-language overview of where the Introibo API is headed. Each version links to its tracking
milestone and the issues that make it up (issue links are added once the backlog is imported)._

_Last updated: 2026-07-02_

## Release train
- **R1 — Groundwork.** The service skeleton and the pre-launch contract decisions surfaced over the
  API (open-season vocabulary, textAvailability, edition governance, lectionary indicators).
- **R2 — 3mi.org.** The calendar endpoints with the **SSPX** preset first — v0.1.0 as the release
  vehicle behind the Ordo plugin on 3mi.org.
- **R3+.** Everything else, in the build order below.

## 🚧 In progress — [v0.1.0](https://github.com/Introibo-App/Api/milestone/1)
**The platform comes online.** A versioned `/v1` service and the calendar endpoints — the **SSPX release
vehicle**: cache-first with static generation, ETags and a data-version stamp, API keys with quotas and
rate limits, cache-purge + data versioning, and an acceptable-use policy.

## 🗓️ Next — [v0.2.0](https://github.com/Introibo-App/Api/milestone/3)
**Particular-calendar presets.** SSPX, FSSP, ICKSP, and the Cum sanctissima toggle.

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
