# Phase 8D.2 — Admin-made content types: blocks, categories, documents, addresses, menu · Report

| | |
|---|---|
| Date | 2026-10-09 |
| Branch | `phase/8-content-modules` → merged into `main` 2026-10-09 |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). Approved 2026-10-09. |

```
PHASE:   8D.2 — completes admin-made content types: a builder block per type, categories and
         documents, redirects when a type's address changes, several taxonomies per module
         (media coverage tags), and a grouped admin menu
STATUS:  APPROVED by the team lead (2026-10-09) and merged into main.
         Decisions applied (team lead, 2026-10-09: "use your defaults"):
         - D-1 a type whose block is used on a page, global block or template cannot be deleted;
               the message names the places
         - D-2 media coverage uses the shared "Tags" list
         - D-3 the menu is grouped into folding sections (Modules, Your content types,
               Categories and tags); Import JSON moves to Tools
         - D-4 delivered as one part; then the starter kit and v0.8.0
ALSO:    JSON Feed replaces outbound RSS in the plan (D-15, docs only; built in Phase 10)
```

## Implemented

| Area | What exists now |
|---|---|
| **A block per type** | Every type made in the admin is in **Add block → Dynamic** with its name and icon (e.g. *Researches*, *Success stories*). Same options as the built-in collections: heading, number of items, order, category and "featured only" filters, grid / list / carousel / featured. Cards show image, title, summary, date and the first details-box value. The block exists as soon as the type is saved. |
| **Disabled types** | Their block leaves *Add block*; pages that already have it still save, and the website leaves it out. Enabling the type brings it back. |
| **Deleting (D-1)** | Refused while the type's block is used, e.g. *The "Success stories" block is used in 1 place (page "Stories"). Remove it there first, or disable the type instead.* (as well as while the type has items, from 8D.1). |
| **Categories** | Option **Categories** on the type. It adds "*Research* categories" under *Categories and tags* (hierarchical), a picker in the item form, a category filter on the listing page and in the block, and the category on the item's page. Deleted with the type. |
| **Documents** | Option **Documents** on the type: the item form gets the documents list (as Projects and Programs), shown for download on the item's page. |
| **Address changes** | Changing `/success-stories` to `/stories` records permanent (301) redirects for the listing page and every item, visible under *SEO → Redirects*. The old address stays reserved: pages and other types cannot take it. Moving back frees it. |
| **Several taxonomies per module** | The engine now supports more than one taxonomy per module (`ContentType::taxonomies()`). **Media coverage has Tags** (the shared list) besides its categories: chosen in the form, shown as tags on the item's page, kept in revisions. |
| **Admin menu (D-3)** | *Content*: Pages, then folding groups **Modules** (built-in types and Testimonials), **Your content types**, **Categories and tags** (every category list and Tags). The group holding the current page is open; others remember whether you opened them. *Import JSON* is under a new **Tools** heading. |

## How it works

- **Blocks:** `ContentTypeBlock` (slug `type/{key}`) is built from each `content_types` row by `BlockRegistry`; the service writes its `block_types` row whenever a type is saved (existing types get theirs in the migration, and `pacms:blocks:sync` keeps them in step). The website renders them with the generic collection component (`type/*` in `registry.js`).
- **Usage:** block trees record their uses in `content_references` (context `content_type_block`), the same way custom blocks are tracked.
- **Taxonomies:** an admin-made type's categories use the taxonomy `ct_{key}`; `ContentTypeRegistry::taxonomies()` lists them with the configured ones for the Categories screens and the menu. The item form sends one list of term ids; each taxonomy keeps the ids that are its own.

## Files

**Created:** `Cms/Blocks/Types/ContentTypeBlock`, migration `2026_10_15_100000_content_type_blocks_and_former_prefixes`, view `components/admin/nav-link`, this report.

**Modified:** `BlockRegistry` (type blocks, `syncContentTypes()`), `BlockTreeRepository` (usage), `BlockPayloadResolver` and `SourceRegistry` (disabled types), `ContentType` (`taxonomies()`), `AdminContentType` (categories, documents), `MediaCoverageType` (tags), `ContentTypeRegistry` (`taxonomies()`), `ContentTypeService` (blocks, delete check, categories, redirects), `ContentTypeController` and `content-types/form`, `ContentController`, `ContentService`, `ContentItem` (snapshots), `ContentPayloadBuilder` (`taxonomies` in the payload), `TermController`, `PagePathService`, `CustomContentType`, `AdminNavigation` and the admin layout, `admin/content/form`, `admin.scss`, `app.js`, `BlockRenderer.jsx`, `registry.js`, `collections.jsx`, `ContentView.jsx`, `public.scss`, `DemoSeeder`, `DATABASE-ARCHITECTURE.md`, `CMS-BLOCK-SCHEMA.md`.

## Database changes

- `content_types.former_prefixes` (json, nullable).
- A `block_types` row `type/{key}` per admin-made type (created by the migration for existing types).

## Tests

| Check | Result |
|---|---|
| Pest (full suite) | **324 passed** (1,811 assertions) |
| Vitest | **72 passed** (14 files) |
| Larastan (level 6) | No errors |
| Pint, ESLint | Clean |
| Production build (Vite) | OK |

New tests:
- **Blocks:** a type's block is in the builder definitions (Dynamic, its icon, insertable) and lists the type's published items with the details-box value on cards.
- **Disabled and deleted:** a disabled type's block is not insertable, still saves and is left out on the website; deleting is refused while a page uses the block (message names the page) and works once it is removed.
- **Categories and documents:** the category screen, terms of other taxonomies refused, the form shows categories and documents, category on the item, archive and block filter, the menu entry.
- **Addresses:** 301s for the listing and items, the old address reserved for pages and types, moving back frees it.
- **Menu:** groups present; the group with the current page is open, the others closed.
- **Media coverage tags:** chosen with the category, shown on the page, removed on save without them.
- **Demo content:** Success stories with categories and a document, its block on the home page, coverage with tags.

## Checked in the browser (local, signed in as super admin)

- The grouped menu (Researches page: *Your content types* open, others folded).
- **Add block → Dynamic** shows *Board members*, *Researches* and *Success stories*.
- The content type form with the new *Categories* and *Documents* options.

Not checked in the browser: the tag list on a coverage page (covered by API tests), the redirect screen entries.

## Notes for your local copy

- Run `php artisan migrate` after pulling (done here). Your *Researches* type got its block at once.
- `php artisan pacms:demo --fresh` now also shows the Success stories block on the home page and the Board members block on About us. It deletes everything, including *Researches*.

## Known issues / deferred

- **JSON import of `type/…` blocks** works only on a site that has the same type (other sites report an unknown block type).
- **Archive filter by tag** is not offered (tags are shown on pages only).
- **Deployment cache:** after deploying, run `php artisan cache:clear` as noted in 8D.1.

## Next

The **starter kit** (designed page templates, section templates, global blocks, one install command), then tag **v0.8.0**.
