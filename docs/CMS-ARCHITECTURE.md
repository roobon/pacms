# Probha Aurora CMS — Architecture & Technical Specification

| | |
|---|---|
| Product | Probha Aurora CMS (internal short name **PACMS**) |
| Document | CMS-ARCHITECTURE.md — system architecture (Phase 1) |
| Status | **Draft for team review** — nothing in this document is implemented yet |
| Date | 2026-09-24 |
| Team | Syed Ziaul Habib (Team Lead / Lead Developer), Hasibul Hasan, Khandoker Humayoun Kobir |
| Related | [CMS-BLOCK-SCHEMA.md](CMS-BLOCK-SCHEMA.md) · [DATABASE-ARCHITECTURE.md](DATABASE-ARCHITECTURE.md) · [API-ARCHITECTURE.md](API-ARCHITECTURE.md) · [SECURITY-ARCHITECTURE.md](SECURITY-ARCHITECTURE.md) · [UI-DESIGN-SYSTEM.md](UI-DESIGN-SYSTEM.md) · [DEPLOYMENT-ARCHITECTURE.md](DEPLOYMENT-ARCHITECTURE.md) · [DEVELOPMENT-ROADMAP.md](DEVELOPMENT-ROADMAP.md) · [PHASE-1-ARCHITECTURE-REVIEW.md](PHASE-1-ARCHITECTURE-REVIEW.md) |

