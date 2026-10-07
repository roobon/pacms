<?php

namespace App\Http\Controllers\Admin\Api;

use App\Cms\Blocks\BlockExpander;
use App\Cms\Blocks\BlockPayloadResolver;
use App\Cms\Blocks\BlockRegistry;
use App\Cms\Blocks\BlockTreeValidator;
use App\Cms\Design\TokenCatalog;
use App\Cms\Display\DisplayModeRegistry;
use App\Cms\Fields\Bindings;
use App\Cms\Fields\FieldDefinitionValidator;
use App\Cms\Sources\SourceRegistry;
use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON endpoints for the Block Builder island (session auth + CSRF, pages.view).
 */
class BlockBuilderController extends Controller
{
    /**
     * Everything the builder needs to render palettes and inspector forms.
     */
    public function definitions(Request $request, BlockRegistry $registry, SourceRegistry $sources): JsonResponse
    {
        abort_unless($request->user()->can('pages.view'), 403);

        $tokens = TokenCatalog::definitions();
        $group = fn (string $prefix) => collect($tokens)
            ->filter(fn ($definition, $name) => str_starts_with($name, $prefix.'.'))
            ->map(fn ($definition, $name) => ['token' => $name, 'label' => $definition['label'] ?: ucfirst(substr($name, strlen($prefix) + 1)), 'value' => $definition['default']])
            ->values();

        return response()->json([
            'types' => array_values(array_map(fn ($type) => $type->toArray(), $registry->all())),
            'display_modes' => DisplayModeRegistry::definitions(),
            'sources' => $sources->describeDynamic(),
            'tokens' => [
                'color' => $group('color'),
                'space' => $group('space'),
                'radius' => $group('radius'),
                'shadow' => $group('shadow'),
                'font' => $group('font'),
                'font_size' => $group('font-size'),
                'container' => $group('container'),
            ],
            'field_types' => FieldDefinitionValidator::TYPES,
            'bindings' => Bindings::compatibility(),
            'permissions' => [
                'custom_attributes' => $request->user()->can('blocks.custom_attributes'),
                'media_upload' => $request->user()->can('media.upload'),
                'templates' => $request->user()->can('templates.manage'),
                'global_blocks' => $request->user()->can('global_blocks.manage'),
                'global_detach' => $request->user()->can('global_blocks.detach'),
            ],
        ]);
    }

    /**
     * Validate and resolve an unsaved tree for the live preview. Nothing is stored.
     */
    public function resolve(Request $request, BlockTreeValidator $validator, BlockPayloadResolver $resolver, FieldDefinitionValidator $definitions, BlockExpander $expander): JsonResponse
    {
        abort_unless($request->user()->can('pages.view'), 403);

        $request->validate([
            'blocks' => ['present', 'array'],
            'context' => ['nullable', 'in:page,global,template,structure'],
            'fields' => ['nullable', 'array'],
        ]);
        $context = (string) $request->input('context', BlockTreeValidator::CONTEXT_PAGE);

        if ($context === BlockTreeValidator::CONTEXT_STRUCTURE) {
            // A custom block type's layout: preview it filled with sample values.
            $fields = $definitions->validate($request->input('fields', []));
            $structure = $validator->validate((array) $request->input('blocks'), $request->user(), $context, $fields);

            return response()->json(['blocks' => $resolver->resolve($expander->preview($structure, $fields), includeHidden: true)]);
        }

        $blocks = $validator->validate((array) $request->input('blocks'), $request->user(), $context);

        return response()->json(['blocks' => $resolver->resolve($blocks, includeHidden: true)]);
    }

    /**
     * Internal link targets for link fields (pages and news), searchable.
     */
    public function linkTargets(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('pages.view'), 403);

        $q = (string) ($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '');

        $pages = Page::query()
            ->when($q !== '', fn ($query) => $query->where('title', 'like', "%{$q}%"))
            ->orderBy('path')->limit(20)->get(['id', 'title', 'path'])
            ->map(fn (Page $page) => ['entity' => 'pages', 'id' => $page->id, 'title' => $page->title, 'path' => '/'.$page->path]);

        $news = News::query()
            ->when($q !== '', fn ($query) => $query->where('title', 'like', "%{$q}%"))
            ->latest('id')->limit(10)->get(['id', 'title', 'slug'])
            ->map(fn (News $item) => ['entity' => 'news', 'id' => $item->id, 'title' => $item->title, 'path' => $item->url()]);

        return response()->json(['data' => $pages->concat($news)->values()]);
    }
}
