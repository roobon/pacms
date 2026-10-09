# Probha Aurora CMS — Phase 1 Architecture Review

| | |
|---|---|
| Phase | 1 — Architecture Review |
| Date | 2026-09-24 |
| Prepared for | Syed Ziaul Habib (Team Lead), Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). **All content requires human review and approval** (master prompt §3). |
| Result | Architecture documented, with 14 decisions awaiting approval and 12 open questions. **Phase 2 has not been started.** |

---

## 1. Summary

The master prompt describes a CMS whose core is a nested visual block engine. Around it sit relational content modules, a moderated testimonial flow, external providers, a public React SPA and an AI-compatible JSON interchange format. Phase 1 turns that into a concrete architecture across nine documents.

The most important design choices:

1. **One Laravel project, one domain, three surfaces**: the Blade admin with React islands, the `/api/v1` JSON API, and the SPA. It deploys as one ZIP.
2. **A "server-assisted SPA shell"** returns correct HTTP status codes and server-rendered SEO/Open Graph tags for every URL, plus the initial page data. This makes the React SPA shareable and crawlable **without SSR**.
3. **The block tree is an adjacency list** in one `blocks` table shared by every owner (pages, news, global blocks, templates, custom block types, mega menus). Published output is an immutable **snapshot** in the same JSON format as import and export, so one serialiser covers publish, revisions, preview and export.
4. **Static, dynamic and external content converge into one normalized Item shape.** Display modes and card templates never know where content came from.
5. **Custom blocks are fields plus a bound block structure**, with declarative bindings and no template language. There is nothing to execute.
6. **Design tokens are runtime CSS variables** mapped onto Bootstrap 5.3's own variables, so rebranding needs no rebuild.
7. **One provider framework** (RSS, Facebook, Flickr, YouTube…) syncs through queued jobs into local tables. Visitors never trigger outbound requests.
8. **Security by construction**: one SSRF-guarded HTTP client, DTD rejection for XML, sanitise-on-write for HTML, tokens or validated literals for styles, and allowlisted API Resources.

---

## 2. Repository inspection (master prompt §54, step 1–2)

The working directory `D:\laragon\www\PACMS` was **empty**. There was no application code and no Git repository, so this is a greenfield project.

| Item | Finding |
|---|---|
| Laravel version | none installed. Spec: 12. Current: **13.33** (see D-01) |
| PHP version | **8.3.33** (Laragon, `D:\laragon\bin\php\php-8.3.33-Win32-vs16-x64`). *Not on the PATH of a normal shell; Laragon's terminal has it.* |
| PHP extensions | bcmath, ctype, curl, dom, exif, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, sodium, xml, xmlreader, xmlwriter, zip, and more. All required extensions are present. `upload_max_filesize`/`post_max_size` = 2G, `memory_limit` = 512M (local). |
| React / Vite versions | none installed. Current: React 19.3, Vite 8.3, React Router 8.4, Bootstrap 5.3.8, Bootstrap Icons 1.13.1, TanStack Query 5.103 |
| Node.js | **PATH has Node v21.0.0, which is EOL and unsupported by Vite 8** (needs ≥ 20.19 or ≥ 22.12). Laragon ships **Node v22.23** at `D:\laragon\bin\nodejs\node-v22`, so use that. |
| npm / Git | npm 10.2.0 · Git 2.41.0 |
| MySQL | **8.4.3** (Laragon). Also available: Apache 2.4.68, Nginx 1.30.4, Redis 5.0.14 (Windows port, old; not needed), Mailpit (local mail testing), HeidiSQL/phpMyAdmin |
| Installed packages, DB config, routes, auth, UI, API, migrations, models, controllers, React components, services, configuration | **none**, because the directory was empty |
| Conflicts | None in code. One environment conflict: Node v21 on PATH (above). |
| Reusable code | None in this directory. |
| Missing architecture | Everything, which Phase 1 now covers. |

Package compatibility notes (checked on Packagist, 2026-09-24):
- `pestphp/pest` 5.x and `symfony/html-sanitizer` 8.x need PHP 8.4, so with PHP 8.3 we use **Pest 4.7** and **html-sanitizer 7.4 (LTS)**.
- `spatie/laravel-permission` 8.3 supports Laravel 12 and 13 and needs PHP ≥ 8.3.
- Sanctum 4.3 and Fortify 1.40 support Laravel 11–13.

---

## 3. Deliverables index (A–AC)

