<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Named route for Laravel email verification notifications to redirect to frontend SPA
Route::get('/verify-email/{id}/{hash}', function (Request $request, string $id, string $hash) {
    return redirect()->to("/verify-email?id={$id}&hash={$hash}");
})->name('verification.verify');

// Catch-all route to serve the Vue SPA
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api).*$');
