# Phase 3 — CMS Core + Media Core · Completion Report

| | |
|---|---|
| Date | 2026-09-24 |
| Branch | `phase/3-cms-core` (not merged, not pushed; waiting for review) |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). **Needs team review before Phase 4.** |

```
PHASE:   3 — CMS Core (+ Media core, per decision D-04)
STATUS:  Complete. All automated checks pass and the end-to-end HTTP smoke test passed on pacms.test.
         Awaiting team review in the browser.
```

## Implemented

| Area | What exists now |
|---|---|
| **Pages** | Hierarchical pages (parent/child, max depth 6), automatic slugs, working paths rebuilt when a parent moves, reserved first segments (`admin`, `api`, `news`, `programs` …) protected, path uniqueness, optimistic locking (`lock_version`) against concurrent edits, soft delete (blocked while sub-pages exist; unpublishes first). Templates: *Default* and *Full width*. |
| **Staged publishing** | The row is the working copy; visitors see the **published snapshot** (`published_revision_id`) at `published_path`. Editing a live page never changes the live version until *Publish changes*. `status` describes the working copy and "Live" is separate. |
| **Workflow** | Draft → In review → Approved → Published / Scheduled / Archived, plus Request changes, Unpublish and Restore. Every transition is permission-checked in `PublishingService` (submit / approve / publish per role), and state-checked and logged (`workflow.*`). Sub-pages can only be published under a live parent. Live parents with live sub-pages can't be taken offline. |
| **Scheduling** | *Schedule* sets Approved + `publish_at` (entered in site time, stored UTC). `pacms:publish-scheduled` runs every minute through the scheduler. Any edit cancels a schedule. |
| **Redirects** | When a published URL changes, 301 redirects are created automatically for the page **and all live sub-pages**. Redirect chains are collapsed and a page never redirects to itself. There's also a manual redirect manager (301/302, hit counter, `javascript:` targets rejected). A live page always wins over a stale redirect. |
| **Revisions** | Full snapshot (fields + SEO + blocks placeholder) on every save, publish and restore. Revision list, **compare any two** (field-level diff), **restore** (creates a new draft revision, so history is never rewritten), and preview of any revision. Weekly pruning keeps all published revisions and the last 50 others. |
| **Secure preview** | Short-lived (30 min) **signed relative URL AND a signed-in user with view permission**. The response is `Cache-Control: no-store` and `X-Robots-Tag: noindex, nofollow`, and the draft payload is embedded server-side (no public API call). |
| **SEO** | `seo_metadata` per page: title, description, canonical, index/follow, OG title/description/image. Generated defaults are `{title} · {site}`, the summary, and the featured image. Server-rendered **title, description, canonical, robots, Open Graph, Twitter card, JSON-LD `WebPage` + `BreadcrumbList`** (+ `Organization` on the homepage). Dynamic `robots.txt` (editable) and XML **sitemap index + pages sitemap** (live and indexable only). |
| **Homepage** | Any live page can be chosen as the homepage (Settings). It is served at `/`, and its own address 301-redirects to `/`. |
| **Taxonomy** | Unified `terms`/`termables` with admin management for *Media categories* (hierarchical) and *Tags* (flat): automatic slugs, uniqueness per taxonomy, cycle protection, and delete blocked while in use. New permission `taxonomies.manage` (Editor, Administrator, Super Admin). |
| **Media Library** | Upload (multi-file, drag-and-drop), **UploadGuard**: extension allowlist, MIME **detected from content** must match, dangerous name parts rejected (`photo.php.jpg`, `.svg`, `.html`, `.exe` …), per-kind size limits, image dimension/megapixel limits (decompression bomb). Images are **re-encoded** (EXIF including GPS stripped, orientation applied) and get **responsive WebP variants** (320–1920 plus original width) and a blurred placeholder, through a queued job. ULID file names. Metadata (alt text required unless decorative, caption, credit, description, focal point), categories and tags. **Usage tracking** ("where it is used"), with deletion blocked while in use (force delete only with `media.force_delete`). **Replace** keeps the ID and references and changes the URL (cache-busting). **Private files** go on the private disk and are downloadable by staff only. Deletion is permanent (files removed). |
| **Media picker** | First React **island** (admin): accessible `<dialog>` with library search, grid selection, "load more", and inline upload with alt text. Used for the featured image and social image. It loads only on pages that use it (2.5 KB gzipped + shared React). |
| **Public SPA** | Catch-all **ContentRoute** resolves any URL through the same `PathResolver` as the server shell (page, home, redirect or 404). CMS pages render with breadcrumbs, title, summary, and a responsive featured image (`srcset`, dimensions, lazy/priority, placeholder, focal point). Preview banner route. React 19 metadata for client navigation. |
| **Public API** | `GET /api/v1/resolve?path=` and `GET /api/v1/pages/{path}` (live only, `Cache-Control: public, max-age=60` + ETag). Payloads come from the **published snapshot** and are cached with **cache-version keys** (`pages`, `media`, `settings`), so they are invalidated automatically on publish, media or settings changes. |
| **Security hardening** | `.htaccess` in public storage denies script execution (verified: a `.php` probe returns 403) and adds `nosniff`. Form Requests authorise **before** validation, so unauthorised users get 403 rather than validation hints. |

