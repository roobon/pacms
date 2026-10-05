<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\Api\MediaController as MediaApiController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DesignTokenController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PagePreviewController;
use App\Http\Controllers\Admin\PageRevisionController;
use App\Http\Controllers\Admin\PageWorkflowController;
use App\Http\Controllers\Admin\RedirectController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TermController;
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
// Password re-confirmation first ("sudo mode"): Fortify's 2FA endpoints require a recently
// confirmed password, and confirming inside a POST would redirect back as a GET.
Route::get('account/security', [AccountController::class, 'security'])
    ->middleware('password.confirm')
    ->name('account.security');

Route::resource('users', UserController::class)->except('show');

// Pages (authorization per action in PagePolicy / PublishingService)
Route::resource('pages', PageController::class)->except('show');
Route::post('pages/{page}/workflow', PageWorkflowController::class)->name('pages.workflow');
Route::get('pages/{page}/revisions', [PageRevisionController::class, 'index'])->name('pages.revisions');
Route::post('pages/{page}/revisions/{revision}/restore', [PageRevisionController::class, 'restore'])->name('pages.revisions.restore');
Route::get('pages/{page}/preview', PagePreviewController::class)->name('pages.preview');

// Media Library (permissions checked in the controller: media.view/upload/update/delete)
Route::get('media', [MediaController::class, 'index'])->name('media.index');
Route::post('media', [MediaController::class, 'store'])->name('media.store');
Route::get('media/{media}', [MediaController::class, 'edit'])->name('media.edit');
Route::put('media/{media}', [MediaController::class, 'update'])->name('media.update');
Route::post('media/{media}/replace', [MediaController::class, 'replace'])->name('media.replace');
Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
Route::get('media/{media}/download', [MediaController::class, 'download'])->name('media.download');

Route::middleware('can:taxonomies.manage')->group(function () {
    Route::get('taxonomies/{taxonomy}', [TermController::class, 'index'])->name('terms.index');
    Route::post('taxonomies/{taxonomy}', [TermController::class, 'store'])->name('terms.store');
    Route::put('taxonomies/{taxonomy}/{term}', [TermController::class, 'update'])->name('terms.update');
    Route::delete('taxonomies/{taxonomy}/{term}', [TermController::class, 'destroy'])->name('terms.destroy');
});

Route::middleware('can:redirects.manage')->group(function () {
    Route::get('redirects', [RedirectController::class, 'index'])->name('redirects.index');
    Route::post('redirects', [RedirectController::class, 'store'])->name('redirects.store');
    Route::delete('redirects/{redirect}', [RedirectController::class, 'destroy'])->name('redirects.destroy');
});

// JSON endpoints for admin React islands (same session + CSRF)
Route::prefix('api')->name('api.')->group(function () {
    Route::get('media', [MediaApiController::class, 'index'])->name('media.index');
    Route::post('media', [MediaApiController::class, 'store'])->name('media.store');
});

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
