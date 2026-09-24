<?php

namespace Database\Seeders;

use App\Auth\PermissionCatalog;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local-development accounts: one user per role, e.g. editor@pacms.test.
 * Refuses to run outside the local environment.
 */
class DevelopmentSeeder extends Seeder
{
    public const PASSWORD = 'Aurora-dev-2026';

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('DevelopmentSeeder skipped: only runs in the local environment.');

            return;
        }

        foreach (array_keys(PermissionCatalog::roles()) as $role) {
            $user = User::query()->firstOrCreate(
                ['email' => "{$role}@pacms.test"],
                ['name' => PermissionCatalog::roleLabel($role).' (dev)', 'password' => self::PASSWORD],
            );
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles([$role]);
        }

        $this->command?->info('Development users created: <role>@pacms.test / '.self::PASSWORD);
    }
}
