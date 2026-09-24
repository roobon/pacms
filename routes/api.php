<?php

use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\SiteController;
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

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', MeController::class)->name('me');
    });
});

Route::fallback(fn () => abort(404));
