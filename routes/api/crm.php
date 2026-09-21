<?php

declare(strict_types=1);

use App\Http\Controllers\Crm\ContactController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['ok' => true]));

Route::get('contacts', [ContactController::class, 'index']);
Route::get('contacts/{contact}', [ContactController::class, 'show']);
Route::patch('contacts/{contact}', [ContactController::class, 'update']);
