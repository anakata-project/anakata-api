<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class CrmRules
{
    public function __construct(
        public int $segmentHighLtv,
        public int $segmentMidLtv,
        public PipelineRules $pipeline,
    ) {}

    /**
     * @return array{
     *     segment_high_ltv: int,
     *     segment_mid_ltv: int,
     *     pipeline: array{
     *         sla_new_lead_business_hours: int,
     *         sla_qualifying_business_days: int,
     *         sla_negotiation_business_days: int,
     *         probability_new_lead: int,
     *         probability_qualifying: int,
     *         probability_quoted: int,
     *         probability_negotiation: int,
     *         probability_deposit_pending: int
     *     }
     * }
     */
    public function toArray(): array
    {
        return [
            'segment_high_ltv' => $this->segmentHighLtv,
            'segment_mid_ltv' => $this->segmentMidLtv,
            'pipeline' => $this->pipeline->toArray(),
        ];
    }
}
