<?php

declare(strict_types=1);

use App\Http\Controllers\Portal\PortalAuthController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/accept', [PortalAuthController::class, 'accept'])->middleware('throttle:auth-email');
Route::post('/auth/login', [PortalAuthController::class, 'login'])->middleware('throttle:login');
Route::post('/auth/forgot', [PortalAuthController::class, 'forgot'])->middleware('throttle:auth-email');
Route::post('/auth/reset', [PortalAuthController::class, 'reset'])->middleware('throttle:auth-email');

Route::middleware('portal.auth')->group(function (): void {
    Route::post('/auth/logout', [PortalAuthController::class, 'logout']);
    Route::get('/auth/me', [PortalAuthController::class, 'me']);
});
