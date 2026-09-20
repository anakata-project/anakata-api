<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

use App\Enums\ConfigKind;
use App\Support\Config\Change;
use App\Support\Config\ConfigDocument;
use App\Support\Config\DocumentDiff;
use App\Support\Config\Warning;
use Closure;
use Illuminate\Validation\Rule;

final class RatesDocument extends ConfigDocument
{
    /**
     * @param  list<RateYear>  $years
     */
    public function __construct(
        public readonly string $currency,
        public readonly array $years,
        public readonly RateTerms $terms,
        public readonly RateRules $rules,
    ) {}

    /**
     * @return array{
     *     currency: string,
     *     years: list<array{year: int, suite_pp: int, owner_pp: int, charter_week: int}>,
     *     terms: array{
     *         cabin_deposit_pct: int,
     *         cabin_balance_days: int,
     *         charter_deposit_pct: int,
     *         charter_deposit_business_days: int,
     *         charter_balance_days: int
     *     },
     *     rules: array{
     *         single_supplement_pct: int,
     *         triple_discount_pct: int,
     *         child_discount_pct: int,
     *         child_discounts_per_adult: int,
     *         child_discounts_per_cabin: int,
     *         back_to_back_pct: int,
     *         festive_supplement_pp: int,
     *         festive_supplement_charter: int
     *     }
     * }
     */
    public static function initial(): array
    {
        return [
            'currency' => 'USD',
            'years' => [
                ['year' => 2027, 'suite_pp' => 13300, 'owner_pp' => 25000, 'charter_week' => 199500],
                ['year' => 2028, 'suite_pp' => 13965, 'owner_pp' => 26250, 'charter_week' => 209475],
                ['year' => 2029, 'suite_pp' => 14663, 'owner_pp' => 27563, 'charter_week' => 219949],
            ],
            'terms' => [
                'cabin_deposit_pct' => 10,
                'cabin_balance_days' => 120,
                'charter_deposit_pct' => 20,
                'charter_deposit_business_days' => 5,
                'charter_balance_days' => 120,
            ],
            'rules' => [
                'single_supplement_pct' => 75,
                'triple_discount_pct' => 10,
                'child_discount_pct' => 15,
                'child_discounts_per_adult' => 1,
                'child_discounts_per_cabin' => 2,
                'back_to_back_pct' => 5,
                'festive_supplement_pp' => 750,
                'festive_supplement_charter' => 12000,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $years = [];

        foreach ($data['years'] ?? [] as $year) {
            if (! is_array($year)) {
                continue;
            }

            $years[] = new RateYear(
                (int) ($year['year'] ?? 0),
                (int) ($year['suite_pp'] ?? 0),
                (int) ($year['owner_pp'] ?? 0),
                (int) ($year['charter_week'] ?? 0),
            );
        }

        $terms = is_array($data['terms'] ?? null) ? $data['terms'] : [];
        $rules = is_array($data['rules'] ?? null) ? $data['rules'] : [];

        return new self(
            (string) ($data['currency'] ?? ''),
            $years,
            new RateTerms(
                (int) ($terms['cabin_deposit_pct'] ?? 0),
                (int) ($terms['cabin_balance_days'] ?? 0),
                (int) ($terms['charter_deposit_pct'] ?? 0),
                (int) ($terms['charter_deposit_business_days'] ?? 0),
                (int) ($terms['charter_balance_days'] ?? 0),
            ),
            new RateRules(
                (int) ($rules['single_supplement_pct'] ?? 0),
                (int) ($rules['triple_discount_pct'] ?? 0),
                (int) ($rules['child_discount_pct'] ?? 0),
                (int) ($rules['child_discounts_per_adult'] ?? 0),
                (int) ($rules['child_discounts_per_cabin'] ?? 0),
                (int) ($rules['back_to_back_pct'] ?? 0),
                (int) ($rules['festive_supplement_pp'] ?? 0),
                (int) ($rules['festive_supplement_charter'] ?? 0),
            ),
        );
    }

    /**
     * @return array{
     *     currency: string,
     *     years: list<array{year: int, suite_pp: int, owner_pp: int, charter_week: int}>,
     *     terms: array{
     *         cabin_deposit_pct: int,
     *         cabin_balance_days: int,
     *         charter_deposit_pct: int,
     *         charter_deposit_business_days: int,
     *         charter_balance_days: int
     *     },
     *     rules: array{
     *         single_supplement_pct: int,
     *         triple_discount_pct: int,
     *         child_discount_pct: int,
     *         child_discounts_per_adult: int,
     *         child_discounts_per_cabin: int,
     *         back_to_back_pct: int,
     *         festive_supplement_pp: int,
     *         festive_supplement_charter: int
     *     }
     * }
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'years' => array_map(
                fn (RateYear $year): array => $year->toArray(),
                $this->years,
            ),
            'terms' => $this->terms->toArray(),
            'rules' => $this->rules->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'currency' => ['required', 'string', Rule::in(['USD'])],
            'years' => ['required', 'array', 'min:1', self::yearsAscending()],
            'years.*.year' => ['required', 'integer', 'distinct', 'min:2020', 'max:2100'],
            'years.*.suite_pp' => ['required', 'integer', 'min:1'],
            'years.*.owner_pp' => ['required', 'integer', 'min:1'],
            'years.*.charter_week' => ['required', 'integer', 'min:1'],
            'terms' => ['required', 'array'],
            'terms.cabin_deposit_pct' => ['required', 'integer', 'min:0', 'max:100'],
            'terms.cabin_balance_days' => ['required', 'integer', 'min:1', 'max:365'],
            'terms.charter_deposit_pct' => ['required', 'integer', 'min:0', 'max:100'],
            'terms.charter_deposit_business_days' => ['required', 'integer', 'min:1', 'max:30'],
            'terms.charter_balance_days' => ['required', 'integer', 'min:1', 'max:365'],
            'rules' => ['required', 'array'],
            'rules.single_supplement_pct' => ['required', 'integer', 'min:0', 'max:100'],
            'rules.triple_discount_pct' => ['required', 'integer', 'min:0', 'max:100'],
            'rules.child_discount_pct' => ['required', 'integer', 'min:0', 'max:100'],
            'rules.child_discounts_per_adult' => ['required', 'integer', 'min:0', 'max:3'],
            'rules.child_discounts_per_cabin' => ['required', 'integer', 'min:0', 'max:3'],
            'rules.back_to_back_pct' => ['required', 'integer', 'min:0', 'max:100'],
            'rules.festive_supplement_pp' => ['required', 'integer', 'min:0'],
            'rules.festive_supplement_charter' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'currency' => 'Currency',
            'terms.cabin_deposit_pct' => 'Cabin deposit %',
            'terms.cabin_balance_days' => 'Cabin balance — days before',
            'terms.charter_deposit_pct' => 'Charter deposit %',
            'terms.charter_deposit_business_days' => 'Charter deposit — business days',
            'terms.charter_balance_days' => 'Charter balance — days before',
            'rules.single_supplement_pct' => 'Single supplement %',
            'rules.triple_discount_pct' => 'Triple sharing discount %',
            'rules.child_discount_pct' => 'Child discount %',
            'rules.child_discounts_per_adult' => 'Child discounts per adult',
            'rules.child_discounts_per_cabin' => 'Child discounts per cabin',
            'rules.back_to_back_pct' => 'Back-to-back discount %',
            'rules.festive_supplement_pp' => 'Festive supplement / guest',
            'rules.festive_supplement_charter' => 'Festive supplement / charter',
        ];
    }

    public static function kind(): ConfigKind
    {
        return ConfigKind::Rates;
    }

    /**
     * @return list<Warning>
     */
    public function warnings(?ConfigDocument $published): array
    {
        $warnings = [];
        $publishedYears = $published instanceof self ? $published->yearsByYear() : [];

        foreach ($this->years as $index => $year) {
            $previous = $index > 0 ? $this->years[$index - 1] : null;

            foreach (self::priceFields() as $field => $label) {
                $value = $year->price($field);
                $path = 'years.'.$index.'.'.self::fieldKey($field);

                if ($previous instanceof RateYear && $value < $previous->price($field)) {
                    $warnings[] = new Warning(
                        $path,
                        $label.' '.$year->year.' is lower than '.$previous->year.'.',
                    );
                }

                $publishedYear = $publishedYears[$year->year] ?? null;

                if ($publishedYear instanceof RateYear) {
                    $publishedValue = $publishedYear->price($field);

                    if ($publishedValue > 0 && abs($value - $publishedValue) / $publishedValue > 0.15) {
                        $move = (($value - $publishedValue) / $publishedValue) * 100;
                        $warnings[] = new Warning(
                            $path,
                            $label.' '.$year->year.' moves '.number_format($move, 1).'% vs published — double-check.',
                        );
                    }
                }
            }

            if ($year->ownerPp <= $year->suitePp) {
                $warnings[] = new Warning(
                    'years.'.$index.'.owner_pp',
                    "Owner's Suite {$year->year} is not above the Suite rate.",
                );
            }
        }

        return $warnings;
    }

    /**
     * @return list<Change>
     */
    public function changesAgainst(?ConfigDocument $published): array
    {
        if ($published !== null && ! $published instanceof self) {
            return parent::changesAgainst($published);
        }

        $from = $published?->toDiffArray() ?? [];
        $to = $this->toDiffArray();

        return DocumentDiff::compare($from, $to, self::diffLabels($from, $to));
    }

    public function year(int $year): ?RateYear
    {
        foreach ($this->years as $row) {
            if ($row->year === $year) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<int, RateYear>
     */
    public function yearsByYear(): array
    {
        $byYear = [];

        foreach ($this->years as $year) {
            $byYear[$year->year] = $year;
        }

        return $byYear;
    }

    /**
     * HTTP/storage shape stays a years list. Diff flattens to years.{year}.{field}
     * so a Suite 2028 edit is one leaf, not the whole list.
     *
     * @return array<string, mixed>
     */
    private function toDiffArray(): array
    {
        $years = [];

        foreach ($this->years as $year) {
            $years[(string) $year->year] = [
                'suite_pp' => $year->suitePp,
                'owner_pp' => $year->ownerPp,
                'charter_week' => $year->charterWeek,
            ];
        }

        return [
            'currency' => $this->currency,
            'years' => $years,
            'terms' => $this->terms->toArray(),
            'rules' => $this->rules->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $from
     * @param  array<string, mixed>  $to
     * @return array<string, string>
     */
    private static function diffLabels(array $from, array $to): array
    {
        $labels = self::labels();
        $fromYears = is_array($from['years'] ?? null) ? $from['years'] : [];
        $toYears = is_array($to['years'] ?? null) ? $to['years'] : [];
        $years = array_unique([...array_keys($fromYears), ...array_keys($toYears)]);

        foreach ($years as $year) {
            $labels['years.'.$year.'.suite_pp'] = 'Suite '.$year;
            $labels['years.'.$year.'.owner_pp'] = "Owner's Suite ".$year;
            $labels['years.'.$year.'.charter_week'] = 'Charter '.$year;
        }

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    private static function priceFields(): array
    {
        return [
            'SUITE' => 'Suite',
            'OWNER' => "Owner's Suite",
            'CHARTER' => 'Charter',
        ];
    }

    private static function fieldKey(string $category): string
    {
        return match ($category) {
            'SUITE' => 'suite_pp',
            'OWNER' => 'owner_pp',
            default => 'charter_week',
        };
    }

    private static function yearsAscending(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            $years = [];

            foreach ($value as $row) {
                if (! is_array($row) || ! array_key_exists('year', $row) || ! is_numeric($row['year'])) {
                    return;
                }

                $years[] = (int) $row['year'];
            }

            $sorted = $years;
            sort($sorted);

            if ($years !== $sorted) {
                $fail('The years must be sorted in ascending order.');
            }
        };
    }
}
