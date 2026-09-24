<?php

namespace App\Services\Users;

use App\Auth\PermissionCatalog;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Staff and user account management with privilege-escalation guards
 * (SECURITY-ARCHITECTURE.md §3.1):
 *  - a user can only assign roles whose permissions they hold themselves;
 *  - only a Super Admin can grant the Super Admin role;
 *  - the last active Super Admin can never be demoted, suspended or deleted;
 *  - nobody can suspend or delete their own account here.
 */
class UserManagementService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Roles the actor may assign to others.
     *
     * @return Collection<int, Role>
     */
    public function assignableRoles(User $actor): Collection
    {
        $roles = Role::query()->with('permissions')->orderBy('id')->get();

        if ($actor->isSuperAdmin()) {
            return $roles;
        }

        $held = $actor->getAllPermissions()->pluck('name');

        return $roles->filter(fn (Role $role) => $role->name !== PermissionCatalog::SUPER_ADMIN
            && $role->permissions->pluck('name')->diff($held)->isEmpty())->values();
    }

    /**
     * @param  array{name: string, email: string, password: string, roles?: list<string>, status: string}  $data
     */
    public function create(User $actor, array $data): User
    {
        $roles = $this->guardRoles($actor, $data['roles'] ?? []);

        return DB::transaction(function () use ($actor, $data, $roles) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            // Accounts created by an administrator are trusted; no verification e-mail round trip.
            $user->forceFill([
                'status' => UserStatus::from($data['status']),
                'email_verified_at' => now(),
            ])->save();

            $user->syncRoles($roles);

            $this->logger->log('user.created', $user, ['roles' => $roles], $actor);

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, password?: ?string, roles?: list<string>, status: string}  $data
     */
    public function update(User $actor, User $user, array $data): User
    {
        $roles = $this->guardRoles($actor, $data['roles'] ?? []);
        $status = UserStatus::from($data['status']);

        if ($actor->is($user) && $status !== UserStatus::Active) {
            throw ValidationException::withMessages(['status' => __('You cannot suspend your own account.')]);
        }

        $losesSuperAdmin = $user->isSuperAdmin()
            && (! in_array(PermissionCatalog::SUPER_ADMIN, $roles, true) || $status !== UserStatus::Active);

        if ($losesSuperAdmin && $this->isLastActiveSuperAdmin($user)) {
            throw ValidationException::withMessages(['roles' => __('This is the last active Super Admin and cannot be demoted or suspended.')]);
        }

        return DB::transaction(function () use ($actor, $user, $data, $roles, $status) {
            $previousRoles = $user->getRoleNames()->sort()->values()->all();

            $user->fill(['name' => $data['name'], 'email' => $data['email']]);
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->status = $status;

            $changed = array_keys($user->getDirty());
            $user->save();
            $user->syncRoles($roles);

            $newRoles = collect($roles)->sort()->values()->all();

            $this->logger->log('user.updated', $user, [
                'fields' => array_values(array_diff($changed, ['password', 'updated_at'])),
                'password_changed' => in_array('password', $changed, true),
            ], $actor);

            if ($previousRoles !== $newRoles) {
                $this->logger->log('user.roles_changed', $user, ['from' => $previousRoles, 'to' => $newRoles], $actor);
            }

            return $user;
        });
    }

    public function delete(User $actor, User $user): void
    {
        if ($actor->is($user)) {
            throw ValidationException::withMessages(['user' => __('You cannot delete your own account.')]);
        }

        if ($user->isSuperAdmin() && $this->isLastActiveSuperAdmin($user)) {
            throw ValidationException::withMessages(['user' => __('The last active Super Admin cannot be deleted.')]);
        }

        $user->delete();

        $this->logger->log('user.deleted', $user, [], $actor);
    }

    /**
     * @param  list<string>  $roles
     * @return list<string>
     */
    private function guardRoles(User $actor, array $roles): array
    {
        $allowed = $this->assignableRoles($actor)->pluck('name')->all();
        $denied = array_diff($roles, $allowed);

        if ($denied !== []) {
            throw ValidationException::withMessages([
                'roles' => __('You are not allowed to assign: :roles.', ['roles' => implode(', ', $denied)]),
            ]);
        }

        return array_values(array_unique($roles));
    }

    private function isLastActiveSuperAdmin(User $user): bool
    {
        return User::role(PermissionCatalog::SUPER_ADMIN)
            ->where('status', UserStatus::Active)
            ->whereKeyNot($user->getKey())
            ->doesntExist();
    }
}
