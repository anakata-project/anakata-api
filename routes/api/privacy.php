<?php

declare(strict_types=1);

use App\Http\Controllers\Privacy\SubjectRequestController;
use Illuminate\Support\Facades\Route;

Route::get('requests', [SubjectRequestController::class, 'index']);
Route::post('requests', [SubjectRequestController::class, 'store']);
Route::get('requests/{subjectRequest}', [SubjectRequestController::class, 'show']);
Route::post('requests/{subjectRequest}/export', [SubjectRequestController::class, 'export']);
Route::get('requests/{subjectRequest}/export', [SubjectRequestController::class, 'download']);
Route::post('requests/{subjectRequest}/complete', [SubjectRequestController::class, 'complete']);
Route::post('requests/{subjectRequest}/erase', [SubjectRequestController::class, 'erase']);
Route::post('requests/{subjectRequest}/reject', [SubjectRequestController::class, 'reject']);
