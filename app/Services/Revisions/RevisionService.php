<?php

namespace App\Services\Revisions;

use App\Enums\RevisionKind;
use App\Models\Contracts\Revisionable;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Snapshots, comparison and restore for revisionable content (CMS-ARCHITECTURE.md §4.3).
 * History is never rewritten: restoring creates a new revision.
 */
class RevisionService
{
    public const SCHEMA_VERSION = '1.0';

    /**
     * @param  array<string, mixed>|null  $snapshot  defaults to the item's current working copy
     */
    public function record(Model&Revisionable $item, RevisionKind $kind, ?User $user = null, ?string $summary = null, ?array $snapshot = null): Revision
    {
        return DB::transaction(function () use ($item, $kind, $user, $summary, $snapshot) {
            $number = (int) Revision::query()
                ->where('revisionable_type', $item->getMorphClass())
                ->where('revisionable_id', $item->getKey())
                ->lockForUpdate()
                ->max('number') + 1;

            return Revision::query()->create([
                'revisionable_type' => $item->getMorphClass(),
                'revisionable_id' => $item->getKey(),
                'number' => $number,
                'kind' => $kind,
                'snapshot' => $snapshot ?? $item->toSnapshot(),
                'schema_version' => self::SCHEMA_VERSION,
                'summary' => $summary !== null ? mb_substr($summary, 0, 255) : null,
                'created_by' => $user?->getKey(),
            ]);
        });
    }

    /**
     * Store a user's unsaved builder tree (CMS-ARCHITECTURE.md §23.4). Only the latest
     * autosave per user and item is kept; it never touches the working copy.
     *
     * @param  list<array<string, mixed>>  $blocks  already validated
     */
    public function autosave(Model&Revisionable $item, User $user, array $blocks): Revision
    {
        return DB::transaction(function () use ($item, $user, $blocks) {
            $this->autosaves($item, $user)->delete();

            return $this->record($item, RevisionKind::Autosave, $user, 'Autosave', ['blocks' => $blocks] + $item->toSnapshot());
        });
    }

    /**
     * The user's autosave when it is newer than the last real save.
     */
    public function pendingAutosave(Model&Revisionable $item, User $user): ?Revision
    {
        $autosave = $this->autosaves($item, $user)->latest('id')->first();
        $updatedAt = $item->getAttribute('updated_at');

        return $autosave !== null && ($updatedAt === null || $autosave->created_at?->gte($updatedAt)) ? $autosave : null;
    }

    /**
     * @return Builder<Revision>
     */
    private function autosaves(Model $item, User $user): Builder
    {
        return Revision::query()
            ->where('revisionable_type', $item->getMorphClass())
            ->where('revisionable_id', $item->getKey())
            ->where('kind', RevisionKind::Autosave)
            ->where('created_by', $user->getKey());
    }

    /**
     * Human-readable list of what differs between two snapshots.
     *
     * @param  array<string, mixed>  $from
     * @param  array<string, mixed>  $to
     * @return list<array{field: string, from: mixed, to: mixed}>
     */
    public function compare(array $from, array $to): array
    {
        $changes = [];
        $flatFrom = $this->flatten($from);
        $flatTo = $this->flatten($to);

        foreach (array_unique(array_merge(array_keys($flatFrom), array_keys($flatTo))) as $key) {
            if (in_array($key, ['schema_version', 'type'], true)) {
                continue;
            }
            $a = $flatFrom[$key] ?? null;
            $b = $flatTo[$key] ?? null;
            if ($a !== $b) {
                $changes[] = ['field' => $key, 'from' => $a, 'to' => $b];
            }
        }

        return $changes;
    }

    /**
     * Summary of changed fields for the revision list, e.g. "title, excerpt".
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function summarize(array $before, array $after): string
    {
        $fields = array_map(fn ($change) => $change['field'], $this->compare($before, $after));

        return $fields === [] ? 'No changes' : 'Changed '.implode(', ', array_slice($fields, 0, 6)).(count($fields) > 6 ? ' …' : '');
    }

    /**
     * Keep all published revisions and the most recent N others per item.
     */
    public function prune(int $keep): int
    {
        $deleted = 0;

        Revision::query()
            ->select('revisionable_type', 'revisionable_id')
            ->groupBy('revisionable_type', 'revisionable_id')
            ->get()
            ->each(function (Revision $group) use ($keep, &$deleted) {
                $ids = Revision::query()
                    ->where('revisionable_type', $group->revisionable_type)
                    ->where('revisionable_id', $group->revisionable_id)
                    ->where('kind', '!=', RevisionKind::Published)
                    ->orderByDesc('number')
                    ->skip($keep)
                    ->take(PHP_INT_MAX)
                    ->pluck('id');

                // Never delete a revision that is currently live.
                $live = collect(['pages', 'global_blocks', 'block_types'])
                    ->flatMap(fn (string $table) => DB::table($table)->whereIn('published_revision_id', $ids)->pluck('published_revision_id'));
                $deleted += Revision::query()->whereKey($ids->diff($live))->delete();
            });

        return $deleted;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            // "fields.title" reads as "title" in the admin.
            $label = str_starts_with($path, 'fields.') ? substr($path, 7) : $path;

            if (is_array($value) && $value !== [] && ! array_is_list($value)) {
                foreach ($this->flatten($value, $path) as $childKey => $childValue) {
                    $flat[str_starts_with($childKey, 'fields.') ? substr($childKey, 7) : $childKey] = $childValue;
                }
            } else {
                $flat[$label] = is_array($value) ? json_encode($value) : $value;
            }
        }

        return $flat;
    }
}
