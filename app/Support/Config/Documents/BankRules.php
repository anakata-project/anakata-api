<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class BankRules
{
    public function __construct(
        public string $bankName,
        public string $accountName,
        public string $accountNumber,
        public string $routing,
        public string $swift,
    ) {}

    /**
     * @return array{bank_name: string, account_name: string, account_number: string, routing: string, swift: string}
     */
    public function toArray(): array
    {
        return [
            'bank_name' => $this->bankName,
            'account_name' => $this->accountName,
            'account_number' => $this->accountNumber,
            'routing' => $this->routing,
            'swift' => $this->swift,
        ];
    }
}
