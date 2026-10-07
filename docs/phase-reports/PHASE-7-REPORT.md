# Phase 7 — Media, advanced · Completion Report

| | |
|---|---|
| Date | 2026-10-07 |
| Branch | `phase/7-media` (not merged) |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). **Needs team review before Phase 8.** |

```
PHASE:   7 — Media (library, upload, metadata, search, filtering, preview, replacement,
         secure storage)
STATUS:  Complete. All automated checks pass. The new Library screens need your browser
         review.
```

Much of the master prompt's Phase 7 list was already built in Phase 3 (Media core):
- upload with the upload guard
- re-encoding and WebP sizes
- metadata and alt text
- replace
- usage tracking, and deletion blocked while in use
- private disk and staff download
- the media picker

Phase 7 completes the rest of the roadmap.

## Implemented

| Area | What exists now |
|---|---|
| **Search** | Name, alt text, caption, **description, credit and tag names**. |
| **Filters** | Type, category, **tag**, **used / not used**, **public / private**, **needs alt text**, **my uploads**. The same filters work in the media picker's API. |
| **Sort** | Newest, oldest, name, largest, smallest. The number of files found is shown. |
| **Bulk actions** | Checkboxes on every tile with "Select all on this page" and a live count. Actions: **add to category**, **add tag**, **make private**, **make public**, **delete**. Each file is handled on its own. Files that can't change (used files can't be deleted or made private) are skipped and listed by name. Deleting asks for confirmation. |
| **Duplicate detection** | The checksum of the *original* upload is stored (`source_checksum`; images are re-encoded, so the stored file differs). The Library skips a file that is already there and links to the existing item; *"Upload even if the file is already in the library"* overrides this. The picker returns the existing item. A JSON import reuses an image already in the library instead of downloading it again. |
| **Private files** | Moving an item between the public and private disk is a button on the item and a bulk action; the files and all sizes are moved. A used file can't be made private, because those pages would lose it. Private files are served only through the staff route `/admin/media/{id}/file` (`media.view`), with `nosniff` and a sandbox content policy. They are never under `/storage`. |
| **Preview** | Images (private ones through the staff route), **PDF** in a frame with an open/download fallback, **video** and **audio** players. Library tiles now show private images too, plus size and dimensions. |
| **SVG (decision D-10)** | **Off by default.** Settings → Media → *Allow SVG uploads* (logged). When on, only roles with `media.upload_svg` (Administrator and Super Admin by default) can upload SVG, up to 1 MB. Every file is cleaned by **enshrined/svg-sanitize** (scripts, event handlers, external references and `foreignObject` removed) and checked again afterwards. SVGs get no raster sizes (they scale by themselves), and their width and height are read from the file. Uploads are logged as `media.svg_uploaded`. |
| **Regenerate sizes** | `php artisan pacms:media:regenerate [ids…] [--missing]` re-creates the responsive WebP sizes, e.g. after changing the configured widths or after a failed queue job. |
| **"Where it is used"** | Links to news, global blocks, templates and custom blocks, not only pages. |

## Files

**Created:**
- Migration `add_source_checksum_to_media_table`
- `App\Services\Media\SvgSanitizer`, command `pacms:media:regenerate`
- Tests `Media/MediaPhase7Test`

**Modified:**
- Media: `MediaService` (SVG, checksums, `findDuplicate`, `setVisibility`, no variants for SVG), `UploadGuard` (SVG path), `Admin\MediaController` (filters, sort, bulk, visibility, file preview), `Admin\Api\MediaController` (duplicates).
- Other backend: `ImportService` (reuses duplicates), `SettingsService` (`media.allow_svg`), `SettingsController`, routes.
- Views and front end: views `admin/media/{index,edit}`, `admin/settings/general`, admin JS (bulk selection), admin SCSS.
- Docs: `DATABASE-ARCHITECTURE.md` and `SECURITY-ARCHITECTURE.md` (SVG row).

**New dependency:** `enshrined/svg-sanitize` (PHP; chosen in Phase 1, D-12).

## Database changes

- **media:** `source_checksum` char(64) null, indexed.
- **settings:** new group `media` (`allow_svg`, default false). No migration is needed; defaults live in code.

## Routes (admin, session + CSRF)

- `POST /admin/media/bulk`
- `POST /admin/media/{media}/visibility`
- `GET /admin/media/{media}/file` (staff preview)
- `GET /admin/api/media` accepts the new filters and `sort`; `POST /admin/api/media` returns `meta.duplicate` for an existing file.

## Tests

| Suite | Result |
|---|---|
| Pest (MySQL `pacms_testing`) | **254 passed, 1067 assertions** (Phase 6: 247) |
| Vitest | **58 passed** |
| Larastan level 6 / Pint / ESLint | No errors / passed / clean |

**New tests:**
- **Duplicates:** skipped in the Library, the picker gets the existing item, and "upload anyway" stores a second copy.
- **Filters and sort:** used, unused, needs alt text, private, tag, search by tag name, largest first.
- **Bulk actions:** category, make private (a used file is skipped, the file moves disk), delete (a used file is skipped), and permissions.
- **Private preview:** staff only, with `nosniff` and a sandbox policy; guests are redirected.
- **SVG:** refused while off; refused for editors when on; for administrators it is cleaned (script, `onload`, `javascript:`, `foreignObject`, remote image all removed), sizes come from the viewBox, there are no variants, and the upload is logged.
- **Settings switch:** SVG can be turned on, and that is logged.
- **Regenerate command:** `--missing`.

## Not verified by me in a browser

- The new Library screen: filters, bulk bar, selection.
- The PDF, video and audio previews. The PDF preview depends on the browser's PDF viewer inside a frame under the site's security policy, so the "Open the PDF / download" links are there as a fallback.
- The Settings switch.

## Known issues / deferred

1. **Public SVGs** are served from `/storage` by the web server, so the extra no-script header can't be added there; the sanitiser is the protection. If the server team wants the header too, it can be added to the web server configuration (nginx/Apache) for `*.svg`.
2. **The media picker** has no filter controls in its window yet (the API supports them). Drag-and-drop upload in the picker is not done.
3. **Bulk actions** apply to the current page of results, not to every file matching the filters.
4. **Duplicate detection** only recognises identical files. Visually similar images (resized or re-saved) are not detected.
5. **Files uploaded before Phase 7** have no `source_checksum`. Duplicates of earlier *images* are therefore not recognised; documents are, through `checksum_sha256`.

## Architectural decisions (Phase 7)

| Decision | Reason |
|---|---|
| A duplicate is skipped with a link to the existing item, unless "upload anyway" is ticked | One file, one set of usages. Library clutter and wasted storage are avoided by default |
| A used file can't be made private | Making it private would silently remove the image from live pages |
| SVG has no raster variants; width and height come from the file | Vectors scale. Raster copies would lose the quality and the point of SVG |
| Sanitise, then check again for script-like content | Defence in depth for the one image format that can carry code |

## Next phase

**Phase 8 — Content Modules:**
- Events, Projects, Programs, Publications, Team, Partners, Testimonials (moderation), Media Coverage and Galleries
- the full News module
- module sidebars (agreed in the Phase 5 review)

**Not started. Waiting for your review and approval.**
