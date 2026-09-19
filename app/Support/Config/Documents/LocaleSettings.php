<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class LocaleSettings
{
    /**
     * @param  list<string>  $live
     */
    public function __construct(
        public string $default,
        public array $live,
        public string $currency,
    ) {}

    /**
     * @return array{default: string, live: list<string>, currency: string}
     */
    public function toArray(): array
    {
        return [
            'default' => $this->default,
            'live' => $this->live,
            'currency' => $this->currency,
        ];
    }
}
