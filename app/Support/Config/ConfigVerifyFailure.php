<?php

declare(strict_types=1);

namespace App\Support\Config;

use App\Enums\ConfigKind;

final readonly class ConfigVerifyFailure
{
    public function __construct(
        public ConfigKind $kind,
        public ?int $version,
        public ?string $path,
        public string $message,
    ) {}

    public function line(): string
    {
        if ($this->version === null) {
            return $this->kind->value.': '.$this->message;
        }

        if ($this->path === null) {
            return $this->kind->value.' v'.$this->version.': '.$this->message;
        }

        return $this->kind->value.' v'.$this->version.': '.$this->path.' — '.$this->message;
    }
}
