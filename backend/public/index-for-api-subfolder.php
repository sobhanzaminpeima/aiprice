<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// This variant lives at public_html/api/index.php while the actual Laravel
// app root lives at ../../aiprice-app/ (outside the web root, one level up
// from public_html). Only the path depth differs from Laravel's stock
// public/index.php — the app boot sequence is unchanged.

if (file_exists($maintenance = __DIR__.'/../../aiprice-app/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../../aiprice-app/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../../aiprice-app/bootstrap/app.php';

$app->handleRequest(Request::capture());
