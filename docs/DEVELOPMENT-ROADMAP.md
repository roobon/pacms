# Probha Aurora CMS — Development Roadmap

| | |
|---|---|
| Document | DEVELOPMENT-ROADMAP.md (Phase 1 deliverable AB) |
| Status | Draft for team review |
| Working agreement | One phase at a time. At the end of each phase: **implement → test → Laravel checks → frontend build → migration/API checks → inspect → fix → review → document → Phase Completion Report → team review together → approval → next phase.** No phase starts automatically. |

---

## 1. Phase overview

The master prompt's 14 phases are kept with their numbers. **Three scope adjustments are proposed (D-04)** because later phases depend on things the original order builds too late:

| # | Phase | Proposed adjustment |
|---|---|---|
| 1 | Architecture review | — (this phase) |
| 2 | Foundation | + `pacms:doctor`, design-system preview page, CI-ready test setup |
| 3 | CMS Core | **+ Media core** (upload, library, picker) moved from Phase 7, because pages need featured images and SEO needs OG images |
| 4 | Block Engine | **+ shared React block renderer and preview iframe** built here (not waiting for Phase 11). **Dynamic mode proven with News** (a minimal News type is pulled forward from Phase 8). External mode proven with a fake provider. |
| 5 | Custom Block Builder | — |
| 6 | AI JSON | — |
| 7 | Media (advanced) | Variants tuning, replace, categories/tags, usage, SVG decision, private media |
| 8 | Content Modules | Remaining types (News is completed here) |
| 9 | Navigation | — |
| 10 | External Providers | Re-verify Meta requirements at phase start |
| 11 | Public React SPA | Completes pages, archives, details and search on top of the Phase 4 renderer |
| 12 | Security / Testing | Audit, not first implementation (security is built in from Phase 2) |
| 13 | Performance | Measure against budgets |
| 14 | Production Preparation | Release script, backup/restore drill, docs |

The phases are sized relative to each other (S/M/L/XL) instead of in calendar time. The team sets dates once Phase 2's velocity is known.

---

## 2. Phases in detail

### Phase 1 — Architecture review (S) · *current*
- **Deliverables**: the 9 documents in `docs/`.
- **Exit**: team approval of the decisions in PHASE-1-ARCHITECTURE-REVIEW.md §5 and answers to the open questions (§6), at least those marked *before Phase 2*.

### Phase 2 — Foundation (M)
- Laravel project (version per D-01), Git repository, `.gitignore`, `.editorconfig`, `.env.example`, `composer.json` platform pin.
- MySQL databases `pacms` and `pacms_testing`, and the `utf8mb4` config.
- Fortify + Sanctum (SPA stateful), Spatie Permission, roles and permission seeder, `Gate::before` for Super Admin, base policies.
- Admin login (Blade) with 2FA, and a registered-user auth API (JSON).
- `SecurityHeaders` middleware, rate limiters, error envelope, `ActivityLogger` + `activity_logs`.
- `settings` table + service. Design tokens v1 (defaults + generated `tokens.css`).
- Bootstrap 5.3 Sass subset, Bootstrap Icons, admin layout (sidebar, top bar, components from UI-DESIGN-SYSTEM.md §8), **design-system preview page**.
- Public React app skeleton: Vite entry, router, TanStack Query, axios client, SPA shell controller with server-injected meta, 404 page.
- `pacms:create-admin`, `pacms:doctor`, scheduler + queue wiring.
- Tests: auth, 2FA, lockout, role/permission matrix scaffolding, security headers, SPA shell 404 status.
- **Exit**: `php artisan test` green, `npm run build` green, a fresh migrate + seed works, the admin can log in, and the SPA shell serves.

### Phase 3 — CMS Core + Media core (L)
- Pages (hierarchy, paths, staged publishing), workflow service and transitions, scheduling, preview (signed + auth), revisions (snapshot, compare, restore, prune), SEO metadata + defaults, redirects (auto on slug change), `content_references`, activity logging on all actions.
- Media core: upload (guarded), variants job, library screen, **media picker island**, alt text, usage tracking.
- Taxonomy (`terms`, `termables`) with admin management.
- Tests: workflow permissions per role, **leak tests** (drafts invisible), preview security, revision restore, upload guard (polyglots, MIME mismatch, EXIF strip).

