# Phase 8B — Content Modules: Projects, Programs, Publications · Report

| | |
|---|---|
| Date | 2026-10-08 |
| Branch | `phase/8-content-modules` (not merged) |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). **Needs team review before Part 8C.** |

```
PHASE:   8B — Projects, Programs, Publications on the content engine; documents;
         module sitemaps
STATUS:  Built and tested on the branch; waiting for the team lead's review.
```

Part 8B adds three modules to the engine built in 8A. Each one got the same admin screens, workflow, revisions, preview, sidebar, archive page, detail page and page-builder block without new engine code. The new work is mostly what makes these modules different: documents, repeaters and the new detail sections.

## Implemented

| Area | What exists now |
|---|---|
| **Projects** | Project status (Ongoing, Planned, Completed, Paused), start and end date, location, project manager, project website, **Documents**. Archive `/projects` with *All projects / Ongoing / Completed / Planned*. The detail page shows Status, Period ("March 2024 – June 2026" or "Since March 2024"), Location and Manager, plus a "Visit the project website" button. Cards show the status, period and place. JSON-LD `Project`. |
| **Programs** | **Objectives** (a list) and **Activities** (title and description), entered with add / move up / move down / remove controls. **Documents**. Shown on the detail page as numbered lists. |
| **Publications** | Publication date, author(s), cover (the image), **document to download** (chosen from the library), external link, categories (`publication_category`). Listed newest *publication* first; undated ones fall back to their website date. The detail page has Published and Author, plus "Download PDF (1.2 MB)" and "Read online" buttons. JSON-LD `CreativeWork`. |
| **Documents** | Projects and programs have a Documents section: choose files from the Media Library or upload them there, add an optional label, and reorder or remove them. **Only public files** are offered and accepted, because visitors download them. A listed file can't be deleted or made private (usage is tracked), and documents are kept in revisions. |
| **Media picker** | Now works for documents as well as images (PDF and Office files): document icon and file name instead of a thumbnail, and upload without alt text. |
| **Blocks** | New **Projects**, **Programs** and **Publications** collection blocks. Projects can be filtered by status; all three support category/featured filters where they apply, and grid, list, carousel or featured display. The four module blocks (with Events) now share one base class and one React component. |
| **Sitemaps** | `/sitemap.xml` now lists one sitemap per module with published items (`/sitemaps/news.xml`, `/sitemaps/events.xml`, `/sitemaps/projects.xml`…). Each lists the archive page and every published item except those set to "hide from search engines". This also fixes 8A, where news and events were missing from the sitemap. |
| **Detail page** | Generic sections any module can use: key facts, action buttons, numbered lists and downloadable documents. The website publish date in the header is now shown for news only. |

### Changes requested during review (2026-10-08)

