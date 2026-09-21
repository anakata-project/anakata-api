<?php

declare(strict_types=1);

use App\Http\Controllers\Engine\CharterEnquiryController;
use App\Http\Controllers\Engine\CheckoutController;
use App\Http\Controllers\Engine\DepartureCabinController;
use App\Http\Controllers\Engine\FeedController;
use App\Http\Controllers\Engine\PromoCheckController;
use App\Http\Controllers\Engine\QuoteController;
use App\Http\Controllers\Engine\WaitlistController;
use Illuminate\Support\Facades\Route;

Route::get('feed', FeedController::class);
Route::get('departures/{departure}/cabins', DepartureCabinController::class);
Route::post('promo/check', PromoCheckController::class)->middleware('throttle:engine-promo');
Route::post('quote', QuoteController::class);

Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:engine-checkout');
Route::post('checkout/{token}/extend', [CheckoutController::class, 'extend'])->middleware('throttle:engine-checkout');
Route::delete('checkout/{token}', [CheckoutController::class, 'destroy']);
Route::post('checkout/{token}/submit', [CheckoutController::class, 'submit'])->middleware('throttle:engine-checkout');

Route::post('waitlist', WaitlistController::class)->middleware('throttle:engine-waitlist');
Route::post('charter-enquiries', CharterEnquiryController::class)->middleware('throttle:engine-charter');