| Ref | Deliverable | Location |
|---|---|---|
| A | Architecture overview | CMS-ARCHITECTURE.md §1–2 |
| B | Database ERD / table plan | DATABASE-ARCHITECTURE.md |
| C | Nested Block Engine | CMS-ARCHITECTURE.md §5 · CMS-BLOCK-SCHEMA.md §2, §5 |
| D | Static / Dynamic / External | CMS-ARCHITECTURE.md §6 · CMS-BLOCK-SCHEMA.md §6 |
| E | Display modes | CMS-ARCHITECTURE.md §7 · CMS-BLOCK-SCHEMA.md §11 |
| F | Custom Block Builder | CMS-ARCHITECTURE.md §11 · CMS-BLOCK-SCHEMA.md §10 |
| G | Templates / Global Blocks | CMS-ARCHITECTURE.md §12 |
| H | Content / Layout / Style / Advanced | CMS-ARCHITECTURE.md §8 · CMS-BLOCK-SCHEMA.md §12–15 |
| I | Responsive system | CMS-ARCHITECTURE.md §9 · CMS-BLOCK-SCHEMA.md §14 |
| J | Design tokens | UI-DESIGN-SYSTEM.md §2–3 · CMS-ARCHITECTURE.md §10 |
| K | Admin UI architecture | CMS-ARCHITECTURE.md §23 · UI-DESIGN-SYSTEM.md §8 |
| L | Public React SPA architecture | CMS-ARCHITECTURE.md §2.3, §24 |
| M | Menu / dropdown / mega menu | CMS-ARCHITECTURE.md §13 |
| N | Authentication / Authorization | SECURITY-ARCHITECTURE.md §2–3 |
| O | External provider framework | CMS-ARCHITECTURE.md §15 |
| P | RSS architecture | CMS-ARCHITECTURE.md §16 · SECURITY-ARCHITECTURE.md §9 |
| Q | Facebook / Meta | CMS-ARCHITECTURE.md §17 |
| R | Testimonials / moderation | CMS-ARCHITECTURE.md §18 |
| S | Media coverage | CMS-ARCHITECTURE.md §19 |
| T | Gallery / providers | CMS-ARCHITECTURE.md §20 |
| U | AI JSON schema | CMS-BLOCK-SCHEMA.md |
| V | JSON import / export | CMS-BLOCK-SCHEMA.md §16–18 |
| W | Laravel architecture | CMS-ARCHITECTURE.md §25 |
| X | API architecture | API-ARCHITECTURE.md |
| Y | Security architecture | SECURITY-ARCHITECTURE.md |
| Z | Performance architecture | CMS-ARCHITECTURE.md §26 |
| AA | Deployment architecture | DEPLOYMENT-ARCHITECTURE.md |
| AB | Roadmap | DEVELOPMENT-ROADMAP.md |
| AC | Risks and trade-offs | this document §7–8 |

The complete master prompt is saved in [MASTER-PROMPT.md](MASTER-PROMPT.md) as the reference specification.

---

## 4. Architecture review findings (master prompt §54, step 28)

### 4.1 Contradictions and ambiguities in the master prompt, and how they are resolved

