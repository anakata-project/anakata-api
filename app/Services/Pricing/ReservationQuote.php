<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\BookingType;
use App\Models\Departure;

final readonly class ReservationQuote
{
    /**
     * @param  list<QuotedParty>  $parties
     * @param  list<string>  $warnings
     */
    public function __construct(
        public Departure $departure,
        public BookingType $type,
        public bool $backToBack,
        public array $parties,
        public array $warnings,
    ) {}

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        $errors = [];

        foreach ($this->parties as $party) {
            foreach ($party->errors as $error) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    public function hasErrors(): bool
    {
        return $this->errors() !== [];
    }

    public function total(): ?int
    {
        if ($this->hasErrors()) {
            return null;
        }

        $total = 0;

        foreach ($this->parties as $party) {
            $quote = $party->quote;
            $total += $quote instanceof Quote ? $quote->total : 0;
        }

        return $total;
    }

    public function deposit(): ?int
    {
        if ($this->hasErrors()) {
            return null;
        }

        $deposit = 0;

        foreach ($this->parties as $party) {
            $quote = $party->quote;
            $deposit += $quote instanceof Quote ? $quote->deposit : 0;
        }

        return $deposit;
    }
}
