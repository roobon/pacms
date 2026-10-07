# Probha Aurora CMS — Security Architecture

| | |
|---|---|
| Document | SECURITY-ARCHITECTURE.md (Phase 1 deliverables N, Y) |
| Status | Draft for team review, not yet implemented |
| Related | [CMS-ARCHITECTURE.md](CMS-ARCHITECTURE.md) · [API-ARCHITECTURE.md](API-ARCHITECTURE.md) · [CMS-BLOCK-SCHEMA.md](CMS-BLOCK-SCHEMA.md) |

Security is a design constraint, not a later phase. Phase 12 *audits* what these rules require from Phase 2 onward.

---

## 1. Threat model (summary)

| Asset | Threats | Primary controls |
|---|---|---|
| Unpublished content | Direct URL/API access, preview link leakage, cache poisoning | Published-only scopes and snapshots, 404 for drafts, signed **and** authenticated preview, `no-store` on preview |
| Admin accounts | Credential stuffing, session hijack, privilege escalation | Rate limits, lockout, 2FA for staff roles, secure cookies, session regeneration, server-side policies |
| Public visitors | Stored XSS through content, imports, feeds, custom CSS and attributes | Sanitise on write, escape on output, allowlists, CSP |
| Server / internal network | SSRF through RSS URLs, asset downloads and source checks | SafeHttpClient (IP validation, pinning, redirect re-validation) |
| Server | Malicious uploads, XML bombs/XXE, oversized input | Upload guard, re-encoding, safe XML, size and depth limits |
| Secrets | Leakage in repo, ZIP, logs or API | `.env` only, encrypted provider tokens, no secrets in settings/API/logs |
| Registered users' data | Exposure of email, IP or user ID through testimonials | Resource allowlists, leak tests, IP retention limit |

---

## 2. Authentication

| Area | Design |
|---|---|
| Guard | One `web` session guard for admins and registered users (one `users` table). Admin access requires the `admin.access` permission. |
| Backend | **Laravel Fortify** (headless): login, logout, registration, password reset, email verification and two-factor authentication. Admin login views are Blade. SPA login uses Fortify's JSON responses. |
| SPA auth | **Sanctum stateful (cookie) authentication** on the same domain (`SANCTUM_STATEFUL_DOMAINS` = site domain). The SPA calls `/sanctum/csrf-cookie` first, and no tokens are stored in `localStorage`. |
| API tokens | Sanctum personal access tokens are reserved for future machine clients. They carry abilities and are not used by the SPA. |
| Passwords | Laravel `hashed` cast (bcrypt cost 12, or argon2id if available). Minimum 12 characters, `Password::uncompromised()` check at registration and change. |
| 2FA | TOTP through Fortify, **required** for Super Admin, Administrator and Editor, and optional for others. Recovery codes are encrypted. |
| Sessions | Database driver. `SESSION_SECURE_COOKIE=true`, `http_only`, `same_site=lax`. Session ID regenerated at login and privilege change. Idle timeout 120 min for admin. Logout invalidates the session. |
| Lockout | Throttle 5 attempts/min per email+IP. After 10 failures in an hour, the account is temporarily locked and the event logged. |
| Registration | Open to the public only for **Registered User** (testimonial submission). Email verification is required. Registration can be disabled in settings. |
| Admin login URL | `/admin/login` (separate view, same guard). No security through obscurity. |

---

## 3. Authorization

### 3.1 Principles

- **Every** admin route, admin API route and user API route authorizes on the server through **Policies** (per model) and **Gates** (non-model abilities). The frontend's hiding of controls is only for convenience.
- Permissions are **fine-grained** (`resource.action`). Roles are named bundles of permissions. Code checks permissions, never role names, with one exception: `Gate::before` gives Super Admin everything.
- Ownership rules (Author edits *own* drafts) live in policies, not controllers.
- Role and permission changes need `users.manage_roles`. A user can never grant a permission they don't hold. The last Super Admin cannot be demoted or deleted.

### 3.2 Permission catalogue (v1)

