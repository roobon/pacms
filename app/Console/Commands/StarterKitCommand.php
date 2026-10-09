<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Starter\StarterKitService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Installs the starter kit: page and section templates and global blocks, and with --pages
 * draft Home, About, Contact and Get involved pages. Safe to run again: it only adds what is
 * missing. --restore puts shipped templates back to their original design.
 */
class StarterKitCommand extends Command
{
    protected $signature = 'pacms:starter
        {--pages : Also create draft pages (Home, About us, Contact, Get involved) from the page templates}
        {--restore= : Put a shipped template back to its original design (its slug, or "all")}
        {--user= : E-mail of the account recorded as author (default: the first super admin)}';

    protected $description = 'Install the starter kit: designed page and section templates and global blocks';

    public function handle(StarterKitService $kit): int
    {
        $user = $this->option('user')
            ? User::query()->where('email', (string) $this->option('user'))->first()
            : User::query()->role('super-admin')->orderBy('id')->first();
        if ($user === null) {
            $this->components->error('No account to record as author. Create one first: php artisan pacms:create-admin');

            return self::FAILURE;
        }

        try {
            $restore = $this->option('restore');
            $rows = is_string($restore) && $restore !== ''
                ? $kit->restore($user, $restore === 'all' ? null : $restore)
                : $kit->install($user, (bool) $this->option('pages'));
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($rows as [$kind, $name, $result]) {
            $this->components->twoColumnDetail("{$kind}: {$name}", $result);
        }
        $this->newLine();
        $this->components->info('Templates are in the builder\'s Templates tab and under Design → Templates; "New page" can start from a page template.');

        return self::SUCCESS;
    }
}
