<?php

declare(strict_types=1);

use App\Http\Controllers\Copilot\CopilotController;
use Illuminate\Support\Facades\Route;

Route::get('conversations', [CopilotController::class, 'index']);
Route::post('conversations', [CopilotController::class, 'store']);
Route::get('conversations/{conversation}', [CopilotController::class, 'show']);
Route::delete('conversations/{conversation}', [CopilotController::class, 'destroy']);
Route::post('conversations/{conversation}/messages', [CopilotController::class, 'storeMessage'])
    ->middleware('throttle:copilot-messages');
Route::post('conversations/{conversation}/decisions', [CopilotController::class, 'storeDecisions'])
    ->middleware('throttle:copilot-messages');
