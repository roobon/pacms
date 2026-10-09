<?php

namespace App\Console\Commands;

use App\Models\News;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Fills the site with demo content for testing (DemoSeeder). With --fresh, the database
 * and the media files are wiped first, so it can be run again any time for a clean start.
 * Never runs in production.
 */
class DemoCommand extends Command
{
    protected $signature = 'pacms:demo
        {--fresh : Wipe the database and uploaded media first, then seed everything again}
        {--force : Allow running outside the local environment (never in production)}';

    protected $description = 'Fill the site with demo content in every area (local testing)';

    public function handle(): int
    {
        if ($this->laravel->environment('production')) {
            $this->components->error('Demo content is never created in production.');

            return self::FAILURE;
        }
        if (! $this->laravel->environment('local') && ! $this->option('force')) {
            $this->components->error('Demo content is meant for the local environment. Use --force to run it here anyway.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            if (! $this->confirm('This deletes ALL data and uploaded files, then creates demo content. Continue?', true)) {
                return self::FAILURE;
            }
            foreach ([config('pacms.media.disk'), config('pacms.media.private_disk')] as $disk) {
                Storage::disk((string) $disk)->deleteDirectory('media');
            }
            $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
        } elseif (News::query()->exists()) {
            $this->components->error('The site already has content. Run with --fresh to start again from a clean database.');

            return self::FAILURE;
        }

        $this->components->info('Creating demo content');
        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        $this->newLine();
        $this->components->twoColumnDetail('Staff accounts', '<role>@pacms.test (e.g. editor@pacms.test) / Aurora-dev-2026');
        $this->components->twoColumnDetail('Registered users', 'rahim@, nadia@, tanvir@, farzana@demo.pacms.test / '.DemoSeeder::USER_PASSWORD);
        $this->components->twoColumnDetail('Not verified', 'unverified@demo.pacms.test (cannot submit testimonials)');

        return self::SUCCESS;
    }
}
