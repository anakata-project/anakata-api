<?php

declare(strict_types=1);

use App\Http\Controllers\Rms\PermissionController;
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
