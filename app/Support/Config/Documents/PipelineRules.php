<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class PipelineRules
{
    public function __construct(
        public int $slaNewLeadBusinessHours,
        public int $slaQualifyingBusinessDays,
        public int $slaNegotiationBusinessDays,
        public int $probabilityNewLead,
        public int $probabilityQualifying,
        public int $probabilityQuoted,
        public int $probabilityNegotiation,
        public int $probabilityDepositPending,
    ) {}

    /**
     * @return array{
     *     sla_new_lead_business_hours: int,
     *     sla_qualifying_business_days: int,
     *     sla_negotiation_business_days: int,
     *     probability_new_lead: int,
     *     probability_qualifying: int,
     *     probability_quoted: int,
     *     probability_negotiation: int,
     *     probability_deposit_pending: int
     * }
     */
    public function toArray(): array
    {
        return [
            'sla_new_lead_business_hours' => $this->slaNewLeadBusinessHours,
            'sla_qualifying_business_days' => $this->slaQualifyingBusinessDays,
            'sla_negotiation_business_days' => $this->slaNegotiationBusinessDays,
            'probability_new_lead' => $this->probabilityNewLead,
            'probability_qualifying' => $this->probabilityQualifying,
            'probability_quoted' => $this->probabilityQuoted,
            'probability_negotiation' => $this->probabilityNegotiation,
            'probability_deposit_pending' => $this->probabilityDepositPending,
        ];
    }
}
