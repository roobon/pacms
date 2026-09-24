<?php

namespace App\Http\Controllers\Admin;

use App\Auth\PermissionCatalog;
use App\Http\Controllers\Controller;
use App\Services\ActivityLog\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Role permission editing (requires users.manage_roles). The Super Admin role is fixed.
 */
class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()->withCount(['users', 'permissions'])->orderBy('id')->get(),
        ]);
    }

    public function edit(Role $role): View
    {
        abort_if($role->name === PermissionCatalog::SUPER_ADMIN, 403, 'The Super Admin role always has every permission.');

        return view('admin.roles.edit', [
            'role' => $role,
            'groups' => PermissionCatalog::groups(),
            'granted' => $role->permissions()->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, Role $role, ActivityLogger $logger): RedirectResponse
    {
        abort_if($role->name === PermissionCatalog::SUPER_ADMIN, 403);

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Permission::query()->pluck('name')->all())],
        ]);

        $requested = array_values(array_unique($data['permissions'] ?? []));
        $actor = $request->user();

        // Nobody can hand out permissions they do not hold themselves.
        if (! $actor->isSuperAdmin()) {
            $notHeld = array_diff($requested, $actor->getAllPermissions()->pluck('name')->all());
            if ($notHeld !== []) {
                throw ValidationException::withMessages(['permissions' => __('You cannot grant permissions you do not hold.')]);
            }
        }

        $before = $role->permissions()->pluck('name')->all();
        $role->syncPermissions($requested);

        $logger->log('security.role_permissions_changed', null, [
            'role' => $role->name,
            'added' => array_values(array_diff($requested, $before)),
            'removed' => array_values(array_diff($before, $requested)),
        ], $actor, PermissionCatalog::roleLabel($role->name));

        return redirect()->route('admin.roles.index')
            ->with('success', __('Permissions for :role updated.', ['role' => PermissionCatalog::roleLabel($role->name)]));
    }
}
