<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

use App\Enums\ConsentDocument;

final readonly class ConsentVersions
{
    public function __construct(
        public string $terms,
        public string $cancellation,
        public string $privacy,
        public string $insurance,
        public string $marketing,
        public string $analytics,
    ) {}

    public function for(ConsentDocument $document): string
    {
        return match ($document) {
            ConsentDocument::Terms => $this->terms,
            ConsentDocument::Cancellation => $this->cancellation,
            ConsentDocument::Privacy => $this->privacy,
            ConsentDocument::Insurance => $this->insurance,
            ConsentDocument::Marketing => $this->marketing,
        };
    }

    /**
     * @return array{terms: string, cancellation: string, privacy: string, insurance: string, marketing: string, analytics: string}
     */
    public function toArray(): array
    {
        return [
            'terms' => $this->terms,
            'cancellation' => $this->cancellation,
            'privacy' => $this->privacy,
            'insurance' => $this->insurance,
            'marketing' => $this->marketing,
            'analytics' => $this->analytics,
        ];
    }
}
