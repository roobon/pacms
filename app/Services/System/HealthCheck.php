<?php

namespace App\Services\System;

use App\Auth\PermissionCatalog;
use App\Services\Settings\SettingsService;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Installation health checks shared by `php artisan pacms:doctor` and the admin dashboard
 * (DEPLOYMENT-ARCHITECTURE.md §4).
 *
 * @phpstan-type CheckResult array{check: string, status: string, message: string, hint: ?string}
 */
class HealthCheck
{
    public const OK = 'ok';

    public const WARNING = 'warning';

    public const FAIL = 'fail';

    public const SCHEDULER_HEARTBEAT = 'pacms:heartbeat:scheduler';

    public const QUEUE_HEARTBEAT = 'pacms:heartbeat:queue';

    private const REQUIRED_EXTENSIONS = [
        'bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl',
        'pdo_mysql', 'tokenizer', 'xml', 'xmlreader', 'zip', 'sodium', 'exif',
    ];

    public function __construct(
        private readonly Migrator $migrator,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return list<array{check: string, status: string, message: string, hint: ?string}>
     */
    public function run(): array
    {
        $checks = [
            $this->phpVersion(),
            $this->extensions(),
            $this->appKey(),
            $this->debugMode(),
            $this->appUrl(),
            $this->database(),
        ];

        // Everything below needs a working, migrated database.
        if ($checks[5]['status'] === self::FAIL) {
            return $checks;
        }

        return array_merge($checks, [
            $this->migrations(),
            $this->roles(),
            $this->writablePaths(),
            $this->storageLink(),
            $this->frontendBuild(),
            $this->tokenStylesheet(),
            $this->heartbeat(self::SCHEDULER_HEARTBEAT, 'Scheduler', 3, 'Add the cron entry: * * * * * php artisan schedule:run'),
            $this->heartbeat(self::QUEUE_HEARTBEAT, 'Queue worker', 15, 'The scheduler starts a worker every minute unless PACMS_MANAGED_QUEUE_WORKER=true (then run queue:work under Supervisor).'),
            $this->failedJobs(),
            $this->mail(),
        ]);
    }

    /**
     * @param  list<CheckResult>  $results
     */
    public function hasFailures(array $results): bool
    {
        return collect($results)->contains('status', self::FAIL);
    }

    /** @return CheckResult */
    private function phpVersion(): array
    {
        $ok = version_compare(PHP_VERSION, '8.3.0', '>=');

        return $this->result('PHP version', $ok ? self::OK : self::FAIL, 'PHP '.PHP_VERSION, $ok ? null : 'PHP 8.3 or newer is required.');
    }

    /** @return CheckResult */
    private function extensions(): array
    {
        $missing = array_values(array_filter(self::REQUIRED_EXTENSIONS, fn ($ext) => ! extension_loaded($ext)));

        return $missing === []
            ? $this->result('PHP extensions', self::OK, 'All required extensions are loaded.')
            : $this->result('PHP extensions', self::FAIL, 'Missing: '.implode(', ', $missing), 'Enable the missing extensions in php.ini.');
    }

    /** @return CheckResult */
    private function appKey(): array
    {
        return filled(config('app.key'))
            ? $this->result('Application key', self::OK, 'APP_KEY is set.')
            : $this->result('Application key', self::FAIL, 'APP_KEY is empty.', 'Run: php artisan key:generate');
    }

    /** @return CheckResult */
    private function debugMode(): array
    {
        if (! config('app.debug')) {
            return $this->result('Debug mode', self::OK, 'APP_DEBUG is off.');
        }

        return app()->isProduction()
            ? $this->result('Debug mode', self::FAIL, 'APP_DEBUG is on in production.', 'Set APP_DEBUG=false in .env.')
            : $this->result('Debug mode', self::OK, 'APP_DEBUG is on ('.app()->environment().' environment).');
    }

    /** @return CheckResult */
    private function appUrl(): array
    {
        $url = (string) config('app.url');

        if (app()->isProduction() && ! str_starts_with($url, 'https://')) {
            return $this->result('Application URL', self::WARNING, $url, 'Use an https:// APP_URL in production.');
        }

        return $this->result('Application URL', self::OK, $url);
    }

    /** @return CheckResult */
    private function database(): array
    {
        try {
            DB::connection()->getPdo();

            return $this->result('Database', self::OK, 'Connected to '.DB::connection()->getDatabaseName().' ('.DB::connection()->getDriverName().').');
        } catch (Throwable $e) {
            return $this->result('Database', self::FAIL, 'Cannot connect: '.$e->getMessage(), 'Check the DB_* values in .env.');
        }
    }

    /** @return CheckResult */
    private function migrations(): array
    {
        if (! $this->migrator->repositoryExists()) {
            return $this->result('Migrations', self::FAIL, 'Migrations have not been run.', 'Run: php artisan migrate --force');
        }

        $files = array_keys($this->migrator->getMigrationFiles([database_path('migrations')]));
        $pending = array_diff($files, $this->migrator->getRepository()->getRan());

        return $pending === []
            ? $this->result('Migrations', self::OK, count($files).' migrations applied.')
            : $this->result('Migrations', self::FAIL, count($pending).' pending migration(s).', 'Run: php artisan migrate --force');
    }

    /** @return CheckResult */
    private function roles(): array
    {
        if (! Schema::hasTable('roles') || ! DB::table('roles')->where('name', PermissionCatalog::SUPER_ADMIN)->exists()) {
            return $this->result('Roles & permissions', self::FAIL, 'Roles are not seeded.', 'Run: php artisan db:seed --class=ProductionSeeder --force');
        }

        $superAdmins = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', PermissionCatalog::SUPER_ADMIN)
            ->count();

        return $superAdmins > 0
            ? $this->result('Roles & permissions', self::OK, "Roles seeded; {$superAdmins} Super Admin account(s).")
            : $this->result('Roles & permissions', self::WARNING, 'No Super Admin account exists.', 'Run: php artisan pacms:create-admin');
    }

    /** @return CheckResult */
    private function writablePaths(): array
    {
        $paths = [storage_path('app'), storage_path('framework'), storage_path('logs'), base_path('bootstrap/cache')];
        $notWritable = array_values(array_filter($paths, fn ($path) => ! is_writable($path)));

        return $notWritable === []
            ? $this->result('Writable directories', self::OK, 'storage/ and bootstrap/cache/ are writable.')
            : $this->result('Writable directories', self::FAIL, 'Not writable: '.implode(', ', $notWritable), 'Give the PHP user write access to these directories.');
    }

    /** @return CheckResult */
    private function storageLink(): array
    {
        return file_exists(public_path('storage'))
            ? $this->result('Public storage link', self::OK, 'public/storage exists.')
            : $this->result('Public storage link', self::FAIL, 'public/storage is missing.', 'Run: php artisan storage:link');
    }

    /** @return CheckResult */
    private function frontendBuild(): array
    {
        if (file_exists(public_path('hot'))) {
            return $this->result('Frontend assets', self::OK, 'Vite dev server is running (public/hot).');
        }

        return file_exists(public_path('build/manifest.json'))
            ? $this->result('Frontend assets', self::OK, 'Production build found (public/build).')
            : $this->result('Frontend assets', self::FAIL, 'public/build/manifest.json is missing.', 'Run: npm ci && npm run build (before creating the release ZIP).');
    }

    /** @return CheckResult */
    private function tokenStylesheet(): array
    {
        $path = $this->settings->get('design', 'stylesheet');

        return $path && Storage::disk('public')->exists($path)
            ? $this->result('Design tokens', self::OK, "Stylesheet {$path} generated.")
            : $this->result('Design tokens', self::WARNING, 'Token stylesheet not generated yet.', 'It is created on first page view, or save Design → Tokens.');
    }

    /** @return CheckResult */
    private function heartbeat(string $key, string $label, int $maxMinutes, string $hint): array
    {
        $last = Cache::get($key);

        if ($last === null) {
            return $this->result($label, self::WARNING, 'No heartbeat recorded yet.', $hint);
        }

        $minutes = (int) floor((now()->timestamp - (int) $last) / 60);

        return $minutes <= $maxMinutes
            ? $this->result($label, self::OK, "Last heartbeat {$minutes} min ago.")
            : $this->result($label, self::WARNING, "Last heartbeat {$minutes} min ago.", $hint);
    }

    /** @return CheckResult */
    private function failedJobs(): array
    {
        $count = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

        return $count === 0
            ? $this->result('Failed jobs', self::OK, 'No failed jobs.')
            : $this->result('Failed jobs', self::WARNING, "{$count} failed job(s).", 'Inspect with php artisan queue:failed.');
    }

    /** @return CheckResult */
    private function mail(): array
    {
        $mailer = (string) config('mail.default');

        if (app()->isProduction() && in_array($mailer, ['log', 'array'], true)) {
            return $this->result('Mail', self::WARNING, "Mailer is '{$mailer}'; e-mails are not delivered.", 'Configure MAIL_* for a real SMTP server.');
        }

        return $this->result('Mail', self::OK, "Mailer: {$mailer}.");
    }

    /**
     * @return array{check: string, status: string, message: string, hint: ?string}
     */
    private function result(string $check, string $status, string $message, ?string $hint = null): array
    {
        return compact('check', 'status', 'message', 'hint');
    }
}