```
admin.access · dashboard.view
pages.view · pages.create · pages.update_own · pages.update_any · pages.delete · pages.submit
pages.approve · pages.publish · pages.custom_css
{news|events|projects|programs|publications|media_coverage|galleries}.view|create|update_own|update_any|delete|submit|approve|publish
team.manage · partners.manage
testimonials.view · testimonials.moderate · testimonials.publish · testimonials.delete
media.view · media.upload · media.update · media.delete · media.force_delete · media.upload_svg
blocks.custom_css · blocks.custom_attributes
block_types.manage · templates.manage · global_blocks.manage · global_blocks.detach
menus.manage · design_tokens.manage
external_sources.manage · external_sources.sync · facebook.connect
seo.manage · redirects.manage
import.run · export.run
users.view · users.manage · users.manage_roles
settings.manage · activity_log.view · backups.manage · revisions.restore
```

### 3.3 Role matrix (proposal: final roles need approval, D-07)

| Capability | Super Admin | Administrator | Editor | Author | Contributor | Moderator | Registered User |
|---|---|---|---|---|---|---|---|
| Admin access | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | – |
| Create content (all types) | ✔ | ✔ | ✔ | ✔ | ✔ (drafts) | – | – |
| Edit own drafts | ✔ | ✔ | ✔ | ✔ | ✔ | – | – |
| Edit others' content | ✔ | ✔ | ✔ | – | – | – | – |
| Submit for review | ✔ | ✔ | ✔ | ✔ | ✔ | – | – |
| Approve / publish / unpublish | ✔ | ✔ | ✔ | publish own (optional) | – | – | – |
| Upload media | ✔ | ✔ | ✔ | ✔ | ✔ | – | – |
| Delete media / force delete | ✔ | ✔ | ✔ / – | – | – | – | – |
| Custom CSS / custom attributes | ✔ | ✔ | – | – | – | – | – |
| Templates, global blocks, menus | ✔ | ✔ | ✔ | – | – | – | – |
| Custom block types, design tokens | ✔ | ✔ | – | – | – | – | – |
| Testimonial moderation | ✔ | ✔ | ✔ | – | – | ✔ | – |
| Testimonial publish / delete | ✔ | ✔ | ✔ | – | – | ✔ / – | – |
| External sources, Facebook connect | ✔ | ✔ | sync only | – | – | – | – |
| JSON import / export | ✔ | ✔ | ✔ | export | – | – | – |
| Users & roles | ✔ | users only (not Super Admin) | – | – | – | – | – |
| Settings, backups | ✔ | ✔ | – | – | – | – | – |
| Activity log | ✔ | ✔ | own actions | – | – | – | – |
| Submit testimonial (public) | – | – | – | – | – | – | ✔ |

Roles and permissions are seeded idempotently and can be adjusted in the admin, apart from the Super Admin role.

---

## 4. Input validation

- Every write goes through a **Form Request** (admin web and API) or a dedicated validator (block trees, imports).
- Block trees are validated against **field definitions** compiled to Laravel rules (CMS-ARCHITECTURE.md §11.2), plus parent/child rules, depth ≤ 12, node count ≤ 2,000 and payload ≤ 2 MB.
- Enums are validated against PHP backed enums, and lengths are always bounded.
- IDs referenced in input (media, entities, terms) are checked to **exist and be usable by the current user**. Private media can't be embedded publicly.
- `$request->validated()` is the only data passed to services. Mass assignment is guarded with `$fillable` on every model.

---

## 5. SQL injection

- Eloquent and the query builder with bound parameters only. `DB::raw` / `whereRaw` are allowed **only** with constant SQL plus bound values, and every use needs a code-review comment.
- Dynamic sources, sorting and filtering accept **only declared enums and keys** (CMS-ARCHITECTURE.md §6.3). User input never becomes a column name, operator or order direction without an allowlist mapping.
- FULLTEXT search passes the query as a bound parameter. Boolean-mode operators are stripped (natural-language mode).

---

## 6. XSS: sanitise on write, escape on output

