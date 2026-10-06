# Phase 4 — Block Engine · Completion Report

| | |
|---|---|
| Date | 2026-10-05 |
| Branch | `phase/4-block-engine` (pushed, not merged) |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). **Needs team review before Phase 5.** |

```
PHASE:   4 — Block Engine (+ minimal News, per decision D-04)
STATUS:  Complete. Waiting for team review.
```

## Implemented

| Area | What exists now |
|---|---|
| **Block storage** | `blocks` adjacency list (owner morph, parent, position, type, 7 JSON sections, hidden flag) and a `block_types` registry. Saving validates the whole tree first, then replaces the owner's rows in one transaction, keeping each block's ULID `uuid`. Limits: depth 12, 2,000 nodes, 2 MB payload. |
| **Staged publishing** | The block tree is part of the page snapshot. Editing blocks on a live page never changes the live version until *Publish changes*. Revisions, compare and restore include blocks, and a restore re-validates them. |
| **22 core block types** | *Layout:* Section, Container, Columns, Column. *Basic:* Heading, Rich text, Image, Button, Button group, Divider, Spacer, Icon, Video. *Content:* Hero, Cards, Statistics, Accordion and Accordion item, FAQ, Quote, Call to action. *Dynamic:* News. Each type is a PHP class that declares fields, defaults, allowed parents/children, source modes and display modes. |
| **Field system** | 15 field types (text, textarea, rich text, number, checkbox, select, link, email, image, media, icon, color, date, video URL, repeater), validated and cleaned on the server. Rich text goes through an HTML sanitiser allowlist. Video URLs are accepted only from YouTube and Vimeo. |
| **References** | Media as `{"$media": id}` (must exist, right kind, public). Links: URL (safe protocols only), anchor, email, or **entity** (page/news by id, resolved to the current URL at render time; unpublished targets render as plain text). |
| **Content sources** | One item shape for every source: *static* (hand-entered items), *dynamic* (News: whitelisted filters category/featured, orders latest/oldest/title, max 24, published only) and *external* (registered providers only, never a raw URL; real providers come in Phase 10). |
| **Display modes** | Grid, list, carousel (CSS scroll-snap, no library), featured and accordion, with validated options. Any source can use any display mode the block allows. |
| **Style system** | Layout (container, width, min height, grid columns, alignment, gap, padding, margin), style (background colour/gradient/image + overlay, typography, border, radius, shadow, entrance animation), responsive overrides (tablet ≤ 991.98px, mobile ≤ 767.98px), and advanced settings (anchor, classes, safe attributes behind a permission, hide per device). Values are **design tokens or validated literals only**. A compiler in JavaScript turns them into CSS scoped to `.b-<uuid>`, so free-form CSS is never accepted. |
| **Shared renderer** | `resources/js/blocks/` is used by both the public site and the builder preview. It renders recursively, and an error boundary per block means one broken block can't break the page. Unknown types are hidden publicly and flagged in preview. Videos are click-to-load (`youtube-nocookie`). The accordion follows ARIA, and if the blocks already contain an H1 the page skips its default title. |
| **Builder (basic)** | On the page edit screen: a **structure tree** (add, select, move up/down, duplicate, hide, delete, with the same placement rules as the server), an **inspector** (Content, Source, Display, Layout, Style, Advanced tabs, with link and media pickers and a TipTap rich-text editor), and a **live preview iframe** with desktop/tablet/mobile widths. The preview is resolved by the server and sent to the iframe with origin-checked `postMessage`; clicking a block in the preview selects it. The tree is saved with the page form, server errors are shown per block, and leaving with unsaved changes asks for confirmation. |
| **Minimal News** | Admin list and form (title, slug, excerpt, featured image, category, featured flag), publish/unpublish/delete with permissions (authors write, editors publish), and a public `/news/{slug}` page with `NewsArticle` JSON-LD. It feeds the dynamic News block. Publishing news refreshes cached page payloads. |

## Files

