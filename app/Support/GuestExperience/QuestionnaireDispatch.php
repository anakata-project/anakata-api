<?php

declare(strict_types=1);

namespace App\Support\GuestExperience;

final readonly class QuestionnaireDispatch
{
    /**
     * @param  list<string>  $to
     * @param  list<int>  $coveredGuestIds
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $to,
        public ?string $blockedReason,
        public array $coveredGuestIds,
        public ?int $guestId,
    ) {}

    public function blocked(): bool
    {
        return $this->blockedReason !== null;
    }
}
