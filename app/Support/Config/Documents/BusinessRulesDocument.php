<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

use App\Enums\ConfigKind;
use App\Services\Config\CurrentConfig;
use App\Support\Config\ConfigDocument;
use App\Support\Config\Warning;

final class BusinessRulesDocument extends ConfigDocument
{
    /**
     * @param  list<CancellationBand>  $bands
     */
    public function __construct(
        public readonly CommissionRules $commission,
        public readonly int $modificationFeeUsd,
        public readonly PaymentsRules $payments,
        public readonly DiscountsRules $discounts,
        public readonly HoldsRules $holds,
        public readonly SlaRules $sla,
        public readonly ManifestsRules $manifests,
        public readonly AlertsRules $alerts,
        public readonly RetentionRules $retention,
        public readonly array $bands,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function initial(): array
    {
        return [
            'commission' => [
                'cap_pct' => 12,
                'default_pct' => 10,
                'payable_days_after_cruise' => 30,
            ],
            'modification_fee_usd' => 0,
            'payments' => [
                'extras_due_hours' => 72,
                'wire_window_hours' => 72,
                'balance_reminder_days' => [21, 7],
            ],
            'discounts' => [
                'online_deposit_discount_pct' => 5,
                'max_total_discount_pct' => null,
            ],
            'holds' => [
                'web_minutes' => 20,
                'web_extension_minutes' => 10,
                'near_term_business_hours' => 48,
                'long_lead_business_days' => 5,
                'business_days' => [1, 2, 3, 4, 5],
                'business_day_start' => '09:00',
                'business_day_end' => '18:00',
                'holidays' => [],
                'near_term_max_days' => 120,
            ],
            'sla' => [
                'response_hours' => 24,
                'refund_business_days' => 15,
                'agency_approval_business_days' => 2,
            ],
            'manifests' => [
                'dpng_fit_days' => 15,
                'dpng_charter_days' => 30,
            ],
            'alerts' => [
                'low_occupancy_pct' => 40,
                'low_occupancy_days_before' => 90,
            ],
            'retention' => [
                'passport_months_after_cruise' => 24,
                'medical_days_after_cruise' => 90,
            ],
            'cancellation' => [
                'bands' => [
                    ['min_days' => 120, 'penalty_pct' => 5],
                    ['min_days' => 90, 'penalty_pct' => 50],
                    ['min_days' => 0, 'penalty_pct' => 100],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $commission = is_array($data['commission'] ?? null) ? $data['commission'] : [];
        $payments = is_array($data['payments'] ?? null) ? $data['payments'] : [];
        $discounts = is_array($data['discounts'] ?? null) ? $data['discounts'] : [];
        $holds = is_array($data['holds'] ?? null) ? $data['holds'] : [];
        $sla = is_array($data['sla'] ?? null) ? $data['sla'] : [];
        $manifests = is_array($data['manifests'] ?? null) ? $data['manifests'] : [];
        $alerts = is_array($data['alerts'] ?? null) ? $data['alerts'] : [];
        $retention = is_array($data['retention'] ?? null) ? $data['retention'] : [];
        $cancellation = is_array($data['cancellation'] ?? null) ? $data['cancellation'] : [];

        $reminders = [];
        foreach ($payments['balance_reminder_days'] ?? [] as $day) {
            if (is_numeric($day)) {
                $reminders[] = (int) $day;
            }
        }

        $maxDiscount = $discounts['max_total_discount_pct'] ?? null;
        $maxDiscount = $maxDiscount === null || $maxDiscount === '' ? null : (int) $maxDiscount;

        $bands = [];
        foreach ($cancellation['bands'] ?? [] as $band) {
            if (! is_array($band)) {
                continue;
            }

            $bands[] = new CancellationBand(
                (int) ($band['min_days'] ?? 0),
                (int) ($band['penalty_pct'] ?? 0),
            );
        }

        usort($bands, fn (CancellationBand $a, CancellationBand $b): int => $b->minDays <=> $a->minDays);

        return new self(
            new CommissionRules(
                (int) ($commission['cap_pct'] ?? 0),
                (int) ($commission['default_pct'] ?? 0),
                (int) ($commission['payable_days_after_cruise'] ?? 0),
            ),
            (int) ($data['modification_fee_usd'] ?? 0),
            new PaymentsRules(
                (int) ($payments['extras_due_hours'] ?? 0),
                (int) ($payments['wire_window_hours'] ?? 0),
                $reminders,
            ),
            new DiscountsRules(
                (int) ($discounts['online_deposit_discount_pct'] ?? 0),
                $maxDiscount,
            ),
            new HoldsRules(
                (int) ($holds['web_minutes'] ?? 0),
                (int) ($holds['web_extension_minutes'] ?? 0),
                (int) ($holds['near_term_business_hours'] ?? 0),
                (int) ($holds['long_lead_business_days'] ?? 0),
                self::intList($holds['business_days'] ?? []),
                is_string($holds['business_day_start'] ?? null) ? $holds['business_day_start'] : '',
                is_string($holds['business_day_end'] ?? null) ? $holds['business_day_end'] : '',
                self::dateList($holds['holidays'] ?? []),
                (int) ($holds['near_term_max_days'] ?? 0),
            ),
            new SlaRules(
                (int) ($sla['response_hours'] ?? 0),
                (int) ($sla['refund_business_days'] ?? 0),
                (int) ($sla['agency_approval_business_days'] ?? 0),
            ),
            new ManifestsRules(
                (int) ($manifests['dpng_fit_days'] ?? 0),
                (int) ($manifests['dpng_charter_days'] ?? 0),
            ),
            new AlertsRules(
                (int) ($alerts['low_occupancy_pct'] ?? 0),
                (int) ($alerts['low_occupancy_days_before'] ?? 0),
            ),
            new RetentionRules(
                (int) ($retention['passport_months_after_cruise'] ?? 0),
                (int) ($retention['medical_days_after_cruise'] ?? 0),
            ),
            $bands,
        );
    }

    /**
     * @return array{
     *     commission: array{cap_pct: int, default_pct: int, payable_days_after_cruise: int},
     *     modification_fee_usd: int,
     *     payments: array{extras_due_hours: int, wire_window_hours: int, balance_reminder_days: list<int>},
     *     discounts: array{online_deposit_discount_pct: int, max_total_discount_pct: int|null},
     *     holds: array{web_minutes: int, web_extension_minutes: int, near_term_business_hours: int, long_lead_business_days: int, business_days: list<int>, business_day_start: string, business_day_end: string, holidays: list<string>, near_term_max_days: int},
     *     sla: array{response_hours: int, refund_business_days: int, agency_approval_business_days: int},
     *     manifests: array{dpng_fit_days: int, dpng_charter_days: int},
     *     alerts: array{low_occupancy_pct: int, low_occupancy_days_before: int},
     *     retention: array{passport_months_after_cruise: int, medical_days_after_cruise: int},
     *     cancellation: array{bands: list<array{min_days: int, penalty_pct: int}>}
     * }
     */
    public function toArray(): array
    {
        return [
            'commission' => $this->commission->toArray(),
            'modification_fee_usd' => $this->modificationFeeUsd,
            'payments' => $this->payments->toArray(),
            'discounts' => $this->discounts->toArray(),
            'holds' => $this->holds->toArray(),
            'sla' => $this->sla->toArray(),
            'manifests' => $this->manifests->toArray(),
            'alerts' => $this->alerts->toArray(),
            'retention' => $this->retention->toArray(),
            'cancellation' => [
                'bands' => array_map(
                    fn (CancellationBand $band): array => $band->toArray(),
                    $this->bands,
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'commission' => ['required', 'array'],
            'commission.cap_pct' => ['required', 'integer', 'min:0', 'max:30'],
            'commission.default_pct' => ['required', 'integer', 'min:0', 'max:30', new BusinessRulesConstraint('default_lte_cap')],
            'commission.payable_days_after_cruise' => ['required', 'integer', 'min:0', 'max:120'],
            'modification_fee_usd' => ['required', 'integer', 'min:0', 'max:10000'],
            'payments' => ['required', 'array'],
            'payments.extras_due_hours' => ['required', 'integer', 'min:0', 'max:2160'],
            'payments.wire_window_hours' => ['required', 'integer', 'min:12', 'max:168'],
            'payments.balance_reminder_days' => ['required', 'array', 'size:2', new BusinessRulesConstraint('reminders_decreasing')],
            'payments.balance_reminder_days.*' => ['required', 'integer', 'min:1', 'max:60'],
            'discounts' => ['required', 'array'],
            'discounts.online_deposit_discount_pct' => ['required', 'integer', 'min:0', 'max:100'],
            'discounts.max_total_discount_pct' => ['nullable', 'integer', 'min:0', 'max:100'],
            'holds' => ['required', 'array'],
            'holds.web_minutes' => ['required', 'integer', 'min:5', 'max:60'],
            'holds.web_extension_minutes' => ['required', 'integer', 'min:0', 'max:60'],
            'holds.near_term_business_hours' => ['required', 'integer', 'min:4', 'max:120'],
            'holds.long_lead_business_days' => ['required', 'integer', 'min:1', 'max:15'],
            'holds.business_days' => ['required', 'array', 'min:1', 'distinct'],
            'holds.business_days.*' => ['required', 'integer', 'min:1', 'max:7'],
            'holds.business_day_start' => ['required', 'date_format:H:i'],
            'holds.business_day_end' => ['required', 'date_format:H:i', new BusinessRulesConstraint('day_end_after_start')],
            'holds.holidays' => ['present', 'array', 'distinct'],
            'holds.holidays.*' => ['date_format:Y-m-d'],
            'holds.near_term_max_days' => ['required', 'integer', 'min:1', 'max:365'],
            'sla' => ['required', 'array'],
            'sla.response_hours' => ['required', 'integer', 'min:1', 'max:72'],
            'sla.refund_business_days' => ['required', 'integer', 'min:1', 'max:60'],
            'sla.agency_approval_business_days' => ['required', 'integer', 'min:1', 'max:10'],
            'manifests' => ['required', 'array'],
            'manifests.dpng_fit_days' => ['required', 'integer', 'min:1', 'max:90'],
            'manifests.dpng_charter_days' => ['required', 'integer', 'min:1', 'max:90'],
            'alerts' => ['required', 'array'],
            'alerts.low_occupancy_pct' => ['required', 'integer', 'min:1', 'max:100'],
            'alerts.low_occupancy_days_before' => ['required', 'integer', 'min:1', 'max:365'],
            'retention' => ['required', 'array'],
            'retention.passport_months_after_cruise' => ['required', 'integer', 'min:1', 'max:120'],
            'retention.medical_days_after_cruise' => ['required', 'integer', 'min:1', 'max:3650'],
            'cancellation' => ['required', 'array'],
            'cancellation.bands' => ['required', 'array', 'min:1', 'max:6', new BusinessRulesConstraint('bands')],
            'cancellation.bands.*.min_days' => ['required', 'integer', 'min:0', 'max:999'],
            'cancellation.bands.*.penalty_pct' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'commission.cap_pct' => 'FIN-005 · Max agency commission',
            'commission.default_pct' => 'RMS · Default agency commission',
            'commission.payable_days_after_cruise' => '§10 · Commission payable after cruise',
            'modification_fee_usd' => 'FIN-006 · Date-change / modification fee',
            'payments.extras_due_hours' => 'Anakata · Extras & collected fees — due before departure',
            'payments.wire_window_hours' => 'RMS · Wire transfer window before auto-release',
            'payments.balance_reminder_days' => '§4.1.4 · Balance reminders — days before due',
            'discounts.online_deposit_discount_pct' => '08 B2 · Online-deposit advantage',
            'discounts.max_total_discount_pct' => '08 B2 · Max total discount',
            'holds.web_minutes' => 'R-B2 · Web checkout hold (+ one silent extension)',
            'holds.web_extension_minutes' => 'R-B2 · Web checkout hold (+ one silent extension)',
            'holds.near_term_business_hours' => 'TEC-004 · Request / agency hold — near-term',
            'holds.long_lead_business_days' => 'TEC-004 · Request / agency hold — long-lead',
            'holds.business_days' => 'TEC-004 · Business days',
            'holds.business_day_start' => 'TEC-004 · Business day start',
            'holds.business_day_end' => 'TEC-004 · Business day end',
            'holds.holidays' => 'TEC-004 · Holidays',
            'holds.near_term_max_days' => 'TEC-004 · Near-term window',
            'sla.response_hours' => 'OPS-009 · Quote / first-response SLA (FIT, groups, charter)',
            'sla.refund_business_days' => 'RMS · Refund execution SLA',
            'sla.agency_approval_business_days' => '§5.5 · Agency approval SLA',
            'manifests.dpng_fit_days' => 'OPS-013 · DPNG manifest deadline — FIT / charter',
            'manifests.dpng_charter_days' => 'OPS-013 · DPNG manifest deadline — FIT / charter',
            'alerts.low_occupancy_pct' => '§10 · Low-occupancy alert',
            'alerts.low_occupancy_days_before' => '§10 · Low-occupancy alert',
            'retention.passport_months_after_cruise' => '§6.4 · Passport retention',
            'retention.medical_days_after_cruise' => 'LEG-002 · Medical notes retention',
            'cancellation.bands' => '§4.1.5 · Cabin cancellation penalty bands',
        ];
    }

    public static function kind(): ConfigKind
    {
        return ConfigKind::BusinessRules;
    }

    public function penaltyFor(int $daysBeforeDeparture): CancellationBand
    {
        foreach ($this->bands as $band) {
            if ($daysBeforeDeparture >= $band->minDays) {
                return $band;
            }
        }

        return $this->bands[array_key_last($this->bands)];
    }

    /**
     * @return list<Warning>
     */
    public function warnings(?ConfigDocument $published): array
    {
        $warnings = [];

        if ($this->manifests->dpngCharterDays < $this->manifests->dpngFitDays) {
            $warnings[] = new Warning(
                'manifests.dpng_charter_days',
                'Charter manifest deadline is shorter than FIT — the source has charter earlier (30 vs 15 days).',
            );
        }

        $sorted = $this->bands;
        for ($i = 1, $count = count($sorted); $i < $count; $i++) {
            if ($sorted[$i]->penaltyPct < $sorted[$i - 1]->penaltyPct) {
                $warnings[] = new Warning(
                    'cancellation.bands',
                    'Penalty drops closer to departure ('.$sorted[$i]->minDays.' days) — check the bands.',
                );
            }
        }

        $current = app(CurrentConfig::class);

        if ($current->has(ConfigKind::EngineSettings)) {
            $engineSla = $current->engineSettings()->charter->responseSlaHours;

            if ($engineSla !== $this->sla->responseHours) {
                $warnings[] = new Warning(
                    'sla.response_hours',
                    'Charter page promises '.$engineSla.' h but the response SLA is '.$this->sla->responseHours.' h — align in Engine Settings.',
                );
            }
        }

        $source = self::fromArray(self::initial());
        $seen = [];

        foreach ($this->changesAgainst($source) as $change) {
            if (isset($seen[$change->label])) {
                continue;
            }

            $seen[$change->label] = true;
            $warnings[] = new Warning(
                $change->path,
                $change->label.' differs from the CEO-confirmed value ('.self::sourceDisplay($change->path).').',
            );
        }

        return $warnings;
    }

    public static function sourceDisplay(string $path): string
    {
        return match ($path) {
            'commission.cap_pct' => '12%',
            'commission.default_pct' => '10% (confirmed 12 Sep 2026)',
            'commission.payable_days_after_cruise' => '30 days',
            'modification_fee_usd' => 'USD 0 — free, subject to availability',
            'payments.extras_due_hours' => '72 hours (Anakata 12 Sep 2026); services taken on board are settled during / after the cruise',
            'payments.wire_window_hours' => '72 hours (confirmed 12 Sep 2026)',
            'payments.balance_reminder_days' => '21 and 7 days',
            'discounts.online_deposit_discount_pct' => '5%',
            'discounts.max_total_discount_pct' => 'no cap',
            'holds.web_minutes', 'holds.web_extension_minutes' => '20 / 10 min (confirmed 12 Sep 2026)',
            'holds.near_term_business_hours' => '48 business hours',
            'holds.long_lead_business_days' => '5 business days',
            'holds.business_days',
            'holds.business_day_start',
            'holds.business_day_end',
            'holds.holidays',
            'holds.near_term_max_days' => 'Not defined in v5 — default',
            'sla.response_hours' => '24 hours',
            'sla.refund_business_days' => '15 business days (confirmed 12 Sep 2026)',
            'sla.agency_approval_business_days' => '2 business days',
            'manifests.dpng_fit_days', 'manifests.dpng_charter_days' => '15 / 30 days',
            'alerts.low_occupancy_pct', 'alerts.low_occupancy_days_before' => '40% at 90 days',
            'retention.passport_months_after_cruise' => '24 months',
            'retention.medical_days_after_cruise' => '90 days',
            'cancellation.bands' => '≥120 d 5% · 90–119 d 50% · 0–89 d 100%',
            default => $path,
        };
    }

    /**
     * @return list<int>
     */
    private static function intList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $days = [];

        foreach ($value as $item) {
            if (is_numeric($item)) {
                $days[] = (int) $item;
            }
        }

        $days = array_values(array_unique($days));
        sort($days);

        return $days;
    }

    /**
     * @return list<string>
     */
    private static function dateList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $dates = [];

        foreach ($value as $item) {
            if (is_string($item) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $item) === 1) {
                $dates[] = $item;
            }
        }

        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }
}
