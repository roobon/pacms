# Probha Aurora CMS — API Architecture

| | |
|---|---|
| Document | API-ARCHITECTURE.md (Phase 1 deliverable X) |
| Status | Draft for team review, not yet implemented |
| Related | [CMS-ARCHITECTURE.md](CMS-ARCHITECTURE.md) · [SECURITY-ARCHITECTURE.md](SECURITY-ARCHITECTURE.md) · [CMS-BLOCK-SCHEMA.md](CMS-BLOCK-SCHEMA.md) |

---

## 1. API surfaces

| Surface | Prefix | Consumers | Auth | Routes file |
|---|---|---|---|---|
| **Public API** | `/api/v1` | Public React SPA, third parties (read-only) | none | `routes/api.php` |
| **User API** | `/api/v1/me`, `/api/v1/testimonials` (POST) | SPA for registered users | Sanctum **stateful cookie** session + CSRF (same domain) | `routes/api.php` |
| **Auth endpoints** | `/auth/*` | SPA login/registration | Fortify (JSON responses) + CSRF | Fortify routes |
| **Preview API** | `/api/v1/preview/*` | SPA preview route | signed URL **and** session with view permission | `routes/api.php` |
| **Admin API** | `/admin/api/*` | Admin React islands only | `web` session + CSRF + `admin.access` + per-action policy | `routes/admin.php` |
| **Feeds / SEO files** | `/rss.xml`, `/rss/*`, `/sitemap*.xml`, `/robots.txt` | aggregators, crawlers | none | `routes/web.php` |

Admin CRUD itself is **not** an API: Blade forms post to `/admin/*` web routes.

### Versioning
- The public API is versioned in the path (`/api/v1`). The master prompt's example paths (`/api/pages`, …) become `/api/v1/pages`, … (decision D-08).
- Within v1 only additive changes are allowed: new fields and new endpoints. Breaking changes need `/api/v2`, with v1 kept for at least 12 months.
- The admin API is internal and unversioned. The admin islands ship with the backend, so both always match.

---

## 2. Public API (v1)

All public endpoints return **only published, public data** through explicit API Resources. Each Resource has a field allowlist and never uses `toArray()` on the model.

### 2.1 Site-wide

| Method & path | Returns |
|---|---|
| `GET /api/v1/site` | Public settings (name, logo, contact, social, locale, default header/footer as **resolved global block payloads**, token CSS URL, feature flags). Cached. |
| `GET /api/v1/menus/{slug}` | Menu tree with resolved URLs. Items linking to unpublished targets are omitted. Mega panels are included as block payloads. |
| `GET /api/v1/resolve?path=/about/team` | `{ "kind": "page", "data": {…page payload…} }` · `{ "kind": "redirect", "to": "/new", "status": 301 }` · `{ "kind": "not_found" }` (HTTP 404) |

### 2.2 Pages and blocks

| Method & path | Returns |
|---|---|
| `GET /api/v1/pages/{path}` | Page payload (below). `{path}` may contain `/`. |
| `GET /api/v1/pages?parent=&page=` | Lightweight list (title, path, excerpt, image), published only, for sitemaps and child listings. |
| `GET /api/v1/blocks/{uuid}/items?page=2` | Next page of items for a **published** collection block ("load more" and pagination). It re-runs the block's saved source config; client parameters other than `page` are ignored. |

