# Phase 2 — Foundation · Completion Report

| | |
|---|---|
| Date | 2026-09-24 |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). **Needs team review before Phase 3.** |
| Decisions applied | D-01 Laravel 12 · D-05 JavaScript · Q-02 English only · Q-03 one site per install · defaults for D-02, D-04, D-06, D-07 |

---

```
PHASE:   2 — Foundation
STATUS:  Complete — all automated checks pass; awaiting team review (manual browser check requested, see Known issues)
```

## Implemented

| Area | What exists now |
|---|---|
| Project | Laravel **12.69.2** on PHP 8.3.33. Composer platform pinned to 8.3.33. MySQL databases `pacms` and `pacms_testing` (utf8mb4). Git repository initialised (branch `main`, **no commits yet**). `.env.example` documented. Stock Laravel README replaced. |
| Authentication | Laravel **Fortify** under `/auth/*`: staff sign-in (Blade), password reset, e-mail verification, profile and password update, and **two-factor authentication** (TOTP, recovery codes, confirmation step). Public registration creates *Registered User* accounts only and can be switched off with `PACMS_PUBLIC_REGISTRATION`. Suspended accounts cannot sign in and are signed out mid-session. Login throttling: 5/min per e-mail+IP and 10/hour per e-mail. Fortify's new passkeys feature is **disabled** (not in the approved design). |
| SPA auth | **Sanctum** stateful cookie auth for the React SPA. Verified end to end over HTTP: CSRF cookie → register → `/api/v1/me` → logout. |
| Authorization | **Spatie Permission 8**: 101 permissions and 7 roles from `App\Auth\PermissionCatalog` (single source of truth, matrix per SECURITY-ARCHITECTURE.md §3.3). Idempotent `RolePermissionSynchronizer` never re-adds permissions an admin removed. `Gate::before` for Super Admin. `UserPolicy`. Admin area gated by `admin.access` + verified e-mail + active status. **2FA enforced** for Super Admin, Administrator and Editor (`PACMS_ENFORCE_2FA`). |
| Escalation guards | Users can only assign roles whose permissions they hold. Only a Super Admin can grant Super Admin. The last active Super Admin cannot be demoted, suspended or deleted. Nobody can suspend or delete themselves. Role editing is limited to `users.manage_roles` (Super Admin by default), and the Super Admin role is locked. |
| Admin UI | Blade + Bootstrap 5.3 + Bootstrap Icons with a custom neutral admin theme (not default Bootstrap). Responsive sidebar (off-canvas on mobile) and permission-filtered navigation. Screens: **Dashboard** (system health, recent activity), **Users** (search, filter, create, edit, suspend, soft delete), **Roles & Permissions**, **Activity Log**, **General Settings**, **Design Tokens** (with live component preview and contrast warnings), **Profile**, **Security** (2FA setup). Shared components: layout, page header, field, flash and error summary, status badge, empty state. Friendly error pages 403/404/419/429/500/503. |
| Design tokens | `TokenCatalog` (colours, fonts, spacing, radius, shadows, motion). Values are validated (hex, fixed font list, px/rem only), stored in settings, and compiled to `storage/app/public/theme/tokens-<hash>.css` with `-rgb` triplets and automatic readable `on-*` text colours. WCAG contrast check on 9 text/background pairs. The public theme maps Bootstrap's CSS variables onto the tokens, so **branding changes need no rebuild**. |
| Settings | `settings` table + `SettingsService`: whitelisted keys, code-defined public/private flags, cached per group. Neutral placeholder defaults ("Your Organization"). |
| Activity log | `activity_logs` + `ActivityLogger`: login, logout, failed login, lockout, registration, verification, password reset, 2FA enable/disable/failure, user create/update/delete, role changes, role-permission changes, settings, design tokens. Secrets are redacted. IP is recorded only for auth, security and user events. Daily pruning after 365 days. |
| Security | `SecurityHeaders` middleware: CSP with **per-request nonce** (Vite tags carry it), `object-src 'none'`, `frame-ancestors 'self'`, nosniff, X-Frame-Options, Referrer-Policy, Permissions-Policy, COOP, and HSTS in production over HTTPS. JSON error envelope `{message, code, errors}` for all API/JSON errors, with no stack traces unless debug. `preventLazyLoading` and `preventSilentlyDiscardingAttributes` outside production. Morph map enforced. Laravel's private-disk `/storage/{path}` route disabled. |
| Public API | `/api/v1/site` (public settings only) and `/api/v1/me` (explicit field allowlist). JSON 404 for unknown API routes. Rate limit 120/min. |
| Public SPA | React 19 + React Router 8 + TanStack Query + axios. **Server-assisted shell**: real HTTP status (404 for unknown URLs), server-rendered title, description, canonical, Open Graph and JSON-LD `Organization`, plus an initial JSON payload (safely encoded) so first paint needs no API call. Pages: Home (from settings, nothing hard-coded), Not Found, Sign in, Create account, My account (lazy-loaded). Skip link, focus moved to the page heading on navigation, error boundary, skeleton loading state. |
| Operations | `php artisan pacms:create-admin` (CLI, no web installer) and `php artisan pacms:doctor` (16 checks, also shown on the dashboard). Scheduler heartbeat, queue heartbeat job, a short-lived queue worker started by the scheduler every minute (shared-hosting mode), and activity-log pruning. `ProductionSeeder` (idempotent) and `DevelopmentSeeder` (local only: one user per role). |

