<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Enums\DeliveryStatus;
use App\Enums\ScheduledRunOutcome;
use App\Models\ContactMerge;
use App\Models\Delivery;
use App\Models\ScheduledRun;
use App\Support\BusinessTime;
use App\Support\Iso;
use App\Support\Schedule\RecordScheduledRuns;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

final class SyncJobs
{
    /**
     * @return array{
     *     jobs: list<array{
     *         command: string,
     *         cadence: string,
     *         timezone: string|null,
     *         description: string,
     *         last_started_at: string|null,
     *         last_finished_at: string|null,
     *         last_outcome: string|null,
     *         last_output: string|null,
     *         next_run_at: string|null
     *     }>,
     *     kpis: array{jobs_failing: int, failures_open: int, merges_this_month: int}
     * }
     */
    public static function list(): array
    {
        $events = app(Schedule::class)->events();
        $commands = array_values(array_unique(array_map(
            fn (Event $event): string => RecordScheduledRuns::commandName($event),
            $events,
        )));

        $latest = ScheduledRun::query()
            ->whereIn('command', $commands === [] ? [''] : $commands)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get()
            ->unique('command')
            ->keyBy('command');

        $jobs = [];

        foreach ($events as $event) {
            $command = RecordScheduledRuns::commandName($event);
            $run = $latest->get($command);
            $timezone = is_string($event->timezone) && $event->timezone !== '' ? $event->timezone : null;
            $next = $event->nextRunDate();

            $jobs[] = [
                'command' => $command,
                'cadence' => $event->expression,
                'timezone' => $timezone,
                'description' => is_string($event->description) && $event->description !== ''
                    ? $event->description
                    : $event->getSummaryForDisplay(),
                'last_started_at' => $run instanceof ScheduledRun ? Iso::utc($run->started_at) : null,
                'last_finished_at' => $run instanceof ScheduledRun ? Iso::utc($run->finished_at) : null,
                'last_outcome' => $run instanceof ScheduledRun ? $run->outcome->value : null,
                'last_output' => $run instanceof ScheduledRun ? $run->output : null,
                'next_run_at' => Iso::utc(CarbonImmutable::instance($next)),
            ];
        }

        $monthStart = BusinessTime::now()->startOfMonth()->utc();
        $monthEnd = BusinessTime::now()->endOfMonth()->utc();

        return [
            'jobs' => $jobs,
            'kpis' => [
                'jobs_failing' => $latest->filter(
                    fn (ScheduledRun $run): bool => $run->outcome === ScheduledRunOutcome::Failed,
                )->count(),
                'failures_open' => (int) DB::table('failed_jobs')->count()
                    + Delivery::query()->where('status', DeliveryStatus::Failed)->count(),
                'merges_this_month' => ContactMerge::query()
                    ->whereBetween('merged_at', [$monthStart, $monthEnd])
                    ->count(),
            ],
        ];
    }
}
