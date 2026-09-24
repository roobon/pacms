<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use App\Services\ActivityLog\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RedirectController extends Controller
{
    public function index(Request $request): View
    {
        $q = (string) ($request->validate(['q' => ['nullable', 'string', 'max:200']])['q'] ?? '');

        return view('admin.redirects.index', [
            'redirects' => Redirect::query()
                ->when($q !== '', fn ($query) => $query->where(
                    fn ($query) => $query->where('source_path', 'like', "%{$q}%")->orWhere('target_path', 'like', "%{$q}%")
                ))
                ->orderBy('source_path')
                ->paginate(50)
                ->withQueryString(),
            'q' => $q,
        ]);
    }

    public function store(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $request->merge(['source_path' => Redirect::normalizePath((string) $request->input('source_path'))]);

        $data = $request->validate([
            'source_path' => ['required', 'string', 'max:512', 'not_in:/', Rule::unique('redirects', 'source_path')],
            'target_path' => ['required', 'string', 'max:1024', 'regex:#^(/|https?://)#i', 'different:source_path'],
            'status_code' => ['required', Rule::in(Redirect::STATUS_CODES)],
        ], [
            'target_path.regex' => __('The target must start with / (a page on this site) or http(s)://.'),
        ]);

        $redirect = Redirect::query()->create($data + ['is_auto' => false, 'created_by' => $request->user()->getKey()]);
        $logger->log('redirect.created', null, $data, subjectLabel: $redirect->source_path);

        return back()->with('success', __('Redirect from :from added.', ['from' => $redirect->source_path]));
    }

    public function destroy(Redirect $redirect, ActivityLogger $logger): RedirectResponse
    {
        $redirect->delete();
        $logger->log('redirect.deleted', null, $redirect->only(['source_path', 'target_path']), subjectLabel: $redirect->source_path);

        return back()->with('success', __('Redirect removed.'));
    }
}
