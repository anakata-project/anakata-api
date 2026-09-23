<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';
    case Pdf = 'pdf';

    public function column(): string
    {
        return match ($this) {
            self::Csv => 'csv_path',
            self::Xlsx => 'xlsx_path',
            self::Pdf => 'pdf_path',
        };
    }
}