**Page payload**
```json
{
  "data": {
    "type": "page",
    "id": 12,
    "title": "Our Programs",
    "path": "programs",
    "url": "https://example.org/programs",
    "excerpt": "…",
    "featured_image": { "src": "…", "srcset": "…", "sizes": "100vw", "width": 1920, "height": 1080, "alt": "…", "placeholder": "data:image/webp;base64,…" },
    "template": "default",
    "header": "main-header",
    "footer": "main-footer",
    "published_at": "2026-09-24T10:00:00+06:00",
    "updated_at": "2026-09-24T10:00:00+06:00",
    "seo": { "title": "…", "description": "…", "canonical": "…", "robots": "index,follow",
             "og": { "title": "…", "description": "…", "image": "…" }, "twitter": { "card": "summary_large_image" },
             "json_ld": [ { "@context": "https://schema.org", "@type": "WebPage", "…": "…" } ] },
    "blocks": [ { "uuid": "01J…", "type": "hero", "content": {…}, "display": {…}, "layout": {…},
                  "style": {…}, "responsive": {…}, "advanced": {…}, "children": [ … ],
                  "items": null } ],
    "definitions": { "custom/staff-profile": { "version": 3, "fields": [ … ], "structure": [ … ] } },
    "globals": { "cta-donate": { "blocks": [ … ] } }
  },
  "meta": { "cache_key": "p12:r88:v…", "generated_at": "…" }
}
```

- Block nodes follow CMS-BLOCK-SCHEMA.md, with **references resolved for rendering**: media becomes image objects, entity links become URLs, tokens stay tokens (resolved in CSS), and dynamic or external blocks carry `items` (normalized Item array) plus `pagination` when applicable.
- Hidden blocks, internal notes, author IDs and `created_by` are never included.
- `advanced.custom_css` is delivered already sanitized and scoped.

### 2.3 Content types

The same pattern applies to every registered content type:

