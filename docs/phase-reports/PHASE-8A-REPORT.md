# Phase 8A — Content Modules: engine, News, Events, sidebars · Report

| | |
|---|---|
| Date | 2026-10-08 |
| Branch | `phase/8-content-modules` (not merged) |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). **Needs team review before Part 8B.** |

```
PHASE:   8A — Content engine, full News module, Events, module sidebars
STATUS:  Built and tested on the branch; waiting for the team lead's review.
         Later parts: 8B (Projects, Programs, Publications), 8C (Team, Partners,
         Testimonials, Media Coverage, Galleries, search indexing).
```

Phase 8 is large, so it is delivered in parts. Part 8A builds **one engine** for every "directly published" module (CMS-ARCHITECTURE §3.3, the Content Type Registry). It moves News onto the engine and adds Events as the first new module. Each later module is mostly a migration, a model and a field list.

## Implemented

| Area | What exists now |
|---|---|
| **Content Type Registry** | `config('pacms.content_types')` lists the modules. Each `ContentType` describes its fields (with form sections), taxonomy, sort orders, filters, archive views (e.g. upcoming / past), card data, detail data and schema.org JSON-LD. Admin screens, routes, navigation, public URLs, archive pages, collection blocks, link pickers, exports and scheduled publishing all read from it. |
| **Admin (every module)** | List with search and status filter. Form sections: Content (title, URL, summary, **article text**), the module's own sections (Events: When, Where, Details), Image & categories, **Sidebar**, Search engines & social sharing, and **Additional content** (optional builder blocks). Publishing box with the workflow, a "Save and update live" note on published items, scheduling, preview and delete. Revisions list with compare and restore. Optimistic locking (a stale form is refused). |
| **Workflow ("Direct" publishing)** | Draft → submit → approve / request changes → publish or schedule → unpublish / archive / restore, with the editorial permissions `{module}.view/create/update_own/update_any/delete/submit/approve/publish`. A published item is live, so changing it (saving or restoring a revision) needs the publish permission. Every save records a revision, and publishing records a "Published" one. |
| **News (full)** | On the engine: scheduling, revisions, builder blocks, sidebar, workflow, preview, archive page `/news` with category filter and pagination. The Phase 4 controller, service, payload builder and views were removed. |
| **Events (new)** | Start and end, all-day flag, time zone (times are entered as they are at the event's location and stored in UTC), venue, address, map link, registration link, organiser and categories (`event_category`). Detail page with a "When / Where / Organiser" panel and a Register button. Archive `/events` with **Upcoming** (soonest first) and **Past** views. JSON-LD `Event`. |
| **Events block** | A new collection block (upcoming events by default, soonest first). It can also show past or all events, a category, or featured events only, with grid, list, carousel or featured display. Event cards show the event's own date label, venue and a "Past event" badge. |
| **Sidebars** | Global blocks have a new kind, **Sidebar** ("Used as" on the global block form). **Settings → Sidebars** sets one default sidebar per module, on the left or right. On each item, editors choose the default, none, or a specific sidebar. Any **published** global block can be chosen; Sidebar-kind blocks are listed first (changed after review: a block named "Left Side Bar" but left as "Reusable section" could not be selected). |
| **Public pages** | Generic detail page (`ContentView`) and archive page (`ArchiveView`). Their filters and page numbers are in the URL, so every state can be shared. Both are server-rendered on first load and use the same payloads as `/api/v1/resolve`, which now returns kind `content` or `archive`. |
| **Preview** | Signed, short-lived preview links for module items (`/preview/{module}/{id}`). They need a signed-in user who may view the item, are never cached and are marked noindex. |
| **Links** | Link fields, the import/export translator and the media "Where it is used" list now handle every module, not only news. |

## Files

**Created:**
- Engine: `App\Cms\Content\{ContentType, ContentTypeRegistry}`, `Types\{NewsType, EventType}`, `App\Models\{ContentItem, Event}`, `App\Policies\EventPolicy`, `App\Services\Content\{ContentService, ContentPayloadBuilder}`, `App\Cms\Sources\ContentSource`.
- Admin: `Admin\ContentController` and the views `admin/content/{index,form,revisions}`.
- Block: `App\Cms\Blocks\Types\EventsBlock`.
- Public SPA: `pages/{ContentView, ArchiveView}.jsx`.
- Migration: `2026_10_10_100000_content_modules_news_events`.
- Tests: `Feature/Content/EventsTest`.

**Removed:** `Admin\NewsController`, `Services\News\{NewsService, NewsPayloadBuilder}`, `Cms\Sources\NewsSource`, `views/admin/news/*`, `pages/NewsView.jsx`.

**Modified:**
- Models and routing: `News` (now extends `ContentItem`), `PathResolver` (archive and content routes for every module), `ResolveController`, `SpaController`, `PreviewController`, `routes/admin.php` (routes per module), `routes/web.php`.
- Navigation and settings: `AdminNavigation` (generated from the registry), `SettingsService`/`SettingsController` and the settings view (Sidebars), `GlobalBlock` (`sidebar` kind), the global block form and service.
- Links, caching and scheduling: `ValueValidator`, `BlockPayloadResolver`, `BlockBuilderController`, `Exporter`, `PortableTranslator`, `PagePayloadBuilder` (cache fingerprint covers every module), `PublishScheduledCommand`, `DynamicSource` (select filters), `SourceRegistry`.
- Public SPA: `ItemCard`, `collections.jsx`, `registry.js`, `PreviewPage`, `useResolvedPath`, `initialData.js`, public SCSS.
- Tests: `NewsTest` (rewritten for the workflow and archive), `ContentSourcesTest`, `App.test.jsx`.
- Docs: `DATABASE-ARCHITECTURE.md`.

## Database changes

- **news:** `publish_at`, `lock_version`, `sidebar_mode` (default `default`), `sidebar_global_block_id` (FK global_blocks, set null), `INDEX(status, publish_at)`.
- **events:** new table, with the common content columns (including `body`), the event columns, `sidebar_*`, the publishable columns, `lock_version` and soft deletes. Indexes on `(status, start_at)`, `(status, published_at)`, `(status, publish_at)` and `(featured, status, start_at)`.
- **settings:** group `content`, key `sidebars` (module → global block and position).
- **global_blocks.kind:** new value `sidebar` (no schema change).

## Routes

- Admin, for each module (`news`, `events`): `admin.{module}.{index,create,store,edit,update,destroy,workflow,revisions,revisions.restore,preview}` under `/admin/{module}`.
- Public: `/{module}` (archive: `?view=`, `?category=`, `?page=`) and `/{module}/{slug}` (detail). Signed preview: `/preview/{module}/{id}`.
- Removed: `admin.news.publish` (replaced by the workflow).

## Tests

| Check | Result |
|---|---|
| Pest (full suite, parallel) | **267 passed** (1224 assertions) |
| Vitest | **62 passed** (11 files) |
| Larastan (level 6) | No errors |
| Pint, ESLint | Clean |
| Production build (Vite) | OK |

New or rewritten tests:
- `News/NewsTest`: workflow publishing, author limits (submit only, no edits to live items), unique slugs, unpublish/delete, cleaned article text, archive with category filter and pagination, revisions with stale-form protection and restore, signed preview (an unsigned link is refused, guests are redirected).
- `Content/EventsTest`: time-zone entry and display, date validation, upcoming/past archive and Events block order, crafted query strings, default / custom / no sidebar, any published global block accepted as a sidebar (Sidebar kind listed first, drafts refused), scheduled publishing via `pacms:publish-scheduled`, navigation by permission.
- `App.test.jsx`: event detail page (details panel, Register link, sidebar), archive (view and category links, pagination), content preview.

## Not verified by me in a browser

- The admin form for Events (datetime inputs, time zone list) and the Sidebar section.
- The new public pages (event details panel, archive chips, pagination) on phones.
- The Events block in the builder's live preview.

## Known issues / deferred

- **Autosave** of the builder is not enabled for module items yet (pages only). Saving works as normal.
- **Previewing an older revision** works for pages only. For module items, revisions can be compared and restored.
- Event times have no recurrence (repeating events) and no online/hybrid attendance mode. JSON-LD always says "offline".
- The archive page title and description are not editable yet. A Settings field for the archive intro can come with 8B if wanted.
- Search indexing of module items arrives with 8C.

## Architectural decisions (Phase 8A)

1. **One engine, many modules.** Modules differ only in their `ContentType` definition, migration and model. This keeps permissions, workflow, revisions, SEO, caching and public rendering identical everywhere.
2. **Two kinds of content per item.** The *article text* is simple rich text, which is quick to write and good for SEO. *Additional content* is optional builder blocks for galleries, buttons and so on.
3. **Event times** are entered in the event's own time zone, stored in UTC, and shown in that zone with its name. "Upcoming" comparisons are therefore always correct.
4. **Sidebars are global blocks** of a dedicated kind. Editors design them with the builder they already know. A module default plus a per-item override covers the common cases without page templates.

## Next part

**8B: Projects, Programs, Publications**, built on the same engine. Do not start it until 8A is reviewed.