### Phase 4 — Block Engine (XL)
- `blocks` storage, TreeRepository, TreeValidator, Serializer (schema 1.0 node format), BlockRegistry, core block type classes (§5.5 of CMS-ARCHITECTURE.md), `pacms:blocks:sync`.
- Field system (definitions → rules → JSON Schema), source system (static/dynamic/external), display-mode registry, StyleCompiler (JS), design-token references, responsive overrides.
- **Shared React block renderer** (recursive, lazy per type, error boundary per block), builder-preview iframe route.
- Payload builder with cache versions. Minimal **News** type for dynamic mode. Fake external provider.
- A basic builder UI (tree + inspector + save) sufficient to create pages. The full builder UX comes in Phase 5.
- Tests: tree save/validate/move, depth/count limits, child rules, every core block renders, display modes, style compiler snapshots, source whitelists.

### Phase 5 — Custom Block Builder (XL)
- Full builder UX: palette, drag and drop (dnd-kit, keyboard), duplicate, copy/paste, hide/show, undo/redo, autosave, device switcher, responsive controls, live preview.
- Repeater editor (add/remove/duplicate/reorder/validate, nested).
- Custom block types (fields + bound structure, `repeat`, `when`), templates (save/insert), global blocks (reference, detach, usage).
- Tests: builder store operations (Vitest), custom-type binding render, template deep copy, global update propagation, detach.

### Phase 6 — AI JSON (L)
- Generated JSON Schemas, versioning + migrator framework, import pipeline (validation stages, asset plan, preview, confirmation, transactional import, report), export (block/section/template/page), downloadable schema + an "AI prompt helper" text for editors.
- Tests: every example in CMS-BLOCK-SCHEMA.md imports, the malicious fixture set is rejected or stripped, missing media is never substituted, and export → import round trip is equivalent.

### Phase 7 — Media, advanced (M)
- Replace, categories and tags, bulk actions, filters and sort, private media serving, duplicate detection, SVG decision implementation, usage-guarded delete, variant regeneration command.

