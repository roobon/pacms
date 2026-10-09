# Phase 8C.2 — Content Modules: Testimonials, Media Coverage, Search · Report

| | |
|---|---|
| Date | 2026-10-09 |
| Branch | `phase/8-content-modules` (not merged) |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). Awaiting review. |

```
PHASE:   8C.2 — Testimonials (submission + moderation), Media Coverage (source checks,
         archive fallback), search indexing
STATUS:  READY FOR REVIEW. Not merged.
         Decisions applied (team lead, 2026-10-09: "use your defaults"):
         - archive rights may be confirmed by anyone with media_coverage.publish; every change logged
         - submitters do not see the rejection reason (setting PACMS_TESTIMONIAL_SHOW_REJECTION_REASON)
         - a simple submission form is in the account page now; Phase 11 polishes it
```

## Implemented

| Area | What exists now |
|---|---|
| **Testimonials: moderation** | Own steps, separate from the editorial workflow: *Pending → Under review → Approved → Published → Archived*, *Rejected* from Pending or Under review, *Review again* from Rejected or Archived, *Unpublish* back to Approved. **Approved is not shown**; only Published is. Rejecting needs an internal reason. Every step, and every edit, goes into the moderation history (from, to, who, note). Permissions: `testimonials.view` (see), `.moderate` (review, approve, reject, edit), `.publish` (publish, unpublish, archive), `.delete`. |
| **Testimonials: admin** | *Content → Testimonials* with a **count badge** of what waits. Lists: To moderate (oldest first), Drafts, Approved, Published, Rejected, Archived, All. The edit screen shows the person, the text, related program/project/event, display options (featured, display order), the moderation box, a preview card, the history and, after staff edits, the **original submission**. Staff can enter testimonials themselves (start as drafts, can be approved directly). |
| **Testimonials: submission** | `POST /api/v1/testimonials` for signed-in users with a **verified e-mail**: name, organisation, designation, text (20–1,500 characters, plain text), related published program/project, photo (JPG/PNG/WebP, max 2 MB, re-encoded, **private until published**), required consent (version and time stored). Hidden honeypot field. **3 a day per account, 10 per IP**, counting only accepted submissions. `GET /api/v1/me/testimonials` (own list with status) and `GET /api/v1/testimonials/options`. A form and "Your testimonials" list are in the **account page**. |
| **Testimonials: privacy** | Public output (`GET /api/v1/testimonials`, the block) is an explicit allowlist. E-mail, user ID, IP, consent data and the rejection reason never appear (tested). IPs are deleted after 90 days (daily command). |
| **Testimonials block** | Display modes **grid (cards), carousel, quote slider (one at a time), single, featured, list, masonry**. Filters: featured, program, project, event. Order: display order, newest, random. Photo and rating can be hidden. |
| **Media Coverage** | A module on the content engine (`/media-coverage`, `/media-coverage/{slug}`): headline, source, link to the original, coverage type (newspaper, magazine, TV, radio, online, other), publication date, summary, image, category (`media_coverage_category`), related program and project, archived PDF and video. Normal editorial workflow, revisions, SEO, sidebar, sitemap. |
| **Archive rights** | An archived copy is shown **only when "We have the right to show the archived copy" is ticked**, with an internal note. Only people who may publish coverage can tick it (locked for others, ignored on save), and each change is logged. Archive files can be uploaded as **private** files (the picker offers private files and uploads privately); they are then streamed through `/media-coverage/{slug}/archive/pdf|video`, which exists only for published items with confirmed rights. |
| **Source checks** | Every 15 minutes the scheduler queues checks that are due; each item is checked about **once a day at its own random time**. HEAD request (GET if refused), redirects followed, through `SafeHttpClient` (SSRF rules). **2xx** → available; **404/410 or unknown host** → a failure, **3 in a row** → unavailable; **401/403/429/5xx/timeout** → "could not be verified", which never counts against the link. *Original link* box in the admin with the last result and **Check now**. Override: automatic / always the original / show the archive instead. Checks never run while a visitor waits. |
| **Coverage page** | Original link, archive, both, or details with "The original is no longer available online", following CMS-ARCHITECTURE §19.2. PDFs open in an embedded viewer with an "Open" link; videos in a player. |
| **Media Coverage block** | Cards with "Source · Type" and the publication date. Filters: category, coverage type, featured, program, project. |
| **Search** | `search_documents` with MySQL FULLTEXT. Pages (text of the **published** version's blocks), News, Events, Projects, Programs, Publications, Team, Galleries and Media Coverage are indexed when published and removed when unpublished, archived or deleted. Pages marked "noindex" are left out. `GET /api/v1/search?q=&type[]=&page=&per_page=` (2–100 characters, max 50 per page, 30 requests/minute per IP). `php artisan pacms:search:rebuild`. A module joins search through `searchable()` on its type, so 8D types can join. |

### Engine changes (used by 8C.2, available to later modules)

- **Fields reserved for a permission** (`'ability' => 'publish'`): locked in the form for others, ignored on save.
- **Media fields**: kind `video`, and `'visibility' => 'any'` to allow private files.
- **Extra admin box** beside a module's form (`adminPanel()`), used for the source check.
- **Search hooks** on content types: `searchable()`, `searchText()`.
- New display modes **masonry, quote-slider, single** (only the Testimonials block offers them so far).
- Media API upload accepts `private=1`.
- A count badge on admin menu entries.

### Deviations from the plan or the documents

- **Search matches word beginnings.** Natural-language mode alone does not find "mangroves" for "mangrove", because MySQL FULLTEXT has no stemming. Matching now uses word prefixes that the server builds from the cleaned words, and ranking still uses natural-language relevance. Visitors' own operators are still removed.
- **`search_documents.type`** column added: the registry key used by the `type[]` filter.
- **Media coverage links to programs and projects through `content_relations`**, like galleries in 8C.1, instead of `program_id`/`project_id` columns.
- **Media coverage tags are not built.** The engine has one taxonomy per module. Categories work. Tags need multi-taxonomy support, which fits 8D.
- **`GET /api/v1/media-coverage`** (a list endpoint) is not built. Like the other modules, coverage is served through `/api/v1/resolve` (archive and detail). Module list endpoints belong with the OpenAPI work in Phase 11.
- **Rate limit counted in the controller**, not the throttle middleware. The middleware counts every request, so a user who corrected two mistakes would have been locked out for the day.

### Fixes found while building

- The **AI JSON schema and prompt** (`resources/schemas/pacms/1.0`) had not been regenerated since 8A. They now list every block, including Events, Projects, Team, Partners, Gallery, Document, HTML, Testimonials and Media Coverage.
- **Pint** failed on the 8C.1 test file (unused import). Fixed in the first commit of this part.

## Files

**Created:**
- Migration `2026_10_13_100000_testimonials_media_coverage_search`.
- Models `Testimonial`, `TestimonialModerationLog`, `MediaCoverage`, `SearchDocument`; enums `TestimonialStatus`, `TestimonialAction`; policies `TestimonialPolicy`, `MediaCoveragePolicy`.
- Type `MediaCoverageType`; blocks `TestimonialsBlock`, `MediaCoverageBlock`; source `TestimonialSource`.
- Services `TestimonialModerationService`, `CoverageDisplay`, `SourceChecker`, `SearchIndexer`, `SearchService`; observer `SearchObserver`; job `CheckMediaCoverageSource`.
- Commands `pacms:coverage:check`, `pacms:testimonials:purge-ips`, `pacms:search:rebuild`.
- Controllers `Admin\TestimonialController`, `Admin\MediaCoverageController`, `Api\V1\TestimonialController`, `Api\V1\SearchController`, `Public\CoverageArchiveController`; resources `TestimonialResource`, `OwnTestimonialResource`.
- Views `admin/testimonials/index`, `admin/testimonials/form`, `admin/media-coverage/source-panel`.
- Frontend `TestimonialsDisplay.jsx` (+ test), `account/TestimonialSection.jsx`.
- `SafeHttpClient::probe()` and `UnresolvableHostException`.
- Tests `Feature/Content/TestimonialsTest`, `Feature/Content/MediaCoverageTest`, `Search/SearchTest`.

**Modified:** `ContentType`, `ContentController`, the content form, `DisplayModeRegistry`, `SourceRegistry`, `AdminNavigation` and the admin layout, the media picker (component and island), the media API, both payload builders (cache group `testimonials`), `ItemCard`, `ItemsDisplay` (exported `Carousel`), `ContentView`, `AccountPage`, `collections.jsx`, `registry.js`, admin and public SCSS, `config/pacms.php`, `AppServiceProvider`, routes (`admin`, `api`, `web`, `console`), `.env.example`, `phpunit.xml`, `tests/Pest.php`, `README.md`, `DATABASE-ARCHITECTURE.md`.

## Database changes

- New tables: **testimonials**, **testimonial_moderation_logs**, **media_coverage**, **search_documents**. See DATABASE-ARCHITECTURE §7 and §11 ("Phase 8C.2 (built)").

## Tests

| Check | Result |
|---|---|
| Pest (full suite) | **306 passed** (all 287 existing + 19 new) |
| Vitest | **68 passed** (13 files) |
| Larastan (level 6) | No errors |
| Pint, ESLint | Clean |
| Production build (Vite) | OK |

New tests:
- `Content/TestimonialsTest`:
  - who may submit (guest, unverified, verified); consent, length, photo type and size, unpublished program refused
  - a stored submission: pending, IP, consent version, private photo, "Original submission" revision, log entry
  - 3 a day per account (form mistakes not counted); honeypot stores nothing
  - every moderation step, the history, and permissions (moderator cannot delete; authors cannot moderate)
  - rejection reason required, hidden from the submitter unless enabled; only one's own submissions listed
  - original kept after an edit; staff drafts
  - **leak test**: API and block never contain e-mail, user ID, IP, consent or rejection data
  - queue badge; IPs purged after 90 days; steps refused in the wrong status
- `Content/MediaCoverageTest`:
  - create, publish and show with facts and the original link
  - archive rights: locked for authors, logged for editors, private file served only with rights
  - fallback (archive / notice / override)
  - check results (2xx, 3 × 404, 403, timeout, unknown host, recovery), redirects with HEAD refused
  - scheduler queueing, Check now, the block filter, check details kept private
- `Search/SearchTest`:
  - pages (block text) and items found, drafts and partners never
  - removed on unpublish and archive
  - type filter, pagination, operators stripped, validation, rebuild

Search tests live in **`tests/Search`** and commit their data, because InnoDB FULLTEXT does not see rows inside the transaction the Feature tests use. They empty the tables after each test.

## Not verified by me in a browser

- The testimonial form in the account page, and the moderation screens.
- The quote slider, masonry and featured layouts with real photos.
- The PDF viewer and video player on a coverage page, and the media picker for private PDFs and videos.

## Deployment notes

- Run `php artisan migrate`, then **`php artisan pacms:search:rebuild` once** to index existing content.
- The scheduler now also runs `pacms:coverage:check` (every 15 minutes) and `pacms:testimonials:purge-ips` (daily). Nothing to add if the cron entry for `schedule:run` exists.
- Parallel tests (`composer test -- --parallel`) need the test user to create databases: `GRANT ALL ON \`pacms_testing%\`.* TO 'pacms'@'localhost';`.

## Known issues / deferred

- Media coverage **tags** (multi-taxonomy, 8D).
- **Global blocks and search**: changing a global block does not re-index the pages that use it. Run `pacms:search:rebuild`, or the page is re-indexed when next published.
- Moderation screens have no revision compare or restore for testimonials (the original and the history are shown). Add it if the team wants it.
- **Search relevance for Bangla**: untested with real Bangla text (R-10, Phase 13).
- The public **search page** and the polished account area come in Phase 11.

## Next

After review and merge: **8D: admin-made content types**, then the starter kit and **v0.8.0**.
