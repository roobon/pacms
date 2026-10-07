# Phase 5 — Custom Block Builder · Completion Report

| | |
|---|---|
| Date | 2026-10-07 |
| Branch | `phase/5-advanced-builder` (not merged) |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). **Needs team review before Phase 6.** |

```
PHASE:   5 — Custom Block Builder (master prompt) / full builder UX (roadmap)
STATUS:  Complete. All automated checks pass. The builder UI has not been browser-tested
         by me, so it needs your review.
```

## Implemented

| Area | What exists now |
|---|---|
| **Global blocks** | Design → Global blocks. A shared tree placed on pages with a `global-ref` block. **Staged**: saving changes nothing live, and *Publish changes* updates every page that uses it (page caches refresh automatically). A "used in N places" list links to each page. Deleting a block that is still used is refused. **Detach** (permission `global_blocks.detach`) turns a placement into a local copy, and the action is logged. **Convert to global block** in the builder moves the selected block into a new, published global block. Global blocks cannot contain global blocks, so there are no loops. |
| **Templates** | Design → Templates, plus **Save as template** in the builder (scope block / section / page, with a category). Insert from the palette's *Templates* tab: a **deep copy** with fresh IDs, so editing a template later never changes pages that used it. The *Offer in the builder* switch hides a template from the palette. |
| **Custom block types** | Design → Custom blocks (permission `block_types.manage`, Administrator by default). **Field builder:** 20 field types including repeaters nested up to 3 levels, plus required, help text, min/max length and options. **Layout:** built with the same builder from core blocks. Each block setting can be **linked to a field** (`$bind`). **Repeat for each row** repeats blocks per repeater row. **Show when** shows blocks only when a field is filled, empty or equal to a value. **Preview** uses sample values. **Staged** with a version number: publishing updates every block of the type, and the values editors entered are kept. **Disable** stops new use while existing blocks keep showing. Deleting a type that is in use is refused. |
| **Safe rendering** | The server expands custom blocks and global blocks into ordinary core blocks before the page data is built. Bindings, conditions and repeats never reach the browser, and the same React components and checks render everything. Plain text linked into rich text is escaped. Values that no longer fit a changed field type are dropped, never guessed. A block whose required setting is linked to an empty field is left out. |
| **Builder UX** | **Drag and drop** (dnd-kit): drag left or right to nest, invalid targets turn red, and moves are announced to screen readers. **Keyboard:** dnd-kit keyboard dragging, plus Alt+↑/↓ (move) and Alt+←/→ (out of / into a container). **Undo/redo** (100 steps; typing in one field counts as one step). **Copy/paste**, including between pages and browser tabs. **Duplicate, hide/show, rename, collapse.** **Shortcuts:** Ctrl+Z, Ctrl+Shift+Z / Ctrl+Y, Ctrl+C, Ctrl+V, Ctrl+D, Delete. **Palette** with Blocks / Templates / Global tabs and search. **Autosave** every 60 s to a per-user autosave revision (valid trees only), with *Restore / Discard* on the next visit. |
| **Responsive controls** | With the tablet or phone preview active, the Layout and Style tabs edit **that device's overrides** (empty = same as desktop, with a *Clear* link). Settings that only make sense once per block (content width, columns, maximum width) stay desktop-only. |
| **Style controls** | New: **gradient** (angle and 2–4 colour stops), **border** (width, line, colour, sides), **typography** (font, size, weight, letter case). All values are tokens or validated literals, as before. |
| **Live preview** | Renders at real device widths (1280 / 820 / 390 px) and is scaled to fit (from the Phase 4 review). It now also previews global blocks, templates and custom type layouts. Blocks inside a global or custom block are locked: a click selects the whole block. |
| **Repeater editor** | Add, remove, duplicate, reorder, nest and per-field errors (already in Phase 4; now also driven by custom field definitions). |

## Files

