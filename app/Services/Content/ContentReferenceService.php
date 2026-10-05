<?php

namespace App\Services\Content;

use App\Models\ContentReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Maintains content_references: which items use which media, global blocks, etc.
 */
class ContentReferenceService
{
    /**
     * Replace all references owned by $owner.
     *
     * @param  list<array{target: Model, context: string, block_uuid?: string|null}>  $references
     */
    public function sync(Model $owner, array $references): void
    {
        DB::transaction(function () use ($owner, $references) {
            ContentReference::query()
                ->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey())
                ->delete();

            foreach ($references as $reference) {
                ContentReference::query()->create([
                    'owner_type' => $owner->getMorphClass(),
                    'owner_id' => $owner->getKey(),
                    'block_uuid' => $reference['block_uuid'] ?? null,
                    'target_type' => $reference['target']->getMorphClass(),
                    'target_id' => $reference['target']->getKey(),
                    'context' => $reference['context'],
                ]);
            }
        });
    }

    public function clear(Model $owner): void
    {
        $this->sync($owner, []);
    }

    /**
     * Everything that references $target, with owners loaded.
     *
     * @return Collection<int, ContentReference>
     */
    public function usagesOf(Model $target): Collection
    {
        return ContentReference::query()
            ->where('target_type', $target->getMorphClass())
            ->where('target_id', $target->getKey())
            ->with('owner')
            ->get()
            ->filter(fn (ContentReference $reference) => $reference->owner !== null)
            ->values();
    }

    public function isUsed(Model $target): bool
    {
        return $this->usagesOf($target)->isNotEmpty();
    }
}
