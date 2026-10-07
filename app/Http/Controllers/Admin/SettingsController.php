<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
    public function __construct(private readonly SettingsService $settings) {}

    public function edit(): View
    {
        return view('admin.settings.general', [
            'site' => $this->settings->group('site'),
            'seo' => $this->settings->group('seo'),
            'media' => $this->settings->group('media'),
            'timezones' => DateTimeZone::listIdentifiers(),
            'livePages' => Page::query()->live()->orderBy('published_path')->get(['id', 'title', 'published_path']),
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
        ]);

        $site = array_map(fn ($value) => $value ?? '', Arr::except($data, ['homepage_page_id', 'robots_txt', 'allow_svg']));
        $site['homepage_page_id'] = isset($data['homepage_page_id']) ? (int) $data['homepage_page_id'] : null;
        $before = $this->settings->group('site');

        $this->settings->set('site', $site, $request->user());
        $this->settings->set('seo', ['robots_txt' => (string) ($data['robots_txt'] ?? '')], $request->user());
        $allowSvg = $request->boolean('allow_svg');
        if ($allowSvg !== (bool) $this->settings->get('media', 'allow_svg')) {
            $this->settings->set('media', ['allow_svg' => $allowSvg], $request->user());
            $logger->log($allowSvg ? 'settings.svg_enabled' : 'settings.svg_disabled', null, [], subjectLabel: 'Media settings');
        }

        $logger->log('settings.updated', null, [
            'group' => 'site',
            'changed' => array_keys(array_diff_assoc(array_map('strval', $site), array_map('strval', array_intersect_key($before, $site)))),
        ], subjectLabel: 'General settings');

        return back()->with('success', __('Settings saved.'));
    }
}