| # | Finding | Resolution |
|---|---|---|
| C-01 | Requires **Laravel 12** but also a system that "evolves for many years". Laravel 12's bug fixes ended 2026-08-13 and security fixes end 2027-02-24, before the CMS will likely be finished. | Recommend Laravel 13 (**D-01**). The architecture is the same either way. |
| C-02 | §11 says *don't* create separate block types per presentation, but §9 lists "Latest News", "Featured News", "News by Category" and "Upcoming Events" as separate blocks. | One `news` / `events` type. The listed variants are **presets** (saved source configurations). |
| C-03 | Team, Partners, Programs, Projects, Publications and Testimonials appear in both "Organizational" and "Dynamic" blocks. | **One block type each**, with static and dynamic source modes. |
| C-04 | "Lightbox" is listed as a gallery display mode, but it is a behaviour that combines with grid, masonry and others. | `display.lightbox: true` flag. |
| C-05 | The candidate tables contain parallel systems: `block_revisions` vs `revisions`, `rss_sources`/`social_accounts`/`gallery_sources`, and six `*_categories`/`*_tags` tables. | Unified into `revisions`, `external_*` and `terms`/`termables` (DATABASE-ARCHITECTURE.md §2). |
| C-06 | `GET /api/media` (public) conflicts with "public APIs expose only approved/published/public" because the library holds drafts' and private files. | No public media listing. Media is exposed only through published content (**D-08**). |
| C-07 | `GET /api/external/facebook/{page}` lets the client choose a page ID, which contradicts "React must never fetch arbitrary sources". | `GET /api/v1/external/{source-slug}` accepts only admin-configured sources. |
| C-08 | "Performance-focused React SPA that remains SEO-ready" without SSR. Social crawlers don't execute JS, and SPAs return 200 for missing pages. | **Server-assisted shell** (CMS-ARCHITECTURE.md §2.3, **D-13**). |
| C-09 | Phase order: Pages (3) need media (7). Dynamic blocks (4) need content types (8). The builder's live preview (5) needs the public block renderer (11). | Roadmap adjustments (**D-04**). |
| C-10 | Accordion is shown as nested child blocks and FAQ as repeater data. Two mechanisms without a rule risk duplicate systems. | Clear rule: children for arbitrary content, repeaters for fixed fields (CMS-ARCHITECTURE.md §5.3). |
| C-11 | Team "email where appropriate" versus "never expose internal user information". | Per-person `show_email` / `show_phone`, off by default. |
| C-12 | RSS "cache duration" is configured on both the source and the RSS block, so two freshness settings could conflict. | Freshness is set once, on the source (its sync interval). |
| C-13 | "Image + Text / Text + Image" are listed as block types, but they are layouts. | **Templates** built from `columns`. |
| C-14 | Testimonials have both "Approved" and "Published" with no stated difference. | Approved = accepted, not visible. Published = visible (CMS-ARCHITECTURE.md §18). |
| C-15 | "Header and Footer are Global Blocks updating everywhere", but landing pages often need none. | Site defaults plus per-page override (none or alternative). |
| C-16 | Workflow states (Draft → Review → Approved → Published) conflict for listed content, where edits to a live item would need a draft copy. | Two publishing modes (staged vs direct, **D-03**) with a documented upgrade path. |

### 4.2 Unnecessary complexity deliberately avoided

- No SSR/SSG server, no Node on production, no GraphQL, and no separate SPA repository or domain.
- No Elasticsearch/Meilisearch (MySQL FULLTEXT through a unified `search_documents` table), no mandatory Redis or Supervisor, and no Docker.
- No template language for custom blocks. Bindings are declarative, not expressions.
- No `react-bootstrap` or Helmet: Bootstrap's own ES-module plugins plus React 19's native `<title>`/`<meta>` support.
- No web installer. CLI commands (`pacms:create-admin`, `pacms:doctor`) instead.
- No generic "everything is an entity" EAV model. Content types are real tables with shared traits.

### 4.3 Duplicate systems removed

| Instead of… | One system |
|---|---|
| Separate editors for pages, templates, global blocks, custom types and mega menus | One builder over one `blocks` table |
| Separate field systems for core blocks, custom blocks and provider config | One field-definition format → forms, validation and JSON Schema |
| Per-source rendering for galleries, RSS and collections | Normalized Items → display modes → cards |
| Per-type category/tag tables | `terms` / `termables` |
| Per-provider tables | `external_*` tables |
| A style compiler in both PHP and JS | JS only. PHP validates values. |
| Separate revision, publish and export formats | Schema 1.0 node format everywhere |

### 4.4 Scalability

- Expected volume for an organisational site is thousands of content items, tens of thousands of media files and dozens of editors. MySQL with the planned indexes handles this comfortably on one server.
- Hot paths are cacheable (the published payload with cache versions), and heavy work is queued.
- **Scale-out path without redesign**: move cache and sessions to Redis, media to S3-compatible storage (Laravel `Storage` disks are already abstracted), add a CDN in front of `/build` and `/storage`, and use persistent queue workers. None of these need architectural change.

### 4.5 Security risks identified (and their controls)

SSRF (RSS, assets, source checks) → SafeHttpClient. XML attacks → DTD rejection. Stored XSS (rich text, imports, feeds, custom CSS and attributes) → sanitise on write, allowlists, CSP. Draft leakage → published scopes, 404s, signed and authenticated preview, leak tests. Upload attacks → allowlist, MIME detection, re-encoding, no execution in storage. Credential exposure → `.env`, encrypted tokens, redaction. Privilege escalation → server-side policies and "cannot grant what you don't hold". Details are in SECURITY-ARCHITECTURE.md.