Decisions marked **D-xx** are listed in [PHASE-1-ARCHITECTURE-REVIEW.md §5](PHASE-1-ARCHITECTURE-REVIEW.md#5-decisions-requiring-team-approval) and need team approval before Phase 2.

---

## Contents

1. [Architecture overview](#1-architecture-overview) (A)
2. [Application topology — one Laravel project, three surfaces](#2-application-topology)
3. [Content model and the Content Type Registry](#3-content-model-and-the-content-type-registry)
4. [Publishing, workflow, preview and revisions](#4-publishing-workflow-preview-and-revisions)
5. [Nested Block Engine](#5-nested-block-engine) (C)
6. [Content Sources — static / dynamic / external](#6-content-sources) (D)
7. [Display Modes](#7-display-modes) (E)
8. [Content / Layout / Style / Advanced](#8-content--layout--style--advanced) (H)
9. [Responsive system](#9-responsive-system) (I)
10. [Design tokens (runtime)](#10-design-tokens-runtime) (J — details in UI-DESIGN-SYSTEM.md)
11. [Custom Block Builder](#11-custom-block-builder) (F)
12. [Templates and Global Blocks](#12-templates-and-global-blocks) (G)
13. [Header, Footer and Menu Builder](#13-header-footer-and-menu-builder) (M)
14. [Media Library](#14-media-library)
15. [External Provider Framework](#15-external-provider-framework) (O)
16. [Feeds — distribution and consumption](#16-feeds-json-feed) (P)
17. [Facebook / Meta integration](#17-facebook--meta) (Q)
18. [Testimonials and moderation](#18-testimonials-and-moderation) (R)
19. [Media Coverage](#19-media-coverage) (S)
20. [Galleries and gallery providers](#20-galleries) (T)
21. [Search](#21-search)
22. [SEO](#22-seo)
23. [Admin UI architecture](#23-admin-ui-architecture) (K)
24. [Public React SPA architecture](#24-public-react-spa-architecture) (L)
25. [Laravel application architecture](#25-laravel-application-architecture) (W)
26. [Performance architecture](#26-performance-architecture) (Z)
27. [Activity logging, error handling, backups](#27-activity-logging-error-handling-backups)
28. [Testing strategy](#28-testing-strategy)

Authentication/authorization (N) and security (Y) are in SECURITY-ARCHITECTURE.md. The AI JSON schema and import/export (U, V) are in CMS-BLOCK-SCHEMA.md. The database (B), API (X), deployment (AA) and roadmap (AB) each have their own document. Risks and trade-offs (AC) are in the review.

---

## 1. Architecture overview

### 1.1 What PACMS is

PACMS is an organisational content hub. Editors manage structured content (news, events, projects, programs, publications, team, partners, testimonials, media coverage, galleries) and assemble pages visually from **nested blocks**. It publishes to a public React website, JSON feeds and a JSON interchange format that AI tools can generate.

### 1.2 Guiding rules (from the master prompt, made concrete)

| Principle | How the architecture enforces it |
|---|---|
| Content separate from presentation | Every block is stored as `source` + `content` + `display` + `layout` + `style` + `responsive` + `advanced`, as separate keys and never mixed (§5, §8). |
| Laravel is authoritative | All data, validation, authorization, publishing and external fetching happen in Laravel. React only renders what the API returns, and the builder only edits drafts through validated admin endpoints. |
| Nothing organisational is hard-coded | Homepage, header, footer, menus, design tokens, settings and content are all database-driven. Seeders only create neutral placeholders. |
| Unpublished content is never public | Public API reads come from *published snapshots* or rows under a `published()` scope. Preview uses a separate authenticated, signed channel (§4). |
| External providers never run on visitor requests | Providers sync through queued jobs into local tables. Visitors only read local, cached data (§15). |
| Imported JSON is never executed | JSON is data validated against a versioned schema and rendered by fixed components (CMS-BLOCK-SCHEMA.md). |
| Simple deployment | One standard Laravel project, ZIP-deployable, no Docker, no Node on the server (DEPLOYMENT-ARCHITECTURE.md). |

### 1.3 System diagram

```
                              PROBHA AURORA CMS  (one Laravel project)
 ┌──────────────────────────────────────────────────────────────────────────────────────┐
 │                                                                                      │
 │   /admin/*  (Blade + Bootstrap)        /api/v1/*  (JSON)          /*  (SPA shell)    │
 │   ├─ CRUD screens (server-rendered)    ├─ Public read API         ├─ Server-injected │
 │   └─ React islands:                    ├─ Auth'd user API         │   SEO meta + OG   │
 │       Block Builder, Media Picker,     │   (testimonial submit)   ├─ Initial payload │
 │       Menu Builder, Repeater editor,   └─ Preview API (signed)    └─ React SPA boots │
 │       JSON Import/Export                                                            │
 │         │  /admin/api/* (session+CSRF)                                               │
 │         ▼                                                                            │
 │   ┌──────────────────────────── Application core ─────────────────────────────────┐  │
 │   │ Policies/Gates · Services (Blocks, Publishing, Revisions, Media, Json, Search,│  │
 │   │ Moderation, External/RSS/Facebook, Seo) · Content Type Registry · Block       │  │
 │   │ Registry · Field System · Sanitizers · SafeHttpClient · Activity Logger       │  │
 │   └───────────────────────────────────────────────────────────────────────────────┘  │
 │         │                 │                    │                     │              │
 │      MySQL 8          Storage (public/     Cache (file/db/        Queue (database)   │
 │   (InnoDB, utf8mb4)   private disks)       redis optional)        + Scheduler (cron) │
 └──────────────────────────────────────────────────────────────────────────────────────┘
                                   │ queued jobs only
                  ┌────────────────┼──────────────────┬──────────────────┐
              RSS feeds      Meta Graph API        Flickr / YouTube     Media-coverage
              (inbound)      (org's own Page)      (future)             source checks
```

### 1.4 Technology stack (versions verified 2026-09-24)

| Layer | Choice | Notes |
|---|---|---|
| Framework | **Laravel 12** (team decision D-01, 2026-09-24) | Laravel 12 bug-fix support ended 2026-08-13 and security fixes end 2027-02-24, so a Laravel 13 upgrade is planned after launch. The architecture is identical on both. |
| PHP | 8.3+ (local: Laragon PHP 8.3.33) | Composer `config.platform.php` pinned to the production PHP version so the ZIP's `vendor/` matches the server. Some latest packages need PHP 8.4 (Pest 5, symfony/html-sanitizer 8), so we use the PHP-8.3-compatible majors (Pest 4, html-sanitizer 7.4 LTS). |
| Database | MySQL 8.0+/8.4 LTS (local: 8.4.3), InnoDB, `utf8mb4_unicode_ci` | Full Bangla/Unicode support. |
| Auth | Laravel Sanctum 4, Laravel Fortify 1.x (headless auth backend, 2FA), Spatie laravel-permission 8 | Fortify is added to avoid hand-writing registration, reset, email verification and 2FA (D-06). |
| Admin UI | Blade + Bootstrap 5.3 + Bootstrap Icons 1.13; React 19 islands only where interaction demands it | |
| Public UI | React 19 + React Router + TanStack Query + Axios + Bootstrap 5.3 | React 19 renders `<title>`/`<meta>` natively, so no Helmet library is needed. |
| Build | Vite + `laravel-vite-plugin` | Node **≥ 20.19 or ≥ 22.12** is required. The Node on this machine's PATH is v21, which is **not supported**, so use Laragon's Node 22. |
| Tests | Pest 4 (PHP), Vitest + React Testing Library (JS) | Tests run against a real MySQL test database. |

---

## 2. Application topology

### 2.1 One Laravel project, three surfaces

The admin, API and public SPA live in **one Laravel project on one domain**. The alternative, a separate SPA project on a separate domain, was rejected for these reasons:

- **Deployment stays one ZIP**, as §47 of the master prompt requires.
- **No CORS and no cross-site cookies.** Sanctum's SPA cookie authentication works out of the box on the same domain.
- **The SPA HTML shell is served by Laravel**, which is how we make the SPA SEO-ready without SSR (§2.3).

| Surface | URL space | Rendering | Auth |
|---|---|---|---|
| Admin / CMS | `/admin/*` | Blade (server) + React islands | `web` session guard, CSRF, permission `admin.access` |
| Admin JSON endpoints (for islands) | `/admin/api/*` | JSON | Same session + CSRF (not tokens) |
| Public API | `/api/v1/*` | JSON (API Resources) | Anonymous. Sanctum stateful cookie for registered-user endpoints. |
| Feeds & SEO files | `/feed.json`, `/feed/*.json`, `/sitemap.xml`, `/robots.txt` | JSON/XML/text generated by Laravel, cached | Anonymous |
| Public website | everything else (`/*`) | SPA shell (Blade) + React SPA | Anonymous or registered user |

### 2.2 Vite entry points

```
resources/js/
├── public/main.jsx          → public SPA (entry 1)
├── admin/app.js             → small admin enhancements (entry 2, no React)
├── admin/islands/*.jsx      → one entry per island (builder, media-picker, menu-builder, json-import …)
├── blocks/                  → SHARED block renderer, display modes, style compiler
│                               (used by the public SPA AND the builder preview)
└── shared/                  → API client, field-definition utils, types
resources/scss/
├── public.scss  (Bootstrap subset + PACMS public theme)
└── admin.scss   (Bootstrap subset + PACMS admin theme)
```

Admin Blade pages load `admin/app.js` plus only the islands they use, so an ordinary CRUD screen ships **no React**.

### 2.3 SEO-ready SPA without SSR: the "server-assisted shell"

A plain SPA has two SEO problems. Social crawlers (Facebook, LinkedIn, X, WhatsApp) **do not run JavaScript**, so shared links would show no title or image. And missing pages return HTTP 200 ("soft 404").

PACMS solves both **without SSR**:

```
GET /news/eco-schools-award
  → Laravel SpaController
      1. PathResolver: redirect? → 301
                       page / news / event / … published? → yes
                       nothing → 404 status
      2. Build SEO head (title, description, canonical, OG, Twitter, JSON-LD)
      3. Build initial payload (same JSON the API would return), cached
      4. Return spa.blade.php:
           <head> …server-rendered meta… <link rel=preload …> </head>
           <body><div id="app"></div>
             <script id="pacms-initial" type="application/json">{…}</script>
             <script type="module" src="/build/public-….js"></script>
  → React hydrates its query cache from #pacms-initial (no second request)
  → Client-side navigation afterwards uses /api/v1 and React 19 <title>/<meta>
```

This gives correct status codes, social previews, first paint with no API round trip, and crawlable metadata. It uses one small Blade template, not a rendering server. SSR/SSG stays out of scope unless Phase 13 measurements show a need.

---

## 3. Content model and the Content Type Registry

### 3.1 Content types

| Type | Table | Publishable | Body uses blocks | Public URL (default prefix, configurable) |
|---|---|---|---|---|
| Page | `pages` | staged (§4.2) | yes (whole page) | `/{path}` (hierarchical) |
| News | `news` | direct | yes | `/news/{slug}` |
| Event | `events` | direct | yes | `/events/{slug}` |
| Project | `projects` | direct | yes | `/projects/{slug}` |
| Program | `programs` | direct | yes | `/programs/{slug}` |
| Publication | `publications` | direct | optional (description is rich text) | `/publications/{slug}` |
| Media Coverage | `media_coverage` | direct | no (summary rich text) | `/media-coverage/{slug}` |
| Gallery | `galleries` | direct | no | `/galleries/{slug}` |
| Team member | `team_members` | active/inactive | no | listed via blocks, optional `/team/{slug}` |
| Partner | `partners` | active/inactive | no | listed via blocks |
| Testimonial | `testimonials` | moderated (§18) | no | listed via blocks |

### 3.2 Shared behaviour through small, composable traits

Behaviour shared by content types lives in traits and interfaces, never in copy-pasted code:

| Trait / interface | Responsibility |
|---|---|
| `HasSlug` | Unique slug generation, reserved-word protection, slug history → redirects |
| `Publishable` | `status`, `published_at`, `published()` scope, transitions guarded by the Publishing service |
| `HasBlocks` | Owns a block tree (`blocks.owner_type/owner_id`) |
| `HasRevisions` | Snapshot on save/publish, restore |
| `HasSeo` | One-to-one `seo_metadata` |
| `HasTerms` | Categories/tags through the unified taxonomy (`terms`/`termables`) |
| `HasFeaturedMedia` | `featured_media_id` + alt/caption fallback |
| `Searchable` | Writes to `search_documents` when published, removes when unpublished |
| `HasActivityLog` | Model events → activity log (§27) |

### 3.3 Content Type Registry

`config/pacms.php` registers every content type in one place:

```php
'content_types' => [
    'news' => [
        'model'        => App\Models\News::class,
        'route_prefix' => 'news',
        'resource'     => App\Http\Resources\Public\NewsResource::class,
        'card'         => App\Cms\Cards\NewsCard::class,     // → normalized Item (§6.4)
        'dynamic_source' => App\Cms\Sources\NewsSource::class, // filters/orders allowed in blocks
        'rss'          => true,
        'sitemap'      => true,
        'searchable'   => true,
        'menu_linkable'=> true,
    ],
    // events, projects, programs, publications, media_coverage, galleries, team, partners, testimonials …
],
```

Routing, dynamic blocks, menus (link pickers), RSS, sitemap, search and JSON references all read from this registry. **Adding a content type** means a migration, a model with the traits, a resource, a card, a source and one registry entry. Nothing else in the engine changes.

---

## 4. Publishing, workflow, preview and revisions

### 4.1 Workflow states

```
 Draft ──submit──▶ In Review ──approve──▶ Approved ──publish──▶ Published ──archive──▶ Archived
   ▲                  │                       │                    │
   └──── request changes / reject ◀───────────┘       unpublish ───┘ (→ Draft)
                                                      schedule: publish_at (scheduler publishes)
```

- Transitions run only through `PublishingService::transition($model, $action, $user)`. It checks the policy for the action, validates that the transition is allowed, records a revision and writes the activity log.
- **Authors and contributors** can create drafts and submit them. **Editors** approve and publish. Roles are mapped in SECURITY-ARCHITECTURE.md.
- **Scheduled publishing**: setting `publish_at` and choosing *Schedule* leaves the item Approved. A minutely scheduler task publishes due items.

### 4.2 Two publishing modes (D-03)

Content that is *listed and filtered* (news in a grid, events by date) and content that is *assembled visually* (pages, the header) need different handling. Using one mode for both is either over-complicated or unsafe.

| | **Staged** — pages, global blocks, templates, custom block types | **Direct** — news, events, projects, programs, publications, media coverage, galleries |
|---|---|---|
| Editing storage | Working copy in tables (`pages` row + `blocks` tree) | Row + `blocks` tree |
| What the public sees | The **published snapshot** (`revisions` row referenced by `published_revision_id`) | The row itself once `status = published` |
| Editing a live item | Safe: edits stay in the working copy until *Publish changes*. The UI shows "Unpublished changes". `status` describes the **working copy**, and "live" is separate (a published snapshot exists), so a live page being edited is *Live + Draft* and can go through review again. Any edit resets In review / Approved / Scheduled to Draft. | Saving a published item updates it live. This needs `*.publish` permission. Every save creates a revision. |
| Review of changes to live items | Yes (submit working copy for review) | Not in v1. Upgrade path: Craft-style draft copies (`draft_of_id`) without schema rewrite (risk R-07) |
| Why | Visual pages are edited over days and must not go live half-finished. Snapshots also make public rendering a single-row read. | Listings, filters, RSS, search and sitemaps query columns directly. Snapshot-only storage would force duplicating every listable column. |

### 4.3 Revisions

- One generic `revisions` table (polymorphic) stores a **full snapshot** of the entity: scalar fields, SEO, term IDs and the whole block tree, **serialised in the same node format as the JSON export** (CMS-BLOCK-SCHEMA.md). One serialiser serves publish, revisions, preview and export.
- Revision kinds: `manual` (save), `published`, `autosave` (builder, max 1 per user per item, overwritten), `restore`, `import`.
- **Compare** uses a structural JSON diff in the admin: fields changed, blocks added/removed/moved/edited. **Restore** writes the snapshot back to the working copy and creates a new revision. History is never rewritten.
- **Retention**: keep all `published` revisions and the last 50 others per item. A weekly pruning job enforces this, and the limit is configurable.

### 4.4 Secure preview

1. The editor clicks *Preview*. Laravel checks the `view` policy and issues a **temporary signed URL** (default 30 min) for `/preview/{type}/{id}?revision=working`.
2. The SPA requests `/api/v1/preview/…` with the same signature **and** the user's session (Sanctum stateful). Both are required, so a leaked link alone reveals nothing.
3. Preview responses are sent with `Cache-Control: no-store` and `X-Robots-Tag: noindex, nofollow`, and are never written to the public cache.
4. The builder's live preview is a separate channel that needs no saving. The builder posts the unsaved tree into an iframe running the public SPA in preview mode (§24.6).

---

## 5. Nested Block Engine

The block engine is the heart of the CMS. It has four parts:

```
Block Registry (types, fields, rules) ─▶ Block Tree Store (MySQL adjacency list)
            │                                        │
            ▼                                        ▼
  Validation (server)                  Serializer (tree ⇄ schema JSON)
            │                                        │
            ▼                                        ▼
  Builder (React island) ◀── admin API ──▶ Payload Builder (resolve sources, globals, custom types)
                                                     │
                                                     ▼
                                    Recursive BlockRenderer (shared React)
```

### 5.1 Storage: adjacency list with owner scoping

Each block is one row in `blocks` (full columns in DATABASE-ARCHITECTURE.md):

```
blocks: id, uuid, owner_type, owner_id, parent_id → blocks.id, position,
        block_type_id → block_types.id, global_block_id (nullable), name,
        content, source, display, layout, style, responsive, advanced (JSON),
        is_hidden, created_by, updated_by, timestamps
index (owner_type, owner_id, parent_id, position)
```

**Why an adjacency list instead of a nested set, closure table or one big JSON column:**

| Need | Adjacency list + owner columns |
|---|---|
| Load a whole page tree | **One indexed query** `WHERE owner_type=? AND owner_id=?`, assembled into a tree in memory in O(n). No recursive SQL needed. |
| Move, reorder or nest (drag and drop) | Update `parent_id`/`position` on the affected siblings only. A nested set would rewrite half the table. |
| Per-block edits, revisions and references | Rows are addressable by `uuid`. `content_references` records media, entity and global links per block. |
| Authorization | Checked on the owner (the page), because blocks never exist without an owner. |
| Depth | Limited to **12 levels** by validation, so tree walks stay cheap. |

The **published snapshot** of a staged owner is a JSON document. It is *derived, immutable and read-only*: all editing, querying and authorization happen on the relational rows. This is the "giant JSON" the master prompt warns against, used only where it helps: a fast, cacheable public read.

### 5.2 Block type definition

Core block types are PHP classes, synchronised into `block_types` by `php artisan pacms:blocks:sync`, which runs on every deploy. Custom block types are rows created in the admin (§11). **Both use the same definition shape:**

```php
final class AccordionBlock extends BlockType
{
    public string $slug = 'accordion';
    public string $category = 'content';           // basic|layout|content|media|organization|dynamic|external|custom
    public string $icon = 'bi-list-nested';
    public array  $sourceModes = ['static'];        // subset of static|dynamic|external
    public array  $displayModes = ['accordion'];    // registered display modes (§7)
    public array  $allowedChildren = ['accordion-item'];
    public ?array $allowedParents = null;           // null = anywhere
    public int    $maxChildren = 50;

    public function fields(): array { return [      // field definitions (§11.2)
        Field::text('heading')->label('Heading'),
        Field::richText('description'),
        Field::checkbox('allow_multiple_open')->default(false),
    ]; }
}
```

The registry exposes the definitions to the builder at `/admin/api/block-types`, to validation, to JSON Schema generation and to the renderer (by slug).

### 5.3 Children versus repeaters: one clear rule

Both mechanisms exist, and the rule for which one to use prevents duplicate systems:

| Use **child blocks** when… | Use a **repeater field** when… |
|---|---|
| each item can contain *arbitrary other blocks*, or needs its own layout, style or visibility | each item has a *fixed set of fields* |
| Hero → Container → Heading/Text/Button; Slider → Slide (any blocks); Accordion → Accordion Item (question + blocks as the answer); Tabs → Tab; Columns → Column | FAQ (Q/A), Statistics (number/label/icon), Timeline entries, static Cards, logos in a static Partners block, Social links |

Repeaters can nest (a repeater field inside a repeater item) up to 3 levels. Rows support add, remove, duplicate, reorder and per-field validation.

### 5.4 Recursive rendering

```jsx
// resources/js/blocks/BlockRenderer.jsx  (simplified)
export function BlockRenderer({ node }) {
  if (node.hidden) return null;
  const Component = registry.resolve(node.type) ?? UnknownBlock; // lazy-loaded per type
  return (
    <BlockFrame node={node}>                 {/* id, class b-<uuid>, visibility, animation */}
      <Component node={node}>
        {node.children?.map(child => <BlockRenderer key={child.uuid} node={child} />)}
      </Component>
    </BlockFrame>
  );
}
```

- `registry.resolve` maps a type slug to a lazily imported component, so heavy blocks (carousel, gallery lightbox, map) are separate chunks.
- `UnknownBlock` renders nothing publicly and a warning in preview, so an unknown type can never crash a page.
- Custom block types resolve to one generic `CustomBlock` component (§11.3).
- Each block receives a stable CSS scope `.b-<uuid8>`. Styles are compiled once per page (§8.4).

### 5.5 Block library (v1)

Every type below is **one** block type. Variants are display modes or presets, never separate types (this is how the master prompt's duplicate lists are resolved):

| Category | Types |
|---|---|
| Basic | heading, rich-text, image, video, button, button-group, divider, spacer, icon |
| Layout | section (full width / boxed), container, columns (1–4 columns and grid preset via `layout.columns`; children are `column`), column, flex-row. *Image + Text / Text + Image* are **templates** of columns (§12). |
| Content | hero, banner, slider → slide, carousel, cards (repeater), statistics (repeater), timeline (repeater), accordion → accordion-item, faq (repeater), tabs → tab, quote, cta |
| Media | gallery, video-gallery, document-list |
| Collections (static/dynamic) | news, events, projects, programs, publications, team, partners, testimonials, media-coverage, galleries |
| External | rss-feed, facebook-feed (with future providers through the same `external` source) |
| Structural | global-ref (renders a Global Block), custom (instances of custom types) |

"Latest News", "Featured News", "News by Category" and "Upcoming Events" are **presets**: named default `source` configurations shown in the block palette under the collection's type.

---

## 6. Content Sources

### 6.1 One pipeline for static, dynamic and external content

```
 source.mode = static     source.mode = dynamic          source.mode = external
 content.items[] (repeater)  CMS entity query (whitelisted)  external_items (synced cache)
          │                        │                              │
          └──────────────┬─────────┴──────────────────────────────┘
                         ▼
             normalized Item[]  (§6.4)
                         ▼
          Display Mode (grid, carousel, …)  →  Card template (per item kind)
```

Display modes and card templates **do not know where items came from**. This is what makes "the renderer must not care which provider supplied the content" hold for every collection block, not just galleries.

### 6.2 Capability per block

Each block type declares `sourceModes`. For example, `news` supports static and dynamic, `rss-feed` supports external only, and `heading` supports static only. The builder shows the **[STATIC] [DYNAMIC] [EXTERNAL]** switch only when more than one mode is allowed.

### 6.3 Dynamic sources are whitelisted, never raw queries

Each content type's `DynamicSource` class declares what a block may ask for:

```php
final class NewsSource extends DynamicSource
{
    public array $filters = ['category' => 'term:news_category', 'tag' => 'term:tag',
                             'featured' => 'bool', 'program' => 'entity:programs', 'project' => 'entity:projects'];
    public array $orders  = ['latest' => ['published_at','desc'], 'oldest' => ['published_at','asc'],
                             'title'  => ['title','asc'], 'manual' => null];
    public int   $maxLimit = 24;
}
```

The stored block config is validated against this declaration, so no arbitrary column, operator or SQL fragment can reach the query builder. Every query applies `published()` and eager-loads what the card needs.

### 6.4 Normalized Item shape

```json
{ "key": "news:128", "kind": "news", "title": "…", "url": "/news/…", "external": false,
  "excerpt": "…", "image": { "src": "…", "srcset": "…", "alt": "…", "width": 1600, "height": 900 },
  "date": "2026-09-20T10:00:00+06:00", "meta": { "category": "Environment", "author": "…" } }
```

Card templates read `kind` plus the well-known `meta` keys. For example, team cards show `meta.designation`, event cards show `meta.start_at` and `meta.venue`, and testimonial cards show `meta.quote` and `meta.organization`.

### 6.5 Where resolution happens

Dynamic and external data are resolved **server-side while the page payload is built**, so the SPA makes one request per page:

- Cache keys include **cache-version counters** per dependency group (`news`, `events`, `global_blocks`, `external:source-12`, …). Saving an entity bumps its counter, so stale payloads become unreachable. This works on the file and database cache drivers, which don't support tags, so shared hosting needs no Redis (D-09).
- Paginated or "load more" collection views call `/api/v1/blocks/{uuid}/items?page=2`, which runs the same source resolution for a published block.

---

## 7. Display Modes

Display mode is presentation. It is stored in `display.mode` and never encoded in the block type.

| Mode | Used by | Main options (`display.*`) |
|---|---|---|
| grid | collections, gallery, cards | `columns{desktop,tablet,mobile}`, `gap` |
| cards | collections | as grid + `card_style` (elevated/outline/flat), `image_ratio` |
| list | collections, rss | `show_image`, `image_position` |
| masonry | gallery, testimonials, cards | `columns`, `gap` (CSS columns, no library) |
| justified | gallery | `row_height` (small JS layout, no library) |
| carousel | collections, gallery, testimonials, logos | `per_view{…}`, `autoplay`, `interval`, `loop`, `arrows`, `dots` |
| slider | slider block, gallery | full-width single slide, same options as carousel |
| featured | collections | first item large, rest in `columns` |
| quote-slider | testimonials, quote | carousel variant with quote typography |
| thumbnail-large | gallery | main image + thumbnail strip |
| single | testimonials, quote | one item |
| accordion / tabs | accordion, tabs, faq | `first_open`, `allow_multiple_open` |

- **Lightbox is a behaviour, not a mode.** Set `display.lightbox: true` on any image-based mode. This resolves a contradiction in the master prompt's gallery list.
- Each mode is a registered definition with an options schema that the builder renders and the server validates. A block's `displayModes` lists the modes it supports.
- Carousel, slider, modal, collapse and tab behaviour use **Bootstrap 5.3's own ES-module plugins** wrapped in small React hooks. That gives Bootstrap's accessibility behaviour without extra libraries.

---

## 8. Content / Layout / Style / Advanced

### 8.1 The four editor tabs map to four stored keys

| Tab | Stored in | Examples |
|---|---|---|
| Content | `content` (+ `source`, `display`) | fields defined by the block type, repeaters, source mode and filters, display mode |
| Layout | `layout` | container (`boxed`/`narrow`/`fluid`/`full`), width/max-width, min-height, columns, align, justify, gap, padding, margin, position (`static`/`relative`/`sticky`) |
| Style | `style` | background (none/color/gradient/image/video + overlay), typography (font token, size token, weight, align, colour), border, radius, shadow, animation (fade/slide/zoom, once) |
| Advanced | `advanced` | anchor id, CSS classes, custom attributes (allowlisted), custom CSS (permission-gated), visibility (breakpoints, guests/members) |

### 8.2 Values are tokens or validated literals

Every style and layout value is one of:

- **a token reference**: `{"$token": "color.primary"}` or `{"$token": "space.5"}`, the preferred form, or
- **a validated literal**: colours as `#RRGGBB`/`#RRGGBBAA`/`rgb()`; lengths as a number plus an allowed unit (`px`, `rem`, `em`, `%`, `vh`, `vw`) within bounds; enums from fixed lists.

No free-form CSS strings are allowed outside the permission-gated custom CSS. This makes the style compiler safe by construction (SECURITY-ARCHITECTURE.md §6).

### 8.3 Inheritance and defaults

The value used for a block is resolved in this order, first match wins:

1. the block's own value for the current breakpoint (§9)
2. the block's own desktop value
3. the block type's `defaults`
4. the design-token default for that property (for example, section padding = `space.section`)

Only values that differ from the inherited value are stored, which keeps JSON small and makes branding changes propagate.

### 8.4 Style compilation, done once

A **single JavaScript StyleCompiler** (`resources/js/blocks/style/`) turns `layout`, `style` and `responsive` into scoped CSS (`.b-3f9a12c0 { … } @media (max-width: 991.98px) { … }`). The SPA and the builder preview use the same compiler, so what editors see is what visitors get. The page's CSS is emitted once as a React 19 `<style>` element. The server only *validates* values; there is no PHP copy of the compiler to drift out of sync.

---

## 9. Responsive system

| Breakpoint | Range | Bootstrap alignment |
|---|---|---|
| desktop (base) | ≥ 992px | `lg` and up |
| tablet | 768–991.98px | `md` |
| mobile | < 768px | `xs`/`sm` |

- Storage is **desktop-first overrides**: base values in `layout`/`style`, overrides in `responsive.tablet` and `responsive.mobile`. Mobile inherits from tablet, and tablet inherits from desktop.
- Visibility per breakpoint: `advanced.visibility.hide_on: ["mobile"]`.
- Collection columns are responsive by definition (`display.columns.{desktop,tablet,mobile}`).
- The builder has a device switcher (Desktop / Tablet / Mobile). It resizes the **preview iframe**, so real media queries apply, and edits made in tablet or mobile mode write overrides.
- Font sizes in the token scale use `clamp()` for fluid typography, which removes most per-breakpoint font overrides.

---

## 10. Design tokens (runtime)

Full token catalogue and component styling are in **UI-DESIGN-SYSTEM.md**. The architecture points are:

- Tokens are stored in `settings` (group `design`), edited in **Admin → Design → Tokens**, and revisioned.
- On save, Laravel generates `storage/app/public/theme/tokens.<hash>.css`. It is **CSS custom properties only**, served with long-lived caching and preloaded by both the SPA shell and the admin preview.
- Bootstrap 5.3 components already read CSS variables (`--bs-btn-bg`, `--bs-link-color`, …). The PACMS theme layer maps them to `--pa-*` tokens and derives hover and active shades with `color-mix()`.
- **Result: rebranding needs no rebuild, no Node on the server and no block changes.**
- The token editor checks WCAG contrast for the defined text/background pairs and warns before saving combinations below AA.

---

## 11. Custom Block Builder

Administrators create block types **without PHP changes and without writing templates or code**.

### 11.1 A custom block = fields + a bound structure

```
Custom block type "Staff Profile"
├─ Fields:      name (text, required) · photo (image) · role (text) · bio (rich text) · links (repeater: label, url)
└─ Structure (a block tree, edited in the same builder):
     section
     └─ columns
        ├─ column → image        (src bound to {{photo}})
        └─ column → heading      (text bound to {{name}})
                    rich-text    (html bound to {{bio}})
                    repeat(links)            ← renders children once per repeater row
                      └─ button (label {{item.label}}, url {{item.url}})
```

- **Bindings** are declarative references (`{"$bind": "photo"}`, `{"$bind": "item.url"}`). They are not expressions and there is no template language, so there is nothing to execute.
- A **`repeat` structural block** iterates a repeater field. `when` is a structural visibility rule that supports only `filled` / `empty` / `equals <literal>`.
- **Instances** store only field values (`content`) plus their own layout, style and advanced overrides. When the type's structure is re-published, every instance picks it up.
- The type definition is **staged and revisioned** (`version` increments on publish). Instances created under an older version keep their data. Missing fields fall back to defaults, and removed fields are kept but ignored and flagged in the builder.

### 11.2 The field system (shared by core blocks, custom blocks and settings)

Supported field types: text, textarea, rich-text, number, url, email, image, video, file, icon, color, date, time, datetime, checkbox, radio, select, multi-select, gallery, relationship, repeater.

```json
{ "key": "stats", "type": "repeater", "label": "Statistics", "min": 1, "max": 12,
  "fields": [ { "key": "value", "type": "number", "required": true },
              { "key": "label", "type": "text", "required": true, "max": 60 },
              { "key": "icon",  "type": "icon" } ] }
```

One definition drives four things:

1. the builder's inspector form (React `FieldRenderer`)
2. **server-side validation**: definitions compile to Laravel rules
3. the generated **JSON Schema** for import
4. the admin preview

A `relationship` field stores `{entity, id}` pairs validated through the Content Type Registry.

### 11.3 Rendering

A single `CustomBlock` React component receives the compiled structure (delivered in the page payload's `definitions` map) and the instance's field values. It walks the structure with the normal `BlockRenderer`, substituting bindings. **The same safe, fixed components render everything.**

---

## 12. Templates and Global Blocks

| | Template | Global Block | Custom Block Type |
|---|---|---|---|
| What it is | A saved block tree (block, section or whole page) | One shared block tree placed in many locations | A new block *type* with a field form |
| Insert behaviour | **Deep copy.** The copy is independent. | **Reference.** A `global-ref` block points to it. | New instance with its own field values |
| Edit one → others change? | No | **Yes, everywhere** | Structure yes, values no |
| Detach | n/a | Authorised users can *Detach*, which converts the reference into a local deep copy (logged) | n/a |
| Versioning | Staged + revisions | Staged + revisions | Staged + revisions + `version` |

- All three are block-tree owners edited in the same builder, so there is no second editor.
- Examples from the master prompt: *Hero Default, Hero Background Image, Program Cards, News Cards, CTA Banner, Image + Text* are **templates**. *Header, Footer, Contact Information, Partner Logos, Newsletter, Social Links, Organization Statistics, CTA* are **global blocks**. *Staff Profile* could be either a template or a custom block type (§11).
- **Usage tracking.** `content_references` records where each global block is used, so the admin shows "used on 14 pages" and deletion of an in-use global block is blocked.

---

## 13. Header, Footer and Menu Builder

*Phase 9A built this section except mega menus (9B). Blocks: `site-logo`, `menu`, `social-links`, `contact-info`, `copyright`, and `account-link` (sign in / my account); `newsletter` is deferred (no newsletter service). Menus serve one cached version for everyone and the website applies item visibility (N-3). Settings live in Design → Header & footer; menus in Design → Menus.*

### 13.1 Header and footer are global blocks of kind `header`/`footer`

```
Header (global block, kind=header)         Footer (global block, kind=footer)
├─ top-bar (contact, social-links)         ├─ columns
├─ site-logo                               │  ├─ site-logo + rich-text (About)
├─ menu (menu=main, style=mega-capable)    │  ├─ menu (menu=footer)
├─ social-links                            │  ├─ contact-info
└─ button (CTA)                            │  └─ newsletter
                                           └─ copyright (text with {{year}} {{site_name}} placeholders only)
```

- The site settings choose the default header and footer. A page can override them in its layout settings (for example, a landing page with no header).
- Header behaviour options: sticky, transparent-over-hero, and mobile style (`offcanvas` or `accordion`).

### 13.2 Menus

- `menus` (main, footer, secondary, custom…) and `menu_items` form an adjacency list with `parent_id` and `position`. Maximum depth is 4.
- Item types: `page`, any registered content type (news, event, project, program, publication…), `term` (category archive), `external_url`, `custom_url`, `group` (label only, no link).
- Linked items store `linkable_type/linkable_id`, and the URL is **resolved when rendered**, so renaming a slug never breaks menus. Links to unpublished targets are hidden publicly and flagged in the admin.
- Options per item: new tab (`rel="noopener"` automatic), icon, CSS class, visibility (everyone / guests / members), and **mega panel**.
- **Mega menu**: a top-level item with `is_mega = true` owns a **block tree** (columns, headings, menu groups, images, text, buttons). It reuses the block engine instead of inventing a mega-menu-specific format.
- The **Menu Builder** is a React island with nested drag and drop (dnd-kit) and keyboard reordering.
- **Rendering** meets the WAI-ARIA disclosure-navigation pattern: buttons with `aria-expanded`, Esc to close and focus returned, and no hover-only menus. On mobile, menus use an offcanvas drawer with accordion sub-levels.

---

## 14. Media Library

- `media` rows hold the disk, path, original name, a generated safe filename (ULID), MIME type (detected from content, not the extension), size, width, height, duration, alt, caption, description, credit, `focal_point`, `variants` (JSON), visibility (`public`/`private`), uploader and soft delete.
- **Image pipeline** (queued job, Intervention Image with GD):
  - re-encode, which strips EXIF data including GPS location
  - generate responsive variants: 320/640/960/1280/1920 widths as WebP plus the original format as fallback
  - record them in `variants`
  - the API returns `srcset`, `sizes`, `width`, `height` and a tiny blurred placeholder
- **Documents and video**: PDF, DOCX, XLSX, PPTX, MP4, WEBM, stored as-is with size limits. Video transcoding is out of scope; YouTube embeds are recommended for large video.
- **Replace** keeps the same media ID and references and regenerates variants. The old file is kept in the revision until pruned.
- **Delete** is blocked while `content_references` shows the media in use, unless a user with `media.force_delete` confirms.
- Categories and tags use the unified taxonomy.
- The **Media Picker** is a React island reused by the builder, forms and the JSON import asset mapper. It supports search, filters, multi-upload and drag-and-drop upload.
- Private media (for example, media-coverage archives without redistribution rights) is served through an authorised controller, not from `/storage`.

Upload security is in SECURITY-ARCHITECTURE.md §8.

---

## 15. External Provider Framework

### 15.1 Contracts

```php
interface ExternalContentProvider
{
    public function key(): string;                                  // 'rss', 'facebook', 'flickr', 'youtube'
    public function configSchema(): array;                          // field definitions (§11.2) for the admin form
    public function validateConfig(array $config): void;            // throws ValidationException
    public function test(ExternalSource $source): ProviderTestResult;
    public function fetch(ExternalSource $source): FetchResult;     // → NormalizedExternalItem[]
    public function capabilities(): array;                          // ['posts','images','videos']
}
```

`ProviderRegistry` is bound in a service provider. Adding YouTube, Instagram or LinkedIn later means one new class plus registration.

### 15.2 Unified storage

| Table | Purpose |
|---|---|
| `external_provider_accounts` | Credentials and tokens per provider account, **encrypted at rest** (Laravel `encrypted` casts), token expiry |
| `external_sources` | One configured feed or page or album: provider, name, slug, config (JSON), status, cache/sync interval, max items, `last_synced_at`, `last_success_at`, `last_status`, `last_error`, `next_sync_at`, consecutive failures |
| `external_items` | Normalized items: `(external_source_id, external_id)` unique, title, link, guid, excerpt, sanitized description, author, category, image URL / local cached media, published_at, `raw_data` (JSON, trimmed) |
| `external_sync_logs` | One row per sync attempt: status, duration, counts, error (retained 90 days) |

This replaces the separate `rss_sources`, `rss_items`, `social_accounts`, `social_posts` and `gallery_sources` candidate tables with one model.

### 15.3 Synchronisation

```
Scheduler (every minute) → DispatchDueExternalSyncs → SyncExternalSourceJob(source)   [queue: external]
   ├─ lock per source (no overlap)
   ├─ provider->fetch()  via SafeHttpClient (SSRF-guarded, timeouts, size caps)
   ├─ sanitize + normalize + upsert external_items, trim to max_items
   ├─ success → last_success_at, bump cache version "external:{id}"
   └─ failure → keep existing items (stale-while-error), last_error, backoff (×2, max 24h), log
Admin actions: Test · Sync Now (dispatch) · Enable/Disable · Clear Cache (delete items, re-sync)
```

Visitors only ever read `external_items`, so providers are never contacted on a visitor request.

---

## 16. Feeds (JSON Feed)

Decision D-15 (team lead, 2026-10-09): content is shared between sites as **JSON Feed 1.1** (https://jsonfeed.org/version/1.1), not RSS. It is smaller and faster to parse, needs no XML hardening, and maps directly onto the public API's item shape. PACMS does not publish RSS; reading other sites' RSS/Atom is kept as a fallback (§16.2), because many sources offer nothing else.

### 16.1 Distribution (CMS → JSON Feed → other websites)

| Feed | Content |
|---|---|
| `/feed.json` | Combined latest published news, events, projects, programs, publications |
| `/feed/{type}.json` (e.g. `/feed/news.json`) | Per type, for every type with a listing page (built-in and admin-made) |
| `?category={slug}` | Optional filter, whitelisted to that type's category taxonomy. No other parameters are accepted. |

- JSON Feed 1.1: `version`, `title`, `home_page_url`, `feed_url`, `description`, `icon`/`favicon` from the logo settings, `language`. Items include stable `id` (`{type}:{id}`), `url`, `title`, `summary` (excerpt), `content_html` (sanitized), `image` (featured image), `date_published`, `date_modified`, `tags` (categories), `authors` where public, and a `_pacms` extension object (`type`, `slug`, type-specific public facts such as event dates) that PACMS consumers use and other readers ignore.
- Built from the public API Resources (same published scopes, so no unpublished or private field can appear), cached under the type's cache-version key, limited to 50 items, sent with `Content-Type: application/feed+json; charset=UTF-8`, `ETag`/`Last-Modified` for conditional requests.
- Feeds are discoverable through `<link rel="alternate" type="application/feed+json">` in the SPA shell.

### 16.2 Consumption (other websites → feed → CMS → feed block)

```
External feed URL (admin-entered)
 → URL validation (https preferred; http only if the admin ticks "allow insecure")
 → SafeHttpClient: DNS resolve → reject private/reserved IPs → pinned connection → manual redirects (≤3, re-validated)
   → 5s connect / 15s total timeout → 5 MB max body (streamed, aborted beyond)
 → Detect format from Content-Type / body:
     JSON Feed 1.x (preferred) → json_decode with depth limit, schema check; `_pacms` extension read when present
     RSS 2.0 / Atom / Media RSS (fallback) → XML safety: reject any DOCTYPE/ENTITY declarations; parse with
       LIBXML_NONET, no LIBXML_NOENT/DTDLOAD (SimplePie fed raw data, its own fetch disabled — confirmed in Phase 10)
 → Sanitize HTML (allowlist) · strip scripts/iframes/styles/event handlers · absolutize + validate links
 → Normalize → upsert external_items → cache version bump
 → Public API /api/v1/external/{source-slug} → React RSS block
```

- React **never fetches feed URLs**. The public API only accepts a source *slug* configured by an admin, never a URL.
- The **feed block** (`rss-feed`, key kept for compatibility) shows items of any feed source (JSON Feed or RSS/Atom) and configures source, item count, layout (list, grid, cards, 2-/3-column, carousel), show/hide image, title, excerpt, date, author, source name, category, a read-more label, and whether links open in a new tab (external links always get `rel="noopener noreferrer"`).
- **Cache duration** (5m, 15m, 30m, 1h, 6h, 12h, 24h) is the source's sync interval. It is the only place freshness is configured, so there are no competing cache settings per block.
- **On failure**: previous items keep showing, the block renders its fallback text if the source has never succeeded, and the admin sees the status, last error and sync log.

---

## 17. Facebook / Meta

### 17.1 Verified current requirements (checked 2026-09-24; re-verify at Phase 10 start)

| Item | Current official position |
|---|---|
| API | Graph API **v26.0** is current. Versions are supported about 2 years. PACMS pins the version in config (`services.meta.graph_version`). |
| Reading **your own** Page's posts (`/{page-id}/feed` or `/published_posts`) | **Page access token** from a person who can perform CREATE_CONTENT, MANAGE or MODERATE on the Page. Permissions: **`pages_read_engagement`** and **`pages_read_user_content`**, plus `pages_show_list` during the connect flow. |
| Reading Pages you do **not** manage | Needs the **Page Public Content Access** feature, which requires App Review. **Out of scope.** PACMS only shows the organisation's own Page. |
| Limits | Maximum `limit=100` per request. The feed returns about 600 ranked posts per year. `/feed` includes unpublished posts, so filter `is_published`. |
| Fields used | `id, message, created_time, permalink_url, full_picture, attachments{media_type,media,url,subattachments}, is_published` |

Whether App Review or Business Verification is needed depends on whether the app is used only by people who hold roles on the app and Page (Standard Access) or not. **This must be confirmed against Meta's documentation and the organisation's Business Manager setup at the start of Phase 10** (open question Q-05).

### 17.2 Design

- **No scraping, ever.** Only the official Graph API through `FacebookProvider`.
- **Credentials**: the Meta App ID and App Secret are in `.env`. The Page access token is stored encrypted in `external_provider_accounts`. We recommend a **Business Manager system-user token** (non-expiring). Otherwise use a long-lived Page token, and warn admins 7 days before expiry.
- **Admin screens**: Connect (OAuth through the Graph API), choose Page, Test, Sync Now, status, last success, last error, next sync, token expiry.
- **Images**: Facebook CDN image URLs are signed and **expire**. PACMS downloads post images from the organisation's own Page into a local cache (media library, category "Facebook cache"), so blocks never show broken images.
- **Sync** runs every 30–60 minutes (configurable) through the provider framework (§15.3). This is far below rate limits.
- **Fallback**: the cached posts are shown. If there are none, the block hides itself or shows a "Follow us on Facebook" link, depending on configuration.
- **Not used by default**: the Facebook Page Plugin iframe, which loads Meta trackers and cookies for every visitor. It is available as an opt-in "embed" display with a privacy notice (Q-08).

---

## 18. Testimonials and moderation

### 18.1 State machine

```
 (registered user)          (moderator)                          (publisher)
 Draft ─submit─▶ Pending ─start review─▶ Under Review ─approve─▶ Approved ─publish─▶ Published ─archive─▶ Archived
                    │                        │                                         │
                    └──────── reject (internal reason) ──▶ Rejected                    └─ unpublish ─▶ Approved
```

- **Approved ≠ Published.** Approved means accepted content that is not yet shown. This allows scheduling and selecting which testimonials to feature.
- Every transition is written to `testimonial_moderation_logs`: from, to, actor, internal note, and rejection reason (never public).
- Moderators can preview, edit (the edit is logged, and the original submission is kept in the first revision), approve, reject, publish, archive and delete. The permissions `testimonials.view/moderate/publish/delete` map one-to-one to these actions.

### 18.2 Submission

- Only **authenticated, email-verified** registered users can submit, through the SPA with a Sanctum cookie session.
- The form has name, organisation, designation, photo (image-only upload, max 2 MB, re-encoded), testimonial text (max 1,500 characters, plain text), related program and project (select from published items), and a **required consent checkbox**. The consent version and timestamp are stored.
- Rate limit: 3 submissions per user per day and 10 per IP per day. A honeypot field is included. Optional Cloudflare Turnstile can be added later.
- Users see their own submissions and statuses in their account (no rejection reason shown by default; configurable).
- **Never public**: email, `user_id`, IP, user agent. `TestimonialResource` exposes an explicit field allowlist, and a test asserts that private keys never appear.

Display modes: single, cards, grid, carousel, quote-slider, featured, list, masonry.

---

## 19. Media Coverage

- A **separate archive entity**, not news. Categories are relational (taxonomy `media_coverage_category`). **Coverage type** is a separate enum column: newspaper, magazine, tv, radio, online, other.
- Fields as in the master prompt, plus `archive_rights_confirmed` (boolean + note). **An archive file cannot be made public unless an editor confirms permission, licence or legal basis.** Otherwise the archive is private (admin-only).

### 19.1 Source availability

```
Scheduled job (daily, spread across the day) → SafeHttpClient HEAD (fallback GET, 1 MB cap)
   2xx/3xx→final 2xx            → available
   404/410, DNS failure          → failure (consecutive_failures++)
   401/403/429/5xx, timeout      → "unverified" (many news sites block bots; not counted as unavailable)
   consecutive_failures ≥ 3      → unavailable
Stored: last_checked_at, http_status, availability, last_error, consecutive_failures
Admin: "Check Source" (dispatches immediately), manual override: auto / force-original / force-archive
```

### 19.2 Display fallback

| Original | Archive | Shown |
|---|---|---|
| available | none | Original link |
| unavailable | PDF | PDF viewer/download |
| unavailable | Video | Video player |
| available | PDF/video | Original link **and** archive |
| unavailable | none | Metadata + "original no longer available" |

Checks never run on visitor requests.

---

## 20. Galleries

- `galleries` (title, slug, description, cover, type photo/video/mixed, date, location, credit, event/project/program FKs, featured, status, **source mode** `cms` or `external` plus `external_source_id`) and `gallery_items` (media_id **or** external video URL, caption, alt override, credit, position).
- **Sources**: *static* is Media Library items picked directly in a gallery block. *Dynamic* is CMS galleries (`gallery_items`). *External* is an `external_source` of provider Flickr, YouTube playlist, Media RSS or the Facebook album of the organisation's own Page.
- All three produce **normalized gallery items** (the §6.4 Item shape with `type: image|video`, `thumbnail`, `source_url`). Display modes (grid, masonry, justified, carousel, slider, thumbnail-large, 2/3/4 columns, plus `lightbox: true`) never know the provider.
- Videos: YouTube/Vimeo are embedded through a **click-to-load facade**. No third-party iframe or cookies load until the visitor clicks, which is good for performance and privacy.
- The lightbox is an accessible modal dialog (focus trap, Esc, arrow keys, captions, credit). It is a small in-house component on Bootstrap's modal, with no library.

---

## 21. Search

- A single **`search_documents`** table (type, entity id, title, body text, url, published_at, boost, `FULLTEXT(title, body)`) is maintained by the `Searchable` trait on publish, update and unpublish, plus a `pacms:search:rebuild` command.
- `GET /api/v1/search?q=&type[]=&page=` uses MySQL `MATCH … AGAINST` in natural-language mode, filtered to published documents, paginated (max 50 per page) and rate-limited.
- Page text is extracted from the published block snapshot (headings, rich text, button labels), with HTML stripped.
- **Extensible**: new content types are included by setting `searchable: true` in the registry.
- Bangla uses spaces between words, so InnoDB's default FULLTEXT tokenizer works. `ngram_token_size` tuning is a Phase 13 item if relevance is poor (R-10). Laravel Scout was not chosen: it indexes per model, which makes cross-type search awkward.

---

## 22. SEO

- `seo_metadata` (one-to-one polymorphic) stores title, description, canonical, robots (index/noindex, follow/nofollow), OG title/description/image, Twitter card type, and structured-data overrides.
- **Defaults** are generated when fields are empty: title pattern `{title} · {site_name}`, description from the excerpt, OG image from the featured image or the site default.
- **Structured data (JSON-LD)**, generated from the content type:
  - `Organization` and `WebSite` (with SearchAction) site-wide
  - `NewsArticle`, `Event`, `BreadcrumbList`
  - `CollectionPage` for galleries
  - `Person` for team members (opt-in per person)
- `/sitemap.xml` is a sitemap index with one sitemap per content type, generated from published items and cached by cache version.
- `robots.txt` is dynamic and editable in Settings. Laravel's default `public/robots.txt` is removed so the route serves it.
- **Redirects**: slug changes create 301 redirects automatically, and admins can add them manually (source path → target, 301/302, hit counter). They are resolved in `PathResolver` before content lookup.
- All SEO head output is rendered **server-side in the SPA shell** (§2.3) and kept in sync client-side.

---

## 23. Admin UI architecture

### 23.1 Principles

- Server-rendered Blade for **all** CRUD, lists, settings and moderation queues. React islands only for the builder, media picker, menu builder, repeater editor, JSON import/export, revision compare, and the token editor with live preview.
- Shared **Blade components** (`<x-admin.page-header>`, `<x-admin.table>`, `<x-admin.filters>`, `<x-admin.form.field>`, `<x-admin.status-badge>`, `<x-admin.publish-box>`, `<x-admin.seo-panel>`, `<x-admin.empty-state>`, `<x-admin.confirm-dialog>`) mean ten content types do not become ten copies of the same markup.
- Forms post normally with Form Requests. Validation errors re-render with field-level messages. Inline AJAX is used only for small conveniences such as slug checks and autosave.
- The admin theme is **fixed and neutral**, separate from the organisation's public branding (UI-DESIGN-SYSTEM.md §8).

### 23.2 Navigation

```
Dashboard
Content      Pages · News · Events · Projects · Programs · Publications
Organization Team · Partners · Testimonials (moderation badge) · Media Coverage
Media        Library · Galleries
Design       Global Blocks (Header/Footer…) · Templates · Custom Blocks · Menus · Design Tokens
Integrations Feed Sources · Facebook · Other Providers · Outbound Feeds
SEO          Redirects · Sitemap & robots
System       Users · Roles & Permissions · Settings · Activity Log · Import / Export · Backups
```

Menu entries are shown **only if the user has the permission**. The server enforces permissions regardless.

### 23.3 Standard screen patterns

- **Index**: search, filters (status, category, author, date), sortable columns, bulk actions (publish, archive, delete; each permission-checked), pagination, empty state.
- **Edit**: main column (fields, block body) plus a right sidebar (publish box with status and workflow buttons, schedule, featured image, taxonomy, SEO panel, revision list).
- **Page edit**: the full-screen **Builder island** (§23.4).

### 23.4 Block Builder (React island)

```
┌──────────────┬──────────────────────────────────────────────┬──────────────────────┐
│ Block palette│  Canvas = <iframe> running public SPA in      │ Inspector            │
│ (search,     │  "builder-preview" mode                       │ [Content][Layout]    │
│ categories,  │  ◀ postMessage(draft tree) ▶                  │ [Style][Advanced]    │
│ templates,   │  Device: [Desktop][Tablet][Mobile]            │ responsive toggles   │
│ globals)     │                                               │ field forms          │
├──────────────┴──────────────────────────────────────────────┴──────────────────────┤
│ Structure tree (nested drag/drop, keyboard move) · Undo/Redo · Save · Preview · Publish│
└──────────────────────────────────────────────────────────────────────────────────────┘
```

- **State**: Zustand store holding a normalized tree (`byUuid`, `childrenOf`) with a patch-based undo/redo history. Selector subscriptions keep large trees responsive.
- **Drag and drop**: dnd-kit (sortable tree), with keyboard sensors for accessibility.
- **Operations**: add, move, nest, duplicate, copy/paste (same-origin clipboard JSON in schema format), hide/show, rename, save as template, convert to or detach from global block.
- **Saving**: a single `PUT /admin/api/owners/{type}/{id}/tree` with the whole tree and the version (`lock_version`) for optimistic concurrency. The server validates everything and returns field-level errors mapped to block UUIDs. Autosave to a per-user `autosave` revision runs every 60 seconds.
- **Preview parity**: the canvas iframe runs the **same block components** as the public site, so what the editor sees is what the visitor gets. The iframe only accepts `postMessage` from the same origin.

---

## 24. Public React SPA architecture

### 24.1 Structure (as specified, with responsibilities)

```
resources/js/public/
├── api/          axios instance (baseURL /api/v1, CSRF for Sanctum), endpoint modules
├── components/
│   ├── common/   Image (srcset/lazy/placeholder), Link (internal/external), ErrorBoundary, Skeleton
│   ├── layout/   SiteShell, HeaderRenderer, FooterRenderer, SkipLink, Breadcrumbs
│   ├── blocks/   → re-exports resources/js/blocks (shared renderer)
│   └── ui/       Button, Card, Badge, Modal, Pagination, Tabs … (Bootstrap-based)
├── pages/        ContentPage (generic), NewsIndex, NewsDetail, EventIndex, …, Search, NotFound, ErrorPage, Account/*
├── hooks/        usePage, useCollection, useMenu, useSettings, usePrefetchOnHover, useReducedMotion
├── services/     analytics hooks (off by default), consent
├── contexts/     SiteSettingsContext, AuthContext
├── routes/       route table, lazy route components
├── utils/        url, date (Asia/Dhaka), text
├── schemas/      block-node shape validators for dev/preview warnings
├── types/        JSDoc typedefs (JavaScript, D-05)
└── styles/       public.scss imports
```

### 24.2 Routing

- Fixed routes for type archives and details (`/news`, `/news/:slug`, `/events`, …), `/search`, `/account/*`, `/preview/*`.
- **Catch-all `*`** calls `GET /api/v1/resolve?path=…`, which returns `{kind: 'page'|'redirect'|'not_found', …}`. CMS-defined page paths (`/about/our-team`) need no client route table.
- Routes are lazy-loaded (`React.lazy`), so the initial bundle contains only the shell, router, query client and core blocks.

### 24.3 Data fetching and caching

- **TanStack Query** handles request de-duplication, caching (`staleTime` 60s for pages, 5min for menus and settings), background refresh, retries (GET only) and prefetch on link hover or focus.
- The **initial payload** from the SPA shell seeds the query cache (§2.3).
- Server HTTP caching adds `Cache-Control: public, max-age=60, stale-while-revalidate=300` plus an `ETag` on public GET endpoints.

### 24.4 States

Every data view renders **loading** (skeletons that match final layout, avoiding layout shift), **error** (friendly message and retry, with an error boundary per block so one broken block never blanks a page), **empty** (configured fallback text) and **not found** (correct 404 from the server shell).

### 24.5 Images and media

- `<Image>` uses the API's `srcset`/`sizes`, explicit `width`/`height` (prevents layout shift), `loading="lazy"` below the fold and `fetchpriority="high"` for the first hero image.
- Video embeds use click-to-load facades (§20).

### 24.6 Builder preview mode

`/__builder-preview` (auth required, `noindex`) boots the same SPA without header or footer chrome by default. It listens for `postMessage` from the admin origin and renders the received tree. It is **isolated** from the ordinary public route table and bundle.

### 24.7 Accessibility

- Skip link and landmarks (`header`, `nav`, `main`, `footer`). Route changes move focus to the page `<h1>` and are announced through a live region.
- Visible focus rings, `prefers-reduced-motion` respected (carousels do not autoplay and animations are disabled), and carousels have pause controls.
- Alt text is required on upload (decorative images need explicit opt-in). The heading-order linter in the builder warns about skipped levels.

---

## 25. Laravel application architecture

```
app/
├── Cms/                        Domain core that is not a Laravel framework concept
│   ├── Blocks/                 BlockType base, core types/*, BlockRegistry, TreeRepository, TreeValidator, Serializer
│   ├── Fields/                 Field definitions, FieldRuleCompiler, JsonSchemaGenerator
│   ├── Display/                DisplayMode definitions + registry
│   ├── Sources/                DynamicSource base + per-type sources, SourceResolver, Item normalizer
│   ├── Cards/                  Per-type card transformers → Item
│   ├── ContentTypes/           ContentTypeRegistry
│   └── Json/                   Schema versions, Migrators (1.0→1.x), Importer, Exporter, ImportPlan, ImportReport
├── Http/
│   ├── Controllers/Admin/      thin; one per resource
│   ├── Controllers/Admin/Api/  JSON for islands
│   ├── Controllers/Api/V1/     public + user API
│   ├── Controllers/Public/     SpaController, FeedController, SitemapController, RobotsController, PreviewController
│   ├── Middleware/             SecurityHeaders, EnsureAdminAccess, …
│   ├── Requests/               Form Requests (validation + authorize)
│   └── Resources/              Public/*, Admin/* API Resources (explicit field allowlists)
├── Models/
├── Policies/
├── Services/
│   ├── Publishing/             PublishingService, Scheduler hooks
│   ├── Revisions/              RevisionService, Differ
│   ├── Payload/                PagePayloadBuilder, CacheVersions
│   ├── Media/                  MediaService, ImageVariants, UploadGuard
│   ├── External/               ProviderRegistry, SyncService, Providers/{Rss,Facebook,Flickr,YouTube}
│   ├── Feeds/                  JsonFeedBuilder
│   ├── Search/                 SearchIndexer, SearchService
│   ├── Moderation/             TestimonialModerationService
│   ├── Seo/                    SeoResolver, StructuredData, SitemapBuilder, PathResolver
│   └── ActivityLog/            ActivityLogger
├── Jobs/                       SyncExternalSource, GenerateImageVariants, CheckMediaCoverageSource, ProcessJsonImport …
├── Events/ + Listeners/        ContentPublished → search index, cache versions, sitemap, activity log
├── Console/Commands/           pacms:blocks:sync, pacms:create-admin, pacms:search:rebuild, pacms:revisions:prune …
├── Providers/
└── Support/                    SafeHttpClient, HtmlSanitizer, CssSanitizer, SafeXml, UrlGuard, Ulid helpers
```

- **Controllers stay thin**: authorize → Form Request → one service call → Resource or redirect.
- **Services own business rules.** Models own relationships, scopes and casts only.
- **Events decouple side effects**: publishing fires `ContentPublished`, and listeners update search, cache versions and logs.
- Domain enums are PHP backed enums (`ContentStatus`, `CoverageType`, `TestimonialStatus`, …).

---

## 26. Performance architecture

| Area | Design |
|---|---|
| Public page read | Published snapshot (1 row) + resolved sources → cached payload keyed by cache versions. A warm-cache request makes no DB queries except version-counter reads (1 cache read). |
| Queries | Eager loading defined in each DynamicSource/Card. `Model::preventLazyLoading()` in non-production, so N+1 queries fail tests. Pagination everywhere (max 50). |
| Indexes | Designed per query in DATABASE-ARCHITECTURE.md: status/published_at composites, slug unique, owner/parent/position, FULLTEXT. |
| External data | Queued sync only. Visitors read `external_items`. |
| Heavy work | Image variants, imports, feed syncs, source checks, sitemap rebuilds, and search reindexing all run on queues. |
| HTTP | ETag + Cache-Control on public API. Long-lived immutable caching for `/build/*` and theme CSS. gzip/brotli at the web server. |
| Frontend bundle | Route- and block-level code splitting. Bootstrap imported by component (SCSS subset, JS ES modules per plugin). Target: **< 120 KB gzipped JS** for the initial route (checked in CI from Phase 11). |
| Images | Responsive WebP variants, lazy loading, explicit dimensions, blur placeholders. |
| Fonts | Self-hosted WOFF2, `font-display: swap`, preload of the body font only. Bangla subset where available. |
| Rendering | Normalized builder store with selectors. Memoised blocks keyed by `uuid`. |
| Budgets (Phase 13) | LCP < 2.5s, CLS < 0.1, INP < 200ms on a mid-range mobile over 4G for the homepage. |

---

## 27. Activity logging, error handling, backups

### 27.1 Activity log

- A custom `ActivityLogger` service and `activity_logs` table (explicit domain events, not automatic diffing of every column). Log entries hold: user, action, subject type and ID, human label, IP (for auth and security events), user agent (hashed), and `properties` JSON (changed field names and relevant IDs, **never secrets or passwords**).
- Actions logged: login, logout, failed login, create, update, delete, publish, unpublish, schedule, media upload, delete and replace, import, export, settings, user, role and permission changes, RSS and provider syncs (failures and manual runs; scheduled successes are counted in `external_sync_logs` instead to avoid noise), testimonial moderation, revision restore, global-block detach, backups.
- Retention: 365 days by default (configurable, pruned weekly).

### 27.2 Errors

- **API**: one JSON error envelope `{ "message", "code", "errors"? }` with the correct HTTP status (see API-ARCHITECTURE.md §6). Validation errors use `422` with field keys. JSON import errors use JSON pointer paths.
- **Admin**: flash messages plus inline field errors. Friendly 403, 404, 419 and 500 pages.
- **Production**: `APP_DEBUG=false`, no stack traces, and every exception logged with request context (daily log files).
- **Provider errors**: stored on the source, shown in the admin, and never surfaced raw to visitors.

### 27.3 Backups

- `spatie/laravel-backup` for database and media, to the local disk first. Dropbox and Google Drive are added later as Flysystem disks without code changes.
- Credentials are kept in `.env`. Backups are scheduled nightly, and actions need the `backups.manage` permission.
- Restore is documented as a manual procedure (DEPLOYMENT-ARCHITECTURE.md §7).

---

## 28. Testing strategy

| Level | Tooling | Scope |
|---|---|---|
| Unit | Pest 4 | Field rule compiler, source whitelists, style value validators, sanitizers, UrlGuard (IP ranges), SafeXml, JSON schema migrators |
| Feature | Pest 4 + real MySQL test DB | Auth, policies per role (matrix tests), workflow transitions, **public API never leaks unpublished or private fields**, preview signature and auth, block tree save/validate/publish, revisions restore, import pipeline end-to-end, JSON Feed distribution (valid 1.1, no leaks), provider sync with faked HTTP |
| Frontend unit | Vitest + React Testing Library | BlockRenderer recursion, each display mode, style compiler output, builder reducer/store operations, repeater editor, field renderer |
| E2E (Phase 11–12) | Playwright (optional, run locally) | Build a page in the builder → publish → visible on public site. Keyboard-only navigation of menus and builder. |
| Security | Dedicated tests | SSRF (private IPs, redirects to private IPs, DNS rebinding simulation), XXE/billion-laughs payloads, upload polyglots, XSS payloads through rich text, JSON import, custom attributes and CSS |

Each phase ends with `php artisan test`, `npm run build`, `php artisan migrate:fresh --seed` on the test DB, route and API checks, and the Phase Completion Report.
