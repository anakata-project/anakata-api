<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class PaymentsRules
{
    /**
     * @param  list<int>  $balanceReminderDays
     */
    public function __construct(
        public int $extrasDueHours,
        public int $wireWindowHours,
        public array $balanceReminderDays,
    ) {}

    /**
     * @return array{extras_due_hours: int, wire_window_hours: int, balance_reminder_days: list<int>}
     */
    public function toArray(): array
    {
        return [
            'extras_due_hours' => $this->extrasDueHours,
            'wire_window_hours' => $this->wireWindowHours,
            'balance_reminder_days' => $this->balanceReminderDays,
        ];
    }
}
