<?php

declare(strict_types=1);

namespace App\Support\Config;

final readonly class Change
{
    public function __construct(
        public string $path,
        public string $label,
        public mixed $from,
        public mixed $to,
    ) {}

    /**
     * @return array{path: string, label: string, from: mixed, to: mixed}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'label' => $this->label,
            'from' => $this->from,
            'to' => $this->to,
        ];
    }
}
