<?php

// In production the SPA and API share one origin, so CORS rarely matters.
// Extra origins (e.g. a staging frontend) go in CORS_ALLOWED_ORIGINS, comma-separated.
$origins = array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))));
$origins[] = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_unique($origins)),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    // The SPA reads these: file names for CSV/invoice downloads, wait time on 429.
    'exposed_headers' => ['Content-Disposition', 'Retry-After'],
    'max_age' => 600,
    'supports_credentials' => true,
];