**Created (main):**
- Migration `create_reusable_block_tables` (global_blocks, block_templates, custom-type columns on block_types, blocks.global_block_id)
- Models `GlobalBlock`, `BlockTemplate`; `BlockType` extended (custom types, revisions, staging)
- `app/Cms/Blocks/` `CustomBlockType`, `BlockExpander`, `Scope`, `Types/{GlobalRef,Repeat,When}Block`
- `app/Cms/Fields/` `Bindings`, `FieldDefinitionValidator`
- `app/Services/Blocks/` `GlobalBlockService`, `BlockTemplateService`, `CustomBlockTypeService`, `ManagesBlockOwners`
- Controllers `Admin\{GlobalBlock,BlockTemplate,CustomBlockType}Controller`, `Admin\Api\ReusableBlockController`
- Views `admin/global-blocks/*`, `admin/block-templates/*`, `admin/block-types/*`, component `x-admin.block-builder`
- JS: `admin/builder/{Palette,ActionDialog,FieldBuilder}.jsx`, `fields/BindableField.jsx`, `hooks/useGlobals.js`, `blocks/components/reusable.jsx`
- Tests: `Blocks/{GlobalBlocks,Templates,CustomBlockTypes,Autosave}Test`, Vitest `store.test.js`, and additions to `tree.test.js` and `BlockRenderer.test.jsx`

**Modified:**
- Block engine: `BlockRegistry` (custom types), `BlockTreeValidator` (contexts, transparent wrappers, bindings, global refs), `BlockTreeRepository` (global_block_id, usage references), `BlockPayloadResolver` (expansion first), `BlockType` (contexts, transparent).
- Fields: `Field` and `FieldValidator` (new field types, bindings).
- Services: `RevisionService` (autosave; pruning keeps live revisions of global blocks and custom types), `PageService` (usage references), `PagePayloadBuilder` (cache key includes globals and block_types).
- Builder JS: `store.js`, `tree.js`, `StructurePanel`, `Inspector`, `Builder`, `PreviewFrame`, `FieldInput`, `LayoutPanel`, `StylePanel`.
- Renderer: `BlockRenderer`, `registry.js`, `frame.js`.
- Admin: navigation and routes.
- Styles: admin SCSS.

**New dependencies:** `@dnd-kit/core`, `@dnd-kit/sortable`, `@dnd-kit/utilities` (admin builder only). `npm audit fix` updated `source-map-js` (dev/build only) to clear a pre-existing advisory.

## Database changes

- **New tables:** `global_blocks`, `block_templates`
- **block_types:** `has_unpublished_changes`, `published_revision_id`, `published_at`, `lock_version`, `created_by`, `updated_by`, `deleted_at`
- **blocks:** `global_block_id` (FK, RESTRICT)
- **Deviations from the doc (doc updated):** templates are not staged (no `published_revision_id`). `global_blocks.description` was added. Only `kind = generic` is offered until Phase 9.

## API changes (admin, session + CSRF)

- `POST /admin/api/blocks/resolve` now accepts `context` (page/global/template/structure) and `fields` (structure preview).
- `GET /admin/api/blocks/definitions` now also returns `field_types`, `bindings` and the new permissions. Published custom types are included (`custom`, `version`, `insertable`).
- New:
  - global blocks: `GET|POST /admin/api/global-blocks`, `POST /admin/api/global-blocks/{global}/detach`
  - templates: `GET|POST /admin/api/templates`, `GET /admin/api/templates/{template}`
  - autosave: `POST /admin/api/autosave` (30/min)
- **Public API:** no new endpoints. Page payloads may contain `global-ref` and `custom/*` blocks, whose children are ordinary blocks marked `locked`.

## UI changes

- **Admin → Design:** Global blocks, Templates, Custom blocks
- **Builder:**
  - toolbar (undo, redo, paste)
  - drag handles, collapse, per-row ⋯ menu (copy, paste after, save as template, convert to global block)
  - palette tabs
  - device-specific editing banner
  - rename field
  - autosave status and restore banner
  - field builder on custom type screens

## Tests

| Suite | Result |
|---|---|
| Pest (MySQL `pacms_testing`) | **206 passed, 817 assertions** (Phase 4: 187) |
| Vitest | **58 passed** (Phase 4: 37), after review fixes |
| Larastan level 6 / Pint / ESLint | No errors / passed / clean |

**Security tests added:**
- bindings rejected outside custom structures, unknown or incompatible bindings rejected
- invalid field definitions (keys, types) rejected
- text linked into rich text is escaped (`<script>` test), and no `$bind`/`repeat`/`when` appears in public output
- global blocks inside global blocks and references to missing global blocks rejected
- permissions: templates, global blocks, detach, custom types, autosave (only editors of the owner)
- autosave stores only valid trees, is per user, and never changes the working copy or the live page
- deleting a global block or custom type that is still used is refused

