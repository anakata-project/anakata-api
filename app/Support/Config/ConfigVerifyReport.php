<?php

declare(strict_types=1);

namespace App\Support\Config;

final readonly class ConfigVerifyReport
{
    /**
     * @param  list<string>  $valid
     * @param  list<ConfigVerifyFailure>  $failures
     */
    public function __construct(
        public array $valid,
        public array $failures,
    ) {}

    public function ok(): bool
    {
        return $this->failures === [];
    }
}
