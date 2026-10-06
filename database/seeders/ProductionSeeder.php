<?php

namespace Database\Seeders;

use App\Auth\RolePermissionSynchronizer;
use App\Cms\Blocks\BlockRegistry;
use App\Cms\Design\DesignTokenService;
use Illuminate\Database\Seeder;

/**
 * Idempotent seeding that is safe on every production deploy:
 * roles/permissions and the design-token stylesheet. No organisational content.
 */
class ProductionSeeder extends Seeder
{
    public function run(RolePermissionSynchronizer $permissions, DesignTokenService $tokens, BlockRegistry $blocks): void
    {
        $blocks->sync();

        $result = $permissions->sync();

        $this->command?->info(sprintf(
            'Permissions created: %d · Roles created: %d',
            count($result['permissions_created']),
            count($result['roles_created']),
        ));

        $tokens->publish();
    }
}