| Content | Control |
|---|---|
| Blade output | `{{ }}` everywhere. `{!! !!}` is allowed only for already-sanitised rich text, through one `<x-rich-text>` component (lint rule / review checklist). |
| React output | JSX escaping. `dangerouslySetInnerHTML` is used **only** in the shared `RichText` component, and only with server-sanitised HTML. |
| Rich text (editor, imports, RSS descriptions, testimonial moderation edits) | **symfony/html-sanitizer 7.4** with the allowlist in CMS-BLOCK-SCHEMA.md §8.3. Applied **on save/import/sync**, so stored HTML is always clean. Re-sanitised on read for external items (defence in depth). |
| URLs (links, buttons, menus, external items) | Protocol allowlist `http, https, mailto, tel` and relative paths. `javascript:`, `data:` and `vbscript:` are rejected. Validated on write, checked again when rendered. |
| Block `advanced.attributes` | Only `data-*`, `aria-*`, `role`, `title`, `lang`. Values are plain text. `on*`, `style`, `href`, `src`, `id` and `class` are refused (classes have their own validated field). |
| Style values | Tokens or validated literals only (CMS-ARCHITECTURE.md §8.2), so no free CSS string reaches the style compiler. |
| Custom CSS (permission `blocks.custom_css` / `pages.custom_css`) | Parsed with **sabberworm/php-css-parser**. Every selector is prefixed with the block or page scope. `@import`, `@charset`, `@namespace`, `expression()`, `behavior`, `-moz-binding`, `javascript:` and external `url()` are rejected (same-site media URLs only). Output is re-serialised from the parse tree, never passed through raw. Size limit 10 KB. |
| JSON-LD | Built server-side with `json_encode(JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)`, so `</script>` can't break out. |
| Initial SPA payload | Embedded as `<script type="application/json">` with the same encoding flags. |
| SVG | See §8. |

A **Content Security Policy** is the second line of defence (§10).

---

## 7. CSRF, CORS and clickjacking

- CSRF protection applies to all web and admin routes and to Sanctum's stateful API (`X-XSRF-TOKEN`). No CSRF exemptions except signed webhook endpoints (none in v1).
- **CORS**: the public API allows `GET` from any origin (read-only, no credentials). The user, preview and admin APIs are same-origin only, with credentials.
- `X-Frame-Options: SAMEORIGIN` and CSP `frame-ancestors 'self'`. The builder preview iframe is same-origin, and `postMessage` handlers check `event.origin` and message schema.

---

## 8. File uploads

| Control | Rule |
|---|---|
| Allowlist | Images: jpg/jpeg, png, gif, webp, avif. Documents: pdf, docx, xlsx, pptx, odt, ods, odp, txt, csv. Video: mp4, webm. Audio: mp3, m4a. **Executable and script types are never accepted**: php, phtml, phar, js, html, htm, svgz, exe, sh, bat, and any double extension ending in these. |
| Detection | MIME detected from content (`finfo`) must match the extension's expected MIME, or the upload is rejected. |
| Filenames | Stored as `<ulid>.<ext-from-detected-mime>`. The original name is kept as metadata only and escaped on display. |
| Images | Re-encoded through Intervention Image (GD). This strips metadata including GPS EXIF and neutralises polyglot payloads. Decompression-bomb guard: max 50 megapixels, max dimension 12,000 px. |
| SVG | **Disabled by default** (D-10). If enabled, only for users with `media.upload_svg`: sanitised with **enshrined/svg-sanitize** (scripts, event handlers, external references and `foreignObject` removed) and served with `Content-Security-Policy: script-src 'none'` and `Content-Type: image/svg+xml`. **Phase 7:** implemented. The switch is Settings → Media → *Allow SVG uploads* (logged). Uploads are ≤ 1 MB. After sanitising, the result is checked again for script-like content, and the upload is refused if any remains. The staff file route sends `Content-Security-Policy: default-src 'none'; … sandbox`. Public SVGs are served from `/storage` by the web server, so the sanitiser is the protection there. |
| Sizes | Default limits: images 10 MB, documents 25 MB, video 100 MB (configurable, and must stay within PHP `upload_max_filesize`/`post_max_size`). Testimonial photos 2 MB. |
| Storage | `public` disk (`storage/app/public`, symlinked) for public media, `private` disk (`storage/app/private`) for private media and imports. **Execution is disabled in storage paths** through web server config (DEPLOYMENT-ARCHITECTURE.md §5) and a bundled `.htaccess` for Apache. |
| Serving | Public files are served directly with `X-Content-Type-Options: nosniff`. Private files go through a controller with a policy check and `Content-Disposition: attachment` for non-image types. |
| Authorization | `media.upload` for the library, verified email for testimonial photos. Testimonial photos stay private until published. |

