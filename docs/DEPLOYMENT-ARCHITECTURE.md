# Probha Aurora CMS — Deployment Architecture

| | |
|---|---|
| Document | DEPLOYMENT-ARCHITECTURE.md (Phase 1 deliverable AA) |
| Status | Draft architecture. **Exact commands and scripts are finalised in Phase 14.** |
| Model | Standard, portable Laravel project deployed as a **ZIP release**. No web installer, Docker, Kubernetes or special platform. |
| Targets | VPS, CyberPanel (OpenLiteSpeed), Nginx or Apache + PHP-FPM, MySQL 8 |

---

## 1. Principles

1. The release is **one ZIP of a normal Laravel project** with `vendor/` and built assets already included, so the server needs **PHP and MySQL only**. No Node and no Composer are required on the server (Composer is optional).
2. Configuration lives in `.env`, which is created on the server and never shipped.
3. Everything stateful lives outside the code: the database, `storage/app` (media, private files, backups) and `.env`. Releases replace code only.
4. Background work uses **cron + Laravel Scheduler**, and the **database queue** works without Supervisor. Supervisor or systemd is an optional upgrade on a VPS.
5. The first administrator is created with an **Artisan CLI command**, not a web wizard.

---

## 2. Environments

| Environment | Where | Notes |
|---|---|---|
| Local development | Laragon (Windows): PHP 8.3.33, MySQL 8.4.3, Apache/Nginx, Node 22 (Laragon's, **not** the v21 on PATH) | `APP_ENV=local`, `pacms.test` virtual host |
| Testing | Local + a CI runner (optional) | Separate DB `pacms_testing` |
| Staging (recommended) | Same host type as production, subdomain, `noindex` | Where each phase's release ZIP is verified first |
| Production | VPS / CyberPanel | `APP_ENV=production`, `APP_DEBUG=false`, HTTPS |

### Server requirements (production)

| Requirement | Value |
|---|---|
| PHP | **Same minor version as `composer.json` `config.platform.php`** (8.3 minimum; 8.4 recommended if the host offers it, D-02) |
| PHP extensions | bcmath, ctype, curl, dom, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, tokenizer, xml, xmlreader, zip, sodium, exif (all present in local Laragon PHP) |
| PHP settings | `memory_limit ≥ 256M`, `upload_max_filesize`/`post_max_size` ≥ largest allowed upload (default 100M for video), `max_execution_time ≥ 60` for web, CLI unlimited |
| MySQL | 8.0+ (8.4 LTS recommended), `utf8mb4` |
| Web server | Document root = **`public/`** |
| Cron | One entry, every minute (§4) |
| Disk | Code about 150 MB plus media growth. Backups need about 2× the media size free if stored locally. |
| HTTPS | Required (Let's Encrypt through CyberPanel/certbot) |
| `mysqldump` | Needed on the server for DB backups |

---

## 3. Release package

### 3.1 Build (on a developer machine or CI)

```
git checkout <release tag>
composer install --no-dev --optimize-autoloader --classmap-authoritative
npm ci && npm run build                     # → public/build (hashed, with manifest)
php artisan test                            # must pass (run before --no-dev install, or in CI)
scripts/release  → probha-aurora-cms-<version>.zip  (+ SHA-256 checksum)
```

- `composer.json` sets `"config": { "platform": { "php": "8.3.x" } }` to match production, so `vendor/` resolves for the server's PHP rather than the build machine's.
- The release script (Phase 14, PowerShell and Bash versions) builds from a clean export, verifies no forbidden files are inside, and writes `RELEASE.txt` (version, git commit, build date, PHP platform, schema version).

### 3.2 Included / excluded

| Included | Excluded |
|---|---|
| `app/ bootstrap/ config/ database/ lang/ public/ (incl. public/build) resources/ routes/ vendor/ artisan composer.json composer.lock package.json .env.example RELEASE.txt docs/ (admin + deployment docs)` | `.env` (any real env file) · `.git/` · `node_modules/` · `tests/` · `storage/app/*` contents (media), `storage/logs/*`, `storage/framework/{cache,sessions,views}/*` (empty dirs with `.gitignore` are kept) · `bootstrap/cache/*.php` · `public/storage` symlink · `public/hot` · local databases (`*.sqlite`, dumps) · IDE files (`.idea/ .vscode/`) · OS files · `.phpunit.cache`, coverage |

---

## 4. First deployment (procedure outline)

```
1.  Upload ZIP → extract to /home/<site>/pacms            (NOT inside public_html itself)
2.  Create MySQL database + dedicated user (utf8mb4; privileges on this DB only)
3.  cp .env.example .env  → edit: APP_URL, DB_*, MAIL_*, SESSION_DOMAIN, SANCTUM_STATEFUL_DOMAINS,
    QUEUE_CONNECTION=database, CACHE_STORE=file|database|redis, META_* (later), backup disks
4.  Point document root → /home/<site>/pacms/public   (CyberPanel: vhost "docRoot"; see §5)
5.  php artisan key:generate --force
6.  php artisan migrate --force
7.  php artisan db:seed --class=ProductionSeeder --force  (roles/permissions, core block types, neutral settings)
8.  php artisan storage:link
9.  php artisan pacms:create-admin                       (interactive: name, email, password → Super Admin)
10. php artisan optimize                                   (config, routes, views, events caches)
11. Permissions: storage/ and bootstrap/cache/ writable by the PHP user; everything else read-only
12. Cron:  * * * * * cd /home/<site>/pacms && php artisan schedule:run >> /dev/null 2>&1
13. php artisan pacms:doctor                               (checks below)
14. Verify: site loads, /admin login, upload an image, publish a test page, /rss.xml, /sitemap.xml
```

### Queue without Supervisor (default)

The scheduler runs a short-lived worker every minute:
```php
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3 --queue=default,media,external,imports')
        ->everyMinute()->withoutOverlapping()->runInBackground();
```
On a VPS with Supervisor or systemd, a persistent `queue:work` replaces this (config flag `pacms.queue.managed_worker=true`).

### `php artisan pacms:doctor` (built in Phase 2, extended every phase)

It checks the PHP version and extensions, `APP_DEBUG` off in production, `APP_KEY` set, DB connection and migrations up to date, storage writable, `public/storage` link, scheduler heartbeat (under 2 min old), queue heartbeat, pending failed jobs, HTTPS `APP_URL`, mail config, token CSS generated, and cache driver reachable. Output is ✔/✘ with a fix hint for each.

---

## 5. Web server configuration

### Nginx
```nginx
server {
    server_name example.org;
    root /home/site/pacms/public;
    index index.php;
    client_max_body_size 100M;

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
    # never execute anything under storage / build
    location ~* ^/(storage|build)/.*\.(php|phtml|phar)$ { deny all; }
    location ~ /\.(?!well-known) { deny all; }

    location /build/ { expires 1y; add_header Cache-Control "public, immutable"; }
    location /storage/ { expires 30d; add_header X-Content-Type-Options nosniff; }
    gzip on; gzip_types text/css application/javascript application/json application/rss+xml image/svg+xml;
}
```

### Apache / OpenLiteSpeed (CyberPanel)
- Use Laravel's standard `public/.htaccess` (rewrite to `index.php`).
- Ship `public/storage/.htaccess` equivalent through `storage/app/public/.htaccess`: `php_flag engine off` or `RemoveHandler .php .phtml .phar` + `Require all denied` for those extensions. For OpenLiteSpeed, the equivalent context rule is documented in Phase 14.
- CyberPanel: **Websites → List → Manage → vHost Conf**: set `docRoot $VH_ROOT/pacms/public`, then enable rewrite (`.htaccess` autoload) and restart LSWS.

---

## 6. Updating an existing installation (release N → N+1)

```
1.  php artisan pacms:backup (DB + media) — or confirm a backup < 24h exists
2.  php artisan down --render="errors::503" --secret=<token>   (admins can bypass with the secret)
3.  Extract new ZIP to a new folder pacms-<ver>/; copy .env; point storage/ to persistent storage
    (recommended layout below) — OR extract over existing folder excluding .env and storage/
4.  php artisan migrate --force
5.  php artisan db:seed --class=ProductionSeeder --force   (idempotent: new permissions/block types)
6.  php artisan pacms:blocks:sync
7.  php artisan optimize:clear && php artisan optimize
8.  php artisan queue:restart
9.  php artisan pacms:doctor
10. php artisan up  → smoke test
Rollback: switch back to previous folder + restore DB backup if migrations ran (migrations with
          destructive changes are called out in release notes).
```

**Recommended server layout** (lets releases swap with near-zero downtime without Git):
```
/home/site/
├── pacms-shared/        .env, storage/  (persistent)
├── pacms-1.0.0/         release (storage → ../pacms-shared/storage, .env → ../pacms-shared/.env symlinks)
├── pacms-1.1.0/
└── pacms → pacms-1.1.0  (symlink; web root = pacms/public)
```
If the host doesn't allow symlinks, the simple "extract over existing folder" method is fully supported.

Git-based deployment can be added later and is not required.

---

## 7. Backup and restore

| Item | Plan |
|---|---|
| Tool | `spatie/laravel-backup` (Phase 14) |
| Contents | DB dump + `storage/app/public` + `storage/app/private` + `.env` **excluded** (secrets are backed up separately by the admin) |
| Schedule | Nightly DB + media. Weekly cleanup with retention of 7 daily, 4 weekly and 6 monthly backups. |
| Destinations | Local disk (`storage/app/backups`, outside the web root) first. **Future** Dropbox and Google Drive through Flysystem adapters as extra disks, with credentials in `.env`. |
| Monitoring | Admin dashboard warns if the newest backup is older than 48 h. Backup failures go to the activity log and mail. |
| Permissions | `backups.manage`: run, download (streamed through a controller, never a public URL) and delete. |
| Restore | Documented manual procedure (Phase 14): maintenance mode → import DB dump → restore media folder → `optimize:clear` → `pacms:doctor`. A restore drill is part of Phase 14 acceptance. |

---

## 8. Production configuration checklist

| Setting | Value |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | `https://…` (used for canonical URLs, feeds, signed URLs) |
| `LOG_CHANNEL` / `LOG_LEVEL` | `daily` / `warning` |
| `SESSION_DRIVER` / `SESSION_SECURE_COOKIE` | `database` / `true` |
| `CACHE_STORE` | `file` (default) · `database` · `redis` if available |
| `QUEUE_CONNECTION` | `database` |
| `FILESYSTEM_DISK` | `public` for media, `private` configured |
| `MAIL_*` | Real SMTP (needed for email verification and password reset) |
| `SANCTUM_STATEFUL_DOMAINS` / `SESSION_DOMAIN` | the production domain |
| OPcache | Enabled, `validate_timestamps=0` optional (then `opcache_reset` on deploy) |

---

## 9. What is intentionally not included

- A web `/install` wizard: the master prompt forbids it, and CLI steps are simpler to audit.
- Docker, Kubernetes, Envoyer or Forge dependencies.
- Node.js on the server.
- A requirement for Redis or Supervisor. Both are supported as optional upgrades.
