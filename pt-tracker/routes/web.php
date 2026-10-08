<?php

use App\Http\Middleware\EnsureAccountIsActive;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');
Route::inertia('/login', 'Login')->name('login');
Route::inertia('/dashboard', 'Dashboard')
    ->middleware(['auth', EnsureAccountIsActive::class])
    ->name('dashboard');

require __DIR__.'/tracker.php';
