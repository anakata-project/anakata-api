<?php

declare(strict_types=1);

namespace App\Support\Schedule;

use App\Enums\ScheduledRunOutcome;
use App\Models\ScheduledRun;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Stringable;

final class RecordScheduledRuns
{
    /** @var list<string> */
    private static array $attached = [];

    /** @var array<int, int> */
    private static array $inFlight = [];

    public static function attach(Event $event): Event
    {
        $command = self::commandName($event);

        if (! in_array($command, self::$attached, true)) {
            self::$attached[] = $command;
        }

        $event->before(function () use ($event, $command): void {
            $run = ScheduledRun::query()->create([
                'command' => $command,
                'started_at' => now(),
                'outcome' => ScheduledRunOutcome::Running,
            ]);

            self::$inFlight[spl_object_id($event)] = $run->id;
        });

        $event->after(function (Stringable $output) use ($event): void {
            self::finish($event, (string) $output, $event->exitCode === 0
                ? ScheduledRunOutcome::Succeeded
                : ScheduledRunOutcome::Failed);
        });

        $event->onFailure(function (Stringable $output) use ($event): void {
            self::finish($event, (string) $output, ScheduledRunOutcome::Failed);
        });

        return $event;
    }

    public static function commandName(Event $event): string
    {
        if ($event instanceof CallbackEvent) {
            return $event->getSummaryForDisplay();
        }

        $normalized = Event::normalizeCommand((string) $event->command);

        if (preg_match('/\bartisan\s+(.+)$/i', $normalized, $matches) === 1) {
            return trim($matches[1]);
        }

        return trim($normalized);
    }

    /**
     * @return list<string>
     */
    public static function attached(): array
    {
        return self::$attached;
    }

    public static function isAttached(Event $event): bool
    {
        return in_array(self::commandName($event), self::$attached, true);
    }

    private static function finish(Event $event, string $output, ScheduledRunOutcome $outcome): void
    {
        $id = self::$inFlight[spl_object_id($event)] ?? null;

        if ($id === null) {
            return;
        }

        ScheduledRun::query()->whereKey($id)->update([
            'finished_at' => now(),
            'outcome' => $outcome,
            'exit_code' => $event->exitCode,
            'output' => mb_substr($output, 0, 500),
        ]);
    }
}