## Build

- **Public initial JS ≈ 94 KB gzipped**, unchanged (budget 120 KB). The renderer added two tiny components.
- **Builder island:** 163 KB gzipped (Phase 4: 137 KB; dnd-kit adds ~26 KB), loaded only on edit screens.

## Manual smoke test (http://pacms.test, dev database)

I created these with the services the admin uses:
- a published global block **Get involved CTA**
- a published custom type **Staff profile** (name, role, biography, links repeater; layout with linked headings, text and one button per link)
- a template **Image + text**
- a page **/phase-5-smoke** using the custom block and the global block

The public API returned only core blocks. The values were filled in, the HTML-looking text was escaped, there was one button per link row, and the CTA came from the global block, all marked `locked`. The page returns 200. These examples are still in the dev DB for your review.

**Not verified by me (needs your browser review):** all builder interaction:
- drag and drop, the palette tabs, and the field builder
- linking settings to fields
- device-specific editing, undo/redo, copy/paste, autosave restore
- the dialogs

## Known issues / deferred

1. **Revision screens:** global blocks, templates and custom types record revisions, but only pages have a screen to browse, compare and restore them. A shared revisions screen fits Phase 6 or 7.
2. **Global block deletion:** usage counts the saved working copies. A page whose *draft* no longer uses a global block, but whose *live* version still does, does not count, so the live page would lose that section if the global block were deleted.
3. **Removed fields:** if a custom type removes a field, values stored for it are dropped the next time the page is saved. The spec suggests keeping them hidden; we chose not to keep unvalidated data.
4. **Not built in this phase:**
   - slider, tabs and gallery blocks, and FAQ JSON-LD (to Phase 8 / 11)
   - `gallery` and `relationship` field types (Phases 7–8)
   - template thumbnails
   - header/footer global blocks (Phase 9)
5. **Autosave** covers the block tree, not the form fields (title, slug…).
6. **Custom type preview:** linked image fields show nothing in the preview (no placeholder image).

## Review fixes

| Finding | Change |
|---|---|
| The *Add block* palette was cut off by the narrow, scrolling Structure column (template screen) | The palette and the ⋯ menu now open in a floating layer above the page (`Popover.jsx`), placed next to their button and kept inside the window. They close on Escape or an outside click. Vitest added. The clipped “Drag to move” toolbar hint was removed (the buttons have tooltips). |
| No list block (only lists inside Text) | New core **List** block (Basic): style bullets / numbers / check marks / icons, an icon per item, an optional link per item, and 1–3 columns (one column on phones). Numbers render as `<ol>`, the others as `<ul>`, with decorative icons hidden from screen readers. Also allowed inside accordion items. Pest and Vitest added. |
| No line height or list item spacing; list text touched the edge of its background | Style → Text gets **Line height** (1–2, validated). Layout gets **left and right padding** for every block (previously top/bottom only). The List block gets **Space between items** (Layout → gap), **Item padding** (none/small/medium/large) and **Lines between items**. Tests added. |
| Icons could only be typed by name (`bi-tree`) | **Browse** button on every icon field: a searchable grid of all 2,078 Bootstrap Icons, with popular icons shown first. The name list is a separate 13 KB chunk loaded on first use. Vitest added. |

## Architectural decisions (Phase 5)

| Decision | Reason |
|---|---|
| Custom blocks and global blocks are expanded on the **server** into core blocks | One renderer and one set of validators and sanitizers. Nothing new is interpreted in the browser, and media and links are resolved in the same batch. |
| `when` is a wrapper block (like `repeat`), not an `advanced.when` setting | Keeps conditions visible in the structure tree and limited to custom structures |
| Block contexts (`page`, `global`, `template`, `structure`) on every type | One rule set decides where `global-ref`, `repeat`, `when` and custom blocks may appear (no cycles) |
| Templates are not staged | Nothing renders them live, and inserting copies the saved tree |
| Undo/redo keeps whole immutable trees | Unchanged branches are shared, so memory stays small and the code is simpler than patches |
| Autosave accepts only valid trees | Autosave revisions are safe to preview and restore like any other revision |

## Next phase

**Phase 6 — AI JSON:** JSON Schema generation, versioning, the import pipeline (validation, assets, preview, report) and export of blocks, sections, templates and pages.
**Not started. Waiting for your review and approval.**
