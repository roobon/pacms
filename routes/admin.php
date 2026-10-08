<?php

use App\Cms\Content\ContentTypeRegistry;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\Api\BlockBuilderController;
use App\Http\Controllers\Admin\Api\MediaController as MediaApiController;
use App\Http\Controllers\Admin\Api\ReusableBlockController;
use App\Http\Controllers\Admin\BlockTemplateController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\CustomBlockTypeController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DesignTokenController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\GlobalBlockController;
use App\Http\Controllers\Admin\ImportController;
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
Route::post('media/bulk', [MediaController::class, 'bulk'])->name('media.bulk');
Route::post('media', [MediaController::class, 'store'])->name('media.store');
Route::get('media/{media}', [MediaController::class, 'edit'])->name('media.edit');
Route::put('media/{media}', [MediaController::class, 'update'])->name('media.update');
Route::post('media/{media}/replace', [MediaController::class, 'replace'])->name('media.replace');
Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
Route::get('media/{media}/download', [MediaController::class, 'download'])->name('media.download');
Route::get('media/{media}/file', [MediaController::class, 'file'])->name('media.file');
Route::post('media/{media}/visibility', [MediaController::class, 'visibility'])->name('media.visibility');

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

// Content modules (Phase 8): the same screens for every registered type (news, events…).
foreach (app(ContentTypeRegistry::class)->all() as $contentType) {
    $key = $contentType->key();
    Route::prefix($contentType->routePrefix())
        ->name($key.'.')
        ->controller(ContentController::class)
        ->whereNumber(['item', 'revision'])
        ->group(function () use ($key) {
            Route::get('/', 'index')->name('index')->defaults('type', $key);
            Route::get('create', 'create')->name('create')->defaults('type', $key);
            Route::post('/', 'store')->name('store')->defaults('type', $key);
            Route::get('{item}/edit', 'edit')->name('edit')->defaults('type', $key);
            Route::put('{item}', 'update')->name('update')->defaults('type', $key);
            Route::delete('{item}', 'destroy')->name('destroy')->defaults('type', $key);
            Route::post('{item}/workflow', 'workflow')->name('workflow')->defaults('type', $key);
            Route::get('{item}/revisions', 'revisions')->name('revisions')->defaults('type', $key);
            Route::post('{item}/revisions/{revision}/restore', 'restore')->name('revisions.restore')->defaults('type', $key);
            Route::get('{item}/preview', 'preview')->name('preview')->defaults('type', $key);
        });
}

// Reusable blocks (Phase 5): global blocks, templates, custom block types
Route::middleware('can:global_blocks.manage')->group(function () {
    Route::resource('global-blocks', GlobalBlockController::class)->except('show');
    Route::post('global-blocks/{global_block}/publish', [GlobalBlockController::class, 'publish'])->name('global-blocks.publish');
});
Route::resource('block-templates', BlockTemplateController::class)->except('show')->middleware('can:templates.manage');
Route::middleware('can:block_types.manage')->group(function () {
    Route::resource('block-types', CustomBlockTypeController::class)->except('show');
    Route::post('block-types/{block_type}/publish', [CustomBlockTypeController::class, 'publish'])->name('block-types.publish');
    Route::post('block-types/{block_type}/toggle', [CustomBlockTypeController::class, 'toggle'])->name('block-types.toggle');
});

// JSON import / export (Phase 6)
Route::middleware('can:import.run')->group(function () {
    Route::get('import', [ImportController::class, 'index'])->name('import.index');
    Route::post('import', [ImportController::class, 'store'])->middleware('throttle:20,1')->name('import.store');
    Route::get('import/schema.json', [ImportController::class, 'schema'])->name('import.schema');
    Route::get('import/{import}', [ImportController::class, 'show'])->name('import.show');
    Route::post('import/{import}/confirm', [ImportController::class, 'confirm'])->name('import.confirm');
    Route::get('import/{import}/report.json', [ImportController::class, 'report'])->name('import.report');
});
Route::middleware('can:export.run')->group(function () {
    Route::get('export/pages/{page}', [ExportController::class, 'page'])->name('export.page');
    Route::get('export/templates/{block_template}', [ExportController::class, 'template'])->name('export.template');
    Route::post('api/export/blocks', [ExportController::class, 'blocks'])->name('api.export.blocks');
});

// JSON endpoints for admin React islands (same session + CSRF)
Route::prefix('api')->name('api.')->group(function () {
    Route::get('blocks/definitions', [BlockBuilderController::class, 'definitions'])->name('blocks.definitions');
    Route::post('blocks/resolve', [BlockBuilderController::class, 'resolve'])->name('blocks.resolve');
    Route::get('global-blocks', [ReusableBlockController::class, 'globals'])->name('globals.index');
    Route::post('global-blocks', [ReusableBlockController::class, 'storeGlobal'])->name('globals.store');
    Route::post('global-blocks/{global}/detach', [ReusableBlockController::class, 'detach'])->name('globals.detach');
    Route::get('templates', [ReusableBlockController::class, 'templates'])->name('templates.index');
    Route::get('templates/{template}', [ReusableBlockController::class, 'template'])->name('templates.show');
    Route::post('templates', [ReusableBlockController::class, 'storeTemplate'])->name('templates.store');
    Route::post('autosave', [ReusableBlockController::class, 'autosave'])->middleware('throttle:30,1')->name('autosave');
    Route::get('link-targets', [BlockBuilderController::class, 'linkTargets'])->name('link-targets');
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