### 4.6 Performance risks identified

Page payloads with many dynamic blocks → one server-side resolution per page, cached with version keys. N+1 in card rendering → eager loading declared per source, and lazy loading disabled in tests. Large builder trees → normalized store with selectors. Bundle growth → per-block lazy chunks and a JS budget. Images → responsive WebP variants. External latency → never on request path.

### 4.7 Maintainability risks identified

The block engine and builder are the most complex parts and are isolated in `app/Cms/*` and `resources/js/blocks|admin/islands`. The field-definition system prevents per-block form code. The Content Type Registry makes new types additive. Schema versioning with migrators protects stored JSON. Decision records live alongside the code.

---

## 5. Decisions requiring team approval

### 5.0 Team answers (2026-09-24)

| Item | Team decision | Consequence |
|---|---|---|
| D-01 Laravel version | **Laravel 12** (as in the master prompt; the recommendation was 13) | Build on `laravel/framework ^12.0`. Security support ends 2027-02-24, so **plan a Laravel 13 upgrade soon after launch** (risk R-01 accepted). Keep code free of deprecated APIs so the upgrade stays small. |
| D-05 React language | **JavaScript** (with JSDoc typedefs) | `resources/js/**` is `.js`/`.jsx`. The block schema types are documented as JSDoc `@typedef`s in `resources/js/blocks/types.js`, and ESLint enforces them. |
| Q-02 Languages | **English only** | No multilingual data model. Bangla text may still appear inside content (fonts include Noto Sans Bengali). Slugs are Latin (`Str::slug`), and editors can edit them manually. R-14 is closed. |
| Q-03 Sites | **One site per installation** | Current design unchanged. Several websites = several installations. |

**Please mark each Approved / Changed / Rejected.** Items marked ★ are needed before Phase 2 starts.

| ID | Decision | Recommendation | Alternative |
|---|---|---|---|
| **D-01** ★ | Laravel version | **Laravel 13** (current, supported to about Q3 2027 for bugs and Q1 2028 for security; PHP 8.3+). Deviates from the master prompt's "Laravel 12". | Laravel 12 as written. It needs a major upgrade soon after launch, with security support ending 2027-02-24. |
| **D-02** ★ | PHP target | Code for 8.3 (local Laragon). Production **8.4** if the host offers it. Composer platform pinned to the production version. | 8.3 everywhere (active support ended 2025-12-31, security until end of 2027) |
| **D-03** | Publishing modes | Staged (working copy + published snapshot) for pages, global blocks, templates and custom types. Direct for listed content modules. | Staged for everything (more complex, with draft copies of rows). Direct for everything (unsafe for pages). |
| **D-04** ★ | Roadmap adjustments | Media core → Phase 3. Shared block renderer, preview and a minimal News type → Phase 4. | Original order (blocks features until Phases 7, 8 and 11) |
| **D-05** ★ | JavaScript or TypeScript for React | **TypeScript** for `resources/js/blocks` and the builder islands (typed block schema, safer refactoring), with `strict` phased in. JSDoc is acceptable if the team prefers plain JS. | Plain JS with JSDoc typedefs |
| **D-06** ★ | Auth backend | Add **Laravel Fortify** (first-party, headless) for registration, reset, verification and 2FA | Hand-written auth controllers |
| **D-07** ★ | Roles and permissions | Matrix in SECURITY-ARCHITECTURE.md §3.3. 2FA mandatory for Super Admin, Administrator and Editor. | Adjust per organisation |
| **D-08** | Public API shape | `/api/v1` prefix. No public media listing. External content by source slug. | Unversioned paths as in the master prompt |
| **D-09** | Caching | Cache-version counters (works with file or DB cache on any host). Redis optional. | Require Redis (tags) |
| **D-10** | SVG uploads | Disabled by default. If enabled, sanitised and permission-gated. | Allow for all uploaders (XSS risk) |
| **D-11** | Table consolidation | `blocks`, `revisions`, `terms`, `external_*` as in DATABASE-ARCHITECTURE.md §2 | Candidate list as written |
| **D-12** | Third-party libraries | PHP: Fortify, Sanctum, Spatie Permission, symfony/html-sanitizer 7.4, sabberworm/php-css-parser, intervention/image, opis/json-schema, simplepie (confirmed in Phase 10), spatie/laravel-backup, enshrined/svg-sanitize (only if D-10 enables SVG), Pest 4, Pint, Larastan. JS: React 19, React Router, TanStack Query, axios, Bootstrap 5.3 + Icons, **Zustand** (builder state), **dnd-kit** (drag and drop), **TipTap** (rich-text editor, MIT core), Vitest + Testing Library. | Each library can be challenged individually |
| **D-13** | SEO approach | Server-assisted SPA shell (status codes, meta, OG, JSON-LD, initial data) | Full SSR (adds a Node server) or a pure SPA (broken social previews) |
| **D-14** | Documentation location | `docs/` in the repository, with decisions continued in `docs/DECISIONS.md` | Project root |
| **D-15** | Feed format for sharing content | **JSON Feed 1.1** out (`/feed.json`, `/feed/{type}.json`); read JSON Feed first, RSS/Atom as fallback. Decided by the team lead, 2026-10-09 | RSS 2.0 out (the original plan) |

