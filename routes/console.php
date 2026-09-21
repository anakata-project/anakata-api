<?php

use App\Support\BusinessTime;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('inventory:release-expired-holds')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('anakata:flag-overdue')
    ->daily()
    ->timezone(BusinessTime::zone())
    ->withoutOverlapping();

Schedule::command('anakata:retention')
    ->daily()
    ->timezone(BusinessTime::zone())
    ->withoutOverlapping();
