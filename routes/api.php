<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\ResolveController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SiteController;
use App\Http\Controllers\Api\V1\TestimonialController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API v1 (API-ARCHITECTURE.md §2–3)
|--------------------------------------------------------------------------
| Only published, public data. Registered-user endpoints use Sanctum's
| same-domain cookie session (auth:sanctum).
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('site', SiteController::class)->name('site');
    Route::post('contact', ContactController::class)->middleware('throttle:20,1')->name('contact');
    Route::get('menus/{slug}', MenuController::class)->where('slug', '[a-z0-9\-]+')->middleware('cache.headers:public;max_age=300;etag')->name('menus.show');

    Route::middleware('cache.headers:public;max_age=60;etag')->group(function () {
        Route::get('resolve', ResolveController::class)->name('resolve');
        Route::get('pages/{path}', PageController::class)->where('path', '[a-z0-9/\-]+')->name('pages.show');
        Route::get('testimonials', [TestimonialController::class, 'index'])->name('testimonials.index');
        Route::get('testimonials/options', [TestimonialController::class, 'options'])->name('testimonials.options');
    });

    Route::get('search', SearchController::class)->middleware(['throttle:search', 'cache.headers:public;max_age=60'])->name('search');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', MeController::class)->name('me');
        Route::delete('me', [AccountController::class, 'destroy'])->middleware('throttle:5,1')->name('me.destroy');
        Route::get('me/testimonials', [TestimonialController::class, 'mine'])->name('me.testimonials');
        // Verified e-mail address required; 3 a day per account and 10 per IP (counted in the controller).
        Route::post('testimonials', [TestimonialController::class, 'store'])
            ->middleware('verified')
            ->name('testimonials.store');
    });
});

Route::fallback(fn () => abort(404));
