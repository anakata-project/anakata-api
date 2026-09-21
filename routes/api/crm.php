<?php

declare(strict_types=1);

use App\Http\Controllers\Crm\ContactController;
use App\Http\Controllers\Crm\ContactMergeController;
use App\Http\Controllers\Crm\ContactTimelineController;
use App\Http\Controllers\Crm\EngineActivityController;
use App\Http\Controllers\Crm\SyncController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['ok' => true]));

Route::get('contacts', [ContactController::class, 'index']);
Route::get('contacts/duplicates', [ContactController::class, 'duplicates']);
Route::get('contacts/{contact}', [ContactController::class, 'show']);
Route::get('contacts/{contact}/timeline', ContactTimelineController::class);
Route::patch('contacts/{contact}', [ContactController::class, 'update']);
Route::post('contacts/{contact}/merge', [ContactController::class, 'merge']);

Route::get('contact-merges', [ContactMergeController::class, 'index']);
Route::post('contact-merges/{merge}/undo', [ContactMergeController::class, 'undo']);

Route::get('activity', EngineActivityController::class);

Route::get('sync/ownership', [SyncController::class, 'ownership']);
Route::get('sync/jobs', [SyncController::class, 'jobs']);
Route::get('sync/failures', [SyncController::class, 'failures']);
Route::post('sync/failures/{id}/retry', [SyncController::class, 'retry'])->where('id', 'job:[A-Za-z0-9-]+|delivery:[0-9]+');
Route::get('sync/identity', [SyncController::class, 'identity']);
Route::get('sync/events', [SyncController::class, 'events']);
