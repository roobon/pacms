<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Directory-like modules (team, partners) managed with one permission
 * (SECURITY-ARCHITECTURE.md §3.2: team.manage, partners.manage).
 */
abstract class ManagedContentPolicy
{
    abstract protected function permission(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->permission());
    }

    public function view(User $user, Model $item): bool
    {
        return $user->can($this->permission());
    }

    public function create(User $user): bool
    {
        return $user->can($this->permission());
    }

    public function update(User $user, Model $item): bool
    {
        return $user->can($this->permission());
    }

    public function delete(User $user, Model $item): bool
    {
        return $user->can($this->permission());
    }

    public function viewRevisions(User $user, Model $item): bool
    {
        return $user->can($this->permission());
    }

    public function restoreRevision(User $user, Model $item): bool
    {
        return $user->can($this->permission()) && $user->can('revisions.restore');
    }
}
