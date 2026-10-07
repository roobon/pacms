# Phase 6 — AI JSON · Completion Report

| | |
|---|---|
| Date | 2026-10-07 |
| Branch | `phase/6-ai-json` (not merged) |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). **Needs team review before Phase 7.** |

```
PHASE:   6 — AI JSON (schema, versioning, validation, security, import, export, preview,
         report, asset validation, missing-reference handling)
STATUS:  Complete. All automated checks pass. A real import with an internet image
         download worked on pacms.test. The import screens need your browser review.
```

## Implemented

| Area | What exists now |
|---|---|
| **Import screen** | Admin → Content → **Import JSON** (permission `import.run`, editors by default). Paste JSON or upload a `.json` file (≤ 2 MB). The screen lists your recent imports. |
| **AI prompt helper** | Instructions to copy into ChatGPT, Claude or Gemini: rules, then every block type on *this* site with its fields (including custom blocks), and an example. |
| **Validation stages** (CMS-BLOCK-SCHEMA.md §16) | 1. Parse and limits: 2 MB, 2,000 blocks, depth 12, 200 assets, UTF-8; a Markdown code fence is accepted. 2. Envelope. 3. Version: 1.x is accepted; newer minors are reported, other majors refused. 4–7. Schema, security and references, using **the same validator as every builder save**. |
| **Invalid content** | An invalid value is removed with a warning. A block that can't be placed is removed with a warning. A missing required value is an **error** and the import cannot run. Every entry has a JSON Pointer and the block's `key`. |
| **References** | Pages are found by path and news by slug; categories by taxonomy + slug; global blocks by key. They are stored as IDs, so later renames don't break them. A missing reference is reported and the field is left empty. External sources are listed under "External configuration requirements". |
| **Assets** | Per-image choice in the preview: **download** into the Media Library, **use a library image**, or **leave empty**. Missing media IDs are reported and **never substituted**. |
| **Safe downloads** (`SafeHttpClient`) | http(s) only on standard ports, no credentials in the URL. Every resolved address must be public: no loopback, private, link-local, CGNAT, metadata or reserved addresses, including IPv6 and IPv4-mapped forms. The connection is pinned to the checked address (no DNS rebinding), redirects are re-checked (max 3), and there are size and time limits. Downloaded files then go through the normal upload checks. Phase 10 can reuse it. |
| **Preview and confirmation** | The report is grouped by section (blocks, unsupported properties, security changes, missing references, assets, external requirements), with a summary (block types, live-content blocks, images) and a read-only builder preview. You choose the target, confirm the warnings, and run the import **once** (double clicks are ignored). |
| **Import result** | Always a **draft**: a new page (a number is added if the URL is taken), a template hidden from the builder, or blocks added to the end of a page's working copy. The import runs as a queued job (synchronously on the local sync queue). Each job is stored in `import_jobs` with its report (downloadable as JSON) and logged. |
| **Export** | **Export JSON** on the page edit screen, on templates, and in each block's ⋯ menu in the builder (permission `export.run`). Media becomes `assets` with the ID and public URL. Links and filters use `$ref` by path/slug, and global blocks are referenced by key. User data and history are never included. |
| **Round trip** | Tested: export a page, then import it on the same site, gives the same tree, and existing media is reused, not downloaded again. |
| **Schema** | JSON Schema 2020-12 generated from the block registry: one content definition per block type, with enums, required fields and lengths. `php artisan pacms:schema` writes the core version to `resources/schemas/pacms/1.0/`. The admin download is live and also includes custom types. Schema 1.0 is now **frozen**. |

## Files

**Created:**
- `app/Cms/Exchange/` `DocumentReader`, `PortableTranslator`, `ImportCleaner`, `ImportReport`, `Exporter`, `SchemaGenerator`
- `app/Services/Exchange/ImportService`, `app/Jobs/RunImport`, `app/Support/Http/` `SafeHttpClient`, `UnsafeUrlException`, `DownloadFailedException`
- `App\Models\ImportJob` + migration `create_import_jobs_table`, command `pacms:schema`
- `Admin\ImportController`, `Admin\ExportController`
- Views `admin/import/{index,show}`, component `x-admin.import-status`
- `resources/schemas/pacms/1.0/{document.schema.json, ai-prompt.txt}`
- Tests `Exchange/{SafeHttpClient,Import,Export}Test`

**Modified:**
- Validation: `BlockTreeValidator` (`withPendingAssets`), `ValueValidator` (pending `$asset` accepted during analysis).
- Admin: the page and template forms (export links), the builder ⋯ menu (Export as JSON), the builder definitions (export permission), navigation, routes.
- Other: the field component (`rows`), the morph map (`import_job`), copy buttons in the admin JS.
- Docs: `CMS-BLOCK-SCHEMA.md` (changelog, implementation notes, example 20.1 link), `DATABASE-ARCHITECTURE.md`.

**New dependencies:** none.

## Database changes

- **New table:** `import_jobs`: kind, status, title, schema_version, translated document, asset plan, report, confirmed options, result, error.
- **Simpler than the design (doc updated):** no uuid. The original payload is not stored, because the report records every change. Expiry and pruning come in Phase 13.

## API and route changes (admin, session + CSRF)