---

## 6. Open questions for the team

| ID | Question | Why it matters | Needed by |
|---|---|---|---|
| Q-01 | **Hosting**: VPS or CyberPanel? Production PHP version? SSH and cron available? Redis available? | Affects D-02, queue and cache setup, and deployment docs | ★ Phase 2 |
| Q-02 | **Languages**: English only, Bangla only, or **bilingual Bangla + English**? | Multilingual content is **very expensive to retrofit**: it affects every content table, slugs, routes, search and the JSON schema. It is not in the master prompt, so it must be decided now. Also: Bangla titles produce empty slugs with Laravel's default `Str::slug`, so we need either Unicode slugs or manual Latin slugs. | ★ Phase 2 |
| Q-03 | **One website or several?** Will one installation serve one organisation site, or several related sites (for example separate program sites)? | Multi-site would change the data model (site scoping on every table). The current design assumes **one site per installation** (several sites = several installations). | ★ Phase 2 |
| Q-04 | Brand assets: logo files, official colours and fonts | Replaces the proposed "Aurora" default tokens | Phase 2 |
| Q-05 | Facebook: does the organisation own the Page and have Business Manager access? Is a system-user token possible? | Determines the token type and App Review needs | Phase 10 |
| Q-06 | Which gallery providers are in v1: Flickr, YouTube, Media RSS? | Phase 10 scope | Phase 8 |
| Q-07 | Email/SMTP provider for verification and password reset | Registration and testimonials depend on it | Phase 2 (local uses Mailpit) |
| Q-08 | Analytics and cookie consent? Facebook Page Plugin embed wanted? | Privacy and CSP | Phase 11 |
| Q-09 | Public registration: only for testimonials, or future member features? | Scope of the account area | Phase 8 |
| Q-10 | Is there existing website content to migrate (WordPress or other)? How much? | A migration importer would be an extra phase or task | Phase 8 |
| Q-11 | Git hosting (for example private GitHub) and who reviews merges | Workflow in DEVELOPMENT-ROADMAP.md §4 | ★ Phase 2 |
| Q-12 | Who confirms legal rights for media-coverage archives? | `archive_rights_confirmed` workflow owner | Phase 8 |

---

## 7. Risks (AC)

| ID | Risk | Likelihood / impact | Mitigation |
|---|---|---|---|
| R-01 | Laravel 12 support ends during the project | High / High | D-01 |
| R-02 | Builder scope creep: the visual builder can absorb unlimited effort | High / High | Fixed v1 block library. A basic builder in Phase 4, full UX in Phase 5. Features beyond the documents need a new decision entry. |
| R-03 | Custom blocks can't express some designs without code | Medium / Medium | Custom blocks are compositions of core blocks. Genuinely new visuals become new **core** block types (PHP class + React component), a normal dev task. |
| R-04 | Shared-hosting limits: cron-driven queue adds about 1 min latency, no Redis, GD memory limits for large images | Medium / Medium | Size and megapixel caps, queued variants, optional Supervisor/Redis. `pacms:doctor` warns. |
| R-05 | SEO: crawlers that don't run JS see meta but not body text | Low / Medium | Google renders JS, and the shell provides meta and JSON-LD. Phase 13 can add a lightweight server-rendered text fallback from the snapshot if needed. |
| R-06 | Meta API changes, token expiry or App Review requirements | Medium / Low | Version pinned, provider isolated, expiry alerts, cached fallback, re-verification at Phase 10 |
| R-07 | Direct publishing: edits to a published news item go live without review | Medium / Low | Only publishers can edit published items. Revisions allow rollback. Upgrade path is draft copies. |
| R-08 | Revision and snapshot storage growth | Medium / Low | Retention policy and prune job |
| R-09 | JSON schema evolution breaks stored or imported JSON | Low / High | Versioning policy, migrators, lazy snapshot migration, round-trip tests |
| R-10 | MySQL FULLTEXT relevance for Bangla or mixed text | Medium / Low | Evaluate in Phase 13 (tokenizer settings, n-gram parser, or an external engine later behind `SearchService`) |
| R-11 | Legal and privacy: media archives, testimonial consent, personal data | Medium / High | Rights confirmation gating, consent records, private fields, IP retention |
| R-12 | Frontend weight (icon font, Bootstrap) | Medium / Low | JS budget, Bootstrap subset, icon sprite evaluation |
| R-13 | Team capacity and review load for AI-assisted code | Medium / Medium | Small PRs, one phase at a time, checklist-based reviews, tests as the acceptance gate |
| R-14 | Non-ASCII slugs (Bangla) | High if bilingual / Medium | Decide with Q-02 |
| R-15 | CSP needs `style-src 'unsafe-inline'` for compiled block CSS | Low / Low | Values are generated from validated input only. Nonce or hash in Phase 12. |

