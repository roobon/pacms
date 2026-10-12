# Probha Aurora CMS — Documentation

**Probha Aurora CMS — Architecture & Technical Specification** (internal short name PACMS)

Team: Syed Ziaul Habib (Team Lead / Lead Developer) · Hasibul Hasan · Khandoker Humayoun Kobir

Current phase: **Phase 11 — Public React SPA.** 11A (search, contact form, account area) approved and merged ([report](phase-reports/PHASE-11A-REPORT.md)); 11B next. Phase 10 (External Providers) approved and merged (v0.10.0): 10A ([report](phase-reports/PHASE-10A-REPORT.md)), the richer text editor ([report](phase-reports/PHASE-10-EDITOR-REPORT.md)) and 10B ([report](phase-reports/PHASE-10B-REPORT.md)). Phase 9 (Navigation, v0.9.0): 9A ([report](phase-reports/PHASE-9A-REPORT.md)), 9B ([report](phase-reports/PHASE-9B-REPORT.md)). Phase 8 (Content Modules, v0.8.0): [reports](phase-reports/).

| Document | Purpose |
|---|---|
| [PHASE-1-ARCHITECTURE-REVIEW.md](PHASE-1-ARCHITECTURE-REVIEW.md) | **Start here.** Summary, repository inspection, review findings, decisions to approve, open questions, risks, Phase 1 report |
| [CMS-ARCHITECTURE.md](CMS-ARCHITECTURE.md) | System architecture: topology, block engine, sources, display, styles, builder, providers, modules, admin UI, SPA, Laravel structure, performance, testing |
| [CMS-BLOCK-SCHEMA.md](CMS-BLOCK-SCHEMA.md) | Versioned JSON format for blocks and pages, AI generation rules, import and export pipeline |
| [DATABASE-ARCHITECTURE.md](DATABASE-ARCHITECTURE.md) | ERD, tables, columns, keys, indexes, migration and seeding plan |
| [API-ARCHITECTURE.md](API-ARCHITECTURE.md) | Public, user, preview and admin APIs, error format, caching, rate limits |
| [SECURITY-ARCHITECTURE.md](SECURITY-ARCHITECTURE.md) | Authentication, roles and permissions, XSS, SSRF, XML, uploads, headers, secrets |
| [UI-DESIGN-SYSTEM.md](UI-DESIGN-SYSTEM.md) | Design tokens, Bootstrap theming, components, accessibility, admin theme |
| [DEPLOYMENT-ARCHITECTURE.md](DEPLOYMENT-ARCHITECTURE.md) | ZIP release model, server setup, cron and queue, updates, backups |
| [DEVELOPMENT-ROADMAP.md](DEVELOPMENT-ROADMAP.md) | Phases 1–14, exit criteria, report template, ways of working |
| [MASTER-PROMPT.md](MASTER-PROMPT.md) | The original master prompt (reference specification, verbatim) |
