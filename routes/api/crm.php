<?php

declare(strict_types=1);

use App\Http\Controllers\Crm\ContactController;
use App\Http\Controllers\Crm\ContactMergeController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['ok' => true]));

Route::get('contacts', [ContactController::class, 'index']);
Route::get('contacts/duplicates', [ContactController::class, 'duplicates']);
Route::get('contacts/{contact}', [ContactController::class, 'show']);
Route::patch('contacts/{contact}', [ContactController::class, 'update']);
Route::post('contacts/{contact}/merge', [ContactController::class, 'merge']);

Route::get('contact-merges', [ContactMergeController::class, 'index']);
Route::post('contact-merges/{merge}/undo', [ContactMergeController::class, 'undo']);
