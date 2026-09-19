<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class SlaRules
{
    public function __construct(
        public int $responseHours,
        public int $refundBusinessDays,
        public int $agencyApprovalBusinessDays,
    ) {}

    /**
     * @return array{
     *     response_hours: int,
     *     refund_business_days: int,
     *     agency_approval_business_days: int
     * }
     */
    public function toArray(): array
    {
        return [
            'response_hours' => $this->responseHours,
            'refund_business_days' => $this->refundBusinessDays,
            'agency_approval_business_days' => $this->agencyApprovalBusinessDays,
        ];
    }
}
