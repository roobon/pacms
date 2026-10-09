<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MediaKind;
use App\Enums\TestimonialAction;
use App\Enums\TestimonialStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Program;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Testimonial;
use App\Services\Testimonials\TestimonialModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Testimonial moderation queue and editor (CMS-ARCHITECTURE.md §18): review, approve,
 * reject (with an internal reason), publish, archive and delete; staff can also enter
 * testimonials themselves.
 */
class TestimonialController extends Controller
{
    /** List filters: key => statuses. "queue" is what waits for a moderator. */
    private const VIEWS = ['queue', 'draft', 'approved', 'published', 'rejected', 'archived', 'all'];

    public function __construct(private readonly TestimonialModerationService $testimonials) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Testimonial::class);

        $filters = $request->validate([
            'view' => ['nullable', Rule::in(self::VIEWS)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $view = $filters['view'] ?? 'queue';
        $counts = Testimonial::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($n) => (int) $n);

        return view('admin.testimonials.index', [
            'items' => Testimonial::query()
                ->with('photo')
                ->when($view === 'queue', fn ($query) => $query->whereIn('status', TestimonialStatus::awaitingModeration()))
                ->when(! in_array($view, ['queue', 'all'], true), fn ($query) => $query->where('status', $view))
                ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($q2) => $q2->where('name', 'like', "%{$q}%")->orWhere('organization', 'like', "%{$q}%")->orWhere('body', 'like', "%{$q}%")))
                // The queue is worked oldest first; other lists show the newest first.
                ->when($view === 'queue', fn ($query) => $query->oldest('id'), fn ($query) => $query->latest('id'))
                ->paginate(30)
                ->withQueryString(),
            'view' => $view,
            'filters' => $filters,
            'counts' => [
                'queue' => ($counts[TestimonialStatus::Pending->value] ?? 0) + ($counts[TestimonialStatus::UnderReview->value] ?? 0),
                'all' => $counts->sum(),
            ] + $counts->all(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Testimonial::class);

        return view('admin.testimonials.form', $this->formData(new Testimonial, $request));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Testimonial::class);

        $testimonial = $this->testimonials->create($request->user(), $this->validated($request, null));

        return redirect()->route('admin.testimonials.edit', $testimonial)->with('success', __('Testimonial saved as a draft.'));
    }

    public function edit(Request $request, int $testimonial): View
    {
        $item = $this->find($testimonial);
        Gate::authorize('view', $item);

        return view('admin.testimonials.form', $this->formData($item->load(['photo', 'user:id,name,email', 'reviewer:id,name']), $request));
    }

    public function update(Request $request, int $testimonial): RedirectResponse
    {
        $item = $this->find($testimonial);
        Gate::authorize('update', $item);

        $lockVersion = (int) $request->validate(['lock_version' => ['required', 'integer']])['lock_version'];
        $this->testimonials->update($request->user(), $item, $this->validated($request, $item), $lockVersion);

        return redirect()->route('admin.testimonials.edit', $item)->with('success', __('Changes saved.'));
    }

    public function transition(Request $request, int $testimonial): RedirectResponse
    {
        $item = $this->find($testimonial);
        Gate::authorize('view', $item);

        $data = $request->validate([
            'action' => ['required', Rule::enum(TestimonialAction::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $action = TestimonialAction::from($data['action']);
        $this->testimonials->transition($item, $action, $request->user(), $data['note'] ?? null);

        $message = match ($action) {
            TestimonialAction::Publish => __('Published. It now appears in Testimonials blocks.'),
            TestimonialAction::Reject => __('Rejected. The reason is kept in the moderation history.'),
            default => __(':action: done.', ['action' => $action->label()]),
        };

        // After a decision, the next testimonial in the queue is one click away.
        return redirect()->route('admin.testimonials.edit', $item)->with('success', $message);
    }

    public function destroy(Request $request, int $testimonial): RedirectResponse
    {
        $item = $this->find($testimonial);
        Gate::authorize('delete', $item);

        $this->testimonials->delete($request->user(), $item);

        return redirect()->route('admin.testimonials.index')->with('success', __('Testimonial from :name deleted.', ['name' => $item->name]));
    }

    /**
     * The photo for moderators, also while it is still private.
     */
    public function photo(int $testimonial): StreamedResponse
    {
        $item = $this->find($testimonial);
        Gate::authorize('view', $item);
        $photo = $item->photo()->first();
        abort_if($photo === null || ! Storage::disk($photo->disk)->exists($photo->path), 404);

        return Storage::disk($photo->disk)->response($photo->path, $photo->original_name, [
            'Content-Type' => $photo->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], 'inline');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Testimonial $item, Request $request): array
    {
        $user = $request->user();
        $original = $item->exists
            ? Revision::query()->where('revisionable_type', $item->getMorphClass())->where('revisionable_id', $item->id)->orderBy('number')->first()
            : null;

        return [
            'item' => $item,
            'canEdit' => $item->exists ? Gate::allows('update', $item) : true,
            'canPickPhoto' => $user->can('media.view'),
            'actions' => $item->exists ? $this->testimonials->availableActions($item, $user) : [],
            'logs' => $item->exists ? $item->moderationLogs()->with('actor:id,name')->get() : collect(),
            // Shown when staff have edited a submission since: what the person actually sent.
            'original' => $item->user_id !== null && $original !== null && $original->summary === 'Original submission' && $item->revisions()->count() > 1 ? $original : null,
            'programs' => Program::query()->orderBy('title')->pluck('title', 'id'),
            'projects' => Project::query()->orderBy('title')->pluck('title', 'id'),
            'events' => Event::query()->orderByDesc('start_at')->limit(200)->pluck('title', 'id'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Testimonial $item): array
    {
        $request->merge(['featured' => $request->boolean('featured')]);
        $max = (int) config('pacms.testimonials.body_max');

        $rules = [
            'name' => ['required', 'string', 'max:191'],
            'designation' => ['nullable', 'string', 'max:191'],
            'organization' => ['nullable', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:'.max($max, 5000)],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'citation' => ['nullable', 'string', 'max:255'],
            'testimonial_date' => ['nullable', 'date'],
            'program_id' => ['nullable', 'integer', Rule::exists('programs', 'id')->whereNull('deleted_at')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'event_id' => ['nullable', 'integer', Rule::exists('events', 'id')->whereNull('deleted_at')],
            'website_url' => ['nullable', 'url:http,https', 'max:1024'],
            'featured' => ['boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
        // Choosing a library photo needs access to the library.
        if ($request->user()->can('media.view')) {
            $rules['photo_media_id'] = ['nullable', 'integer', Rule::exists('media', 'id')->where('kind', MediaKind::Image->value)];
        }

        $data = $request->validate($rules, [], ['body' => 'testimonial', 'program_id' => 'program', 'project_id' => 'project', 'event_id' => 'event', 'website_url' => 'website']);
        $data['body'] = Testimonial::plainText((string) $data['body']);
        $data['position'] = (int) ($data['position'] ?? 0);
        foreach (['designation', 'organization', 'citation', 'website_url', 'testimonial_date', 'rating', 'program_id', 'project_id', 'event_id'] as $optional) {
            $data[$optional] = ($data[$optional] ?? null) === '' ? null : ($data[$optional] ?? null);
        }

        return $data;
    }

    private function find(int $id): Testimonial
    {
        return Testimonial::query()->findOrFail($id);
    }
}