## Files

**Created (main):**
- Migrations: `media`, `terms`/`termables`, `pages`/`revisions`, `seo_metadata`/`redirects`/`content_references`
- Models: `Page`, `Media`, `Revision`, `Term`, `SeoMetadata`, `Redirect`, `ContentReference`; concerns `HasSeo`, `HasRevisions`, `HasTerms`; contract `Revisionable`
- Enums: `ContentStatus`, `WorkflowAction`, `RevisionKind`, `MediaKind`
- Services: `PageService`, `PagePathService`, `PagePayloadBuilder`, `PublishingService`, `RevisionService`, `RedirectService`, `SeoResolver`, `PathResolver`, `MediaService`, `UploadGuard`, `ImageProcessor`, `ContentReferenceService`, `CacheVersions`
- Policies: `EditorialPolicy` (reusable for news, events … in Phase 8), `PagePolicy`
- Controllers:
  - Admin: `Page`, `PageWorkflow`, `PageRevision`, `PagePreview`, `Media`, `Api\Media`, `Term`, `Redirect`
  - Public: `Preview`, `SeoFiles`
  - API: `V1\Resolve`, `V1\Page`
- Other backend: `PageRequest`, `Admin\MediaResource`, `GenerateImageVariants` job, commands `pacms:publish-scheduled` and `pacms:revisions:prune`
- Views: `admin/pages/{index,form,revisions}`, `admin/media/{index,edit}`, `admin/terms/index`, `admin/redirects/index`, components `media-picker` and `content-status`
- JS:
  - admin: `http.js`, `islands/media-picker*`
  - public: `ContentRoute`, `PageView`, `PreviewPage`, `Image`, `SeoHead`, `useResolvedPath`
- Tests: `Pages/{PageManagement,PublishingWorkflow,PreviewAndRevisions}Test`, `Public/PublicPagesTest`, `Media/MediaLibraryTest`, `Admin/TaxonomyAndRedirectsTest`, `Unit/RedirectServiceTest`, `admin/app.test.js`, updated `App.test.jsx`
- `storage/app/public/.htaccess`

**Modified:**
- Backend: `PermissionCatalog` (+`taxonomies.manage`), `SettingsService` (homepage, robots.txt, cache-version bump), `SettingsController` + view, `SpaController` (real page resolution), `spa.blade.php` (full SEO head), `AdminNavigation`, admin layout (route params, islands stack), `AppServiceProvider` (morph map)
- Routes and config: `routes/*`, `config/pacms.php`, `vite.config.js`
- Frontend: `admin.scss`, `public.scss`, `admin/app.js` (slug and drop zone)
- Tests: `tests/Pest.php` (helpers)
- Docs: `DATABASE-ARCHITECTURE.md` and `CMS-ARCHITECTURE.md` (refinements below)

