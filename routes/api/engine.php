<?php

declare(strict_types=1);

use App\Http\Controllers\Engine\DepartureCabinController;
use App\Http\Controllers\Engine\FeedController;
use App\Http\Controllers\Engine\PromoCheckController;
use App\Http\Controllers\Engine\QuoteController;
use Illuminate\Support\Facades\Route;

Route::get('feed', FeedController::class);
Route::get('departures/{departure}/cabins', DepartureCabinController::class);
Route::post('promo/check', PromoCheckController::class)->middleware('throttle:engine-promo');
Route::post('quote', QuoteController::class);
