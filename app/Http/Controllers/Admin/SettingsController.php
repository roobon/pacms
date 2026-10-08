<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Content\ContentTypeRegistry;
use App\Http\Controllers\Controller;
use App\Models\GlobalBlock;
use App\Models\Page;
use App\Services\ActivityLog\ActivityLogger;
use App\Services\Settings\SettingsService;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ContentTypeRegistry $types,
    ) {}

    public function edit(): View
    {
        return view('admin.settings.general', [
            'site' => $this->settings->group('site'),
            'seo' => $this->settings->group('seo'),
            'media' => $this->settings->group('media'),
            'timezones' => DateTimeZone::listIdentifiers(),
            'livePages' => Page::query()->live()->orderBy('published_path')->get(['id', 'title', 'published_path']),
            'contentTypes' => $this->types->all(),
            'sidebars' => (array) $this->settings->get('content', 'sidebars', []),
            'sidebarBlocks' => GlobalBlock::query()->sidebarChoices()->get(['id', 'name', 'kind']),
        ]);
    }

    public function update(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'tagline' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:500'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:500'],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'homepage_page_id' => ['nullable', 'integer', Rule::exists('pages', 'id')->whereNotNull('published_revision_id')->whereNull('deleted_at')],
            'robots_txt' => ['nullable', 'string', 'max:5000'],
            'allow_svg' => ['boolean'],
            'sidebars' => ['array'],
            'sidebars.*.global_block_id' => ['nullable', 'integer', Rule::exists('global_blocks', 'id')->whereNotNull('published_revision_id')->whereNull('deleted_at')],
            'sidebars.*.position' => ['nullable', Rule::in(['left', 'right'])],
        ], [], ['sidebars.*.global_block_id' => 'sidebar']);

        $site = array_map(fn ($value) => $value ?? '', Arr::except($data, ['homepage_page_id', 'robots_txt', 'allow_svg', 'sidebars']));
        $site['homepage_page_id'] = isset($data['homepage_page_id']) ? (int) $data['homepage_page_id'] : null;
        $before = $this->settings->group('site');

        $this->settings->set('site', $site, $request->user());
        $this->settings->set('seo', ['robots_txt' => (string) ($data['robots_txt'] ?? '')], $request->user());
        $allowSvg = $request->boolean('allow_svg');
        if ($allowSvg !== (bool) $this->settings->get('media', 'allow_svg')) {
            $this->settings->set('media', ['allow_svg' => $allowSvg], $request->user());
            $logger->log($allowSvg ? 'settings.svg_enabled' : 'settings.svg_disabled', null, [], subjectLabel: 'Media settings');
        }

        $this->saveSidebars((array) ($data['sidebars'] ?? []), $request);

        $logger->log('settings.updated', null, [
            'group' => 'site',
            'changed' => array_keys(array_diff_assoc(array_map('strval', $site), array_map('strval', array_intersect_key($before, $site)))),
        ], subjectLabel: 'General settings');

        return back()->with('success', __('Settings saved.'));
    }

    /**
     * Default sidebar per content module (only registered modules are kept).
     *
     * @param  array<string, mixed>  $input
     */
    private function saveSidebars(array $input, Request $request): void
    {
        if (! $request->has('sidebars')) {
            return;
        }

        // The position is kept even without a default sidebar: items that choose their own
        // sidebar use it too.
        $sidebars = [];
        foreach (array_keys($this->types->all()) as $key) {
            $row = (array) ($input[$key] ?? []);
            $sidebars[$key] = [
                'global_block_id' => empty($row['global_block_id']) ? null : (int) $row['global_block_id'],
                'position' => ($row['position'] ?? 'right') === 'left' ? 'left' : 'right',
            ];
        }

        // Saving bumps the "settings" cache group, which detail payloads (with their sidebar) depend on.
        if ($sidebars != (array) $this->settings->get('content', 'sidebars', [])) {
            $this->settings->set('content', ['sidebars' => $sidebars], $request->user());
        }
    }
}