**Created (main):**
- Migrations: `create_block_tables` (block_types, blocks), `create_news_table`
- `app/Cms/Fields/` (Field, FieldValidator, VideoUrl), `app/Cms/Validation/` (Errors, ValueValidator), `app/Cms/Blocks/` (BlockType, BlockRegistry, BlockTreeValidator, BlockTreeRepository, BlockPayloadResolver, StyleValidator, 22 `Types/*`), `app/Cms/Display/DisplayModeRegistry`, `app/Cms/Sources/` (SourceRegistry, DynamicSource, NewsSource, ExternalProvider)
- `app/Support/Html/HtmlSanitizer`
- Models `Block`, `BlockType`, `News`; `NewsPolicy`; `NewsService`, `NewsPayloadBuilder`; `NewsController`, `Admin\Api\BlockBuilderController`; command `pacms:blocks:sync`
- Views `admin/news/{index,form}`
- JS: `resources/js/blocks/**` (renderer, components, display modes, style compiler, SmartLink, Image), `resources/js/admin/builder/**` (store, tree, inspector, panels, fields, preview frame), `admin/islands/page-builder.jsx`, public `NewsView`, `BuilderPreviewPage`, `Breadcrumbs`
- Tests: `Blocks/{BlockTree,ContentSources,BuilderApi}Test`, `News/NewsTest`, Vitest `compile.test.js`, `BlockRenderer.test.jsx`, `tree.test.js`

**Modified:** `Page` (snapshot includes blocks), `PageService` (blocks, media usage tracking per block), `PagePayloadBuilder`, `PageRequest`, `PageController`, `PathResolver`, `SpaController`, `ResolveController`, `PreviewController` (builder preview shell), `AdminNavigation`, `AppServiceProvider`, `ProductionSeeder`, `config/pacms.php`, routes, `admin/pages/form.blade.php`, `MediaPickerDialog` (portal, so it works inside the builder), `Image.jsx` (moved to `blocks/common`, re-exported), SCSS, `vite.config.js`, `tests/Pest.php`.

**New dependencies:** `symfony/html-sanitizer` (PHP), `zustand` and `@tiptap/*` (admin builder only).

## Database changes

- **New tables:** `block_types`, `blocks`, `news`
- **Deviations from the doc (doc updated):** `blocks.global_block_id`, and the custom-type columns on `block_types`, wait for Phase 5. `news` has no `publish_at` yet (Phase 8).

## API changes

- **Public:** page payloads now include resolved `blocks`. `GET /api/v1/resolve` handles `kind: "news"`. New public route `/news/{slug}`.
- **Admin JSON:** `GET /admin/api/blocks/definitions`, `POST /admin/api/blocks/resolve` (validates and resolves a tree for preview), `GET /admin/api/link-targets`
- **Builder preview shell:** `GET /__builder-preview` (signed-in, `pages.view`, `no-store`, `noindex`)

## UI changes

- **Page edit:** full-width **Page content** builder (structure, inspector, live preview)
- **Admin navigation:** Content → News, News categories
- **Public site:** pages render their blocks, news article pages

## Tests

| Suite | Result |
|---|---|
| Pest (MySQL `pacms_testing`) | **186 passed, 712 assertions** (Phase 3: 149), ~225 s, after review fixes |
| Vitest | **37 passed** (Phase 3: 14), after review fixes |
| Larastan level 6 | No errors |
| Pint / ESLint | Passed / clean |

**Security tests added:**
- rich text: script, event handlers and `javascript:` URLs are removed
- links: `javascript:` URLs rejected (only http/https, relative, `mailto:` and anchors are allowed)
- media: private or missing media can't be referenced
- styles: the CSS compiler ignores anything that isn't a token or a validated value (injection test)
- custom attributes only with permission, and only `data-*`/`aria-*`/`role`/`title`/`lang`
- dynamic sources: unknown filters are ignored, limit capped, unpublished news never shown
- external sources: only registered providers (an SSRF-style URL is rejected)
- placement rules, depth/node limits
- hidden blocks are absent from the public payload
- the builder API requires permission
- authors can't publish news or change published news

## Build

`npm run build` succeeds:
- **Public initial JS ≈ 94 KB gzipped:** React 81.7 + main 12.3 (budget 120 KB). Block components are in the main bundle; the builder preview page is lazy-loaded.
- **Page builder island:** 137 KB gzipped (mostly TipTap), loaded **only on the page edit screen**.

## Manual smoke test (http://pacms.test, dev database)

Verified over HTTP with the services the builder uses:
- A page "Block smoke" (`/block-smoke`) with Section → H1 heading, Columns (rich text + button linking to a news item by id) and a dynamic News block was created and published.
- The public API returned the tree with `<script>` removed from rich text, the entity link resolved to `/news/phase-4-smoke-story`, and the News block listing the published article.
- `/block-smoke` and `/news/phase-4-smoke-story` return 200. An unknown news slug returns 404, and `/__builder-preview` redirects guests to login.

