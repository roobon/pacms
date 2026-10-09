# Probha Aurora CMS — Documentation

**Probha Aurora CMS — Architecture & Technical Specification** (internal short name PACMS)

Team: Syed Ziaul Habib (Team Lead / Lead Developer) · Hasibul Hasan · Khandoker Humayoun Kobir

Current phase: **Phase 8 — Content Modules, in parts.** Parts 8A (content engine, News, Events, sidebars — [report](phase-reports/PHASE-8A-REPORT.md)) and 8B (Projects, Programs, Publications — [report](phase-reports/PHASE-8B-REPORT.md)) approved and merged, and so are 8C.1 (Team, Partners, Galleries — [report](phase-reports/PHASE-8C1-REPORT.md)) and 8C.2 (Testimonials, Media Coverage, search — [report](phase-reports/PHASE-8C2-REPORT.md)), and 8D.1 (admin-made content types: engine and types — [report](phase-reports/PHASE-8D1-REPORT.md)); 8D.2 (blocks, categories, documents, address redirects, menu — [report](phase-reports/PHASE-8D2-REPORT.md)) approved and merged; the starter kit ([report](phase-reports/PHASE-8-STARTER-KIT-REPORT.md)) is ready for review. Phase 7: [report](phase-reports/PHASE-7-REPORT.md) (v0.7.0).

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
