<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class CharterSettings
{
    /**
     * @param  list<string>  $groupContexts
     */
    public function __construct(
        public string $headline,
        public string $intro,
        public string $itineraryLabel,
        public int $responseSlaHours,
        public array $groupContexts,
        public string $thankYou,
    ) {}

    /**
     * @return array{
     *     headline: string,
     *     intro: string,
     *     itinerary_label: string,
     *     response_sla_hours: int,
     *     group_contexts: list<string>,
     *     thank_you: string
     * }
     */
    public function toArray(): array
    {
        return [
            'headline' => $this->headline,
            'intro' => $this->intro,
            'itinerary_label' => $this->itineraryLabel,
            'response_sla_hours' => $this->responseSlaHours,
            'group_contexts' => $this->groupContexts,
            'thank_you' => $this->thankYou,
        ];
    }
}
