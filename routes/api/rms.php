<?php

declare(strict_types=1);

use App\Http\Controllers\Rms\BusinessRulesController;
use App\Http\Controllers\Rms\CalendarController;
use App\Http\Controllers\Rms\DepartureController;
use App\Http\Controllers\Rms\EngineSettingsController;
use App\Http\Controllers\Rms\ItineraryController;
use App\Http\Controllers\Rms\PermissionController;
use App\Http\Controllers\Rms\RatesController;
use App\Http\Controllers\Rms\RoleController;
use App\Http\Controllers\Rms\UserController;
use App\Http\Controllers\Rms\YachtController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['ok' => true]));

Route::get('permissions', [PermissionController::class, 'index']);

Route::get('roles', [RoleController::class, 'index']);
Route::post('roles', [RoleController::class, 'store']);
Route::patch('roles/{role}', [RoleController::class, 'update']);
Route::delete('roles/{role}', [RoleController::class, 'destroy']);
Route::get('roles/{role}/history', [RoleController::class, 'history']);

Route::get('users', [UserController::class, 'index']);
Route::post('users', [UserController::class, 'store']);
Route::patch('users/{user}', [UserController::class, 'update']);
Route::post('users/{user}/disable', [UserController::class, 'disable']);
Route::post('users/{user}/enable', [UserController::class, 'enable']);
Route::post('users/{user}/resend-invitation', [UserController::class, 'resendInvitation']);
Route::get('users/{user}/history', [UserController::class, 'history']);

Route::get('rates', [RatesController::class, 'current']);
Route::post('rates/validate', [RatesController::class, 'validateDocument']);
Route::post('rates/price-check', [RatesController::class, 'priceCheck']);
Route::post('rates/versions', [RatesController::class, 'store']);
Route::get('rates/versions', [RatesController::class, 'index']);
Route::get('rates/versions/{version}', [RatesController::class, 'show'])
    ->whereNumber('version');

Route::get('engine-settings', [EngineSettingsController::class, 'current']);
Route::post('engine-settings/validate', [EngineSettingsController::class, 'validateDocument']);
Route::post('engine-settings/versions', [EngineSettingsController::class, 'store']);
Route::get('engine-settings/versions', [EngineSettingsController::class, 'index']);
Route::get('engine-settings/versions/{version}', [EngineSettingsController::class, 'show'])
    ->whereNumber('version');

Route::get('business-rules', [BusinessRulesController::class, 'current']);
Route::post('business-rules/validate', [BusinessRulesController::class, 'validateDocument']);
Route::post('business-rules/versions', [BusinessRulesController::class, 'store']);
Route::get('business-rules/versions', [BusinessRulesController::class, 'index']);
Route::get('business-rules/versions/{version}', [BusinessRulesController::class, 'show'])
    ->whereNumber('version');

Route::get('yachts', [YachtController::class, 'index']);

Route::get('itineraries', [ItineraryController::class, 'index']);
Route::get('itineraries/defaults', [ItineraryController::class, 'defaults']);
Route::post('itineraries', [ItineraryController::class, 'store']);
Route::get('itineraries/{itinerary}', [ItineraryController::class, 'show']);
Route::patch('itineraries/{itinerary}', [ItineraryController::class, 'update']);
Route::post('itineraries/{itinerary}/image', [ItineraryController::class, 'image']);
Route::delete('itineraries/{itinerary}', [ItineraryController::class, 'destroy']);
Route::get('itineraries/{itinerary}/history', [ItineraryController::class, 'history']);

Route::get('calendar', CalendarController::class);

Route::get('departures', [DepartureController::class, 'index']);
Route::post('departures', [DepartureController::class, 'store']);
Route::post('departures/generate-season', [DepartureController::class, 'generate']);
Route::get('departures/{departure}/layout', [DepartureController::class, 'layout'])->whereNumber('departure');
Route::get('departures/{departure}', [DepartureController::class, 'show'])->whereNumber('departure');
Route::patch('departures/{departure}', [DepartureController::class, 'update'])->whereNumber('departure');
Route::delete('departures/{departure}', [DepartureController::class, 'destroy'])->whereNumber('departure');
Route::get('departures/{departure}/history', [DepartureController::class, 'history'])->whereNumber('departure');
