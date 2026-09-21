<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class LegalEntityRules
{
    /**
     * @param  list<string>  $addressLines
     */
    public function __construct(
        public string $name,
        public array $addressLines,
        public string $email,
        public string $website,
        public string $ein,
        public BankRules $bank,
    ) {}

    /**
     * @return array{
     *     name: string,
     *     address_lines: list<string>,
     *     email: string,
     *     website: string,
     *     ein: string,
     *     bank: array{bank_name: string, account_name: string, account_number: string, routing: string, swift: string}
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'address_lines' => $this->addressLines,
            'email' => $this->email,
            'website' => $this->website,
            'ein' => $this->ein,
            'bank' => $this->bank->toArray(),
        ];
    }
}
