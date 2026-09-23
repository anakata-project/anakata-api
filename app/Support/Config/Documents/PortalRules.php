<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class PortalRules
{
    public function __construct(public int $inviteValidDays) {}

    /**
     * @return array{invite_valid_days: int}
     */
    public function toArray(): array
    {
        return [
            'invite_valid_days' => $this->inviteValidDays,
        ];
    }
}
