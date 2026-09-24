<?php

namespace App\Policies;

use App\Models\User;

/**
 * Super Admins pass every check through Gate::before; these rules apply to everyone else.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.manage');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can('users.manage') && ! $target->isSuperAdmin();
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->can('users.manage') && ! $target->isSuperAdmin() && ! $actor->is($target);
    }
}
