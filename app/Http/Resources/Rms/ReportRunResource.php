<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Enums\ReportFormat;
use App\Enums\ReportRunStatus;
use App\Models\ReportRun;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ReportRun $resource
 */
#[SchemaName('ReportRunResource')]
class ReportRunResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     definition_key: string,
     *     parameters: array<string, mixed>,
     *     window_from: string,
     *     window_to: string,
     *     requested_by: int|null,
     *     status: ReportRunStatus,
     *     error: string|null,
     *     rows: int,
     *     generated_at: string|null,
     *     formats: list<ReportFormat>,
     *     purged_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        $run = $this->resource;
        $formats = [];

        if ($run->csv_path !== null) {
            $formats[] = ReportFormat::Csv;
        }

        if ($run->xlsx_path !== null) {
            $formats[] = ReportFormat::Xlsx;
        }

        if ($run->pdf_path !== null) {
            $formats[] = ReportFormat::Pdf;
        }

        return [
            'id' => $run->id,
            'definition_key' => $run->definition_key,
            'parameters' => $run->parameters,
            'window_from' => $run->window_from->toDateString(),
            'window_to' => $run->window_to->toDateString(),
            'requested_by' => $run->requested_by,
            'status' => $run->status,
            'error' => $run->error,
            'rows' => $run->rows,
            'generated_at' => $run->generated_at?->toJSON(),
            'formats' => $formats,
            'purged_at' => $run->purged_at?->toJSON(),
        ];
    }
}
