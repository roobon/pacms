<?php

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared rules for editorial content types ({type}.view|create|update_own|update_any|delete).
 *
 * Authors may edit their own items while they are Draft or Published (editing a published
 * item creates a new draft); items in review or approved are locked for them until an
 * approver requests changes. Archived items must be restored before editing.
 */
abstract class EditorialPolicy
{
    abstract protected function type(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->type().'.view');
    }

    public function view(User $user, Model $item): bool
    {
        return $user->can($this->prefix($item).'.view');
    }

    public function create(User $user): bool
    {
        return $user->can($this->type().'.create');
    }

    public function update(User $user, Model $item): bool
    {
        if ($item->getAttribute('status') === ContentStatus::Archived) {
            return false;
        }

        if ($user->can($this->prefix($item).'.update_any')) {
            return true;
        }

        return $user->can($this->prefix($item).'.update_own')
            && $this->owns($user, $item)
            && in_array($item->getAttribute('status'), [ContentStatus::Draft, ContentStatus::Published], true);
    }

    public function delete(User $user, Model $item): bool
    {
        return $user->can($this->prefix($item).'.delete')
            && ($user->can($this->prefix($item).'.update_any') || $this->owns($user, $item));
    }

    public function viewRevisions(User $user, Model $item): bool
    {
        return $this->view($user, $item);
    }

    public function restoreRevision(User $user, Model $item): bool
    {
        return $user->can('revisions.restore') && $this->update($user, $item);
    }

    /** Permission prefix for an item (admin-made types: the item's own type). */
    protected function prefix(Model $item): string
    {
        return $this->type();
    }

    protected function owns(User $user, Model $item): bool
    {
        return (int) $item->getAttribute('author_id') === (int) $user->getKey();
    }
}
