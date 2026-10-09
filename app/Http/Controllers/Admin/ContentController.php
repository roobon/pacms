<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Blocks\BlockTreeRepository;
use App\Cms\Content\ContentType;
use App\Cms\Content\ContentTypeRegistry;
use App\Cms\Fields\VideoUrl;
use App\Enums\ContentStatus;
use App\Enums\MediaKind;
use App\Enums\WorkflowAction;
use App\Http\Controllers\Controller;
use App\Models\ContentItem;
use App\Models\Gallery;
use App\Models\GalleryItem;
use App\Models\GlobalBlock;
use App\Models\Media;
use App\Models\Revision;
use App\Models\Term;
use App\Rules\SummaryText;
use App\Services\Content\ContentService;
use App\Services\Revisions\RevisionService;
use App\Services\Settings\SettingsService;
use App\Support\Html\HtmlSanitizer;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin screens of every content module (news, events…): list, form, workflow, revisions
 * and preview. Routes are registered per type (routes/admin.php) with the type key as a
 * route default, so names stay readable: admin.news.edit, admin.events.index…
 */
class ContentController extends Controller
{
    public function __construct(
        private readonly ContentTypeRegistry $types,
        private readonly ContentService $content,
    ) {}

    public function index(Request $request): View
    {
        $type = $this->type($request);
        $this->authorizeType($request, $type, 'view');

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ContentStatus::class)],
        ]);

        return view('admin.content.index', [
            'type' => $type,
            'items' => $type->query()
                ->with('author:id,name')
                ->when($filters['q'] ?? null, fn ($query, $q) => $query->where('title', 'like', "%{$q}%"))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        $type = $this->type($request);
        $this->authorizeType($request, $type, 'create');
        $item = $type->newItem();
        if (isset($type->fields()['timezone'])) {
            $item->setAttribute('timezone', $this->siteTimezone());
        }

        return view('admin.content.form', $this->formData($type, $item, $request));
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $this->type($request);
        $this->authorizeType($request, $type, 'create');

        $item = $this->content->create($type, $request->user(), $this->validated($type, $request));

        return redirect()->to($type->adminUrl('edit', $item))
            ->with('success', __(':Item created as a draft.', ['item' => $type->singular()]));
    }

    public function edit(Request $request): View
    {
        $type = $this->type($request);
        $item = $this->item($type, $request);
        Gate::authorize('view', $item);

        return view('admin.content.form', $this->formData($type, $item->load(['seo', 'featuredMedia', 'terms', 'author:id,name']), $request));
    }

    public function update(Request $request): RedirectResponse
    {
        $type = $this->type($request);
        $item = $this->item($type, $request);
        Gate::authorize('update', $item);

        $lockVersion = (int) $request->validate(['lock_version' => ['required', 'integer']])['lock_version'];
        $this->content->update($type, $request->user(), $item, $this->validated($type, $request), $lockVersion);

        return redirect()->to($type->adminUrl('edit', $item))->with('success', __('Changes saved.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $type = $this->type($request);
        $item = $this->item($type, $request);
        Gate::authorize('delete', $item);

        $this->content->delete($type, $request->user(), $item);

        return redirect()->to($type->adminUrl())
            ->with('success', __(':Item ":title" deleted.', ['item' => $type->singular(), 'title' => $item->title]));
    }

    public function workflow(Request $request): RedirectResponse
    {
        $type = $this->type($request);
        $item = $this->item($type, $request);
        Gate::authorize('view', $item);

        $data = $request->validate([
            'action' => ['required', Rule::enum(WorkflowAction::class)],
            'note' => ['nullable', 'string', 'max:1000'],
            'publish_at' => ['nullable', 'date'],
        ]);
        $action = WorkflowAction::from($data['action']);
        $timezone = $this->siteTimezone();

        $this->content->transition($type, $item, $action, $request->user(), [
            'note' => $data['note'] ?? null,
            // The form sends local site time; store UTC.
            'publish_at' => isset($data['publish_at']) ? CarbonImmutable::parse($data['publish_at'], $timezone)->utc() : null,
        ]);

        $message = match ($action) {
            WorkflowAction::Publish => __('Published. It is live at :url', ['url' => url($item->url())]),
            WorkflowAction::Schedule => __('Scheduled for :time.', ['time' => $item->publish_at?->timezone($timezone)->format('j M Y, H:i')]),
            default => __(':action: done.', ['action' => $action->label()]),
        };

        return redirect()->to($type->adminUrl('edit', $item))->with('success', $message);
    }

    public function revisions(Request $request, RevisionService $revisions): View
    {
        $type = $this->type($request);
        $item = $this->item($type, $request);
        Gate::authorize('viewRevisions', $item);

        $compare = null;
        if ($request->filled(['from', 'to'])) {
            $from = $this->revision($item, $request->integer('from'));
            $to = $this->revision($item, $request->integer('to'));
            $compare = ['from' => $from, 'to' => $to, 'changes' => $revisions->compare($from->snapshot, $to->snapshot)];
        }

        return view('admin.content.revisions', [
            'type' => $type,
            'item' => $item,
            'revisions' => $item->revisions()->with('author:id,name')->paginate(25),
            'compare' => $compare,
            'canRestore' => Gate::allows('restoreRevision', $item),
        ]);
    }

    public function restore(Request $request): RedirectResponse
    {
        $type = $this->type($request);
        $item = $this->item($type, $request);
        Gate::authorize('restoreRevision', $item);

        // Restoring changes the item in place, so a live item needs the publish permission too.
        if ($item->isPublished() && ! $request->user()->can($type->ability('publish'))) {
            abort(403, __('Only publishers can change published :items.', ['items' => strtolower($type->label())]));
        }

        $revision = $this->revision($item, (int) $request->route('revision'));
        $this->content->restore($type, $request->user(), $item, $revision);

        return redirect()->to($type->adminUrl('edit', $item))
            ->with('success', __('Revision #:number restored.', ['number' => $revision->number]));
    }

    /**
     * Short-lived signed preview link (CMS-ARCHITECTURE.md §4.4).
     */
    public function preview(Request $request): RedirectResponse
    {
        $type = $this->type($request);
        $item = $this->item($type, $request);
        Gate::authorize('view', $item);

        return redirect(URL::temporarySignedRoute(
            'preview.content',
            now()->addMinutes((int) config('pacms.preview.ttl_minutes')),
            ['type' => $type->routePrefix(), 'id' => $item->getKey()],
            absolute: false,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(ContentType $type, ContentItem $item, Request $request): array
    {
        return [
            'type' => $type,
            'item' => $item,
            'canEdit' => $item->exists
                ? Gate::allows('update', $item) && ($item->status !== ContentStatus::Published || $request->user()->can($type->ability('publish')))
                : true,
            'actions' => $item->exists ? $this->content->availableActions($type, $item, $request->user()) : [],
            // Terms to choose from, per taxonomy (categories, tags…).
            'termChoices' => collect($type->taxonomies())->map(fn (string $label, string $taxonomy) => Term::query()->inTaxonomy($taxonomy)->orderBy('name')->get(['id', 'name'])),
            'sidebars' => GlobalBlock::query()->sidebarChoices()->get(['id', 'name', 'kind']),
            'timezones' => DateTimeZone::listIdentifiers(),
            'revisions' => $item->exists ? $item->revisions()->with('author:id,name')->limit(10)->get() : collect(),
            'blocks' => $item->exists ? app(BlockTreeRepository::class)->load($item) : [],
            // Current documents (file names shown in the list) and media chosen in media fields.
            'documents' => $type->documents() && $item->exists
                ? $item->attachments()->with('media')->get()->filter(fn ($attachment) => $attachment->media !== null)
                    ->map(fn ($attachment) => ['media_id' => $attachment->media_id, 'label' => $attachment->label, 'name' => $attachment->media->original_name, 'size' => $attachment->media->humanSize()])
                    ->values()->all()
                : [],
            'relationOptions' => collect($type->relationFields())->map(function (array $field) {
                return $this->types->get((string) $field['target'])->query()->orderBy('title')->get(['id', 'title', 'status'])
                    ->mapWithKeys(fn (ContentItem $option) => [$option->id => $option->title.($option->isPublished() ? '' : ' ('.strtolower($option->status->label()).')')])
                    ->all();
            })->all(),
            'relationValues' => $item->exists ? collect($type->relationFields())->map(fn (array $field, string $name) => $item->relatedIds($name))->all() : [],
            'galleryItems' => $item instanceof Gallery && $item->exists
                ? $item->items()->with('media')->get()->map(fn (GalleryItem $row) => [
                    'media_id' => $row->media_id, 'video_url' => $row->video_url, 'caption' => $row->caption, 'alt_override' => $row->alt_override, 'credit' => $row->credit,
                    'thumbnail' => $row->media?->thumbnailUrl(320), 'name' => $row->media?->original_name,
                ])->all()
                : [],
            'mediaFields' => collect($type->fields())
                ->filter(fn (array $field) => $field['type'] === 'media')
                ->map(fn (array $field, string $name) => ($id = $item->getAttribute($name)) ? Media::query()->find((int) $id) : null)
                ->all(),
            'siteTimezone' => $this->siteTimezone(),
        ];
    }

    /**
     * Common fields, module fields, categories, SEO and the block tree.
     *
     * @return array<string, mixed>
     */
    private function validated(ContentType $type, Request $request): array
    {
        $html = app(HtmlSanitizer::class);
        $slug = trim((string) $request->input('slug'));
        $request->merge([
            'slug' => $slug === '' ? null : strtolower($slug),
            'featured' => $request->boolean('featured'),
            // Summary: small editor output, cleaned to paragraphs, bold, italic and links.
            'excerpt' => is_string($request->input('excerpt')) ? ($html->inline($request->input('excerpt')) ?: null) : $request->input('excerpt'),
            // Article text: rich text, cleaned with the same allowlist as Text blocks.
            'body' => is_string($request->input('body')) ? ($html->sanitize($request->input('body')) ?: null) : $request->input('body'),
            'seo' => array_merge((array) $request->input('seo', []), [
                'robots_index' => $request->boolean('seo.robots_index', true),
                'robots_follow' => $request->boolean('seo.robots_follow', true),
            ]),
        ]);
        foreach ($type->fields() as $name => $field) {
            if ($field['type'] === 'checkbox') {
                $request->merge([$name => $request->boolean($name)]);
            }
        }

        $image = Rule::exists('media', 'id')->where('kind', MediaKind::Image->value);
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'excerpt' => ['nullable', 'string', 'max:20000', new SummaryText],
            'body' => ['nullable', 'string', 'max:200000'],
            'featured_media_id' => ['nullable', 'integer', $image],
            'featured' => ['boolean'],
            'sidebar_mode' => ['nullable', Rule::in(array_keys(ContentItem::SIDEBAR_MODES))],
            'sidebar_global_block_id' => ['nullable', 'required_if:sidebar_mode,custom', 'integer', Rule::exists('global_blocks', 'id')->whereNotNull('published_revision_id')->whereNull('deleted_at')],
            'blocks' => ['nullable', 'string', 'max:'.((int) config('pacms.blocks.max_payload_kb') * 1024), 'json'],
            'seo' => ['array'],
            'seo.title' => ['nullable', 'string', 'max:255'],
            'seo.description' => ['nullable', 'string', 'max:500'],
            'seo.canonical_url' => ['nullable', 'url:http,https', 'max:2048'],
            'seo.robots_index' => ['boolean'],
            'seo.robots_follow' => ['boolean'],
            'seo.og_title' => ['nullable', 'string', 'max:255'],
            'seo.og_description' => ['nullable', 'string', 'max:500'],
            'seo.og_image_media_id' => ['nullable', 'integer', $image],
        ];
        if ($type->taxonomies() !== []) {
            $rules['terms'] = ['array'];
            $rules['terms.*'] = ['integer', Rule::exists('terms', 'id')->whereIn('taxonomy', array_keys($type->taxonomies()))];
        }
        $attributes = ['featured_media_id' => 'image', 'sidebar_global_block_id' => 'sidebar', 'seo.og_image_media_id' => 'social image'];
        foreach ($type->relationFields() as $name => $field) {
            $rules[$name] = ['nullable', 'array', 'max:100'];
            $rules["{$name}.*"] = ['nullable', 'integer'];
            $attributes[$name] = strtolower((string) $field['label']);
        }
        if (isset($type->fields()['gallery_items'])) {
            $rules['gallery_items.*'] = ['array'];
            $rules['gallery_items.*.media_id'] = ['nullable', 'integer', Rule::exists('media', 'id')->where('kind', MediaKind::Image->value)->where('disk', config('pacms.media.disk'))];
            $rules['gallery_items.*.video_url'] = ['nullable', 'string', 'max:1024', function (string $attribute, mixed $value, \Closure $fail) {
                if (is_string($value) && $value !== '' && VideoUrl::parse($value) === null) {
                    $fail(__('Use a YouTube or Vimeo link.'));
                }
            }];
            $rules['gallery_items.*.caption'] = ['nullable', 'string', 'max:1000'];
            $rules['gallery_items.*.alt_override'] = ['nullable', 'string', 'max:255'];
            $rules['gallery_items.*.credit'] = ['nullable', 'string', 'max:191'];
            $attributes['gallery_items.*.media_id'] = 'photo';
            $attributes['gallery_items.*.video_url'] = 'video link';
        }
        foreach ($type->fields() as $name => $field) {
            if ($field['type'] === 'relation') {
                continue;
            }
            $rules[$name] = $field['rules'];
            $attributes[$name] = strtolower((string) $field['label']);
            if (isset($field['item_rules'])) {
                // Each value of a list field (multiple choice).
                $rules["{$name}.*"] = $field['item_rules'];
            }
            foreach ($field['type'] === 'repeater' ? $field['fields'] : [] as $sub => $subField) {
                $rules["{$name}.*"] = ['array'];
                $rules["{$name}.*.{$sub}"] = $subField['rules'];
                $attributes["{$name}.*.{$sub}"] = strtolower((string) $subField['label']);
            }
        }
        if ($type->documents()) {
            $rules['documents'] = ['nullable', 'array', 'max:30'];
            $rules['documents.*'] = ['array'];
            // Public library documents only: visitors download them from the item's page.
            $rules['documents.*.media_id'] = ['required', 'integer', Rule::exists('media', 'id')->where('kind', MediaKind::Document->value)->where('disk', config('pacms.media.disk'))];
            $rules['documents.*.label'] = ['nullable', 'string', 'max:255'];
            $attributes['documents.*.media_id'] = 'document';
            $attributes['documents.*.label'] = 'document label';
        }

        $data = $request->validate($rules, [], $attributes);
        $data['sidebar_mode'] ??= 'default';

        // Fields reserved for a module permission keep their value for everyone else.
        foreach ($type->fields() as $name => $field) {
            if (isset($field['ability']) && ! $request->user()->can($type->ability((string) $field['ability']))) {
                unset($data[$name]);
            }
        }

        if ($type->taxonomies() !== []) {
            $data['terms'] ??= [];
        }
        // Lists the editor emptied are not sent by the browser at all.
        foreach ($type->fields() as $name => $field) {
            if (in_array($field['type'], ['repeater', 'relation', 'gallery', 'checklist'], true)) {
                $data[$name] ??= [];
            }
        }
        if ($type->documents()) {
            $data['documents'] ??= [];
        }
        if ($request->filled('blocks')) {
            $data['blocks'] = json_decode((string) $request->input('blocks'), true, 64) ?? [];
        } else {
            unset($data['blocks']);
        }

        return $data;
    }

    /**
     * Type-level checks (list, create) go through the type's permission: admin-made types
     * share one model class, so a class-based policy could not tell them apart.
     */
    private function authorizeType(Request $request, ContentType $type, string $action): void
    {
        abort_unless($request->user()->can($type->ability($action)), 403);
    }

    private function type(Request $request): ContentType
    {
        $type = $this->types->find((string) $request->route('type'));
        // /admin/types/{type} is for admin-made types only; built-in modules have their own routes.
        $adminMadeRoute = str_starts_with((string) $request->route()?->getName(), 'admin.types.');
        abort_if($type === null || $type->isAdminMade() !== $adminMadeRoute, 404);

        return $type;
    }

    private function item(ContentType $type, Request $request): ContentItem
    {
        return $type->query()->findOrFail((int) $request->route('item'));
    }

    private function revision(ContentItem $item, int $number): Revision
    {
        return Revision::query()
            ->where('revisionable_type', $item->getMorphClass())
            ->where('revisionable_id', $item->getKey())
            ->where('number', $number)
            ->firstOrFail();
    }

    private function siteTimezone(): string
    {
        return (string) app(SettingsService::class)->get('site', 'timezone', 'UTC');
    }
}
