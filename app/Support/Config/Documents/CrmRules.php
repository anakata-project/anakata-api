<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class CrmRules
{
    public function __construct(
        public int $segmentHighLtv,
        public int $segmentMidLtv,
    ) {}

    /**
     * @return array{segment_high_ltv: int, segment_mid_ltv: int}
     */
    public function toArray(): array
    {
        return [
            'segment_high_ltv' => $this->segmentHighLtv,
            'segment_mid_ltv' => $this->segmentMidLtv,
        ];
    }
}
