<?php

use App\Http\Controllers\Public\CoverageArchiveController;
use App\Http\Controllers\Public\JsonFeedController;
use App\Http\Controllers\Public\PreviewController;
use App\Http\Controllers\Public\SeoFilesController;
use App\Http\Controllers\Public\SpaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
| Authentication pages are registered by Fortify under /auth.
| Admin routes live in routes/admin.php (registered in bootstrap/app.php).
| Everything else is served by the public React SPA through the server-assisted
| shell, which sets the correct HTTP status and SEO metadata per URL.
*/

Route::get('robots.txt', [SeoFilesController::class, 'robots'])->name('robots');
// JSON Feed 1.1 (D-15): sharing content with other sites.
Route::middleware('cache.headers:public;max_age=600;etag')->group(function () {
    Route::get('feed.json', [JsonFeedController::class, 'combined'])->name('feed');
    Route::get('feed/{module}.json', [JsonFeedController::class, 'module'])->where('module', '[a-z0-9-]+')->name('feed.module');
});
Route::get('sitemap.xml', [SeoFilesController::class, 'index'])->name('sitemap');
Route::get('sitemaps/pages.xml', [SeoFilesController::class, 'pages'])->name('sitemap.pages');
Route::get('sitemaps/{module}.xml', [SeoFilesController::class, 'module'])->where('module', '[a-z0-9-]+')->name('sitemap.module');

// Archived copies of press coverage kept as private files (shown only with confirmed rights).
Route::get('media-coverage/{slug}/archive/{kind}', CoverageArchiveController::class)
    ->where(['slug' => '[a-z0-9-]+', 'kind' => 'pdf|video'])
    ->middleware('throttle:60,1')
    ->name('media-coverage.archive');

// Secure preview: valid signature AND a signed-in user who may view the page.
Route::get('preview/pages/{page}', [PreviewController::class, 'page'])
    ->middleware(['signed:relative', 'auth'])
    ->name('preview.page');
Route::get('preview/{type}/{id}', [PreviewController::class, 'content'])
    ->where(['type' => '[a-z0-9-]+', 'id' => '[0-9]+'])
    ->middleware(['signed:relative', 'auth'])
    ->name('preview.content');

// Builder live preview frame: the SPA renders trees the builder posts to it (same origin).
Route::get('__builder-preview', [PreviewController::class, 'builder'])
    ->middleware(['auth', 'can:pages.view'])
    ->name('preview.builder');

Route::fallback(SpaController::class)->name('spa');
