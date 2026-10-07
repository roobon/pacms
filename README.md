# Probha Aurora CMS (PACMS)

Organisational content management system: Laravel 12 backend and admin, public React SPA.

- Architecture and specifications: [docs/](docs/README.md)
- Current status: **Phase 5 (Custom Block Builder) approved and merged (v0.5.0). Phase 6 (AI JSON import/export) is next.** See [docs/phase-reports/PHASE-5-REPORT.md](docs/phase-reports/PHASE-5-REPORT.md).

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

## Quality checks

```bash
composer test        # Pest (uses MySQL database pacms_testing)
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
