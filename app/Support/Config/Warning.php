<?php

declare(strict_types=1);

namespace App\Support\Config;

final readonly class Warning
{
    public function __construct(
        public string $path,
        public string $message,
    ) {}

    /**
     * @return array{path: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'message' => $this->message,
        ];
    }
}
