<?php

namespace App\Auth;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotently brings roles and permissions in line with PermissionCatalog.
 * Safe to run on every deploy:
 *  - missing permissions are created;
 *  - a role created now receives its full default permission set;
 *  - an existing role only receives *newly created* permissions from its defaults, so
 *    permissions an administrator deliberately removed are not re-added;
 *  - Super Admin always holds every permission.
 */
class RolePermissionSynchronizer
{
    /**
     * @return array{permissions_created: list<string>, roles_created: list<string>}
     */
    public function sync(): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return DB::transaction(function () {
            $existing = Permission::query()->where('guard_name', 'web')->pluck('name')->all();
            $created = array_values(array_diff(PermissionCatalog::all(), $existing));

            foreach ($created as $name) {
                Permission::query()->create(['name' => $name, 'guard_name' => 'web']);
            }

            $rolesCreated = [];
            foreach (PermissionCatalog::roles() as $name => $definition) {
                $role = Role::query()->where(['name' => $name, 'guard_name' => 'web'])->first();

                if ($role === null) {
                    $role = Role::query()->create(['name' => $name, 'guard_name' => 'web']);
                    $role->syncPermissions($definition['permissions']);
                    $rolesCreated[] = $name;
                } elseif ($name === PermissionCatalog::SUPER_ADMIN) {
                    $role->syncPermissions(PermissionCatalog::all());
                } else {
                    $new = array_values(array_intersect($definition['permissions'], $created));
                    if ($new !== []) {
                        $role->givePermissionTo($new);
                    }
                }
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return ['permissions_created' => $created, 'roles_created' => $rolesCreated];
        });
    }
}
