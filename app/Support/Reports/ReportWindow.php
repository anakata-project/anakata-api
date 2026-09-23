<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Enums\ReportCadence;
use App\Models\ReportSubscription;
use App\Support\BusinessTime;
use App\Support\Metrics\MetricWindow;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class ReportWindow
{
    public function due(ReportSubscription $subscription, ?CarbonImmutable $now = null): bool
    {
        $now ??= BusinessTime::now();

        if ($now->lessThan($now->setTimeFromTimeString($subscription->clock().':00'))) {
            return false;
        }

        return match ($subscription->cadence) {
            ReportCadence::Daily => true,
            ReportCadence::Weekly => $now->dayOfWeekIso === $subscription->weekday,
            ReportCadence::Monthly => $now->day === $subscription->day_of_month,
            ReportCadence::Quarterly => $now->day === $subscription->day_of_month
                && in_array($now->month, [1, 4, 7, 10], true),
        };
    }

    public function period(ReportSubscription $subscription, ?CarbonImmutable $now = null): string
    {
        $now ??= BusinessTime::now();

        return match ($subscription->cadence) {
            ReportCadence::Daily => $now->toDateString(),
            ReportCadence::Weekly => $now->startOfWeek(CarbonImmutable::MONDAY)->toDateString(),
            ReportCadence::Monthly => $now->format('Y-m'),
            ReportCadence::Quarterly => $now->year.'-Q'.$now->quarter,
        };
    }

    public function resolve(ReportSubscription $subscription, ?CarbonImmutable $now = null): MetricWindow
    {
        $now ??= BusinessTime::now();
        $label = is_string($subscription->parameters['window'] ?? null)
            ? $subscription->parameters['window']
            : '';

        return match ($label) {
            'yesterday' => $this->day($now->subDay()),
            'last_week' => $this->week($now),
            'last_month' => $this->month($now),
            'last_quarter' => $this->quarter($now),
            default => throw new InvalidArgumentException('Unknown report window ['.$label.'].'),
        };
    }

    private function day(CarbonImmutable $day): MetricWindow
    {
        $date = $day->toDateString();

        return new MetricWindow($date, $date);
    }

    private function week(CarbonImmutable $now): MetricWindow
    {
        $start = $now->startOfWeek(CarbonImmutable::MONDAY)->subWeek();

        return new MetricWindow($start->toDateString(), $start->addDays(6)->toDateString());
    }

    private function month(CarbonImmutable $now): MetricWindow
    {
        $start = $now->subMonthNoOverflow()->startOfMonth();

        return new MetricWindow($start->toDateString(), $start->endOfMonth()->toDateString());
    }

    private function quarter(CarbonImmutable $now): MetricWindow
    {
        $start = $now->quarter === 1
            ? CarbonImmutable::create($now->year - 1, 10, 1, 0, 0, 0, $now->timezoneName)
            : CarbonImmutable::create($now->year, (($now->quarter - 2) * 3) + 1, 1, 0, 0, 0, $now->timezoneName);

        if (! $start instanceof CarbonImmutable) {
            throw new InvalidArgumentException('Could not resolve the previous quarter.');
        }

        return new MetricWindow($start->toDateString(), $start->addMonths(3)->subDay()->toDateString());
    }
}
