# Probha Aurora CMS (PACMS)

A content management system for organisations: a **Laravel 12** backend and admin, and a public **React** website built from blocks.

**Status:** Phase 11 (public React website) in progress: 11A (search, contact form, account area) merged; 11S (starter kit: five design styles) merged; 11B (states, accessibility, performance, OpenAPI) next. Latest release: **v0.10.0**. See the [roadmap](docs/DEVELOPMENT-ROADMAP.md) and the [phase reports](docs/phase-reports/).

---

## What PACMS does

| Area | Highlights |
|---|---|
| **Pages** | Visual block builder (sections, columns, heroes, sliders, cards, collections…), drafts, review and publishing workflow, scheduling, revisions with compare and restore, preview, SEO, page templates |
| **Content modules** | News, Events, Projects, Programmes, Publications, Team, Partners, Galleries, Testimonials (with moderation), Media Coverage, plus **your own content types** made in the admin (Design → Content types) |
| **Media** | Library with responsive images, alt text, categories, private files, usage tracking |
| **Navigation** | Menus with a drag-and-drop builder, mega panels, header and footer built from blocks, mobile drawer |
| **Design** | Design tokens (colours, fonts, spacing), global blocks, custom block types, a starter kit of designed templates |
| **Text editor** | Headings, theme colours and highlights, alignment, highlight boxes, images with captions, tables, links, full screen |
| **Sharing** | JSON Feed of your content (`/feed.json`), reading other sites' feeds (JSON Feed, RSS, Atom, YouTube) and your Facebook Page's posts |
| **AI-friendly JSON** | Import and export pages, sections and templates as JSON that tools like ChatGPT can write ([format](docs/CMS-BLOCK-SCHEMA.md)) |
| **Search & SEO** | Site search (page and header search), sitemaps, redirects, structured data |
| **Visitors** | Contact form with spam protection and a Messages inbox; accounts to send testimonials, change details or delete the account |
| **Security** | Roles and permissions, two-factor sign-in for staff, HTML cleaning, safe outgoing requests, activity log |

---

## Requirements

- **PHP 8.3 or newer** with: bcmath, ctype, curl, dom, exif, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, sodium, xml, xmlreader, zip
- **MySQL 8**
- **Composer 2**
- **Node.js 22.12+ or 20.19+**, only to build the front-end assets (not needed on the server)

---

## Getting started (local)

```bash
git clone https://github.com/roobon/pacms.git
cd pacms
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Edit `.env` (see [Configuration](#configuration)), create the database, then:

```bash
php artisan migrate --seed          # tables, roles and permissions, and the local staff accounts
php artisan storage:link
npm run build                       # or keep "npm run dev" running while you work
php artisan pacms:starter --pages   # optional: templates, header, footer, menus and draft pages
php artisan pacms:doctor            # checks the installation
```

**Run it:**

```bash
composer dev                        # web server, queue worker, log viewer and Vite together
# or separately:
php artisan serve                   # http://127.0.0.1:8000
npm run dev
php artisan schedule:work           # scheduled publishing, feed syncs and other timed jobs
```

| Where | Address |
|---|---|
| Website | `http://127.0.0.1:8000/` |
| Admin | `http://127.0.0.1:8000/admin` |
| Public API | `http://127.0.0.1:8000/api/v1/…` |

On Laragon (Windows), use the site address Laragon gives you (e.g. `http://pacms.test`) instead of `php artisan serve`.

### Sign-in accounts (local only)

In the `local` environment the seeder creates one staff account per role: **`<role>@pacms.test`**, password **`Aurora-dev-2026`**. For example `super-admin@`, `administrator@`, `editor@`, `author@`, `contributor@`, `moderator@pacms.test`.

For a real installation, create your own first account with `php artisan pacms:create-admin`.

### Working on more than one computer

```bash
git fetch origin
git checkout main && git pull
git checkout <your-branch> && git pull
composer install && npm install     # when dependencies changed
php artisan migrate                 # when there are new migrations
php artisan pacms:blocks:sync       # when there are new block types
```

---

## Configuration

The important `.env` settings:

| Setting | Local | Production |
|---|---|---|
| `APP_ENV`, `APP_DEBUG` | `local`, `true` | `production`, `false` |
| `APP_URL` | `http://127.0.0.1:8000` | `https://www.your-site.org` |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | your MySQL | your MySQL |
| `SANCTUM_STATEFUL_DOMAINS` | `127.0.0.1:8000,localhost:8000` | `www.your-site.org` |
| `SESSION_SECURE_COOKIE` | `false` if your browser drops sign-ins over http | `true` (https) |
| `PACMS_ENFORCE_2FA` | `false` to skip two-factor setup for staff | `true` |
| `PACMS_MANAGED_QUEUE_WORKER` | `false` | `true` only if a Supervisor/systemd worker runs |
| `MAIL_*` | `log` is fine | your SMTP server |
| `META_APP_ID`, `META_APP_SECRET` | optional | only for "Connect with Facebook" |

`SANCTUM_STATEFUL_DOMAINS` must name the address you open the site at. Otherwise signing in on the public website does not stick.

---

## Demo content

```bash
php artisan pacms:demo --fresh
```

This **wipes the database and uploaded files**, then fills every area with linked sample content. It includes:
- news, events, projects, programmes, publications, team, partners, galleries;
- testimonials, media coverage and a feed source;
- menus, header and footer, and pages built from blocks.

