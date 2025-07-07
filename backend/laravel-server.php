<?php

/**
 * Laravel Server Entry Point
 * Alternative to using 'php artisan serve'
 */

define('LARAVEL_START', microtime(true));

// Register the Composer auto loader
require __DIR__.'/vendor/autoload.php';

// Bootstrap the Laravel application
$app = require_once __DIR__.'/bootstrap/app.php';

// Create kernel instance
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Handle the request
$request = Illuminate\Http\Request::capture();
$response = $kernel->handle($request);

// Send the response
$response->send();

// Terminate the application
$kernel->terminate($request, $response);
