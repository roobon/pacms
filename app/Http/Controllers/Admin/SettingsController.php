<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLog\ActivityLogger;
use App\Services\Settings\SettingsService;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function edit(): View
    {
        return view('admin.settings.general', [
            'site' => $this->settings->group('site'),
            'timezones' => DateTimeZone::listIdentifiers(),
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
        ]);

        $data = array_map(fn ($value) => $value ?? '', $data);
        $before = $this->settings->group('site');

        $this->settings->set('site', $data, $request->user());

        $logger->log('settings.updated', null, [
            'group' => 'site',
            'changed' => array_keys(array_diff_assoc($data, array_intersect_key($before, $data))),
        ], subjectLabel: 'General settings');

        return back()->with('success', __('Settings saved.'));
    }
}
