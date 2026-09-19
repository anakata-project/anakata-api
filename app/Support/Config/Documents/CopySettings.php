<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class CopySettings
{
    /**
     * @param  list<string>  $confirmationSteps
     */
    public function __construct(
        public string $bookNowPayLater,
        public string $travelingWithChildren,
        public string $soloAndTriple,
        public string $payToday,
        public string $detailsNote,
        public array $confirmationSteps,
    ) {}

    /**
     * @return array{
     *     book_now_pay_later: string,
     *     traveling_with_children: string,
     *     solo_and_triple: string,
     *     pay_today: string,
     *     details_note: string,
     *     confirmation_steps: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'book_now_pay_later' => $this->bookNowPayLater,
            'traveling_with_children' => $this->travelingWithChildren,
            'solo_and_triple' => $this->soloAndTriple,
            'pay_today' => $this->payToday,
            'details_note' => $this->detailsNote,
            'confirmation_steps' => $this->confirmationSteps,
        ];
    }
}