---

## 9. Outbound requests (SSRF) and XML

### 9.1 SafeHttpClient: the only way PACMS makes outbound requests

Used by RSS sync, gallery providers, media-coverage checks, JSON-import asset downloads and Facebook image caching. The Meta Graph API client also goes through it, with host pinned to `graph.facebook.com`.

1. **Scheme**: `https` (and `http` only where the admin explicitly allowed it for that source). All other schemes (`file`, `ftp`, `gopher`, `data`, `php`, `phar`, …) are rejected.
2. **Port**: 443 or 80 only.
3. **Host**: no IP-literal hosts other than public IPs, and no `localhost`, `*.local`, `*.internal` or single-label names.
4. **DNS**: resolve all A/AAAA records. **Reject if any** address is in a blocked range:
   `0.0.0.0/8, 10.0.0.0/8, 100.64.0.0/10, 127.0.0.0/8, 169.254.0.0/16 (incl. cloud metadata 169.254.169.254), 172.16.0.0/12, 192.0.0.0/24, 192.0.2.0/24, 192.168.0.0/16, 198.18.0.0/15, 198.51.100.0/24, 203.0.113.0/24, 224.0.0.0/4, 240.0.0.0/4, 255.255.255.255/32, ::/128, ::1/128, ::ffff:0:0/96 (mapped — check embedded IPv4), 64:ff9b::/96, fc00::/7, fe80::/10, ff00::/8`.
5. **Pinning**: connect to the **validated IP** (cURL `CURLOPT_RESOLVE`) so DNS rebinding between check and connect can't redirect the request.
6. **Redirects**: followed manually, at most 3, and **each hop is re-validated** through steps 1–5.
7. **Limits**: connect timeout 5 s, total 15 s. Response body streamed and aborted beyond the per-use limit (RSS 5 MB, asset 20 MB, source check 1 MB). Content-Type checked against the expected type.
8. **Hygiene**: no cookies, no forwarding of internal headers, a fixed `User-Agent: PACMS/1.0 (+site URL)`, and credentials only for the provider's own host.
9. **Errors**: normalised messages stored on the source. Raw internal details are logged only.

Unit tests cover every blocked range, IPv6-mapped addresses, redirect to a private IP, and a DNS answer that mixes public and private addresses.

### 9.2 Safe XML (RSS/Atom)

- Size is checked **before** parsing.
- **Reject any document containing `<!DOCTYPE`** with `<!ENTITY`. Legitimate feeds don't need DTDs. This blocks XXE and billion-laughs attacks outright.
- Parse with `LIBXML_NONET` and **never** with `LIBXML_NOENT` or `LIBXML_DTDLOAD`. libxml's own entity limits stay on (no `LIBXML_PARSEHUGE`).
- Parsed text goes through the HTML sanitiser and URL validator before storage.
- Test fixtures include XXE (`file:///etc/passwd`), a billion-laughs payload, external DTDs and oversized documents.

### 9.3 JSON import

- Size, depth and node-count limits are checked before full decoding logic. `json_decode` uses a depth limit.
- Imported content is treated purely as data. There are no code paths that `eval`, `include`, deserialise PHP objects (`unserialize` is never used on user input) or interpret templates.
- The full validation pipeline is in CMS-BLOCK-SCHEMA.md §16. Custom CSS and custom types are permission-gated there too.

---

## 10. HTTP security headers

Set by `SecurityHeaders` middleware (web, admin, API) and mirrored in web-server examples:

| Header | Value (public site) |
|---|---|
| `Content-Security-Policy` | `default-src 'self'; script-src 'self' 'nonce-{n}'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; media-src 'self' https:; font-src 'self'; connect-src 'self'; frame-src https://www.youtube-nocookie.com https://player.vimeo.com https://www.facebook.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'; upgrade-insecure-requests` |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` (production, once HTTPS is verified) |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=(), payment=(), usb=()` |
| `X-Frame-Options` | `SAMEORIGIN` |
| `Cross-Origin-Opener-Policy` | `same-origin` |

- `style-src 'unsafe-inline'` is needed for the per-page compiled `<style>` element (§8.4 of the architecture). That CSS is generated only from validated values, so the risk is contained. Moving to nonce- or hash-based styles is a Phase 12 item.
- `img-src https:` allows external images kept with the `external` asset strategy and RSS thumbnails. This can be tightened if the organisation always downloads assets.
- The admin CSP is the same, except `frame-src 'self'` for the preview iframe.

---

## 11. Secrets and configuration

- Secrets live in `.env` only: `APP_KEY`, DB credentials, mail, `META_APP_ID/SECRET`, backup disk credentials, Flickr/YouTube keys. `.env` is in `.gitignore` and **excluded from the release ZIP** (DEPLOYMENT-ARCHITECTURE.md). `.env.example` has keys only, no values.
- Provider tokens obtained at runtime (Facebook Page token) are stored in `external_provider_accounts.credentials` with Laravel's `encrypted` cast (AES-256, `APP_KEY`). **Rotating `APP_KEY` requires `APP_PREVIOUS_KEYS`** so they can be re-encrypted (documented).
- Secrets never appear in `settings`, API responses, JSON exports, activity-log properties or exception messages. The log formatter redacts known keys.
- `APP_DEBUG=false` and `APP_ENV=production` in production. `php artisan pacms:doctor` flags debug mode as a failure (DEPLOYMENT-ARCHITECTURE.md §4).

---

## 12. Unpublished-content guarantees

1. The public controllers use only `published()` scopes or published snapshots (staged types). A shared test helper asserts this for every registered content type.
2. Drafts and soft-deleted items return **404**, not 403, on public endpoints.
3. Preview requires signature **plus** an authenticated session with the `view` policy. Responses are `no-store` and `noindex`.
4. The public payload cache is only written by public builders. Preview builders have no cache write path.
5. Menus, search, sitemap, RSS, dynamic blocks, related-item lists and JSON-LD all read from the same published scopes.
6. Scheduled publishing and unpublishing bump cache versions immediately.

---

## 13. Privacy

- Testimonials: email, `user_id`, IP, consent data and rejection reason are never public. IPs are purged after 90 days.
- Team members: email and phone are public only when explicitly enabled.
- Activity logs store IP only for auth and security events, and user agents only as a hash. Retention is 365 days.
- Third-party embeds (YouTube, Vimeo, Facebook plugin) are click-to-load and nothing is loaded before interaction. Analytics are off by default. Cookie consent is required if analytics are ever enabled (Q-08).
- Media-coverage archives are public only when rights are confirmed.

---

## 14. Logging, monitoring and incident basics

- Security-relevant events go to the activity log: failed logins, lockouts, 2FA changes, role and permission changes, custom CSS saves, SVG uploads, imports with stripped content, provider credential changes and backups.
- Laravel's daily log channel, with production level `warning` and above, plus `security` channel entries.
- Admin dashboard warnings for: debug enabled, queue not running (no heartbeat in 10 min), scheduler not running, provider tokens expiring, failed jobs, and a backup older than 48 h.

---

## 15. Security checklist per phase (definition of done)

- [ ] New routes have a policy or gate check, plus a test for an unauthorised role.
- [ ] New public fields are added to a Resource allowlist and covered by leak tests.
- [ ] New input is validated (Form Request or field definitions) with bounded lengths.
- [ ] No new `{!! !!}`, `dangerouslySetInnerHTML`, `DB::raw`, `unserialize` or outbound HTTP outside the approved helpers.
- [ ] New secrets are added to `.env.example` (key only) and documented.
- [ ] `composer audit` and `npm audit --omit=dev` are clean (or exceptions documented).