**Removed:** `public/robots.txt` (now dynamic).

## Database changes

- **New tables:** `media`, `terms`, `termables`, `pages`, `revisions`, `seo_metadata`, `redirects`, `content_references`
- **Permission:** 1 new (`taxonomies.manage`). The synchroniser grants it to Editor, and to Administrator and Super Admin, without touching any customised roles.
- **Deviation from the doc:** `media` has **no soft delete** (a deleted file must not stay publicly reachable). The doc is updated.
- **Deferred:** `users.avatar_media_id` and `pages.custom_css` go to Phase 4 with the builder, and `search_documents` to Phase 11.

## API changes

- **Added:** `GET /api/v1/resolve`, `GET /api/v1/pages/{path}`, `GET /robots.txt`, `GET /sitemap.xml`, `GET /sitemaps/pages.xml`, and `GET /preview/pages/{page}` (signed + auth)
- **Admin JSON:** `GET|POST /admin/api/media`

## UI changes

- **Admin navigation:** Content → Pages; Media → Library, Media categories, Tags; SEO → Redirects
- **New screens:**
  - Pages: list (tree order), edit (with publish box), revisions/compare
  - Media: library grid and upload, media details
  - Taxonomy terms, redirects
  - Settings: homepage + robots.txt
- **Public site:** CMS pages and preview

## Tests

| Suite | Result |
|---|---|
| Pest (MySQL `pacms_testing`) | **149 passed, 559 assertions** (Phase 2: 89), ~150 s |
| Vitest | **14 passed** (Phase 2: 9) |
| Larastan level 6 | No errors |
| Pint / ESLint | Passed / clean |

**Security tests added:**
- a draft, archived or deleted page is never public (shell, API, resolve)
- the payload never includes working-copy titles or internal fields
- preview requires signature + auth + permission, and expiry is enforced
- workflow permissions are checked per role
- upload attacks are rejected: PHP disguised as JPG, double extension, SVG with script, HTML, EXE
- EXIF/GPS is removed after re-encoding
- decompression-bomb guard
- media delete guard and force-delete permission
- private files are not reachable publicly
- redirect targets reject `javascript:`
- terms can't be deleted while in use

## Build

`npm run build` succeeds:
- **Public initial JS ≈ 95 KB gzipped**: React 81.7 + main 12.9 (budget 120 KB)
- **Media picker island:** 2.5 KB gzipped, loaded only where used

## Manual smoke test (HTTP against http://pacms.test, dev database)

Everything below was verified over HTTP:
- Admin sign-in; all new screens return 200.
- Upload of a 1800×1000 JPEG: stored as a ULID `.jpg` with WebP variants 320/640/960/1280/1800, served as `image/webp`.
- Setting alt text.
- Page created as a draft: the public URL returns **404** and the preview link **200**.
- Publish: the public URL returns **200**, with title, description, **og:image** and `summary_large_image`.
- `api/v1/resolve` and `api/v1/pages/…` return the payload with `srcset`.
- The media "where it is used" list shows the page.
- The sitemap lists the page.
- `robots.txt` includes the sitemap line.
- A `.php` file placed in public storage returns **403**.

The smoke-test page "Smoke Test Page" and its image remain in the local dev DB. Delete them in the admin if you like.

## Security check (SECURITY-ARCHITECTURE.md §15)

- [x] New routes are authorised by policy, permission middleware or controller checks, with tests for unauthorised roles.
- [x] Public data goes only through published snapshots and explicit payload fields (leak tests).
- [x] All input is validated with bounded lengths. Upload guard and URL/redirect validation are in place.
- [x] No new `{!! !!}` except the JSON payloads with `JSON_HEX_*` encoding (already covered). No `unserialize`. Raw SQL fragments are constant only (`selectRaw` counting in `TermController`).
- [x] Storage no-exec `.htaccess`. Private media uses a separate disk.
- [x] `composer audit` / `npm audit`: see Phase 2 (no new advisories on Intervention Image 4.3).

