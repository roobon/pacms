<?php

namespace App\Http\Controllers\Admin\Api;

use App\Cms\Blocks\BlockTreeRepository;
use App\Cms\Blocks\BlockTreeValidator;
use App\Cms\Fields\FieldDefinitionValidator;
use App\Http\Controllers\Controller;
use App\Models\BlockTemplate;
use App\Models\BlockType;
use App\Models\GlobalBlock;
use App\Models\Page;
use App\Services\Blocks\BlockTemplateService;
use App\Services\Blocks\GlobalBlockService;
use App\Services\Revisions\RevisionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Builder endpoints for reusable content (CMS-ARCHITECTURE.md §12, §23.4): the template and
 * global block palettes, "Save as template", "Convert to global block", "Detach", autosave.
 */
class ReusableBlockController extends Controller
{
    /**
     * Published global blocks that can be placed.
     */
    public function globals(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('pages.view'), 403);

        $globals = GlobalBlock::query()->whereNotNull('published_revision_id')->orderBy('name')->get(['id', 'name', 'description', 'has_unpublished_changes']);

        return response()->json(['data' => $globals->map(fn (GlobalBlock $global) => [
            'id' => $global->id,
            'name' => $global->name,
            'description' => $global->description,
            'edit_url' => $request->user()->can('global_blocks.manage') ? route('admin.global-blocks.edit', $global) : null,
        ])]);
    }

    /**
     * "Convert to global block": the selected blocks become a new, published global block.
     */
    public function storeGlobal(Request $request, GlobalBlockService $service): JsonResponse
    {
        abort_unless($request->user()->can('global_blocks.manage'), 403);

        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'blocks' => ['required', 'array', 'min:1']]);
        $global = $service->publish($request->user(), $service->create($request->user(), $data));

        return response()->json(['data' => ['id' => $global->id, 'name' => $global->name]], 201);
    }

    /**
     * "Detach": a local copy of a global block's published tree.
     */
    public function detach(Request $request, GlobalBlock $global, GlobalBlockService $service): JsonResponse
    {
        abort_unless($request->user()->can('global_blocks.detach'), 403);

        return response()->json(['blocks' => $service->detachedCopy($request->user(), $global)]);
    }

    /**
     * Templates offered in the builder palette.
     */
    public function templates(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('pages.view'), 403);

        $templates = BlockTemplate::query()->where('status', 'published')->orderBy('category')->orderBy('name')
            ->get(['id', 'name', 'description', 'scope', 'category']);

        return response()->json(['data' => $templates]);
    }

    /**
     * A template's tree, to be inserted as a deep copy (the builder assigns fresh uuids).
     */
    public function template(Request $request, BlockTemplate $template, BlockTreeRepository $blocks): JsonResponse
    {
        abort_unless($request->user()->can('pages.view') && $template->isAvailable(), 404);

        return response()->json(['blocks' => $blocks->load($template)]);
    }

    /**
     * "Save as template" from the builder.
     */
    public function storeTemplate(Request $request, BlockTemplateService $service): JsonResponse
    {
        abort_unless($request->user()->can('templates.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'scope' => ['required', Rule::in(array_keys(BlockTemplate::SCOPES))],
            'category' => ['nullable', 'string', 'max:64'],
            'blocks' => ['required', 'array', 'min:1'],
        ]);
        $template = $service->create($request->user(), $data);

        return response()->json(['data' => ['id' => $template->id, 'name' => $template->name]], 201);
    }

    /**
     * Store the user's unsaved tree as an autosave revision (only valid trees).
     */
    public function autosave(Request $request, BlockTreeValidator $validator, FieldDefinitionValidator $definitions, RevisionService $revisions): JsonResponse
    {
        $data = $request->validate([
            'owner' => ['required', Rule::in(['page', 'global_block', 'block_template', 'block_type'])],
            'id' => ['required', 'integer'],
            'blocks' => ['present', 'array'],
            'fields' => ['nullable', 'array'],
        ]);

        [$owner, $context] = $this->owner($data['owner'], (int) $data['id']);
        abort_unless($this->mayEdit($request, $owner), 403);

        $bindable = $context === BlockTreeValidator::CONTEXT_STRUCTURE ? $definitions->validate($data['fields'] ?? []) : [];
        $blocks = $validator->validate($data['blocks'], $request->user(), $context, $bindable);
        $revision = $revisions->autosave($owner, $request->user(), $blocks);

        return response()->json(['saved_at' => $revision->created_at?->toIso8601String()]);
    }

    /**
     * @return array{0: Page|GlobalBlock|BlockTemplate|BlockType, 1: string}
     */
    private function owner(string $type, int $id): array
    {
        return match ($type) {
            'page' => [Page::query()->findOrFail($id), BlockTreeValidator::CONTEXT_PAGE],
            'global_block' => [GlobalBlock::query()->findOrFail($id), BlockTreeValidator::CONTEXT_GLOBAL],
            'block_template' => [BlockTemplate::query()->findOrFail($id), BlockTreeValidator::CONTEXT_TEMPLATE],
            default => [BlockType::query()->custom()->findOrFail($id), BlockTreeValidator::CONTEXT_STRUCTURE],
        };
    }

    private function mayEdit(Request $request, Model $owner): bool
    {
        return match (true) {
            $owner instanceof Page => Gate::allows('update', $owner),
            $owner instanceof GlobalBlock => $request->user()->can('global_blocks.manage'),
            $owner instanceof BlockTemplate => $request->user()->can('templates.manage'),
            default => $request->user()->can('block_types.manage'),
        };
    }
}