| Method & path | Notes |
|---|---|
| `GET /api/v1/news` | `?category=&tag=&featured=&program=&project=&q=&page=&per_page=` (≤ 50) |
| `GET /api/v1/news/{slug}` | Detail including `blocks` body |
| `GET /api/v1/events` | `?when=upcoming\|past\|all&category=&program=&project=&from=&to=` |
| `GET /api/v1/events/{slug}` | Detail. Includes an `ics` link (`/api/v1/events/{slug}.ics`) |
| `GET /api/v1/projects`, `/{slug}` | `?project_status=&program=&featured=` |
| `GET /api/v1/programs`, `/{slug}` | `?featured=` |
| `GET /api/v1/publications`, `/{slug}` | `?category=&year=&featured=` |
| `GET /api/v1/team` | `?department=` (active only; email/phone only if `show_*` is set) |
| `GET /api/v1/partners` | `?category=` (active only) |
| `GET /api/v1/testimonials` | `?featured=&program=&project=&event=` (published only; **never** email, user_id, IP, consent data, rejection reason) |
| `GET /api/v1/media-coverage`, `/{slug}` | `?category=&coverage_type=&year=`. Includes the `display` decision (original/archive/both, from §19.2 of the architecture). Archive URLs appear only when rights are confirmed. |
| `GET /api/v1/galleries`, `/{slug}` | `?gallery_type=&event=&program=&project=`. Items are normalized (CMS or external). |
| `GET /api/v1/terms/{taxonomy}` | Public taxonomies only (for filter UIs) |
| `GET /api/v1/external/{source-slug}` | Normalized cached items of an **enabled** external source (RSS, Facebook, …). The source is identified by admin-defined slug, **never a URL or page ID** (this replaces the master prompt's `/api/external/rss/{source}` and `/api/external/facebook/{page}`). |
| `GET /api/v1/search` | `?q=` (2–100 chars) `&type[]=news&type[]=events&page=` |

**About the master prompt's `GET /api/media`**: a public listing of the whole Media Library is **not provided**. The library holds unpublished and private files, so exposing it would leak drafts (decision D-08). Media reaches the public only as part of the published content that uses it.

### 2.4 Collection response shape

```json
{
  "data": [ { "id": 128, "type": "news", "title": "…", "slug": "…", "url": "/news/…", "excerpt": "…",
              "image": { … }, "published_at": "…", "categories": [ {"name": "…", "slug": "…"} ] } ],
  "links": { "first": "…", "last": "…", "prev": null, "next": "…" },
  "meta":  { "current_page": 1, "per_page": 12, "total": 57, "last_page": 5 }
}
```
This is Laravel's paginated Resource Collection format, unchanged.

---

## 3. User API (registered users)

| Method & path | Notes |
|---|---|
| `GET /sanctum/csrf-cookie` | Initialises the CSRF cookie for the SPA |
| `POST /auth/register`, `/auth/login`, `/auth/logout`, `/auth/forgot-password`, `/auth/reset-password`, `/auth/email/verification-notification` | Fortify. JSON when `Accept: application/json`. Throttled. |
| `GET /api/v1/me` | `{ id, name, email, email_verified }` for the **current user only** |
| `GET /api/v1/me/testimonials` | The user's own submissions with status (no internal notes) |
| `POST /api/v1/testimonials` | Multipart: fields + photo + `consent=true`. Needs a verified email. Rate limit 3/day/user and 10/day/IP. Returns `201` with status `pending`. |
| `GET /api/v1/testimonials/options` | Published programs, projects and events for the form's selects |

---

## 4. Preview API

| Method & path | Notes |
|---|---|
| `GET /api/v1/preview/{type}/{id}?signature=…&expires=…&revision=working\|{number}` | Needs a valid signature **and** an authenticated session with the `view` policy on the item. Returns the same shape as the public payload, built from the working copy or revision. Headers: `Cache-Control: no-store`, `X-Robots-Tag: noindex, nofollow`. |
| `GET /__builder-preview` (web) | SPA preview frame for the builder. Auth required. It receives trees by `postMessage` and never through the URL. |

---

## 5. Admin API (for React islands)

The Admin API uses session auth, `X-CSRF-TOKEN` and `admin.access` middleware. **Every endpoint authorizes through a policy.** The frontend's hiding of controls is only a convenience.

| Area | Endpoints |
|---|---|
| Block registry | `GET /admin/api/block-types` (definitions the user may insert), `GET /admin/api/display-modes`, `GET /admin/api/tokens` |
| Block trees | `GET /admin/api/trees/{ownerType}/{ownerId}` · `PUT …/tree` (full tree + `lock_version`; 409 on conflict; 422 with errors keyed by block uuid + JSON pointer) · `POST …/autosave` |
| Publishing | `POST /admin/api/workflow/{type}/{id}/{action}` (submit, approve, publish, schedule, unpublish, archive, request_changes) |
| Revisions | `GET /admin/api/revisions/{type}/{id}` · `GET …/{number}` · `GET …/compare?from=&to=` · `POST …/{number}/restore` |
| Preview | `POST /admin/api/preview-links/{type}/{id}` → signed URL |
| Templates & globals | `GET/POST /admin/api/templates` · `POST /admin/api/templates/{id}/insert` · `POST /admin/api/global-blocks/{id}/detach` (body: target block uuid) |
| Custom blocks | `GET/PUT /admin/api/custom-block-types/{id}` (fields + structure) |
| Dynamic source helpers | `GET /admin/api/sources/{entity}/options` (filters and orders the entity allows) · `POST /admin/api/sources/preview` (resolves a source config for the builder, drafts excluded) |
| Pickers | `GET /admin/api/pick/{entity}?q=` (entity picker) · `GET /admin/api/pick/terms/{taxonomy}` |
| Media | `GET /admin/api/media?q=&kind=&term=&page=` · `POST /admin/api/media` (upload, multi) · `PATCH /admin/api/media/{id}` (metadata) · `POST /admin/api/media/{id}/replace` · `DELETE /admin/api/media/{id}` (409 if in use) · `GET /admin/api/media/{id}/usage` |
| Menus | `GET/PUT /admin/api/menus/{id}/tree` |
| JSON | `POST /admin/api/imports` (upload/paste → job) · `GET /admin/api/imports/{uuid}` (status, report, plan) · `PATCH /admin/api/imports/{uuid}` (asset strategies, target) · `POST /admin/api/imports/{uuid}/confirm` · `GET /admin/api/exports/{type}/{id}?scope=block&uuid=` |
| External | `POST /admin/api/external-sources/{id}/test` · `…/sync` · `…/clear-cache` |
| Media coverage | `POST /admin/api/media-coverage/{id}/check-source` |
| Design tokens | `GET/PUT /admin/api/design-tokens` (contrast warnings returned) |

---

## 6. Conventions

### 6.1 Errors (all JSON APIs)

```json
{ "message": "The given data was invalid.", "code": "validation_failed",
  "errors": { "title": ["The title field is required."] } }
```

| HTTP | `code` | When |
|---|---|---|
| 400 | `bad_request` | malformed input |
| 401 | `unauthenticated` | no or expired session |
| 403 | `forbidden` | policy denied (no detail about why the resource exists) |
| 404 | `not_found` | also used for **unpublished** items on public endpoints, so a draft cannot be detected by its status code |
| 409 | `conflict` | stale `lock_version`, media in use, workflow transition not allowed |
| 413 | `payload_too_large` | upload or import too large |
| 419 | `csrf_mismatch` | CSRF token expired |
| 422 | `validation_failed` | Validation. Builder errors use `errors["blocks.{uuid}.content.title"]` plus `pointer` |
| 429 | `too_many_requests` | rate limit (`Retry-After` header) |
| 500 | `server_error` | generic message only. Details are logged, never returned. |
| 503 | `maintenance` / `provider_unavailable` | maintenance mode |

### 6.2 Other rules
- JSON keys are `snake_case`. Timestamps are ISO-8601 with offset. Dates are `YYYY-MM-DD`.
- Pagination: `page` plus `per_page` (default 12, max 50). The `…/items` endpoint uses the block's own limit.
- Filtering parameters are **declared per endpoint**. Unknown parameters are ignored, and invalid values return 422.
- Sorting uses a fixed `sort` enum per endpoint. Raw column names are never accepted.
- URLs in responses are absolute for `url` and `canonical`, and root-relative elsewhere.

### 6.3 Caching and headers

| Endpoint group | Headers |
|---|---|
| Public GET | `Cache-Control: public, max-age=60, stale-while-revalidate=300`, `ETag` (hash of payload cache key), `Vary: Accept-Encoding` |
| `/api/v1/site`, `/menus/*` | `max-age=300` |
| User, preview, admin | `Cache-Control: no-store, private` |
| Feeds | `max-age=900`, `ETag`, `Last-Modified` |
| All | security headers from SECURITY-ARCHITECTURE.md §10 |

Server-side, payloads are cached with **cache-version keys** (CMS-ARCHITECTURE.md §6.5). Publishing anything bumps the relevant versions, so no manual cache clearing is needed.

### 6.4 Rate limits (Laravel `RateLimiter`)

| Limiter | Limit |
|---|---|
| `public-api` | 120 requests/min per IP |
| `search` | 30/min per IP |
| `auth` (login, register, reset) | 5/min per IP + email key; lockout after 10 failures/hour |
| `testimonial-submit` | 3/day per user, 10/day per IP |
| `admin-api` | 300/min per user |
| `import` | 10/hour per user |
| `external-sync-now` | 6/hour per source |

---

## 7. Feeds and SEO endpoints (non-JSON)

| Path | Output |
|---|---|
| `/rss.xml` | RSS 2.0, combined latest items |
| `/rss/news.xml`, `/rss/events.xml`, `/rss/projects.xml`, `/rss/programs.xml`, `/rss/publications.xml` | Per-type RSS. Optional `?category={slug}` (whitelisted to that type's taxonomy; unknown slug → 404) |
| `/sitemap.xml` | sitemap index → `/sitemaps/{type}.xml` |
| `/robots.txt` | from settings, and always includes the `Sitemap:` line |
| `/events/{slug}.ics` | iCalendar for one event |

---

## 8. Testing the API

- **Contract tests** per endpoint check the shape, including snapshot tests of Resource output.
- **Leak tests**: create draft, in-review, archived and soft-deleted items plus private fields, then assert that every public endpoint returns none of them. Include the testimonial private-key assertion.
- **Authorization matrix**: every admin API route × every role → expected 200/403.
- **Rate-limit** and **CSRF** tests for user and admin endpoints.
- An OpenAPI description (`docs/openapi/public-v1.yaml`) is written in Phase 11 for the public API only.
