<?php

use App\Auth\RolePermissionSynchronizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test bootstrap
|--------------------------------------------------------------------------
| Feature tests run against the MySQL database configured in phpunit.xml
| (pacms_testing) inside transactions, with roles/permissions seeded.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => app(RolePermissionSynchronizer::class)->sync())
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * A verified, active user with the given role. Staff get confirmed 2FA by default
 * because privileged roles are required to use it.
 */
function userWithRole(string $role, bool $twoFactor = true): User
{
    $factory = User::factory();

    if ($twoFactor) {
        $factory = $factory->withTwoFactor();
    }

    $user = $factory->create();
    $user->assignRole($role);

    return $user;
}
