<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Actions\Action;
use App\Enums\ReportFormat;
use App\Models\ReportRun;
use App\Models\User;
use App\Support\History\History;

final class RecordReportDownload extends Action
{
    public function handle(ReportRun $run, ReportFormat $format, User $actor): void
    {
        $this->transaction(function () use ($run, $format, $actor): void {
            History::record($run, 'report.downloaded', after: [
                'format' => $format->value,
            ], actor: $actor);
        });
    }
}
