<?php

use App\Support\BusinessTime;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Telescope\Console\PruneCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('inventory:release-expired-holds')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('engine:expire-stripe-checkouts')
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

Schedule::command('anakata:events-retention')
    ->daily()
    ->timezone(BusinessTime::zone())
    ->withoutOverlapping();

Schedule::command('anakata:documents-due')
    ->daily()
    ->timezone(BusinessTime::zone())
    ->withoutOverlapping();

if (class_exists(PruneCommand::class)) {
    Schedule::command('telescope:prune --hours=48')->daily();
}
