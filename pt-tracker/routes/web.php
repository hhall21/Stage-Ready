<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Api\ClientInvitationController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CoachRegistrationController;
use App\Http\Controllers\LegalDocumentController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureAdministrator;
use App\Http\Middleware\EnsurePaidCoaching;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'Welcome')->name('home');
Route::get('/privacy', [LegalDocumentController::class, 'privacy'])->name('privacy');
Route::get('/terms', [LegalDocumentController::class, 'terms'])->name('terms');
Route::inertia('/payment', 'Payment')->name('payment');
Route::inertia('/register', 'Register')->name('register');
Route::post('/api/register', [CoachRegistrationController::class, 'store'])->middleware('throttle:registration');
Route::post('/stripe/webhook', StripeWebhookController::class);
Route::middleware(['auth', 'auth.session', EnsureAccountIsActive::class])->group(function () {
    Route::get('/api/billing', [BillingController::class, 'index']);
    Route::get('/subscription/cancel', [BillingController::class, 'cancellationPage'])->name('billing.cancel');
    Route::post('/api/billing/cancel', [BillingController::class, 'cancel'])->middleware('throttle:billing');
    Route::post('/api/billing/checkout', [BillingController::class, 'checkout'])->middleware('throttle:billing');
    Route::post('/api/billing/confirm', [BillingController::class, 'confirm'])->middleware('throttle:billing');
    Route::post('/api/billing/portal', [BillingController::class, 'portal'])->middleware('throttle:billing');
    Route::inertia('/email/verify', 'VerifyEmail')->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect('/payment');
    })->middleware(['signed', 'throttle:verification'])->name('verification.verify');
    Route::post('/api/email/verification-notification', function (Request $request) {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'A verification link has been requested. Check your inbox.']);
    })->middleware('throttle:verification');
});
Route::middleware(['auth', 'auth.session', EnsureAccountIsActive::class, EnsureAdministrator::class])->group(function () {
    Route::get('/admin', [AdminController::class, 'page'])->name('admin');
    Route::prefix('api/admin')->group(function () {
        Route::get('users', [AdminController::class, 'index']);
        Route::post('coaches', [AdminController::class, 'storeCoach'])->middleware('throttle:invitations');
        Route::patch('users/{user}/access', [AdminController::class, 'updateAccess'])->whereNumber('user');
        Route::post('coaches/{coach}/setup-link', [AdminController::class, 'resendSetupLink'])->whereNumber('coach')->middleware('throttle:invitations');
    });
});
Route::get('/login', function (Request $request) {
    $next = $request->query('next', '');

    return Inertia::render('Login', ['next' => is_string($next) && preg_match('#^/join/[A-Za-z0-9]{64}$#', $next) ? $next : '/dashboard', 'passwordReset' => $request->boolean('reset')]);
})->name('login');
Route::inertia('/forgot-password', 'ForgotPassword')->name('password.request');
Route::get('/reset-password/{token}', function (Request $request, string $token) {
    $email = $request->query('email', '');

    return Inertia::render('ResetPassword', ['token' => $token, 'email' => is_string($email) ? $email : '']);
})->name('password.reset');
Route::get('/join/{token}', [ClientInvitationController::class, 'show'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:invitation-accept')->name('invitation.show');
Route::inertia('/dashboard', 'Dashboard')
    ->middleware(['auth', 'auth.session', EnsureAccountIsActive::class, EnsurePaidCoaching::class])
    ->name('dashboard');

require __DIR__.'/tracker.php';
