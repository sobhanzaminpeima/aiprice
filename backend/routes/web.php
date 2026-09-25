<?php

use Illuminate\Support\Facades\Route;

// SPA fallback. API paths are excluded so an unknown /api/... URL returns a
// JSON 404 instead of trying to serve the SPA shell.
Route::get('/{any}', function () {
    $spa = public_path('spa.html');
    abort_unless(is_file($spa), 404);
    return response()->file($spa);
})->where('any', '^(?!api/|up$).*$');
