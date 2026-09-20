<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Config\Documents\BusinessRulesDocument;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * A business window is [start, end) on a business day that isn't a holiday, in Galápagos time.
 *
 * addBusinessHours(CarbonInterface $from, int $hours): CarbonImmutable: count only time
 * inside business windows. A start outside a window begins at the next window's opening.
 *
 * endOfNthBusinessDay(CarbonInterface $from, int $n): CarbonImmutable: the closing time
 * of the n-th business day after the day of $from. The day of $from never counts, even
 * if it's a business day.
 *
 * holdExpiry(CarbonInterface $requestedAt, CalendarDate $departureDate, BusinessRulesDocument $rules):
 * { expires_at, rule: NEAR_TERM|LONG_LEAD }:
 * - near-term when the departure is ≤ near_term_max_days days after the request's
 *   Galápagos date → addBusinessHours(requestedAt, holds.near_term_business_hours) (48)
 * - otherwise → endOfNthBusinessDay(requestedAt, holds.long_lead_business_days) (5)
 *
 * All results are returned in UTC.
 */
final class BusinessHours
{
    private const MAX_WALK_DAYS = 366;

    /**
     * @param  list<int>  $businessDays
     * @param  list<string>  $holidays
     */
    public function __construct(
        private readonly array $businessDays,
        private readonly string $start,
        private readonly string $end,
        private readonly array $holidays,
        private readonly int $nearTermMaxDays,
        private readonly string $zone,
    ) {
        if ($this->businessDays === []) {
            throw new InvalidArgumentException('Business days must not be empty.');
        }

        if (! self::isHi($this->start) || ! self::isHi($this->end)) {
            throw new InvalidArgumentException('Business day start and end must be valid H:i.');
        }

        if ($this->start >= $this->end) {
            throw new InvalidArgumentException('Business day start must be before the end.');
        }
    }

    public static function fromDocument(BusinessRulesDocument $rules): self
    {
        $holds = $rules->holds;

        return new self(
            $holds->businessDays,
            $holds->businessDayStart,
            $holds->businessDayEnd,
            $holds->holidays,
            $holds->nearTermMaxDays,
            BusinessTime::zone(),
        );
    }

    public function addBusinessHours(CarbonInterface $from, int $hours): CarbonImmutable
    {
        if ($hours < 0) {
            throw new InvalidArgumentException('Hours must not be negative.');
        }

        $cursor = $this->inZone($from);
        $origin = $cursor->startOfDay();
        $remaining = $hours * 60;

        while ($remaining > 0) {
            [$open, $close] = $this->nextWindow($cursor, $origin);
            $available = (int) $open->diffInMinutes($close);

            if ($remaining <= $available) {
                return $open->addMinutes($remaining)->utc();
            }

            $remaining -= $available;
            $cursor = $close;
        }

        return $cursor->utc();
    }

    public function endOfNthBusinessDay(CarbonInterface $from, int $n): CarbonImmutable
    {
        if ($n < 1) {
            throw new InvalidArgumentException('Business-day count must be at least 1.');
        }

        $day = $this->inZone($from)->startOfDay();
        $counted = 0;

        for ($step = 0; $step < self::MAX_WALK_DAYS; $step++) {
            $day = $day->addDay();

            if (! $this->isOpenDay($day)) {
                continue;
            }

            $counted++;

            if ($counted === $n) {
                return $this->atTime($day, $this->end)->utc();
            }
        }

        throw new RuntimeException('No business day within 366 days.');
    }

    public function holdExpiry(
        CarbonInterface $requestedAt,
        CarbonImmutable $departureDate,
        BusinessRulesDocument $rules,
    ): HoldExpiry {
        $requestYmd = $this->inZone($requestedAt)->format('Y-m-d');
        $departureYmd = $departureDate->format('Y-m-d');
        $daysAfter = BusinessTime::calendarDaysBetween($requestYmd, $departureYmd);

        if ($daysAfter <= $this->nearTermMaxDays) {
            return new HoldExpiry(
                $this->addBusinessHours($requestedAt, $rules->holds->nearTermBusinessHours),
                HoldExpiry::NEAR_TERM,
            );
        }

        return new HoldExpiry(
            $this->endOfNthBusinessDay($requestedAt, $rules->holds->longLeadBusinessDays),
            HoldExpiry::LONG_LEAD,
        );
    }

    /**
     * Open Galápagos days strictly after the day of $from, up to and including
     * the day of $to. The day of $from never counts (same as endOfNthBusinessDay).
     */
    public function businessDaysElapsed(CarbonInterface $from, CarbonInterface $to): int
    {
        $day = $this->inZone($from)->startOfDay();
        $end = $this->inZone($to)->startOfDay();
        $counted = 0;

        while ($day->lt($end)) {
            $day = $day->addDay();

            if ($this->isOpenDay($day)) {
                $counted++;
            }
        }

        return $counted;
    }

    public function remainingBusinessMinutes(CarbonInterface $from, CarbonInterface $until): int
    {
        $start = $this->inZone($from);
        $end = $this->inZone($until);

        if ($end->lte($start)) {
            return 0;
        }

        $cursor = $start;
        $origin = $cursor->startOfDay();
        $total = 0;

        while ($cursor->lt($end)) {
            [$open, $close] = $this->nextWindow($cursor, $origin);

            if ($open->gte($end)) {
                break;
            }

            $windowEnd = $close->lt($end) ? $close : $end;
            $total += (int) $open->diffInMinutes($windowEnd);
            $cursor = $close;
        }

        return $total;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function nextWindow(CarbonImmutable $cursor, CarbonImmutable $origin): array
    {
        $day = $cursor->startOfDay();

        for ($step = 0; $step <= self::MAX_WALK_DAYS; $step++) {
            if ((int) $origin->diffInDays($day) > self::MAX_WALK_DAYS) {
                break;
            }

            if ($this->isOpenDay($day)) {
                $open = $this->atTime($day, $this->start);
                $close = $this->atTime($day, $this->end);

                if ($cursor->lt($close)) {
                    $from = $cursor->gt($open) ? $cursor : $open;

                    if ($from->lt($close)) {
                        return [$from, $close];
                    }
                }
            }

            $day = $day->addDay();
            $cursor = $day->startOfDay();
        }

        throw new RuntimeException('No business window within 366 days.');
    }

    private function isOpenDay(CarbonImmutable $day): bool
    {
        return in_array($day->isoWeekday(), $this->businessDays, true)
            && ! in_array($day->format('Y-m-d'), $this->holidays, true);
    }

    private function atTime(CarbonImmutable $day, string $hi): CarbonImmutable
    {
        [$hour, $minute] = array_map(intval(...), explode(':', $hi));

        return $day->setTime($hour, $minute);
    }

    private function inZone(CarbonInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance($at)->setTimezone($this->zone);
    }

    private static function isHi(string $value): bool
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }
}
