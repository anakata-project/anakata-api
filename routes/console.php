<?php

declare(strict_types=1);

use App\Support\Schedule\AnakataSchedule;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

AnakataSchedule::register(app(Schedule::class));
