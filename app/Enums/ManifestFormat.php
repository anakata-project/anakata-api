<?php

declare(strict_types=1);

namespace App\Enums;

enum ManifestFormat: string
{
    case Pdf = 'pdf';
    case Csv = 'csv';
    case Xlsx = 'xlsx';

    public function contentType(): string
    {
        return match ($this) {
            self::Pdf => 'application/pdf',
            self::Csv => 'text/csv; charset=UTF-8',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }
}
