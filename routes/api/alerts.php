<?php

declare(strict_types=1);

use App\Http\Controllers\Alerts\AlertController;
use Illuminate\Support\Facades\Route;

Route::get('alerts', [AlertController::class, 'index']);
Route::get('alerts/kinds', [AlertController::class, 'kinds']);
Route::post('alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge']);
