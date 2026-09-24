<?php

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

Route::fallback(SpaController::class)->name('spa');
