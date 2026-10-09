<?php

namespace App\Http\Controllers\Admin;

use App\Cms\External\ProviderRegistry;
use App\Http\Controllers\Controller;
use App\Models\ExternalSource;
use App\Services\ActivityLog\ActivityLogger;
use App\Services\Cache\CacheVersions;
use App\Services\External\ExternalSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Design → External sources (Phase 10): feeds of other sites (and, in 10B, the Facebook
 * Page). "Manage external sources" adds and changes them; "Sync external sources" may
 * also sync now.
 */
class ExternalSourceController extends Controller
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly ExternalSyncService $sync,
        private readonly ActivityLogger $logger,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('external_sources.manage') || $request->user()->can('external_sources.sync'), 403);

        return view('admin.external-sources.index', [
            'sources' => ExternalSource::query()->withCount('items')->orderBy('name')->get(),
            'providers' => $this->providers->all(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeManage($request);

        return view('admin.external-sources.form', $this->formData(new ExternalSource(['provider' => 'feed'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);
        $source = new ExternalSource;
        $source->provider = (string) $request->input('provider', 'feed');
        $this->fillFrom($request, $source);
        $source->slug = $this->uniqueSlug((string) $request->input('slug') ?: $source->name);
        $source->forceFill(['created_by' => $request->user()->id, 'updated_by' => $request->user()->id, 'next_sync_at' => now()])->save();
        $this->logger->log('external_source.created', $source, ['provider' => $source->provider], $request->user(), $source->name);

        // First items at once, so the source can be used in blocks straight away.
        $log = $this->sync->sync($source, 'manual');

        return redirect()->route('admin.external-sources.edit', $source)->with($log->status === 'ok' ? 'success' : 'warning', $log->status === 'ok'
            ? __('Source added with :count items. Show it with a Feed block.', ['count' => $source->items()->count()])
            : __('Source added, but the first sync failed: :error', ['error' => $log->error]));
    }

    public function edit(Request $request, ExternalSource $externalSource): View
    {
        abort_unless($request->user()->can('external_sources.manage') || $request->user()->can('external_sources.sync'), 403);

        return view('admin.external-sources.form', $this->formData($externalSource->loadCount('items')) + [
            'logs' => $externalSource->logs()->limit(10)->get(),
            'recent' => $externalSource->items()->orderByRaw('published_at is null')->orderByDesc('published_at')->limit(5)->get(),
        ]);
    }

    public function update(Request $request, ExternalSource $externalSource): RedirectResponse
    {
        $this->authorizeManage($request);
        $before = $externalSource->config;
        $this->fillFrom($request, $externalSource);
        $externalSource->forceFill(['updated_by' => $request->user()->id]);
        if ($externalSource->config !== $before) {
            $externalSource->next_sync_at = now(); // a new address is read at once by the scheduler
        }
        $externalSource->save();
        app(CacheVersions::class)->bump('external');
        $this->logger->log('external_source.updated', $externalSource, [], $request->user(), $externalSource->name);

        return back()->with('success', __('Changes saved.'));
    }

    public function destroy(Request $request, ExternalSource $externalSource): RedirectResponse
    {
        $this->authorizeManage($request);
        $externalSource->items()->delete();
        $externalSource->delete();
        app(CacheVersions::class)->bump('external');
        $this->logger->log('external_source.deleted', $externalSource, [], $request->user(), $externalSource->name);

        return redirect()->route('admin.external-sources.index')->with('success', __('Source ":name" deleted. Feed blocks that showed it are now empty.', ['name' => $externalSource->name]));
    }

    /**
     * Read the address in the form without saving anything.
     */
    public function test(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);
        $source = new ExternalSource;
        $source->provider = (string) $request->input('provider', 'feed');
        $provider = $this->providers->find($source->provider) ?? abort(404);
        $data = $request->validate($provider->configRules(), [], ['config.feed_url' => 'feed address']);
        $source->config = $provider->config((array) ($data['config'] ?? []));

        $result = $this->sync->test($source);

        return back()->withInput()->with('test', $result);
    }

    public function syncNow(Request $request, ExternalSource $externalSource): RedirectResponse
    {
        abort_unless($request->user()->can('external_sources.sync') || $request->user()->can('external_sources.manage'), 403);
        // API-ARCHITECTURE.md §5: at most 6 manual syncs per source and hour.
        $key = "external-sync-now:{$externalSource->id}";
        if (RateLimiter::tooManyAttempts($key, 6)) {
            return back()->with('warning', __('This source was synced several times in the last hour. Try again in :minutes minutes.', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]));
        }
        RateLimiter::hit($key, 3600);

        $log = $this->sync->sync($externalSource, 'manual');
        $this->logger->log('external_source.synced', $externalSource, ['status' => $log->status], $request->user(), $externalSource->name);

        return back()->with($log->status === 'ok' ? 'success' : 'warning', $log->status === 'ok'
            ? __('Synced: :count items (:new new).', ['count' => $log->items_fetched, 'new' => $log->items_created])
            : __('The sync failed: :error The items stored before keep showing.', ['error' => $log->error]));
    }

    public function clear(Request $request, ExternalSource $externalSource): RedirectResponse
    {
        $this->authorizeManage($request);
        $this->sync->clear($externalSource);

        return back()->with('success', __('Stored items deleted. They come back with the next sync.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(ExternalSource $source): array
    {
        return [
            'source' => $source,
            'providers' => $this->providers->all(),
            'intervals' => ExternalSource::INTERVALS,
        ];
    }

    private function fillFrom(Request $request, ExternalSource $source): void
    {
        $provider = $this->providers->find($source->provider) ?? abort(422, 'Unknown provider.');
        $request->merge(['status' => $request->boolean('enabled', true) ? 'enabled' : 'disabled']);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:500'],
            'sync_interval_minutes' => ['required', 'integer', Rule::in(array_keys(ExternalSource::INTERVALS))],
            'max_items' => ['required', 'integer', 'min:1', 'max:200'],
            'status' => ['required', 'in:enabled,disabled'],
            'slug' => ['nullable', 'string', 'max:191'],
        ] + $provider->configRules(), [], ['config.feed_url' => 'feed address', 'max_items' => 'number of items to keep']);

        $source->fill([
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'sync_interval_minutes' => (int) $data['sync_interval_minutes'],
            'max_items' => (int) $data['max_items'],
            'status' => $data['status'],
            'config' => $provider->config((array) ($data['config'] ?? [])),
        ]);
    }

    private function uniqueSlug(string $wanted): string
    {
        $base = Str::limit(Str::slug($wanted) ?: 'source', 80, '');
        $slug = $base;
        for ($n = 2; ExternalSource::withTrashed()->where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->can('external_sources.manage'), 403);
    }
}
