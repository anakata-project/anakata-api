<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Enums\ReportFormat;
use App\Services\Documents\PdfRenderer;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

final class ReportFiles
{
    public function __construct(private readonly PdfRenderer $pdf) {}

    /**
     * @param  array{headers: list<string>, rows: list<list<int|string>>}  $table
     * @param  list<ReportFormat>  $formats
     * @return array<string, string>
     */
    public function render(string $title, array $table, array $formats): array
    {
        $written = [];

        foreach ($formats as $format) {
            $written[$format->value] = match ($format) {
                ReportFormat::Csv => $this->csv($table),
                ReportFormat::Xlsx => $this->xlsx($table),
                ReportFormat::Pdf => $this->pdf($title, $table),
            };
        }

        return $written;
    }

    /**
     * @param  array{headers: list<string>, rows: list<list<int|string>>}  $table
     */
    private function csv(array $table): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Could not build the report CSV.');
        }

        fputcsv($handle, $table['headers']);

        foreach ($table['rows'] as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        if ($csv === false) {
            throw new RuntimeException('Could not build the report CSV.');
        }

        return $csv;
    }

    /**
     * @param  array{headers: list<string>, rows: list<list<int|string>>}  $table
     */
    private function xlsx(array $table): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'report');

        if ($tmp === false) {
            throw new RuntimeException('Could not build the report spreadsheet.');
        }

        $writer = new Writer;
        $writer->openToFile($tmp);
        $writer->addRow(Row::fromValues($table['headers']));

        foreach ($table['rows'] as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();
        $bytes = file_get_contents($tmp);
        unlink($tmp);

        if ($bytes === false) {
            throw new RuntimeException('Could not build the report spreadsheet.');
        }

        return $bytes;
    }

    /**
     * @param  array{headers: list<string>, rows: list<list<int|string>>}  $table
     */
    private function pdf(string $title, array $table): string
    {
        $html = '<html><body><h1>'.e($title).'</h1><table border="1" cellpadding="4" cellspacing="0"><thead><tr>';

        foreach ($table['headers'] as $header) {
            $html .= '<th>'.e($header).'</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($table['rows'] as $row) {
            $html .= '<tr>';

            foreach ($row as $cell) {
                $html .= '<td>'.e((string) $cell).'</td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table></body></html>';

        return $this->pdf->render($html);
    }
}
