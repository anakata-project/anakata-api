<?php

declare(strict_types=1);

use App\Enums\ScheduledRunOutcome;
use App\Models\ScheduledRun;
use App\Support\Schedule\RecordScheduledRuns;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(ConfigSeeder::class);
});

test('every scheduled command is listed and has a recording hook', function (): void {
    $scheduled = collect(app(Schedule::class)->events())
        ->map(fn (Event $event): string => RecordScheduledRuns::commandName($event))
        ->all();

    expect($scheduled)->not->toBeEmpty();

    foreach (app(Schedule::class)->events() as $event) {
        expect(RecordScheduledRuns::isAttached($event))->toBeTrue();
    }

    $response = $this->actingAs(salesExecUser())
        ->getJson('/api/crm/sync/jobs')
        ->assertOk();

    assertNoSensitiveFields($response);

    $listed = collect($response->json('data'))->pluck('command')->all();
    expect($listed)->toBe($scheduled);

    foreach ($response->json('data') as $row) {
        expect($row['last_started_at'])->toBeNull();
        expect($row['last_outcome'])->toBeNull();
        expect($row['next_run_at'])->not->toBeNull();
    }

    expect($response->json('meta.kpis'))->toHaveKeys(['jobs_failing', 'failures_open', 'merges_this_month']);
});

test('a scheduled command records start, finish and outcome', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains(RecordScheduledRuns::commandName($event), 'anakata:flag-overdue'));

    expect($event)->toBeInstanceOf(Event::class);

    $event->run($this->app);

    $run = ScheduledRun::query()->where('command', 'anakata:flag-overdue')->first();
    expect($run)->not->toBeNull();
    expect($run?->outcome)->toBe(ScheduledRunOutcome::Succeeded);
    expect($run?->started_at)->not->toBeNull();
    expect($run?->finished_at)->not->toBeNull();

    $row = collect($this->actingAs(salesExecUser())->getJson('/api/crm/sync/jobs')->json('data'))
        ->firstWhere('command', 'anakata:flag-overdue');

    expect($row['last_outcome'])->toBe('succeeded');
    expect($row['last_started_at'])->not->toBeNull();
});

test('a failing scheduled command records the failure', function (): void {
    $event = app(Schedule::class)->exec('exit 1');
    RecordScheduledRuns::attach($event);
    $event->run($this->app);

    $run = ScheduledRun::query()->where('command', 'exit 1')->first();
    expect($run?->outcome)->toBe(ScheduledRunOutcome::Failed);
    expect($run?->exit_code)->not->toBe(0);
});
