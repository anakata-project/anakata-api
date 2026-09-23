<?php

declare(strict_types=1);

namespace App\Jobs\Reports;

use App\Actions\Reports\GenerateReport;
use App\Enums\ReportRunStatus;
use App\Models\ReportRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class GenerateReportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $reportRunId) {}

    public function handle(GenerateReport $generate): void
    {
        $run = ReportRun::query()->find($this->reportRunId);

        if ($run instanceof ReportRun && $run->status === ReportRunStatus::Queued) {
            $generate->handle($run);
        }
    }
}