- **List block → Style → "None (plain lines)"**: items without bullets, numbers or icons.
- **Builder item rows overlapping**: the new content-form styles reused the builder's `.pa-repeater` class names and broke its item rows. They now have their own names (`.pa-list-editor`).
- **Summary under the title**: regular weight in the body text colour, the same width as the title.
- **Document block** (builder → Basic): choose a PDF or Office file from the library. A PDF can be shown **in the page** (480, 720 or 960 px high) or as a **download card** (name, description, type, size, Download button). The card is always shown, and on phones the embedded viewer is hidden because phones often show it blank. Only public files are accepted, and a file used in a block can't be deleted or made private.
- **HTML block** (builder → Basic): paste your own HTML for tables, layouts and formatting. **Cleaned on save**: scripts, `<style>`, iframes, forms, event handlers, `javascript:` links and inline styles that load anything (`url()`, `@import`) are removed. Classes (including Bootstrap's), ids, ARIA attributes and other inline styles are kept. Only roles with the new permission **`blocks.custom_html`** (Administrator by default) can add or change it. Other editors can keep, move or delete an existing HTML block, but not change it: the server signs the markup when a permitted user saves it, and refuses changes without that signature.
- **Save page as template** (builder toolbar, Structure panel): saves every block on the page as a "Whole page" template, available under Add block → Templates.

## Files

**Created:**
- Migration `2026_10_11_100000_content_modules_projects_programs_publications`.
- Models `Project`, `Program`, `Publication`, `Attachment`; policies for the three modules.
- Types `ProjectType`, `ProgramType`, `PublicationType`; blocks `ContentCollectionBlock` (base), `ProjectsBlock`, `ProgramsBlock`, `PublicationsBlock`.
- Admin island `content-fields.jsx` (`RepeaterField`, `DocumentsField`).
- Blocks `DocumentBlock`, `HtmlBlock`; builder input `HtmlInput.jsx`.
- Tests `Feature/Content/ProjectsProgramsPublicationsTest`, `Feature/Blocks/HtmlAndDocumentBlocksTest`.

**Modified:**
- Engine: `ContentType` (media and repeater fields, dates, `documents()`, `showsPublishDate()`), `ContentItem` (attachments, documents in revisions), `ContentService` (documents, media usage), `ContentPayloadBuilder` (documents, `show_date`).
- Admin and public controllers: `Admin\ContentController` (validation and form data), `SeoFilesController` (module sitemaps).
- Blocks: `EventsBlock` (now on the base class), `ListBlock` ("None" style), `BlockTreeValidator` (HTML block signature), `BlockPayloadResolver` (file extension), `Field`/`FieldValidator` (`html` type, `accept('document')` for media fields), `HtmlSanitizer::html()`, `PermissionCatalog` (`blocks.custom_html`).
- Builder: `ImageInput` (documents), `FieldInput` (HTML), `Palette` (HTML only with the permission), `StructurePanel` (Save page as template).
- Front end: the media picker (documents), `ContentView` (facts, actions, lists, documents), `ItemCard` (`meta.place`), `collections.jsx`/`registry.js` (shared `CollectionBlock`).
- Views and styles: `admin/content/form`, the media-picker component, admin and public SCSS.
- Config, routes and docs: `config/pacms.php`, `AppServiceProvider`, `routes/web.php`, `vite.config.js`, `DATABASE-ARCHITECTURE.md`.

## Database changes

- **projects**, **programs**, **publications:** new tables. Common content columns, their own columns (see DATABASE-ARCHITECTURE §7) and the publishable columns, with indexes on status/published_at, status/publish_at and featured.
- **attachments:** new table (media_id restrict, attachable morph, label, position).

## Tests

| Check | Result |
|---|---|
| Pest (full suite, parallel) | **280 passed** (1344 assertions) |
| Vitest | **63 passed** (11 files) |
| Larastan (level 6) | No errors |
| Pint, ESLint | Clean |
| Production build (Vite) | OK |

New tests:
- `Content/ProjectsProgramsPublicationsTest`: admin forms, project with status, dates and ordered documents (public payload, JSON-LD, usage protection), private and wrong files refused, archive views and the Projects block by status, program repeaters (empty rows dropped, emptied lists cleared, length limits), publication download and order, revision restore of lists and documents, deleting an item frees its files, module sitemaps with hidden items left out.
- `Blocks/HtmlAndDocumentBlocksTest`: HTML cleaning (kept vs removed), the permission and signature rule (editors keep or move, but cannot add or change), the Document block (public PDF resolved, private and image files refused, usage protection), saving a whole page as a template.
- `App.test.jsx`: facts, actions, lists and documents on a detail page.

## Not verified by me in a browser

- The Objectives/Activities and Documents controls (keyboard use, screen reader announcements).
- The document picker's upload of Office files.
- The new detail sections and the Projects/Programs/Publications blocks on phones.

## Known issues / deferred

- **Partners, the project manager as a team member, and galleries** on projects and programs need the Partners, Team and Galleries modules (8C). The project manager is plain text for now.
- **RSS feeds** (`/rss/projects.xml` etc., from the master prompt) are not built yet. I suggest the SEO phase.
- Programs have no categories (the spec doesn't list any). It's one line to add if wanted.
- The database design named the title column `name` for projects and programs. The engine uses `title` everywhere; the admin label is still "Title".

## Architectural decisions (Phase 8B)

1. **Field types belong to the engine.** Media fields, repeaters and dates live in `ContentType`, so later modules (8C) can use them without new code.
2. **Documents are attachments, not blocks.** They are tracked as usages (protecting the files) and kept in revisions. Only public files can be used.
3. **One collection block base.** Module blocks differ only in their name, icon and default filters.

## Planned after 8C: starter kit (team lead's request, 2026-10-08)

Ready-made, professionally designed page templates (Home, About, Contact, module landing pages…), section templates (hero, features, stats, CTA, FAQ, partner logos…) and global blocks (footer, CTA band, module sidebars), installed with one command and editable like anything else. It comes after 8C, because the Team, Testimonials, Partners and Galleries blocks it needs are built there.

## Next part

**8C: Team, Partners, Testimonials (moderation), Media Coverage, Galleries, search indexing.** Do not start it until 8B is reviewed.
