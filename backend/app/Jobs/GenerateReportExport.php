<?php

namespace App\Jobs;

use App\Models\ReportExport;
use App\Services\Reporting\ReportExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $reportExportId,
    ) {
    }

    public function handle(ReportExportService $reportExportService): void
    {
        $reportExport = ReportExport::withoutGlobalScopes()->findOrFail($this->reportExportId);

        $reportExportService->generate($reportExport);
    }
}
