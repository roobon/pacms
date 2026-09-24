<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Design\DesignTokenService;
use App\Cms\Design\TokenCatalog;
use App\Http\Controllers\Controller;
use App\Services\ActivityLog\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DesignTokenController extends Controller
{
    public function __construct(private readonly DesignTokenService $tokens) {}

    public function edit(): View
    {
        return view('admin.design.tokens', [
            'definitions' => collect(TokenCatalog::definitions())->reject(fn ($d) => $d['type'] === 'raw')->groupBy('group', true),
            'values' => $this->tokens->values(),
            'fonts' => TokenCatalog::FONT_STACKS,
            'warnings' => $this->tokens->contrastWarnings(),
        ]);
    }

    public function update(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $request->validate(['tokens' => ['required', 'array']]);

        $before = $this->tokens->values();
        $warnings = $this->tokens->save((array) $request->input('tokens'), $request->user());
        $after = $this->tokens->values();

        $logger->log('design.tokens_updated', null, [
            'changed' => array_keys(array_diff_assoc($after, $before)),
        ], subjectLabel: 'Design tokens');

        return redirect()->route('admin.design.tokens')
            ->with('success', __('Design tokens saved and published.'))
            ->with('token_warnings', $warnings);
    }

    /**
     * Standalone page rendering public UI components with the current tokens
     * (UI-DESIGN-SYSTEM.md §9). Embedded in the token editor through an iframe.
     */
    public function preview(): View
    {
        return view('admin.design.preview', [
            'tokenStylesheet' => $this->tokens->stylesheetUrl(),
        ]);
    }
}
