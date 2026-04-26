<?php

namespace App\Jobs;

use App\Models\ReportExport;
use App\Services\Reporting\ReportExportService;
use App\Services\Workflow\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $reportExportId,
    ) {}

    public function handle(ReportExportService $reportExportService): void
    {
        $reportExport = ReportExport::withoutGlobalScopes()->findOrFail($this->reportExportId);

        $reportExportService->generate($reportExport);
    }

    public function failed(Throwable $exception): void
    {
        $reportExport = ReportExport::withoutGlobalScopes()
            ->with('requester')
            ->find($this->reportExportId);

        if (! $reportExport) {
            return;
        }

        $reportExport->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => $exception->getMessage(),
        ]);

        if ($reportExport->requester) {
            app(NotificationService::class)->notifyUser(
                $reportExport->requester,
                'report_export_failed',
                'Report export failed',
                'Your '.$reportExport->report_type.' export could not be generated.',
                '/reports',
                $reportExport,
                ['report_export_id' => $reportExport->id]
            );
        }
    }
}