## Files created

```
app/Actions/Fortify/CreateNewUser.php (rewritten)       app/Auth/{PermissionCatalog,RolePermissionSynchronizer,AuthActivitySubscriber}.php
app/Cms/Design/{TokenCatalog,DesignTokenService}.php     app/Console/Commands/{CreateAdminCommand,DoctorCommand}.php
app/Enums/UserStatus.php                                  app/Jobs/QueueHeartbeat.php
app/Http/Controllers/Admin/{Dashboard,User,Role,ActivityLog,Settings,DesignToken,Account}Controller.php
app/Http/Controllers/Api/V1/{Site,Me}Controller.php       app/Http/Controllers/Public/SpaController.php
app/Http/Middleware/{SecurityHeaders,EnsureAdminAccess,RequireTwoFactor,EnsureUserIsActive}.php
app/Http/Requests/Admin/UserRequest.php                   app/Http/Resources/Public/{Site,Me}Resource.php
app/Http/Responses/LoginResponse.php                      app/Models/{Setting,ActivityLog}.php
app/Policies/UserPolicy.php                               app/Services/{ActivityLog/ActivityLogger,Public/SitePayload,Settings/SettingsService,System/HealthCheck,Users/UserManagementService}.php
app/Support/{Admin/AdminNavigation,Color/Color,Http/ApiErrorRenderer}.php
config/pacms.php                                          routes/admin.php
database/migrations/2026_09_24_110000_create_settings_table.php, …110100_create_activity_logs_table.php
database/seeders/{ProductionSeeder,DevelopmentSeeder}.php
resources/views/{spa,admin/**,auth/**,components/admin/**,components/auth/layout,errors/**}.blade.php
resources/scss/{admin,public}.scss, resources/scss/vendor/_bootstrap-subset.scss
resources/js/admin/app.js, resources/js/public/** (main, App, routes, pages, components, hooks, api, contexts, utils), resources/js/test/setup.js
tests/Pest.php, tests/Unit/{Color,PermissionCatalog}Test.php, tests/Feature/{Admin,Api,Auth,Authorization,Console,Public}/*Test.php
eslint.config.js, phpstan.neon, README.md, docs/phase-reports/PHASE-2-REPORT.md
```

## Files modified

`bootstrap/app.php`, `bootstrap/providers.php` (Pint), `app/Models/User.php`, `app/Providers/{App,Fortify}ServiceProvider.php`, `app/Actions/Fortify/UpdateUserProfileInformation.php`, `config/fortify.php` (prefix `/auth`, features), `config/filesystems.php` (private disk route off), `routes/{web,api,console}.php`, `database/migrations/0001_01_01_000000_create_users_table.php` (status, last login, soft deletes; never run on a shared DB), `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`, `vite.config.js`, `package.json`, `composer.json`, `phpunit.xml` (MySQL test DB), `.env.example`, `tests/TestCase.php`, `docs/DATABASE-ARCHITECTURE.md`, `docs/UI-DESIGN-SYSTEM.md` (small implementation notes). Removed: Tailwind scaffold, `welcome.blade.php`, Fortify passkeys migration, Sail.