- Import:
  - `GET /admin/import`
  - `POST /admin/import` (20/min)
  - `GET /admin/import/{job}`
  - `POST /admin/import/{job}/confirm`
  - `GET /admin/import/{job}/report.json`
  - `GET /admin/import/schema.json`
- Export:
  - `GET /admin/export/pages/{page}`
  - `GET /admin/export/templates/{template}`
  - `POST /admin/api/export/blocks`
- No public API changes.

## Tests

| Suite | Result |
|---|---|
| Pest (MySQL `pacms_testing`) | **240 passed, 985 assertions** (Phase 5: 208) |
| Vitest | **58 passed** |
| Larastan level 6 / Pint / ESLint | No errors / passed / clean |

**Tests required by the roadmap:**
- **Spec examples:** every complete example in CMS-BLOCK-SCHEMA.md §20 is read from the document itself and analysed without errors.
- **Malicious samples:**
  - removed and reported: `<script>`, `onclick`, `javascript:` links, `$bind` outside custom blocks, custom CSS, `onclick` attributes, unknown types, unknown envelope properties
  - with the import stopped: required values made empty by that removal
- **Broken documents:** invalid JSON, missing or future versions, `site` packages, nesting too deep, and pages without a title are refused with a clear message.
- **Missing media is never substituted:** an unsafe internal image URL (SSRF) leaves the image empty, its block is removed with a warning, and the rest imports.
- **Round trip:** export then import gives an equivalent tree (blocks, links, filters, global block, background image, featured image).

**Other tests added:**
- **15 SSRF cases:** schemes, credentials, ports, loopback, private, metadata, CGNAT, IPv6, IPv4-mapped, DNS resolving to a private address, mixed DNS results, unresolvable names.
- **Downloads:** a redirect to the metadata address, the size limit, HTTP errors.
- **Running and permissions:** an import runs only once; authors can't import; other users can't open your import; template and export permissions.

## Manual check (pacms.test)

Through the real services, without simulated downloads, I imported a page with:
- a heading (level given as a number)
- rich text containing `<script>`
- a list
- an image from https://www.php.net

**Result:**
- **Script:** removed and reported.
- **Image:** downloaded through `SafeHttpClient`, checked by the upload guard, and stored as media #6.
- **Page:** "AI import smoke" (`/ai-import-smoke`) was created as a draft, and it's still in the dev DB for you to look at.

**Not verified by me in a browser:** the import screens (report layout, asset choices, preview) and the export buttons.

## Known issues / deferred

1. Custom block type **definitions** in a document are not imported; create the types first. Blocks of existing custom types import normally.
2. "Update in place" using exported `uuid`s is not offered: imports always create new blocks or pages.
3. Not available yet, so reported: external sources (Phase 10), page header/footer (Phase 9), hand-picked dynamic items (`pick`), audience visibility.
4. You can import blocks into a page from the Import screen, but not yet directly from inside the builder (paste JSON into the builder).
5. `import_jobs` are not pruned yet (Phase 13).
6. The preview does not show images that haven't been downloaded yet; they appear after the import.

## Review fixes

| Finding | Change |
|---|---|
| A ChatGPT-made component was refused: “A page document needs a "page" object” | Common envelope mistakes are repaired and noted instead of refused. A `page` document with its blocks at the top level becomes a page when a title is known, otherwise it imports as blocks or a section (you choose where they go). A missing or made-up `kind` (e.g. `component`) is worked out from the contents. A single block object is read as a list of one. The AI instructions now show the section/component shape too. |
| A second ChatGPT page had its blocks under `"content": {"blocks": […]}` and a `"status"` | Blocks are also found under `content.blocks`, `content`, `body`, `sections` or `components` (top level or inside `page`); `status` is ignored with a note (imports are always drafts). Test added. |
| An imported ChatGPT page had no space between sections; the final call to action ran edge to edge; 4 statistics wrapped into 3 + 1 | These were our defaults, not ChatGPT: sections and calls to action now get default vertical spacing (half the section token, so between two sections it is 3–6 rem), heroes get the full token. Blocks placed directly on the page line up with the site width. Statistics default to one column per number (max 4). Editor settings still override all of these (zero-specificity rules). |
| The error screen gave no way to correct the document; “Unknown import”, “1 errors” | The submitted JSON is kept (`import_jobs.source`) and shown in an editable box with **Check again**. The headings and plurals were fixed. Tests added. |

## Architectural decisions (Phase 6)

| Decision | Reason |
|---|---|
| Translate to the internal format, then use the builder's own validator | One set of security rules for imports and the builder; nothing import-specific can drift |
| Fix by removal, error only when removal can't fix it | Matches §16: unsupported or unsafe values become warnings, a missing required value stops the import |
| Leniency after confirmation (drop a block whose required image failed) | A reviewed import shouldn't fail because one image server is down; the report says what was dropped |
| `SafeHttpClient` built now, not in Phase 10 | Asset downloads need SSRF protection now; Phase 10 reuses it |
| Store the translated document, not the original payload | The report already records every change. Less sensitive data is kept |
| Imports always create drafts or hidden templates | Rule 8 of the schema: a person always reviews before anything goes live |

## Next phase

**Phase 7 — Media, advanced:**
- managing files: replace, categories and tags, bulk actions, filters and sort
- private files and duplicate detection
- the decision on allowing SVG files
- blocking deletion of files that are in use
- a command to regenerate image sizes

**Not started. Waiting for your review and approval.**
