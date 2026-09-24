<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DesignTokenController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin routes — prefix /admin, name "admin."
|--------------------------------------------------------------------------
| Middleware (bootstrap/app.php): web, auth, verified, admin (admin.access),
| two-factor.required, throttle:admin. Each action authorizes itself as well.
*/

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('account/profile', [AccountController::class, 'profile'])->name('account.profile');
Route::get('account/security', [AccountController::class, 'security'])->name('account.security');

Route::resource('users', UserController::class)->except('show');

Route::middleware('can:users.manage_roles')->group(function () {
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
});

Route::get('activity', ActivityLogController::class)->middleware('can:activity_log.view')->name('activity.index');

Route::middleware('can:settings.manage')->group(function () {
    Route::get('settings/general', [SettingsController::class, 'edit'])->name('settings.general');
    Route::put('settings/general', [SettingsController::class, 'update'])->name('settings.general.update');
});

Route::middleware('can:design_tokens.manage')->group(function () {
    Route::get('design/tokens', [DesignTokenController::class, 'edit'])->name('design.tokens');
    Route::put('design/tokens', [DesignTokenController::class, 'update'])->name('design.tokens.update');
});

Route::get('design/preview', [DesignTokenController::class, 'preview'])->name('design.preview');
