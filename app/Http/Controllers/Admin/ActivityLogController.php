<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:64'],
            'user' => ['nullable', 'integer'],
        ]);

        $logs = ActivityLog::query()
            ->with('user:id,name,email')
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', 'like', $action.'%'))
            ->when($filters['user'] ?? null, fn ($query, $user) => $query->where('user_id', $user))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.activity.index', [
            'logs' => $logs,
            'filters' => $filters,
            'actionGroups' => ['auth', 'security', 'user', 'settings', 'design'],
        ]);
    }
}
