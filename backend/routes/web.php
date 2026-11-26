<?php

use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', \App\Http\Controllers\SitemapController::class);

// SPA fallback. API paths are excluded so an unknown /api/... URL returns a
// JSON 404 instead of trying to serve the SPA shell.
Route::get('/{any}', function (\Illuminate\Http\Request $request) {
    $spa = public_path('spa.html');
    abort_unless(is_file($spa), 404);
    // Real title, description, structured data and readable content per URL
    // (see SeoShell); Vue takes over the page once it loads.
    $origin = rtrim((string) config('app.frontend_url', config('app.url')), '/');
    $page = (new \App\Services\Seo\SeoShell($origin))->render((string) file_get_contents($spa), $request->path() === '/' ? '' : $request->path());
    // The shell names the hashed bundles of the current release, so browsers
    // must revalidate it; otherwise visitors keep seeing the previous deploy.
    return response($page['html'], $page['status'], [
        'Content-Type' => 'text/html; charset=utf-8',
        'Cache-Control' => 'no-cache, must-revalidate',
    ]);
})->where('any', '^(?!api/|up$|sitemap\.xml$).*$');
