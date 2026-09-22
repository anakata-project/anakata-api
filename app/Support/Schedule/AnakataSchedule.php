<?php

declare(strict_types=1);

namespace App\Support\Schedule;

use App\Support\BusinessTime;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Telescope\Console\PruneCommand;

final class AnakataSchedule
{
    public static function register(Schedule $schedule): void
    {
        if (self::alreadyRegistered($schedule)) {
            return;
        }

        RecordScheduledRuns::attach(
            $schedule->command('inventory:release-expired-holds')
                ->everyMinute()
                ->withoutOverlapping(),
        );

        RecordScheduledRuns::attach(
            $schedule->command('engine:expire-stripe-checkouts')
                ->everyMinute()
                ->withoutOverlapping(),
        );

        RecordScheduledRuns::attach(
            $schedule->command('anakata:crm-tasks')
                ->everyFiveMinutes()
                ->withoutOverlapping(),
        );

        RecordScheduledRuns::attach(
            $schedule->command('anakata:alerts')
                ->everyFiveMinutes()
                ->withoutOverlapping(),
        );

        RecordScheduledRuns::attach(
            $schedule->command('anakata:flag-overdue')
                ->daily()
                ->timezone(BusinessTime::zone())
                ->withoutOverlapping(),
        );

        RecordScheduledRuns::attach(
            $schedule->command('anakata:retention')
                ->daily()
                ->timezone(BusinessTime::zone())
                ->withoutOverlapping(),
        );

        RecordScheduledRuns::attach(
            $schedule->command('anakata:events-retention')
                ->daily()
                ->timezone(BusinessTime::zone())
                ->withoutOverlapping(),
        );

        RecordScheduledRuns::attach(
            $schedule->command('anakata:documents-due')
                ->daily()
                ->timezone(BusinessTime::zone())
                ->withoutOverlapping(),
        );

        RecordScheduledRuns::attach(
            $schedule->command('anakata:voyage-status')
                ->dailyAt('00:15')
                ->timezone(BusinessTime::zone())
                ->withoutOverlapping()
                ->onOneServer()
                ->description('Move FULLY_PAID to ON_BOARD and ON_BOARD to COMPLETED'),
        );

        RecordScheduledRuns::attach(
            $schedule->command('anakata:ledger-check')
                ->dailyAt('02:00')
                ->timezone(BusinessTime::zone())
                ->withoutOverlapping()
                ->onOneServer()
                ->description('Report ledger drift and never correct it'),
        );

        RecordScheduledRuns::attach(
            $schedule->command('anakata:commission-scan')
                ->dailyAt('02:30')
                ->timezone(BusinessTime::zone())
                ->withoutOverlapping()
                ->onOneServer()
                ->description('Scan for commission leakage'),
        );

        RecordScheduledRuns::attach(
            $schedule->command('anakata:manifests-due')
                ->dailyAt('06:00')
                ->timezone(BusinessTime::zone())
                ->withoutOverlapping()
                ->onOneServer()
                ->description('Issue due manifests and chase missing passenger data'),
        );

        RecordScheduledRuns::attach(
            $schedule->command('anakata:occupancy-check')
                ->dailyAt('07:00')
                ->timezone(BusinessTime::zone())
                ->withoutOverlapping()
                ->onOneServer()
                ->description('Raise low-occupancy alerts'),
        );

        RecordScheduledRuns::attach(
            $schedule->command('anakata:document-check')
                ->hourly()
                ->timezone(BusinessTime::zone())
                ->withoutOverlapping()
                ->onOneServer()
                ->description('Re-queue one failed automatic document send'),
        );

        if (class_exists(PruneCommand::class)) {
            RecordScheduledRuns::attach(
                $schedule->command('telescope:prune --hours=48')->daily(),
            );
        }
    }

    private static function alreadyRegistered(Schedule $schedule): bool
    {
        foreach ($schedule->events() as $event) {
            if (str_contains(RecordScheduledRuns::commandName($event), 'anakata:flag-overdue')) {
                return true;
            }
        }

        return false;
    }
}
