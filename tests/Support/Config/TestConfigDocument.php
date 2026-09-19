<?php

declare(strict_types=1);

namespace Tests\Support\Config;

use App\Enums\ConfigKind;
use App\Support\Config\ConfigDocument;
use App\Support\Config\Warning;

final class TestConfigDocument extends ConfigDocument
{
    /**
     * @param  list<TestBand>  $bands
     */
    public function __construct(
        public readonly TestTerms $terms,
        public readonly string $title,
        public readonly array $bands,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $bands = [];

        foreach ($data['bands'] ?? [] as $band) {
            if (! is_array($band)) {
                continue;
            }

            $bands[] = new TestBand(
                (int) ($band['min'] ?? 0),
                (int) ($band['pct'] ?? 0),
            );
        }

        $terms = is_array($data['terms'] ?? null) ? $data['terms'] : [];

        return new self(
            new TestTerms((int) ($terms['cabin_deposit_pct'] ?? 0)),
            (string) ($data['title'] ?? ''),
            $bands,
        );
    }

    /**
     * @return array{terms: array{cabin_deposit_pct: int}, title: string, bands: list<array{min: int, pct: int}>}
     */
    public function toArray(): array
    {
        return [
            'terms' => [
                'cabin_deposit_pct' => $this->terms->cabinDepositPct,
            ],
            'title' => $this->title,
            'bands' => array_map(
                fn (TestBand $band): array => [
                    'min' => $band->min,
                    'pct' => $band->pct,
                ],
                $this->bands,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'terms' => ['required', 'array'],
            'terms.cabin_deposit_pct' => ['required', 'integer', 'min:0', 'max:100'],
            'title' => ['required', 'string', 'min:1'],
            'bands' => ['required', 'array'],
            'bands.*.min' => ['required', 'integer'],
            'bands.*.pct' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'terms.cabin_deposit_pct' => 'Cabin deposit %',
            'title' => 'Title',
            'bands' => 'Cancellation bands',
        ];
    }

    public static function kind(): ConfigKind
    {
        return ConfigKind::Rates;
    }

    public function warnings(?ConfigDocument $published): array
    {
        if ($this->terms->cabinDepositPct <= 50) {
            return [];
        }

        return [
            new Warning('terms.cabin_deposit_pct', 'Cabin deposit % is unusually high.'),
        ];
    }
}
