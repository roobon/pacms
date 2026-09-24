<?php

namespace App\Console\Commands;

use App\Auth\PermissionCatalog;
use App\Auth\RolePermissionSynchronizer;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates a Super Admin from the command line — PACMS has no web installer
 * (DEPLOYMENT-ARCHITECTURE.md §4).
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'pacms:create-admin
        {--name= : Full name}
        {--email= : E-mail address (login)}
        {--password= : Password (omit to be prompted securely)}';

    protected $description = 'Create a Super Admin account';

    public function handle(RolePermissionSynchronizer $permissions, ActivityLogger $logger): int
    {
        $permissions->sync();

        $name = $this->option('name') ?: text('Name', required: true, validate: fn ($v) => mb_strlen($v) > 191 ? 'Too long.' : null);
        $email = Str::lower($this->option('email') ?: text('E-mail', required: true));
        $plain = $this->option('password') ?: password('Password (min. '.config('pacms.security.password_min_length').' characters, letters and numbers)', required: true);

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $plain],
            [
                'name' => ['required', 'string', 'max:191'],
                'email' => ['required', 'email', 'max:191', Rule::unique(User::class)],
                'password' => ['required', 'string', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($name, $email, $plain) {
            $user = User::create(['name' => $name, 'email' => $email, 'password' => $plain]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole(PermissionCatalog::SUPER_ADMIN);

            return $user;
        });

        $logger->log('user.created', $user, ['roles' => [PermissionCatalog::SUPER_ADMIN], 'via' => 'cli'], null);

        $this->components->info("Super Admin {$user->email} created. Sign in at ".url('/auth/login'));

        if (config('pacms.security.enforce_two_factor')) {
            $this->components->warn('Two-factor authentication will be required at first sign-in.');
        }

        return self::SUCCESS;
    }
}
