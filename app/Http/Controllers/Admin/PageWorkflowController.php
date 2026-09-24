<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WorkflowAction;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Publishing\PublishingService;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Workflow buttons in the publish box. PublishingService enforces permissions and states.
 */
class PageWorkflowController extends Controller
{
    public function __invoke(Request $request, Page $page, PublishingService $publishing): RedirectResponse
    {
        Gate::authorize('view', $page);

        $data = $request->validate([
            'action' => ['required', Rule::enum(WorkflowAction::class)],
            'note' => ['nullable', 'string', 'max:1000'],
            'publish_at' => ['nullable', 'date'],
        ]);

        $action = WorkflowAction::from($data['action']);
        $timezone = (string) app(SettingsService::class)->get('site', 'timezone', 'UTC');

        $publishing->transition($page, $action, $request->user(), [
            'note' => $data['note'] ?? null,
            // The form sends local site time; store UTC.
            'publish_at' => isset($data['publish_at']) ? CarbonImmutable::parse($data['publish_at'], $timezone)->utc() : null,
        ]);

        $message = match ($action) {
            WorkflowAction::Publish => __('Published. The page is live at :url', ['url' => url((string) $page->publicUrl())]),
            WorkflowAction::Schedule => __('Scheduled for :time.', ['time' => $page->publish_at?->timezone($timezone)->format('j M Y, H:i')]),
            default => __(':action: done.', ['action' => $action->label()]),
        };

        return redirect()->route('admin.pages.edit', $page)->with('success', $message);
    }
}
