<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use App\Services\System\HealthCheck;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, HealthCheck $health): View
    {
        $user = $request->user();
        $canPages = $user->can('pages.view');

        return view('admin.dashboard', [
            'stats' => array_filter([
                'pages' => $canPages ? Page::query()->count() : null,
                'live' => $canPages ? Page::query()->live()->count() : null,
                'media' => $user->can('media.view') ? Media::query()->count() : null,
                'users' => $user->can('users.view') ? User::query()->count() : null,
            ], fn ($value) => $value !== null),
            // Work waiting for this user: reviews for approvers, returned drafts for authors.
            'awaitingReview' => $user->can('pages.approve')
                ? Page::query()->where('status', ContentStatus::InReview)->with('author:id,name')->latest('updated_at')->limit(8)->get()
                : null,
            'myDrafts' => $canPages
                ? Page::query()->where('author_id', $user->id)->where('status', ContentStatus::Draft)->latest('updated_at')->limit(5)->get()
                : null,
            'scheduled' => $canPages
                ? Page::query()->where('status', ContentStatus::Approved)->whereNotNull('publish_at')->orderBy('publish_at')->limit(5)->get()
                : null,
            'health' => $user->can('settings.manage') ? $health->run() : null,
            'recentActivity' => $user->can('activity_log.view')
                ? ActivityLog::query()->with('user:id,name')->latest('id')->limit(8)->get()
                : null,
        ]);
    }
}
