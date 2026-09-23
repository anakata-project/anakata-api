<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Enums\Permission;
use App\Enums\ReportFormat;

final readonly class ReportDefinition
{
    /**
     * @param  list<ReportFormat>  $formats
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $sentence,
        public Permission $permission,
        public array $formats,
    ) {}

    /**
     * @return array{key: string, title: string, sentence: string, permission: string, formats: list<string>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'sentence' => $this->sentence,
            'permission' => $this->permission->value,
            'formats' => array_map(fn (ReportFormat $format): string => $format->value, $this->formats),
        ];
    }
}
