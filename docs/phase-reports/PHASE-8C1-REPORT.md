# Phase 8C.1 — Content Modules: Team, Partners, Galleries · Report

| | |
|---|---|
| Date | 2026-10-08 |
| Branch | `phase/8-content-modules` → merged into `main` 2026-10-08 |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). Approved 2026-10-08. |

```
PHASE:   8C.1 — Team, Partners, Galleries; links between content items
STATUS:  APPROVED by the team lead (2026-10-08) and merged into main.
         8C.2 (Testimonials, Media Coverage, search) follows after review.
```

## Implemented

| Area | What exists now |
|---|---|
| **Team** | Name, designation, photo, short bio, biography, department (`department` categories), e-mail and phone (each shown on the website **only if ticked**), social links, display order. Pages at `/team/{name}` with a details box and contact buttons; JSON-LD `Person`. Listed in display order. |
| **Partners** | Organisation, logo, description, website, category (`partner_category`), display order. **No pages of their own**: cards and logos link to the partner's website. `/partners` lists them. |
| **Managed modules** | Team and Partners use **one permission each** (`team.manage`, `partners.manage`, as in the security design) and no editorial workflow: items are **active** or **inactive** ("Publish" / "Unpublish" only; other workflow actions are refused by the server). Revisions, preview and SEO work as for the other modules. |
| **Galleries** | Photos from the Media Library (**several at once**) and YouTube/Vimeo videos, each with a caption, alternative text and credit, in a chosen order. Also a cover, type (photos / videos / both), date, location, photographer and tags, plus links to an **event, project or program**. The page `/galleries/{slug}` shows a grid with a **full-screen viewer**: arrow keys, Esc, focus kept inside, and videos load only when opened. Only public photos are accepted, and a photo used in a gallery can't be deleted or made private. |
| **Links between items** | A new *relation* field type. **Projects** link a **project manager** (team member, shown as a link to their profile instead of the typed name), **partners** (logo row on the project page) and a **gallery** (photos shown on the project page). **Programs** link partners and a gallery. Links to inactive, unpublished or deleted items are not shown. Partners and team members keep their own display order. |
| **Blocks** | **Team** (person cards, 4 columns, square photos), **Partners** (logo wall: small, medium or large, optionally grey until hover; or cards), **Galleries** (gallery cards), **Gallery** (one chosen gallery's photos with the viewer; choose it in Source → *One gallery*). |
| **Admin** | Labels per module (Team: *Name, Short bio, Biography, Photo*; Partners: *Organisation, Logo*; Galleries: *Cover image*). Long link lists become a scrollable checklist. "Departments" and "Partner categories" in the menu. |

### Fixes found while building

- **Block filters with text values did nothing** (8A/8B): the builder turned every filter value into a number, so Events "When: Past" or Projects "Status: Completed" were silently dropped. Now fixed.
- **Row order of repeaters, documents and gallery items**: when some rows lacked a value (e.g. a video row has no photo), validation returned the rows out of order. Rows are now saved in the order submitted.

## Files

**Created:**
- Migration `2026_10_12_100000_content_modules_team_partners_galleries`.
- Models `TeamMember`, `Partner`, `Gallery`, `GalleryItem`, `ContentRelation`; policies `ManagedContentPolicy`, `TeamMemberPolicy`, `PartnerPolicy`, `GalleryPolicy`.
- Types `TeamType`, `PartnerType`, `GalleryType`; blocks `TeamBlock`, `PartnersBlock`, `GalleriesBlock`, `GalleryBlock`.
- Admin island `GalleryField.jsx`; public `GalleryGrid.jsx` (with lightbox), `LogoWall.jsx`.
- Tests `Feature/Content/TeamPartnersGalleriesTest`, `blocks/display/GalleryGrid.test.jsx`.

**Modified:**
- Engine: `ContentType` (managed modules, `ability()`, detail pages, display order, labels, relation fields), `ContentItem` (relations, snapshot), `ContentService` (relations, gallery items, managed actions, row order), `ContentPayloadBuilder` (relation facts, related sections).
- Projects and programs: `ProjectType` and `ProgramType` (relation fields).
- Sources: `DynamicSource` (`item` filter).
- Routing and controllers: `PathResolver` and `SeoFilesController` (no partner pages), `Admin\ContentController` (relations, gallery items, labels).
- Admin: `AdminNavigation`, the media picker (multiple selection, public only), `SourcePanel` (filter values).
- Public site: `ContentView`, `ItemCard`, `collections.jsx`, `registry.js`.
- Config, views and styles: `config/pacms.php`, `AppServiceProvider`, the content form, admin and public SCSS.
- Docs: `DATABASE-ARCHITECTURE.md`.

## Database changes

- New tables: **team_members**, **partners**, **galleries**, **gallery_items**, **content_relations**. See DATABASE-ARCHITECTURE §7.
- **`content_relations` replaces the planned `partnerables` table and the separate FK columns** (project manager, gallery, event/project/program on galleries). It's one generic table that 8D's admin-made types will also use.

## Tests

| Check | Result |
|---|---|
| Pest (full suite, parallel) | **287 passed** (1401 assertions) |
| Vitest | **65 passed** (12 files) |
| Larastan (level 6) | No errors |
| Pint, ESLint | Clean |
| Production build (Vite) | OK |

New tests:
- `Content/TeamPartnersGalleriesTest`:
  - team managed with one permission, scheduling refused, contact details only when ticked, JSON-LD `Person`
  - display order in archive and block
  - partners without pages, links to their websites, not in the sitemap
  - gallery with photos and a video in the submitted order, captions and alt override, usage protection, the Gallery block for one gallery
  - private photos and non-YouTube/Vimeo links refused
  - project linked to manager, partners (inactive hidden, display order) and gallery
  - relations and gallery items restored from a revision
- `GalleryGrid.test.jsx`: viewer opening, arrow keys, video loaded only when opened, closing; logo wall links and names.

## Not verified by me in a browser

- The gallery editor (adding many photos, reordering, captions) and the full-screen viewer on phones.
- The logo wall with real logos of different shapes.

## Known issues / deferred

- External gallery sources (Facebook, Flickr, YouTube playlists) come in Phase 10, as planned.
- Galleries have no masonry layout yet (square grid only).
- Reordering is done with arrow buttons; there's no drag and drop in these lists.

## Next

**8C.2: Testimonials** (public submission form, moderation queue with reasons, privacy rules), **Media Coverage** (source links with scheduled availability checks and archive fallback) and **search indexing**. Do not start until 8C.1 is reviewed.
