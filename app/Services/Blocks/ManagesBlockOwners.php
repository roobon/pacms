<?php

namespace App\Services\Blocks;

use App\Cms\Blocks\BlockTreeRepository;
use App\Models\BlockTemplate;
use App\Models\BlockType;
use App\Models\GlobalBlock;
use App\Models\User;
use App\Services\Content\ContentReferenceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shared steps for services whose models own a block tree (global blocks, templates,
 * custom block types): optimistic locking, storing the tree and its usage references,
 * and unique slugs.
 *
 * @property-read BlockTreeRepository $blocks
 * @property-read ContentReferenceService $references
 */
trait ManagesBlockOwners
{
    /**
     * Reload $model locked for update and check nobody saved it meanwhile.
     *
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @return TModel
     *
     * @throws ValidationException
     */
    protected function lockFresh(Model $model, int $lockVersion): Model
    {
        $fresh = $model->newQuery()->lockForUpdate()->findOrFail($model->getKey());

        if ((int) $fresh->getAttribute('lock_version') !== $lockVersion) {
            throw ValidationException::withMessages([
                'lock_version' => __('This was changed by someone else while you were editing. Reload it to see the latest version, then apply your changes again.'),
            ]);
        }

        return $fresh;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes  validated (BlockTreeValidator)
     */
    protected function storeTree(Model $owner, array $nodes, ?User $user): void
    {
        $this->blocks->save($owner, $nodes, $user);
        $this->references->sync($owner, $this->blocks->references($nodes));
    }

    /**
     * @param  class-string<GlobalBlock|BlockTemplate|BlockType>  $modelClass  a model with a unique `slug` column and soft deletes
     */
    protected function uniqueSlug(string $modelClass, string $wanted, ?int $ignoreId = null, string $prefix = ''): string
    {
        $base = Str::limit(Str::slug($wanted) ?: 'item', 80, '');
        $candidate = $base;
        $n = 2;

        while ($modelClass::withTrashed()->where('slug', $prefix.$candidate)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $candidate = "{$base}-{$n}";
            $n++;
        }

        return $prefix.$candidate;
    }
}
