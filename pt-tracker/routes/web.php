<?php

use App\Http\Controllers\Api\ClientInvitationController;
use App\Http\Middleware\EnsureAccountIsActive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'Welcome')->name('home');
Route::get('/login', function (Request $request) {
    $next = (string) $request->query('next', '');

    return Inertia::render('Login', ['next' => preg_match('#^/join/[A-Za-z0-9]{64}$#', $next) ? $next : '/dashboard', 'passwordReset' => $request->boolean('reset')]);
})->name('login');
Route::inertia('/forgot-password', 'ForgotPassword')->name('password.request');
Route::get('/reset-password/{token}', fn (Request $request, string $token) => Inertia::render('ResetPassword', ['token' => $token, 'email' => (string) $request->query('email', '')]))->name('password.reset');
Route::get('/join/{token}', [ClientInvitationController::class, 'show'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:invitation-accept')->name('invitation.show');
Route::inertia('/dashboard', 'Dashboard')
    ->middleware(['auth', 'auth.session', EnsureAccountIsActive::class])
    ->name('dashboard');

require __DIR__.'/tracker.php';
