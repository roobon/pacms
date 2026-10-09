<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\OwnTestimonialResource;
use App\Http\Resources\Public\TestimonialResource;
use App\Models\Event;
use App\Models\Program;
use App\Models\Project;
use App\Models\Testimonial;
use App\Services\Testimonials\TestimonialModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

/**
 * Testimonials in the public API (API-ARCHITECTURE.md §3–4): the published list, the
 * submission form's options, submitting (verified registered users) and one's own list.
 */
class TestimonialController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'featured' => ['nullable', 'boolean'],
            'program' => ['nullable', 'integer'],
            'project' => ['nullable', 'integer'],
            'event' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ]);

        $items = Testimonial::query()->published()->with('photo')
            ->when($request->boolean('featured'), fn ($q) => $q->where('featured', true))
            ->when($filters['program'] ?? null, fn ($q, $id) => $q->where('program_id', $id))
            ->when($filters['project'] ?? null, fn ($q, $id) => $q->where('project_id', $id))
            ->when($filters['event'] ?? null, fn ($q, $id) => $q->where('event_id', $id))
            ->orderBy('position')->orderByDesc('published_at')->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 12))
            ->withQueryString();

        return TestimonialResource::collection($items);
    }

    /**
     * Published programs and projects for the form's "related to" choices.
     */
    public function options(): JsonResponse
    {
        $list = fn (string $model) => $model::query()->published()->orderBy('title')->get(['id', 'title'])
            ->map(fn ($item) => ['id' => $item->id, 'title' => $item->title])->values();

        return response()->json(['data' => [
            'programs' => $list(Program::class),
            'projects' => $list(Project::class),
            'events' => $list(Event::class),
            'consent' => ['version' => config('pacms.testimonials.consent_version'), 'text' => config('pacms.testimonials.consent_text')],
            'body_max' => (int) config('pacms.testimonials.body_max'),
        ]]);
    }

    public function mine(Request $request): AnonymousResourceCollection
    {
        return OwnTestimonialResource::collection(
            Testimonial::query()->where('user_id', $request->user()->id)->latest('id')->limit(50)->get(),
        );
    }

    public function store(Request $request, TestimonialModerationService $testimonials): JsonResponse
    {
        $published = fn (string $table) => Rule::exists($table, 'id')->where('status', 'published')->whereNull('deleted_at');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'organization' => ['nullable', 'string', 'max:191'],
            'designation' => ['nullable', 'string', 'max:191'],
            'body' => ['required', 'string', 'min:20', 'max:'.(int) config('pacms.testimonials.body_max')],
            'program_id' => ['nullable', 'integer', $published('programs')],
            'project_id' => ['nullable', 'integer', $published('projects')],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) config('pacms.testimonials.photo_max_kb')],
            'consent' => ['accepted'],
            // Honeypot: hidden from people, filled in by bots.
            'homepage' => ['nullable', 'string', 'max:255'],
        ], [
            'consent.accepted' => __('Please agree to your testimonial being published.'),
        ], ['body' => 'testimonial', 'program_id' => 'program', 'project_id' => 'project']);

        // 3 a day per account and 10 per IP (API-ARCHITECTURE.md §8). Only submissions that were
        // accepted count, so correcting a mistake in the form never uses up the quota.
        $limits = ['testimonial-submit:user:'.$request->user()->id => 3, 'testimonial-submit:ip:'.$request->ip() => 10];
        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return response()->json(['message' => __('You have sent several testimonials today. Please try again tomorrow.')], 429, [
                    'Retry-After' => RateLimiter::availableIn($key),
                ]);
            }
        }
        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key, 86400);
        }

        if (! empty($data['homepage'])) {
            // Answer like a success so the bot learns nothing; nothing is stored.
            return response()->json(['data' => ['status' => 'pending', 'status_label' => 'Waiting for review']], 201);
        }

        $data['body'] = Testimonial::plainText((string) $data['body']);
        $testimonial = $testimonials->submit($request->user(), $data, $request->file('photo'), $request->ip());

        return (new OwnTestimonialResource($testimonial))->response()->setStatusCode(201);
    }
}
