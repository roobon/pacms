<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\System\HealthCheck;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, HealthCheck $health): View
    {
        $user = $request->user();

        return view('admin.dashboard', [
            'userCount' => $user->can('users.view') ? User::query()->count() : null,
            'health' => $user->can('settings.manage') ? $health->run() : null,
            'recentActivity' => $user->can('activity_log.view')
                ? ActivityLog::query()->with('user:id,name')->latest('id')->limit(8)->get()
                : null,
        ]);
    }
}