---

## 8. Key trade-offs

| Choice | Gains | Costs |
|---|---|---|
| Adjacency list + snapshots | Cheap edits and moves, fast public reads, one format for revisions and export | Snapshot and working copy must be kept conceptually separate. Two representations. |
| Same-domain monolith | One ZIP, no CORS, simple auth, SEO shell | Admin and public site deploy together |
| JS-only style compiler | Exact preview parity, no duplicated logic | Styles apply after JS loads. Mitigated by the SPA shell's critical CSS and skeletons. |
| Cache-version counters | Works on any cache driver | Slightly more cache reads than tag-based invalidation |
| Declarative custom-block bindings | Safe, portable, AI-friendly | Less expressive than a template language (by design) |
| Unified taxonomy / external tables | Fewer tables, consistent features | Polymorphic pivots rely on app-level integrity instead of FKs |
| Direct publishing for modules | Simple, and listings are always consistent | No staged review of edits to already-published items (v1) |

---

## 9. Phase Completion Report

```
PHASE:                  1 — Architecture Review
STATUS:                 Complete (documentation only), pending team review and approval of §5 decisions

IMPLEMENTED:            Architecture design only — no application code, by design.
                        Environment inspection; verification of current Laravel, Meta Graph API
                        and package versions; WCAG contrast calculation for the proposed palette.

FILES CREATED:          docs/CMS-ARCHITECTURE.md
                        docs/CMS-BLOCK-SCHEMA.md
                        docs/DATABASE-ARCHITECTURE.md
                        docs/API-ARCHITECTURE.md
                        docs/SECURITY-ARCHITECTURE.md
                        docs/UI-DESIGN-SYSTEM.md
                        docs/DEPLOYMENT-ARCHITECTURE.md
                        docs/DEVELOPMENT-ROADMAP.md
                        docs/PHASE-1-ARCHITECTURE-REVIEW.md
                        docs/MASTER-PROMPT.md (reference copy of the specification)
                        docs/README.md (index)

FILES MODIFIED:         none (empty directory)
DATABASE CHANGES:       none (designed in DATABASE-ARCHITECTURE.md)
API CHANGES:            none (designed in API-ARCHITECTURE.md)
UI CHANGES:             none (designed in UI-DESIGN-SYSTEM.md)
TESTS:                  n/a — no code. Test strategy defined (CMS-ARCHITECTURE.md §28).
BUILD:                  n/a
SECURITY CHECK:         Threat model and controls documented (SECURITY-ARCHITECTURE.md)
PERFORMANCE CHECK:      Budgets and design documented (CMS-ARCHITECTURE.md §26)
DEPLOYMENT IMPACT:      ZIP-based model documented. Local Node on PATH (v21) must be switched to
                        Laragon Node 22 before Phase 2.
KNOWN ISSUES:           Laravel version (D-01) and multilingual scope (Q-02) are unresolved and
                        block Phase 2. Meta App Review needs re-verification at Phase 10.
ARCHITECTURAL DECISIONS: D-01 … D-14 (§5), all awaiting approval
NEXT PHASE:             Phase 2 — Foundation. NOT started. Waiting for architecture review/approval.
```
