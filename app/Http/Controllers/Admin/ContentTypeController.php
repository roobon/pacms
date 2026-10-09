<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Content\ContentTypeRegistry;
use App\Cms\Content\Types\AdminContentType;
use App\Cms\Fields\FieldDefinitionValidator;
use App\Http\Controllers\Controller;
use App\Models\CustomContentType;
use App\Services\Content\ContentTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design → Content types (Phase 8D): create types without code. Each one gets an admin list
 * and form, workflow, revisions, archive and detail pages and its own permissions.
 */
class ContentTypeController extends Controller
{
    public function __construct(private readonly ContentTypeService $service) {}

    public function index(Request $request): View
    {
        $this->authorizeManage($request);

        return view('admin.content-types.index', [
            'types' => CustomContentType::query()->withCount('items')->orderBy('position')->orderBy('label')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeManage($request);

        return view('admin.content-types.form', $this->formData(new CustomContentType));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        $type = $this->service->create($request->user(), $this->validated($request, creating: true));

        return redirect()->route('admin.content-types.edit', $type)->with('success', __('Content type ":label" created. It is in the Content menu, and its items appear at /:prefix.', [
            'label' => $type->label, 'prefix' => $type->route_prefix,
        ]));
    }

    public function edit(Request $request, CustomContentType $contentType): View
    {
        $this->authorizeManage($request);

        return view('admin.content-types.form', $this->formData($contentType->loadCount('items')));
    }

    public function update(Request $request, CustomContentType $contentType): RedirectResponse
    {
        $this->authorizeManage($request);

        $this->service->update($request->user(), $contentType, $this->validated($request, creating: false));

        return redirect()->route('admin.content-types.edit', $contentType)->with('success', __('Changes saved.'));
    }

    public function destroy(Request $request, CustomContentType $contentType): RedirectResponse
    {
        $this->authorizeManage($request);

        $this->service->delete($request->user(), $contentType);

        return redirect()->route('admin.content-types.index')->with('success', __('Content type ":label" deleted.', ['label' => $contentType->label]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(CustomContentType $type): array
    {
        return [
            'contentType' => $type,
            'adminType' => $type->exists ? app(ContentTypeRegistry::class)->findCustom($type->id) : null,
            'fieldTypes' => array_intersect_key(FieldDefinitionValidator::TYPES, array_flip(AdminContentType::FIELD_TYPES)),
            'displays' => AdminContentType::DISPLAYS,
            'sectionOnly' => AdminContentType::SECTION_ONLY,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        $request->merge([
            'has_archive' => $request->boolean('has_archive'),
            'searchable' => $request->boolean('searchable'),
            'has_categories' => $request->boolean('has_categories'),
            'has_documents' => $request->boolean('has_documents'),
            'is_active' => $creating || $request->boolean('is_active'),
        ]);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'singular' => ['nullable', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'regex:/^bi-[a-z0-9-]{1,60}$/'],
            'route_prefix' => ['nullable', 'string', 'max:64'],
            'workflow' => $creating ? ['required', 'in:editorial,managed'] : ['prohibited'],
            'has_archive' => ['boolean'],
            'searchable' => ['boolean'],
            'has_categories' => ['boolean'],
            'has_documents' => ['boolean'],
            'is_active' => ['boolean'],
            'fields' => ['nullable', 'string', 'max:200000', 'json'],
            'display' => ['nullable', 'array'],
            'display.*' => ['string'],
        ], [], ['route_prefix' => 'URL', 'label' => 'name']);

        $data['fields'] = json_decode((string) ($data['fields'] ?? '[]'), true) ?? [];

        return $data;
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->can('content_types.manage'), 403);
    }
}
