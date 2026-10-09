# Probha Aurora CMS (PACMS)

Organisational content management system: Laravel 12 backend and admin, public React SPA.

- Architecture and specifications: [docs/](docs/README.md)
- Current status: **Phase 7 (Media, advanced) approved and merged (v0.7.0). Phase 8 (Content Modules) in progress: Part 8A (content engine, News, Events, sidebars) approved and merged; Part 8B (Projects, Programs, Publications) approved and merged; Part 8C.1 (Team, Partners, Galleries) approved and merged; 8C.2 (Testimonials, Media Coverage, search) approved and merged; 8D (admin-made content types) is next.** See [docs/phase-reports/PHASE-8C2-REPORT.md](docs/phase-reports/PHASE-8C2-REPORT.md).

## Requirements

PHP 8.3+ (bcmath, ctype, curl, dom, exif, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, sodium, xml, xmlreader, zip) · MySQL 8 · Composer 2 · **Node.js ≥ 22.12 or ≥ 20.19** (build only; not needed on the server).

## Local setup (Laragon)

```bash
composer install
npm install
cp .env.example .env          # then set APP_ENV=local, APP_DEBUG=true, APP_URL=http://pacms.test, DB_*
php artisan key:generate
php artisan migrate --seed    # roles/permissions, token stylesheet, and local dev users
php artisan storage:link
npm run build                 # or: npm run dev
php artisan pacms:create-admin
php artisan pacms:doctor
```

In the `local` environment, `DevelopmentSeeder` creates one account per role (`<role>@pacms.test`, password in `database/seeders/DevelopmentSeeder.php`). Set `PACMS_ENFORCE_2FA=false` locally to skip the two-factor requirement for privileged roles.

- Admin: `/admin` (sign in at `/auth/login`)
- Public site: `/`
- Public API: `/api/v1/*`

## Demo content

```bash
php artisan pacms:demo --fresh   # wipes the database and uploaded media, then fills every area
```

Creates linked demo content through the real services: team, partners, galleries, programs, projects, events, news, publications, media coverage and testimonials in several workflow and moderation states, plus categories, media (images, PDFs, a private archive file), a sidebar and a call-to-action global block, and pages with blocks (Home is set as the home page). Run it again any time for a clean start. Local environment only; never in production.

- Staff: `<role>@pacms.test` / `Aurora-dev-2026` (e.g. `editor@pacms.test`, `moderator@pacms.test`)
- Registered users: `rahim@`, `nadia@`, `tanvir@`, `farzana@demo.pacms.test` / `Aurora-demo-2026`; `unverified@demo.pacms.test` has no verified e-mail

## Quality checks

```bash
composer test        # Pest (uses MySQL database pacms_testing; tests/Search commits data, see tests/Pest.php)
composer analyse     # Larastan level 6
composer lint        # Pint (PSR-12 / Laravel style)
npm test             # Vitest
npm run lint         # ESLint
npm run build        # production assets
```

## Deployment

A standard Laravel project released as a ZIP. There is no web installer. See [docs/DEPLOYMENT-ARCHITECTURE.md](docs/DEPLOYMENT-ARCHITECTURE.md).

## Team

Syed Ziaul Habib (Team Lead / Lead Developer) · Hasibul Hasan · Khandoker Humayoun Kobir. Development is AI-assisted (ChatGPT, Claude Code). All code is reviewed and approved by the team.
