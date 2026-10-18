<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BodyweightTrendController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ClientInvitationController;
use App\Http\Controllers\Api\DailyCheckInController;
use App\Http\Controllers\Api\ExerciseProgressController;
use App\Http\Controllers\Api\MesocycleController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\WorkoutController;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\Exercise;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::get('csrf-token', [AuthController::class, 'csrf']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:password-email');
    Route::post('reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:password-email');
    Route::post('invitations/{token}/accept', [ClientInvitationController::class, 'accept'])->where('token', '[A-Za-z0-9]{64}')->middleware(['auth.session', 'throttle:invitation-accept']);
    Route::middleware(['auth', 'auth.session', EnsureAccountIsActive::class])->group(function () {
        Route::get('user', [AuthController::class, 'user']);
        Route::get('clients', [ClientController::class, 'index']);
        Route::get('client-invitations', [ClientInvitationController::class, 'index']);
        Route::post('client-invitations', [ClientInvitationController::class, 'store'])->middleware('throttle:invitations');
        Route::post('client-invitations/{invitation}/resend', [ClientInvitationController::class, 'resend'])->whereNumber('invitation')->middleware('throttle:invitations');
        Route::delete('client-invitations/{invitation}', [ClientInvitationController::class, 'destroy'])->whereNumber('invitation');
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('exercises', fn () => response()->json(Exercise::orderBy('exercise_name')->paginate(50)));
        Route::prefix('clients/{client}')->whereNumber('client')->group(function () {
            Route::delete('coaching', [ClientController::class, 'destroy']);
            Route::get('exercise-progress', ExerciseProgressController::class);
            Route::get('bodyweight-trend', BodyweightTrendController::class);
            Route::get('mesocycles', [MesocycleController::class, 'index']);
            Route::post('mesocycles', [MesocycleController::class, 'store']);
            Route::put('mesocycles/{mesocycle}', [MesocycleController::class, 'update'])->whereNumber('mesocycle');
            Route::get('daily-check-ins', [DailyCheckInController::class, 'index']);
            Route::post('daily-check-ins', [DailyCheckInController::class, 'store']);
            Route::get('daily-check-ins/{checkIn}', [DailyCheckInController::class, 'show'])->whereNumber('checkIn');
            Route::patch('daily-check-ins/{checkIn}', [DailyCheckInController::class, 'update'])->whereNumber('checkIn');
            Route::delete('daily-check-ins/{checkIn}', [DailyCheckInController::class, 'destroy'])->whereNumber('checkIn');
            Route::get('workouts', [WorkoutController::class, 'index']);
            Route::post('workouts', [WorkoutController::class, 'store']);
            Route::get('workouts/{workout}', [WorkoutController::class, 'show'])->whereNumber('workout');
            Route::patch('workouts/{workout}', [WorkoutController::class, 'update'])->whereNumber('workout');
            Route::patch('workouts/{workout}/sets/{set}', [WorkoutController::class, 'logSet'])->whereNumber(['workout', 'set']);
            Route::delete('workouts/{workout}', [WorkoutController::class, 'destroy'])->whereNumber('workout');
        });
    });
});
