<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-email');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth-email');
Route::post('/accept-invitation', [AuthController::class, 'acceptInvitation'])->middleware('throttle:auth-email');

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});
