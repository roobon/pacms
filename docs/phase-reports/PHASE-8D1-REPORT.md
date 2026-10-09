# Phase 8D.1 — Admin-made content types: engine and types · Report

| | |
|---|---|
| Date | 2026-10-09 |
| Branch | `phase/8-content-modules` (not merged) |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). Awaiting review. |

```
PHASE:   8D.1 — content types made in the admin ("custom post types"): the engine change,
         Design → Content types with the field builder, permissions, admin screens and pages
STATUS:  READY FOR REVIEW. Not merged.
         Decisions applied (team lead, 2026-10-09: "use your defaults"):
         - a type with items cannot be deleted; it can be disabled (hidden, items kept)
         - new types get permissions like News (editors everything; authors and contributors write and submit)
         - delivered in two parts: 8D.1 (this) and 8D.2
ALSO ON THIS BRANCH (requested during the part):
         - demo content: php artisan pacms:demo --fresh
         - public design fixes found with the demo content
         - Slider block (full-width slideshow), used on the demo home page
```

## Implemented

| Area | What exists now |
|---|---|
| **Design → Content types** | New permission **"Manage content types"** (`content_types.manage`; administrators have it). Create a type with: name (plural) and one item (singular), menu icon, **address** (`/success-stories`), publishing (**editorial workflow** like News, or **active / inactive** like Team, with a display order; fixed once created), a listing page on/off, site search on/off. The list shows each type's address, field count, item count and status. |
| **Fields** | The **Custom Blocks field builder**, limited to: text, long text, rich text, number, checkbox, dropdown, radio buttons, multiple choice, web address, e-mail, image, file, date, date and time, video link, and repeater (rows of text, long text, web address, e-mail, number or date). For each field, choose where it appears: **in the details box** (short values), **as its own section** (long text, rich text, image, file, video, rows), or **not shown** (internal). |
| **Everything a built-in module has** | As soon as a type is saved, with no deployment: a menu entry under Content, the admin list and form (common fields plus the type's own fields), workflow and scheduling, revisions and restore, preview, SEO, sidebar choice, builder content, public **listing** (`/success-stories`) and **detail pages**, the sitemap and site search. |
| **Permissions** | Created with the type and shown in *Roles & Permissions* as "*Name* (content type)". Editorial types: `{type}.view, create, update_own, update_any, delete, submit, approve, publish`; managed types: `{type}.manage`. Given to roles like News; removed when the type is deleted. |
| **Rules** | The address must be free: not reserved, not another type's, not a top-level page (and pages can no longer take a type's address). Field keys may not clash with item columns (title, slug…). A type with items cannot be deleted; **disabling** hides it from the menu and website and keeps its items. Values of a removed field stay stored and return if the field is added again. |
| **Public pages** | Details box values are formatted (choices as their labels, dates, Yes, numbers as plain digits); sections show rich text, text, images, a file download, a click-to-load video (YouTube/Vimeo) or a list of rows. Cards show the first details-box value. Hidden fields never reach the website. JSON-LD `CreativeWork`. |

### Engine change (the "one model class = one type" assumption is gone)

Content types now provide their own **items query** (`query()`), **new item** (`newItem()`) and **admin URLs** (`adminUrl()`). Every place that used a model class to find items now asks the type: content service, admin controller and views, public resolver, previews, archives, sitemaps, search, block sources, link pickers, entity links, JSON export/import, the media usage list and the policies. Built-in modules behave exactly as before (all earlier tests unchanged and passing).

### How admin-made types work

- **Storage:** `content_types` holds each definition; all items share `custom_items`, with the type's own values in a JSON column. `CustomItem` maps those values to attributes, so forms, revisions and snapshots need no special code.
- **No new routes or config per type:** your deployment caches routes and config (`php artisan optimize`), so admin-made types use one route set with the type as a parameter (`/admin/types/{type}/…`), and the registry reads types from the database on each request.

## Files

**Created:**
- Migration `2026_10_14_100000_admin_made_content_types`.
- Models `CustomContentType`, `CustomItem`; type `AdminContentType`; service `ContentTypeService`; policy `CustomItemPolicy`.
- Controller `Admin\ContentTypeController`; views `admin/content-types/index`, `admin/content-types/form`.
- Island `content-type-fields.jsx` (+ `ContentTypeFields.jsx`, reusing the field builder's `FieldList`).
- Tests `Feature/Content/AdminContentTypesTest`.

**Modified:** `ContentType` (query, newItem, adminUrl, hasArchive, isAdminMade), `ContentTypeRegistry` (database types, reload), `ContentItem` (`contentTypeKey()`), `ContentService`, `ContentController`, content views (`index`, `form`, `revisions`), `PathResolver`, `PreviewController`, `SeoFilesController`, `ContentPayloadBuilder`, `SearchIndexer`, `ContentSource`, `SourceRegistry`, `BlockBuilderController`, `BlockPayloadResolver`, `Exporter`, `PortableTranslator`, `ValueValidator`, `EditorialPolicy`, `PermissionCatalog`, `PagePathService`, `AdminNavigation` and the admin layout, `ContentView.jsx`, public SCSS, `FieldBuilder.jsx` (export), `AppServiceProvider`, `routes/admin.php`, `vite.config.js`, `DemoSeeder`, `DATABASE-ARCHITECTURE.md`, `README.md`.

## Database changes

- New tables **content_types** and **custom_items**. See DATABASE-ARCHITECTURE §7.

## Tests

| Check | Result |
|---|---|
| Pest (full suite) | **317 passed** |
| Vitest | **72 passed** (14 files) |
| Larastan (level 6) | No errors |
| Pint, ESLint | Clean |
| Production build (Vite) | OK |

New tests (`Content/AdminContentTypesTest`):
- **Access:** only "Manage content types" may create types.
- **A new type works at once:** registry entry, 8 permissions with role defaults, menu entry, admin form with the type's fields, group in Roles & Permissions.
- **Clashes refused:** reserved and page addresses, reserved field keys, unsupported field types, unsupported row types. Pages cannot take a type's address.
- **Items:** stored and validated (required field, choice values, rich text cleaned); published and shown with formatted facts, sections in field order, hidden fields left out; listing card and sitemap.
- **Workflow and permissions:** authors submit but cannot publish; other roles have no access; built-in modules are not reachable through `/admin/types/…`.
- **Revisions:** they keep and restore the type's own fields.
- **Managed types:** one permission, display order, no listing page.
- **Disable and delete:** disabling hides a type and keeps its items; deletion is refused while items exist, and deleting removes the permissions.

The demo seeder now also creates two admin-made types: **Success stories** (editorial, with every kind of display) and **Board members** (active/inactive, no listing page).

## Not verified by me in a browser

- **The Content types screen and its field builder.** The views render in tests and the build passes, but I could not sign in to the admin from the headless browser.
- **The admin form of an admin-made type**, especially the rich text editor and the multiple-choice list. The public pages were checked in the browser.

## Known issues / deferred to 8D.2

- **Builder blocks:** no builder block per admin-made type yet (they can be listed in blocks from 8D.2).
- **Categories and documents** for admin-made types, and the media coverage tags left from 8C.2 (several taxonomies per module).
- **URL changes:** changing a type's address does not yet create redirects from the old URLs.
- **Menu:** the admin menu is not yet grouped when there are many types.
- **Deployment cache:** after deploying a code change that alters how pages are built, run `php artisan cache:clear`. Cached page data otherwise keeps the old output until the content changes (seen once here during development).

## Next

**8D.2:** builder blocks per type, categories and documents, address redirects, the menu for many types, coverage tags. Then the starter kit and **v0.8.0**.