It's for local testing only and never runs in production. Besides the staff accounts above, it creates registered users `rahim@`, `nadia@`, `tanvir@` and `farzana@demo.pacms.test` (password `Aurora-demo-2026`).

## Starter kit

```bash
php artisan pacms:starter            # add what is missing (never changes your edits)
php artisan pacms:starter --pages    # also create draft Home, About us, Contact and Get involved pages
php artisan pacms:starter --pages --style=bold   # the pages, header and footer in one style
php artisan pacms:starter --restore=all   # put the shipped templates back to their original design
```

The kit comes in **six styles**: Classic, Bold, Editorial, Impact, Minimal and Institutional. Each style has a matching design for every area below, so a site built in one style looks consistent, and mixing styles also works.

| Area | Designs |
|---|---|
| Header | 6: classic, dark with a Donate button, centred logo, top bar with contact details, menu button only, institutional (primary-colour bar, menu in capitals) |
| Footer | 6: classic columns, big call to action, centred, dark "stay in touch", one compact row, institutional (partner logos between primary-colour bands) |
| Home page | 8: classic, photo slider, story-led, numbers first, community, institutional (photo strip, titles in capitals, initiatives between thin lines), **Programmes** (your programmes and projects first) and **Full width** (edge to edge, after leaf.global: photo-and-text rows, a wide banner, news and photos as tiles); the last two go with any style |
| About us | 6: mission and values, team, story and milestones, impact report, partners and history, institutional |
| Contact | 6: classic, form beside a details panel, questions first, departments and offices, form only, institutional |
| Photo gallery | 6: albums grid, latest album on dark, masonry wall, carousel and grid, photos and videos, institutional |

The Institutional style also has **section templates** for any page (Templates tab → Institutional): a photo strip, a feature row, a report with its cover, news in four columns and a logo band.

**Full-width building blocks** (Templates tab → Full width): a photo filling half the screen beside text (photo left or right), a wide banner, news as photo tiles and gallery photos as tiles. They use the **Split** block, the Image block's **Fill the width** option and the **Tiles** display, which you can also use on your own. **Page: Full width** (New page → Start from → Pages) is a whole page built from them, for any page that is not the home page.

The kit also includes:
- Get involved and landing pages for News, Events, Projects, Programmes and Publications;
- **14 section templates**;
- a call to action, contact details and sidebars as global blocks;
- the **main** and **footer** menus.

**Choosing a design:** every shipped design has a picture.
- **New page → Start from → See the designs** shows the page designs.
- **Design → Header & footer** shows the headers and footers as cards.
- The builder's **Templates** tab groups templates by area.

The files are in `resources/starter-kit/` (JSON import format), and the pictures are in `public/images/starter-kit/`.

---

## Useful commands

| Command | What it does |
|---|---|
| `php artisan pacms:create-admin` | Create a Super Admin account |
| `php artisan pacms:doctor` | Check the installation (permissions, scheduler, queue, storage…) |
| `php artisan pacms:starter` | Install the starter kit (see above) |
| `php artisan pacms:demo --fresh` | Reset the site with demo content (local only) |
| `php artisan pacms:blocks:sync` | Register the built-in block types (every deploy) |
| `php artisan pacms:search:rebuild` | Rebuild the site search index |
| `php artisan pacms:media:regenerate` | Regenerate responsive image sizes |
| `php artisan pacms:external:sync --source=<key>` | Sync one external source now |
| `php artisan pacms:schema` | Generate the JSON Schema and AI prompt helper files |

These run on a schedule (via `schedule:run` or `schedule:work`): publishing scheduled items, syncing feeds, checking media coverage links, pruning old revisions and removing old testimonial IP addresses.

---

## Quality checks

```bash
composer test       # Pest (uses the MySQL database pacms_testing)
composer analyse    # Larastan, level 6
composer lint       # Pint
npm test            # Vitest
npm run lint        # ESLint
npm run build       # production assets
```

---

## Deployment

PACMS is a standard Laravel application, released as a ZIP; there is no web installer. A server needs:
- PHP, MySQL and HTTPS;
- one cron entry for the scheduler.

The full steps, for shared hosting and VPS, are in [docs/DEPLOYMENT-ARCHITECTURE.md](docs/DEPLOYMENT-ARCHITECTURE.md).

## Documentation

| Document | About |
|---|---|
| [docs/README.md](docs/README.md) | Index of all documentation |
| [CMS-ARCHITECTURE](docs/CMS-ARCHITECTURE.md) | How the CMS works: blocks, content types, menus, providers |
| [CMS-BLOCK-SCHEMA](docs/CMS-BLOCK-SCHEMA.md) | The JSON format for import, export and AI tools |
| [DATABASE-ARCHITECTURE](docs/DATABASE-ARCHITECTURE.md) | Tables and relations |
| [API-ARCHITECTURE](docs/API-ARCHITECTURE.md) | Public and admin APIs |
| [SECURITY-ARCHITECTURE](docs/SECURITY-ARCHITECTURE.md) | Security model |
| [DEVELOPMENT-ROADMAP](docs/DEVELOPMENT-ROADMAP.md) | Phases and progress |
| [Phase reports](docs/phase-reports/) | What each phase delivered, with tests and decisions |

## Team

Syed Ziaul Habib (Team Lead / Lead Developer) · Hasibul Hasan · Khandoker Humayoun Kobir.
Development is AI-assisted (ChatGPT, Claude Code); all code is reviewed and approved by the team.
