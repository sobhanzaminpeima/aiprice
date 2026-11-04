<?php

use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', \App\Http\Controllers\SitemapController::class);

// SPA fallback. API paths are excluded so an unknown /api/... URL returns a
// JSON 404 instead of trying to serve the SPA shell.
Route::get('/{any}', function () {
    $spa = public_path('spa.html');
    abort_unless(is_file($spa), 404);
    // The shell names the hashed bundles of the current release, so browsers
    // must revalidate it; otherwise visitors keep seeing the previous deploy.
    return response()->file($spa, [
        'Content-Type' => 'text/html; charset=utf-8',
        'Cache-Control' => 'no-cache, must-revalidate',
    ]);
})->where('any', '^(?!api/|up$|sitemap\.xml$).*$');
