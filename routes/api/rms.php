<?php

declare(strict_types=1);

use App\Http\Controllers\Rms\EngineSettingsController;
use App\Http\Controllers\Rms\PermissionController;
use App\Http\Controllers\Rms\RatesController;
use App\Http\Controllers\Rms\RoleController;
use App\Http\Controllers\Rms\UserController;
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
