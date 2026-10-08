<?php

use App\Http\Controllers\Api\ContactEnquiryController;
use Illuminate\Support\Facades\Route;

Route::post('/contact-enquiries', [ContactEnquiryController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('api.contact-enquiries.store');
