<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class FeesSettings
{
    public function __construct(
        public int $tctPp,
        public PngFees $png,
        public bool $showInPricePanel,
        public string $footnote,
    ) {}

    /**
     * @return array{
     *     tct_pp: int,
     *     png: array{
     *         foreign_over_12: int,
     *         foreign_12_and_under: int,
     *         can_adult: int,
     *         can_minor: int,
     *         national_or_resident: int,
     *         exempt_under_age: int
     *     },
     *     show_in_price_panel: bool,
     *     footnote: string
     * }
     */
    public function toArray(): array
    {
        return [
            'tct_pp' => $this->tctPp,
            'png' => $this->png->toArray(),
            'show_in_price_panel' => $this->showInPricePanel,
            'footnote' => $this->footnote,
        ];
    }
}
