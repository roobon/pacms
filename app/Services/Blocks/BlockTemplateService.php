<?php

namespace App\Services\Blocks;

use App\Cms\Blocks\BlockTreeRepository;
use App\Cms\Blocks\BlockTreeValidator;
use App\Enums\RevisionKind;
use App\Models\BlockTemplate;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogger;
use App\Services\Content\ContentReferenceService;
use App\Services\Revisions\RevisionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Block templates (CMS-ARCHITECTURE.md §12): saved trees that editors insert as independent
 * deep copies. Saved from the builder ("Save as template") or edited in their own screen.
 */
class BlockTemplateService
{
    use ManagesBlockOwners;

    public function __construct(
        protected readonly BlockTreeRepository $blocks,
        protected readonly ContentReferenceService $references,
        private readonly BlockTreeValidator $validator,
        private readonly RevisionService $revisions,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $data  name, description?, scope, category?, status?, blocks
     */
    public function create(User $user, array $data): BlockTemplate
    {
        $nodes = $this->tree($data, $user);

        return DB::transaction(function () use ($user, $data, $nodes) {
            $template = new BlockTemplate;
            $template->fill($this->attributes($data));
            $template->slug = $this->uniqueSlug(BlockTemplate::class, (string) $data['name']);
            $template->created_by = $template->updated_by = $user->id;
            $template->save();

            $this->storeTree($template, $nodes, $user);
            $this->revisions->record($template, RevisionKind::Manual, $user, 'Created');
            $this->logger->log('block_template.created', $template, ['blocks' => count($nodes)], $user);

            return $template;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, BlockTemplate $template, array $data, int $lockVersion): BlockTemplate
    {
        $nodes = array_key_exists('blocks', $data) ? $this->tree($data, $user) : null;

        return DB::transaction(function () use ($user, $template, $data, $nodes, $lockVersion) {
            $template = $this->lockFresh($template, $lockVersion);
            $before = $template->toSnapshot();

            $template->fill($this->attributes($data));
            if ($nodes !== null) {
                $this->storeTree($template, $nodes, $user);
            }
            $template->updated_by = $user->id;
            $template->lock_version++;
            $template->save();

            $this->revisions->record($template, RevisionKind::Manual, $user, $this->revisions->summarize($before, $template->toSnapshot()));
            $this->logger->log('block_template.updated', $template, [], $user);

            return $template;
        });
    }

    public function delete(User $user, BlockTemplate $template): void
    {
        DB::transaction(function () use ($user, $template) {
            $template->delete();
            $this->references->clear($template);
            $this->logger->log('block_template.deleted', $template, [], $user);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function tree(array $data, User $user): array
    {
        $nodes = $this->validator->validate((array) ($data['blocks'] ?? []), $user, BlockTreeValidator::CONTEXT_TEMPLATE);

        if ($nodes === []) {
            throw ValidationException::withMessages(['blocks' => __('A template needs at least one block.')]);
        }

        return $nodes;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'scope' => $data['scope'] ?? 'section',
            'category' => $data['category'] ?? null,
            'status' => $data['status'] ?? 'published',
        ];
    }
}