## Performance check

- Page payloads are cached by revision and version keys (1 row read + cache hit when warm).
- Eager loading throughout, with lazy-loading violations failing in tests.
- Image variants are generated off-request in a queued job. Picker and library use thumbnails, not originals.
- Sitemap output is cached by version.

## Deployment impact

- **New scheduled commands:** `pacms:publish-scheduled` (every minute) and `pacms:revisions:prune` (weekly). Both come with the existing cron line.
- **Queue:** image variants use the queue (production: the database queue plus the scheduler worker).
- **Local `.env` changed:** `QUEUE_CONNECTION=sync`, so variants are made immediately on the dev machine (Laragon has no cron).
- **Storage:** `storage/app/public/.htaccess` (Apache/LiteSpeed). For Nginx, the equivalent rule is in DEPLOYMENT-ARCHITECTURE.md §5.
- **New migrations:** `php artisan migrate --force` and `db:seed --class=ProductionSeeder --force`.

## Known issues / notes

1. **Pages have no body content yet.** Page content blocks arrive with the **Block Engine in Phase 4**. Pages currently show title, summary and featured image. This is by design (D-04).
2. **Animated GIFs** are flattened to their first frame by re-encoding (security trade-off). If the organisation needs animated GIFs, use MP4/WebM video instead.
3. **Revision compare** is field-level. The block-level visual diff comes with Phase 4/5.
4. The page edit form has no "unsaved changes" warning yet (planned with the builder autosave in Phase 5).
5. Tests take about 150 s, mostly MySQL migrations on Windows. `schema:dump` can speed this up later.

## Architectural decisions (Phase 3)

| Decision | Reason |
|---|---|
| `status` = working-copy state, and "Live" is separate (published snapshot) | Lets live pages go through review again without taking them offline |
| Any edit resets In review / Approved / Scheduled to Draft | Approval always applies to the exact content that goes live |
| Sub-pages publish only under a live parent; live parents with live children can't go offline | No orphaned public URLs |
| Top-level slugs reserved for application and future content-type prefixes (`programs`, `news` …) | Avoids future URL collisions with Phase 8 archives |
| Media deletion is permanent (no soft delete) | A soft-deleted file would still be publicly reachable at its URL |
| Preview payload embedded server-side | The draft never travels through the public API. Signature + auth + policy |
| Relative signed preview URLs | Work regardless of which host name the admin uses |

## Fixes during the Phase 3 review (2026-09-24 → 2026-10-05)

| Finding | Resolution | Commit |
|---|---|---|
| Public site unstyled while `npm run dev` runs: the dev server reported `http://[::1]:5173`, and CSP cannot express IPv6 literals, so the browser blocked its stylesheet and fonts | Vite dev server bound to `127.0.0.1:5173`. The server logs a warning if an IPv6 origin appears again | `319146c` |
| Dashboard out of date ("Phase 2 foundation" text) and empty for editors | New dashboard: content statistics (permission-filtered), **Waiting for your review** (approvers), **Your drafts**, **Scheduled to publish**, plus the existing health and activity panels. 3 dashboard tests added | `56ed1c2` (controller, committed mid-change) + this commit (view, tests) |

`56ed1c2` was committed while the dashboard change was half done (controller updated, view not yet). That left the dashboard erroring and 8 tests failing until the follow-up commit. **Current state: Pest 149 passed, Vitest 14 passed, Larastan and Pint clean.**

## Next phase

**Phase 4 — Block Engine:** block storage (adjacency list), core block types, field system, static/dynamic/external sources, display modes, style compiler, responsive overrides, the shared React block renderer and builder preview, and the first dynamic source (minimal News, per D-04).
**Not started. Waiting for your review and approval.**
