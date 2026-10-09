<?php

namespace App\Policies;

use App\Models\CustomItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Items of content types made in the admin (Phase 8D). Each type has its own permissions:
 * the editorial set ({type}.view, .update_own…) or, for types that are simply active or
 * inactive, one {type}.manage. List and create checks go through the type
 * (ContentType::ability()), since all these items share one model class.
 */
class CustomItemPolicy extends EditorialPolicy
{
    protected function type(): string
    {
        return 'custom';
    }

    protected function prefix(Model $item): string
    {
        /** @var CustomItem $item */
        return $item->type()->permissionKey();
    }

    public function view(User $user, Model $item): bool
    {
        return $this->managed($item) ? $user->can($this->manage($item)) : parent::view($user, $item);
    }

    public function update(User $user, Model $item): bool
    {
        return $this->managed($item) ? $user->can($this->manage($item)) : parent::update($user, $item);
    }

    public function delete(User $user, Model $item): bool
    {
        return $this->managed($item) ? $user->can($this->manage($item)) : parent::delete($user, $item);
    }

    public function viewRevisions(User $user, Model $item): bool
    {
        return $this->view($user, $item);
    }

    public function restoreRevision(User $user, Model $item): bool
    {
        return $user->can('revisions.restore') && $this->update($user, $item);
    }

    private function managed(Model $item): bool
    {
        /** @var CustomItem $item */
        return $item->type()->isManaged();
    }

    private function manage(Model $item): string
    {
        /** @var CustomItem $item */
        return (string) $item->type()->managePermission();
    }
}
