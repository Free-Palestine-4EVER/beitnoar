<?php

use Illuminate\Support\Facades\Route;

// Explicit routes for PWA assets to prevent catch-all HTML fallback
Route::get('/sw.js', function () {
    $path = public_path('sw.js');
    if (! file_exists($path)) {
        $path = public_path('build/sw.js');
    }
    if (file_exists($path)) {
        return response()->file($path, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
    abort(404);
});

Route::get('/manifest.webmanifest', function () {
    $path = public_path('manifest.webmanifest');
    if (! file_exists($path)) {
        $path = public_path('build/manifest.webmanifest');
    }
    if (file_exists($path)) {
        return response()->file($path, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }
    abort(404);
});

// SPA catch-all route for public menu views
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '.*');
