# Phase 8 — Starter kit · Report

| | |
|---|---|
| Date | 2026-10-09 |
| Branch | `phase/8-content-modules` (not merged) |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). Awaiting review. |

```
PHASE:   8 (last part) — starter kit: designed page and section templates and global blocks,
         installed with one command; "New page → Start from" a page template
STATUS:  READY FOR REVIEW. Not merged.
         Decisions applied (team lead, 2026-10-09: "use your defaults"):
         - S-1 pages only with --pages, created as drafts, never over existing pages
         - S-2 no images shipped: photo spots are soft panels with an icon
         - S-3 header/footer come with navigation (Phase 9); CTA band and contact box ship now
         - S-4 Contact page: contact details and an e-mail button; contact form in Phase 11
         - S-5 after merge: tag v0.8.0 on main
ALSO:    two engine fixes found while checking the templates (see "Fixes")
```

## Implemented

| Area | What exists now |
|---|---|
| **`php artisan pacms:starter`** | Installs the kit. Running it again only adds what is missing and never changes what you edited. `--pages` also creates draft *Home*, *About us*, *Contact* and *Get involved* pages (Home becomes the home page if none is set). `--restore=<slug>` or `--restore=all` puts shipped templates back to their original design. `--user=` chooses the author (default: the first super admin). |
| **Page templates (9)** | Home, About us, Contact, Get involved, and landing pages for News, Events, Projects, Programmes and Publications. Their collection blocks show your real content as soon as it exists. |
| **Section templates (14)** | Hero (centred on theme colours, text and photo, slider), introduction with photo, impact numbers, three feature cards, latest news, upcoming events, testimonials, partner logos, team grid, questions and answers, contact details, call-to-action band. In the builder's **Templates** tab, grouped by category. |
| **Global blocks (5)** | *Call to action* (used by every page template: edit once, changes everywhere), *Contact details*, and sidebars for news, events and general pages. Published on install. |
| **New page → Start from** | The New page form lists the page templates (the kit's first). The new page gets a copy of the template's sections, with new block ids. Ignored if blocks were already added in the builder. |
| **The demo** | `pacms:demo` installs the kit's templates and global blocks too (not its pages). |

### How it works

- The kit is **files in the JSON import format** (`resources/starter-kit/`, listed in `kit.php`), read through the same document reader, translator and cleaner as a JSON import. So every template is checked exactly like an AI-generated document, and the kit tests that format.
- A kit file that is not perfectly clean (any error, or any warning that something was removed) stops the install with the file and the place. The tests install the whole kit, so a broken template fails the build.
- Shipped templates are marked `is_system` (the column planned in Phase 1 for this) and have stable slugs (`kit-…`), so a second run and `--restore` find them.

## Fixes found while checking the templates in the browser

1. **Page cache keys could be too long.** Cached page data used a key with a version for every content type plus the page's path. With several admin-made types (8D) and a longer path, it passed the 255 characters of the database cache, and the page failed with a server error. Keys now use a fixed-length hash. Test added.
2. **Text on gradients and chosen text colours.** Readable text was given only to solid theme-colour backgrounds. Now a gradient uses its first colour's readable text colour, and a text colour chosen by the editor also reaches headings, eyebrows, muted text and outline buttons (before, a white hero kept a dark heading and an invisible outline button). A hero's button group can now be centred: a stylesheet rule overrode its own *Justify* setting.

## Files

**Created:** `Services/Starter/StarterKitService`, `Console/Commands/StarterKitCommand`, `resources/starter-kit/` (`kit.php`, 5 global blocks, 14 section and 9 page templates), `tests/Feature/Starter/StarterKitTest`, `tests/Feature/Cache/CacheVersionsTest`, this report.

**Modified:** `BlockTemplateService` (optional slug), `PageController`, `PageRequest` and `admin/pages/form` (Start from), `CacheVersions`, `PagePayloadBuilder`, `compile.js` (+ test), `public.scss`, `DemoSeeder` (+ test), `config/pacms.php` (version 0.8.0; the admin footer showed 0.2.0), `README.md`, `DEPLOYMENT-ARCHITECTURE.md`, `DEVELOPMENT-ROADMAP.md`.

## Database changes

None.

## Tests

| Check | Result |
|---|---|
| Pest (full suite) | **329 passed** (1,848 assertions) |
| Vitest | **72 passed** (14 files) |
| Larastan (level 6) | No errors |
| Pint, ESLint | Clean |
| Production build (Vite) | OK |

New tests:
- The whole kit installs through the import pipeline: every global block (published) and template (`is_system`), page templates counted, no pages without `--pages`.
- A second run adds only what is missing and keeps edits; `--restore` of one template and of all; an unknown slug fails.
- `--pages`: drafts for Home, Contact and Get involved, an existing *About* page left alone, Home set as the home page; published, it renders with the shared call to action.
- New page → Start from: a copy with new block ids; section templates refused.
- Cache keys stay 40 characters with many content types, and still change when a version changes.
- Style compiler: gradients and chosen text colours.

## Checked in the browser (local)

Installed on your local database (`php artisan pacms:starter`). Temporary pages made from the Home, About us and Contact templates were checked on the website with the demo content and then deleted: hero, numbers, introduction, news, programmes and events, testimonials, partners, team, contact details, FAQ and the call to action all render as designed. The New page form shows **Start from**.

## Known issues / deferred

- **Footer and header** templates: Phase 9 (navigation).
- **Contact form**: Phase 11; the Contact template uses contact details and an e-mail button.
- **Template thumbnails** in the Templates tab are not shown yet (names, categories and descriptions are).
- Placeholder contact details (`info@example.org`, `+880 1700 000000`) must be replaced by the editor.

## Next

After review and merge: tag **v0.8.0**, then **Phase 9 — Navigation**.