### Phase 8 — Content Modules (XL)
- Events, Projects, Programs, Publications, Team, Partners, Testimonials (+ submission API, moderation queue, logs), Media Coverage (+ scheduled source checks, fallback display), Galleries (CMS mode). The News module is completed.
- **Module sidebars** (agreed 2026-10-07): global blocks of kind `sidebar`, built in the builder. Settings choose a sidebar and its side (left or right) per content type (news, events, projects, programs…). Each item can override it or turn it off. On desktop the content takes about two-thirds and the sidebar one-third; on phones the sidebar goes below the content.
- Admin CRUD for all (shared Blade components), dynamic sources and cards for each, search indexing.
- Tests: per module CRUD + policies + leak tests, moderation state machine, testimonial privacy assertions, source-check classification.
- **Delivered in parts (agreed 2026-10-08), each reviewed before the next:**
  - **8A**: content engine (Content Type Registry), full News, Events, module sidebars. *Merged.*
  - **8B**: Projects, Programs, Publications, documents, module sitemaps; Document and HTML blocks, "Save page as template". *Merged.*
  - **8C**: Team, Partners, Testimonials (submission + moderation), Media Coverage, Galleries, search indexing; links from projects/programs to partners, team and galleries. *Merged (8C.1, 8C.2); media coverage tags deferred to 8D.*
  - **8D — admin-made content types** ("custom post types", added at the team lead's request; not in the master prompt): a *Design → Content types* screen (permission "Manage content types") to create types without code. You set the names, icon, URL prefix, categories, documents and listing page, and build the fields with the Custom Blocks field builder, each shown in the details box, as its own section, or hidden. Every such type gets everything the built-in modules have (workflow, revisions, SEO, preview, sidebar, archive, detail page, sitemap, builder block, permissions). All admin-made types share one table with JSON fields, so no deployment is needed. Built-in modules stay in code. The admin content menu is reorganised here to handle any number of types. *Merged (8D.1: engine, types, admin screens, public pages; 8D.2: builder blocks, categories and documents, address redirects, menu, coverage tags).*
  - **Starter kit**: designed page templates (Home, About, Contact, module landing pages), section templates and global blocks (CTA band, contact details, sidebars), installed with one command (`pacms:starter`) and editable afterwards. Decisions (2026-10-09): pages only with `--pages`, as drafts; no images shipped; the footer comes with Phase 9; the contact page uses contact details and an e-mail button until a contact form is built (Phase 11).
- Tag **v0.8.0** after the starter kit.

### Phase 9 — Navigation (M)
- Menus + Menu Builder island, item types, visibility, mega-menu block trees, header/footer global blocks, site-wide default header/footer, mobile offcanvas/accordion, accessible disclosure navigation. Starter-kit header and footer global blocks (deferred from the Phase 8 starter kit).

### Phase 10 — External Providers (L)
- Re-verify Meta requirements. SafeHttpClient + SafeXml (if not already built for Phase 6 asset downloads), provider framework, feed provider (JSON Feed first, RSS/Atom fallback), feed block, outbound JSON Feeds (D-15), Facebook provider (connect, token storage, image caching), gallery providers (Flickr/YouTube, if confirmed in scope), sync scheduling, admin status screens.
- Tests: the SSRF suite, the XML attack suite, provider sync with faked HTTP, stale-while-error behaviour, JSON Feed 1.1 validity and leak tests.

### Phase 11 — Public React SPA (L)
- Homepage from blocks, generic page route, archives and details for all types, search UI, account area (testimonial submission), SEO head sync, JSON-LD, sitemap, galleries + lightbox, external content blocks, loading/error/empty states, accessibility pass, prefetching, code-splitting review, OpenAPI for the public API. A contact form block (spam-protected, e-mail delivery), then used by the starter kit's Contact page.

### Phase 12 — Security / Testing audit (M)
- Audit against SECURITY-ARCHITECTURE.md: authentication, authorization matrix, uploads, JSON import, SSRF, XML, XSS (payload corpus through every input), API leaks, providers, headers/CSP (nonce/hash styles), dependency audit. Playwright E2E for the critical paths.

### Phase 13 — Performance (M)
- Measure against budgets (CMS-ARCHITECTURE.md §26): query counts per endpoint, slow query log, index review, payload cache hit rates, bundle analysis, image audit, Lighthouse/Web Vitals on staging, icon font vs sprite decision, FULLTEXT relevance for Bangla.

### Phase 14 — Production Preparation (M)
- Release scripts (PowerShell + Bash), final deployment docs, environment docs, backup/restore (including a drill), queue/scheduler docs, security checklist, **administrator guide**, **developer guide**, ZIP release procedure, first staging → production run.

---

## 3. Phase Completion Report template

Every phase ends with this report (added to `docs/phase-reports/PHASE-<n>-REPORT.md`):

```
PHASE:
STATUS:                 (Complete / Complete with known issues / Incomplete — never "complete" without passing tests)
IMPLEMENTED:
FILES CREATED:
FILES MODIFIED:
DATABASE CHANGES:
API CHANGES:
UI CHANGES:
TESTS:                  (command + counts + failures)
BUILD:                  (npm run build output summary, bundle sizes)
SECURITY CHECK:         (checklist from SECURITY-ARCHITECTURE.md §15)
PERFORMANCE CHECK:
DEPLOYMENT IMPACT:      (new env keys, commands, cron/queue changes)
KNOWN ISSUES:
ARCHITECTURAL DECISIONS:
NEXT PHASE:
```

---

## 4. Ways of working

| Topic | Agreement |
|---|---|
| Branching | `main` (always releasable) ← `phase/<n>-<name>` branches ← short feature branches. Each phase merges after review. Tag `v0.<phase>.0` per phase. |
| Commits | Small and descriptive. AI-assisted changes are reviewed by a team member before merge (master prompt §3). |
| Code review | At least one reviewer other than the author. The security checklist applies to every PR. |
| Definition of done | Tests written and green, build green, docs updated, no new warnings in `pacms:doctor`, the phase report updated. |
| Coding standards | PSR-12 via **Laravel Pint**, ESLint + Prettier for JS, and Larastan (PHPStan) level 6 from Phase 2, raised over time. |
| Documentation | Architecture changes update the relevant `docs/*.md` in the same PR. Decisions are appended to the decision log (PHASE-1-ARCHITECTURE-REVIEW.md §5 continues as `docs/DECISIONS.md` from Phase 2). |
