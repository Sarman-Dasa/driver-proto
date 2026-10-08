<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Named route for Laravel email verification notifications to redirect to frontend SPA
Route::get('/verify-email/{id}/{hash}', function (Request $request, string $id, string $hash) {
    $queryString = $request->getQueryString() ? '&'.$request->getQueryString() : '';

    return redirect()->to("/verify-email?id={$id}&hash={$hash}{$queryString}");
})->name('verification.verify');

// Named route for email change verification notifications
Route::get('/profile/verify-email-change/{id}/{hash}', function (Request $request, string $id, string $hash) {
    $queryString = $request->getQueryString() ? '&'.$request->getQueryString() : '';

    return redirect()->to("/profile?verify_email_change=1&id={$id}&hash={$hash}{$queryString}");
})->name('profile.verify-email-change');

// Named route for password reset notification link to redirect to frontend SPA
Route::get('/reset-password/{token}', function (Request $request, string $token) {
    $queryString = $request->getQueryString() ? '&'.$request->getQueryString() : '';

    return redirect()->to("/reset-password?token={$token}{$queryString}");
})->name('password.reset');

// Catch-all route to serve the Vue SPA
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api).*$');