**Not verified by me (needs your browser review):** the builder UI itself: adding blocks, the inspector, media/link pickers, live preview and device switch, saving and validation messages. The smoke page and news item remain in the dev DB.

## Security check (SECURITY-ARCHITECTURE.md §15)

- [x] New routes are authorised (policy, permission middleware), with tests for unauthorised roles.
- [x] All block input is validated on the server per field. Nothing from the builder is trusted, and the client-side rules only mirror the server.
- [x] No raw HTML except sanitised rich text. No free-form CSS. No raw external URLs as sources.
- [x] The preview iframe accepts messages only from the same origin and the parent window. The preview shell is `no-store` and `noindex`.
- [x] Public payloads come from the published snapshot. Hidden blocks and unpublished news are excluded.

## Performance check

- One query loads a whole block tree. Media, page and news references are resolved in batches (no N+1).
- Payloads stay cached by cache-version keys, with `news` added to the fingerprint.
- Scoped CSS is generated once per render and is only as large as the styles actually used.
- The builder preview resolves on a debounce and aborts stale requests.

## Deployment impact

- `php artisan migrate --force` and `php artisan db:seed --class=ProductionSeeder --force` (which also syncs block types). Alternatively, run `php artisan pacms:blocks:sync` after deploying new block types.
- `composer install` and `npm ci && npm run build` (new dependencies).

## Known issues / deferred to Phase 5

1. **Builder:** no drag-and-drop yet (move up/down buttons), no undo/redo, no copy/paste between pages, and no per-device editing of responsive overrides in the UI (the engine and renderer support them).
2. **Style UI:** gradient, border and typography controls are not in the inspector yet (the engine supports them). No custom CSS.
3. **Not yet available:** custom block types, block templates, global blocks, and JSON import/export.
4. Slider, tabs and gallery blocks, and FAQ `FAQPage` JSON-LD.
5. **News is minimal:** no scheduling, revisions, body blocks or archive page (full module in Phase 8). There are no real external providers yet (Phase 10).

## Review fixes (2026-10-06)

| Finding | Change |
|---|---|
| The page title should be optional (home page review) | New **Show page title** checkbox in *Page settings*, on by default (`pages.show_title`, migration `add_show_title_to_pages_table`). It is part of the snapshot, so it goes live with *Publish changes* and is restored with revisions; older snapshots restore with the title shown. When off, the title and summary are not shown, but a visually hidden H1 remains for screen readers and search engines. A Heading 1 block still replaces the default title. Tests: 1 Pest, 3 Vitest. |
| A card in a half-width column was only ~150px wide | Item grids (Cards, News, Statistics) treat the per-device column count as a **maximum**: columns never get narrower than a minimum width (15rem, statistics 9rem), so a 3-column grid in a narrow column drops to fewer columns, and fewer items than columns share the row (`auto-fit`). |
| The builder's *desktop* preview showed the phone layout | The preview frame was as wide as the stage (~690px), below the site's 768px phone breakpoint. It now renders at real device widths (desktop 1280px, tablet 820px, phone 390px) and is scaled down to fit, with the scale shown in the toolbar. |
| News cards appeared twice on the home page screenshot | Not a defect: the payload holds three articles once each. The repeated row came from the full-page screenshot tool stitching the page. |

## Architectural decisions (Phase 4)

| Decision | Reason |
|---|---|
| Block types are PHP classes and are synced to `block_types` | One source of truth for validation. The DB row serves the builder and future custom types |
| Tree saved with the page form (hidden input), not autosaved | Keeps optimistic locking, staged publishing and revisions exactly as in Phase 3 |
| Entity links and media stored by id and resolved at render time | Renames and moves never break links. Unpublished targets disappear safely |
| Styles = tokens or validated literals, compiled to scoped CSS on the client | No CSS injection surface, and a theme change restyles every page |
| Every source normalises to one item shape | Display modes and renderers don't care where items come from |
| Preview is resolved by the server, rendered by the shared public renderer | What editors see is what visitors get |

## Next phase

**Phase 5 — Advanced builder:** drag-and-drop, undo/redo, templates, global blocks, custom block types, import/export, and the remaining style controls.
**Not started. Waiting for your review and approval.**
