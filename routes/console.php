<?php

declare(strict_types=1);

use App\Support\BusinessTime;
use App\Support\Schedule\RecordScheduledRuns;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Telescope\Console\PruneCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

RecordScheduledRuns::attach(
    Schedule::command('inventory:release-expired-holds')
        ->everyMinute()
        ->withoutOverlapping(),
);

RecordScheduledRuns::attach(
    Schedule::command('engine:expire-stripe-checkouts')
        ->everyMinute()
        ->withoutOverlapping(),
);

RecordScheduledRuns::attach(
    Schedule::command('anakata:flag-overdue')
        ->daily()
        ->timezone(BusinessTime::zone())
        ->withoutOverlapping(),
);

RecordScheduledRuns::attach(
    Schedule::command('anakata:retention')
        ->daily()
        ->timezone(BusinessTime::zone())
        ->withoutOverlapping(),
);

RecordScheduledRuns::attach(
    Schedule::command('anakata:events-retention')
        ->daily()
        ->timezone(BusinessTime::zone())
        ->withoutOverlapping(),
);

RecordScheduledRuns::attach(
    Schedule::command('anakata:documents-due')
        ->daily()
        ->timezone(BusinessTime::zone())
        ->withoutOverlapping(),
);

if (class_exists(PruneCommand::class)) {
    RecordScheduledRuns::attach(
        Schedule::command('telescope:prune --hours=48')->daily(),
    );
}