## Database changes

Tables: `users` (+ `status`, `last_login_at`, `last_login_ip`, `deleted_at`, 2FA columns), `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `personal_access_tokens`, `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`, `settings`, `activity_logs`. 8 migrations, and `migrate:fresh --seed` is verified.
Deviation from DATABASE-ARCHITECTURE.md: `activity_logs.ip` is `varchar(45)` (doc updated). `users.avatar_media_id` moves to Phase 3 with the `media` table (doc updated).

## API changes

`GET /api/v1/site`, `GET /api/v1/me` (auth:sanctum), JSON 404 fallback under `/api/*`, and Fortify endpoints under `/auth/*` (JSON when `Accept: application/json`).

## UI changes

The admin shell and 9 admin screens, 6 auth screens, 6 error pages, the design-system preview page, and the public SPA shell with 5 routes.

## Tests

| Suite | Result |
|---|---|
| Pest (`composer test`) against MySQL `pacms_testing` | **87 passed, 221 assertions**, 0 failed (~66 s; most of that time is MySQL migrations on Windows) |
| Vitest (`npm test`) | **5 passed** |
| Larastan level 6 (`composer analyse`) | **No errors** |
| Pint (`composer lint`) | Passed |
| ESLint (`npm run lint`) | Passed, 0 warnings |

Coverage highlights: auth (login, 2FA challenge path, suspended users, throttling, JSON login), registration (role forced, self-assignment ignored, password policy, verification e-mail), 2FA enforcement per role, the **screen-level permission matrix** (14 role/screen cases), escalation guards, role editing, settings and **token CSS-injection attempts rejected**, contrast warnings, API allowlists and error envelope, rate limiting, security headers and CSP nonce, SPA shell (SEO tags, real 404, **`</script>` break-out prevented**), and the CLI commands.

## Build

`npm run build` succeeds. Public initial JS is about **112 KB gzipped** (react chunk 81.4 + client 19.1 + main 11.7), within the 120 KB budget. Account pages load as separate chunks. Admin JS is 24 KB gzipped (no React). CSS: public 31.8 KB and admin 30.8 KB gzipped, plus Bootstrap Icons. `php artisan optimize` (config, route, event and view caches) succeeds.

## Security check (SECURITY-ARCHITECTURE.md §15)

- [x] Every admin route passes `auth`, `verified`, `admin.access`, 2FA enforcement and throttling, and each action has a policy or permission check, with tests for unauthorised roles.
- [x] Public fields go through resource allowlists (`MeResource` key list is asserted in tests). Private settings are not exposed (tested).
- [x] All input is validated (Form Request or `validate()`), with bounded lengths. Token values are allowlisted.
- [x] No `DB::raw` or `unserialize`, and no outbound HTTP. `{!! !!}` is used only for (a) the JSON payloads, encoded with `JSON_HEX_*` flags (tested), and (b) Fortify's server-generated 2FA QR-code SVG.
- [x] New env keys are documented in `.env.example`.
- [x] `composer audit`: no advisories. `npm audit`: 0 vulnerabilities.

## Performance check

Settings are cached per group. The token stylesheet is a static, content-hashed file. Lazy loading of relations throws outside production (N+1 guard). Public SPA first paint needs no API request (initial payload). Route-level code splitting is in place.

## Deployment impact

- New env keys: `PACMS_ENFORCE_2FA`, `PACMS_PUBLIC_REGISTRATION`, `PACMS_MANAGED_QUEUE_WORKER`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_SECURE_COOKIE`.
- New commands: `php artisan db:seed --class=ProductionSeeder --force` (every deploy), `php artisan pacms:create-admin` (first install), and `php artisan pacms:doctor`.
- Cron `* * * * * php artisan schedule:run` is required (verified locally: heartbeats and the worker run).
- `php artisan storage:link` is required (token stylesheet).

## Known issues / needs your attention

1. **Manual browser check not yet done.** I verified everything over HTTP (status codes, headers, forms, SPA JSON auth) and with automated tests, but could not click through a real browser. Please check the admin screens, the 2FA QR setup with a phone, the SPA pages and the browser console (CSP). Especially check `npm run dev` hot reload under the CSP.
2. **`http://pacms.test` is not live yet.** Laragon creates the virtual host when Apache is reloaded (Laragon → Menu → Apache → Reload, or Stop/Start All). Until then use `php artisan serve`.
3. **Node on PATH is v21**, which Vite 8 does not support. Use Laragon's Node 22 (`D:\laragon\bin\nodejs\node-v22`): put it first on PATH, or switch Laragon's Node version.
4. **Git: repository initialised, but no commit made.** I did not commit without your approval. Recommended first commit: "Phase 1 docs + Phase 2 foundation".
5. Development users (`<role>@pacms.test`) exist in the **local** DB only. The seeder refuses to run outside `local`.
6. The local DB contains the smoke-test settings "Probha Aurora Demo". Change them in Admin → Settings.
7. Test runtime (~66 s) is dominated by migrating MySQL on Windows. `php artisan schema:dump` can speed this up later.

## Architectural decisions (made during Phase 2)

| Decision | Reason |
|---|---|
| Fortify routes under `/auth/*`, Blade auth pages for staff, JSON for the SPA | Keeps SPA URLs (`/account/*`) free, and gives one backend for both |
| Fortify passkeys disabled | Not in the approved design. Can be enabled later (feature flag + migration) |
| Administrator role excludes `users.manage_roles` | Only Super Admin edits role definitions. Administrators still manage users within their own permissions |
| Default `status` attribute on `User` | Strict model mode revealed that unrefreshed models had a null status |
| Laravel's private-disk `/storage/{path}` route disabled | It overlaps public storage URLs. Private files will be served by authorised controllers (Phase 3/7) |
| Token font choices limited to a fixed list | Prevents CSS injection through free-text font stacks |
| Server-rendered `<title>`/robots removed on SPA boot | React 19 renders page metadata itself. Crawlers still read the server tags |

## Team review round (2026-09-24)

Manual review by the team lead in the browser, following the Phase 2 review checklist.

| Finding | Type | Resolution | Commit |
|---|---|---|---|
| Small buttons: label not vertically centred | Bug (UI) | `.btn` uses inline-flex centring with the 44px touch target | `907d883` |
| Token stylesheet linked through `APP_URL` broke on other hosts | Bug | Same-site stylesheet URL is now root-relative | `907d883` |
| `php`/Vite failed on the dev machine (PATH pointed to `php.exe`; Node 21.0) | Environment | User PATH fixed; Node ≥20.19/22.12 declared (`engines`, `.nvmrc`, `engine-strict`) | `f2f2001` |
| No feedback after clicking the e-mail verification link | UX | Success notice on `/account?verified=1` | `e8bb2e0` |
| Open tab showed "Something went wrong" after a rebuild (stale chunk) | Bug (deploys) | `lazyWithReload`: one guarded reload when a lazy chunk is missing, with 4 tests | `5636968` |
| Admin sidebar white on large screens (Bootstrap `.offcanvas-lg` override) | Bug (UI) | Explicit background at ≥lg | `974fcbd` |
| Sidebar version text 3.75:1 contrast | Accessibility | Raised to 6.96:1 | `a04589a` |
| **A6/A7: two-factor could not be enabled** | Bug (security) | Security page now requires password confirmation first. End-to-end 2FA test added | `51b718b` |

Checks after fixes: **Pest 89 passed**, **Vitest 9 passed**, Larastan, Pint and ESLint clean, and the build succeeds. Items confirmed in the browser by the reviewer: D1, D4, D5, B3, the admin dashboard and navigation, and the other checklist items reported as tested. **A6/A7 needs a re-test after `51b718b`.**
Section F decisions: the proposed defaults are accepted unless the team objects (role matrix, Super-Admin-only role editing, 2FA for privileged roles, public registration on, default palette).

## Next phase

**Phase 3 — CMS Core + Media core** (per D-04): pages with hierarchy and staged publishing, workflow, scheduling, secure preview, revisions, SEO metadata and redirects, taxonomy, and the media library core (upload guard, image variants, media picker).
**Not started. Waiting for your review and approval.**
