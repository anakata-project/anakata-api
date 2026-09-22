<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class NpsRules
{
    public function __construct(
        public int $surveyHoursAfterReturn,
        public int $alertBelow,
        public int $reviewRequestFrom,
        public string $reviewUrl,
    ) {}

    /**
     * @return array{survey_hours_after_return: int, alert_below: int, review_request_from: int, review_url: string}
     */
    public function toArray(): array
    {
        return [
            'survey_hours_after_return' => $this->surveyHoursAfterReturn,
            'alert_below' => $this->alertBelow,
            'review_request_from' => $this->reviewRequestFrom,
            'review_url' => $this->reviewUrl,
        ];
    }
}
