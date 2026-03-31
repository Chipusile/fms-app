<?php

namespace App\Services\Reporting;

use App\Jobs\GenerateReportExport;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ReportExportService
{
    public function __construct(
        private readonly ReportQueryService $reportQueryService,
    ) {
    }

    public function queueExport(User $actor, array $payload): ReportExport
    {
        $reportExport = ReportExport::create([
            'tenant_id' => $actor->tenant_id,
            'requested_by' => $actor->id,
            'report_type' => $payload['type'],
            'format' => $payload['format'],
            'status' => 'queued',
            'filters' => $this->extractFilters($payload),
        ]);

        GenerateReportExport::dispatch($reportExport->id)
            ->onQueue(config('fleet.reports.export_queue', 'reports'));

        return $reportExport->fresh(['requester']);
    }

    public function generate(ReportExport $reportExport): ReportExport
    {
        $reportExport->update([
            'status' => 'processing',
            'started_at' => now(),
            'failed_at' => null,
            'error_message' => null,
        ]);

        try {
            $report = $this->reportQueryService->exportDataset(
                $reportExport->report_type,
                $reportExport->tenant_id,
                $reportExport->filters ?? [],
            );

            $csv = $this->toCsv($report['columns'], $report['rows']);
            $disk = config('fleet.reports.export_disk', config('filesystems.default'));
            $timestamp = now()->format('Ymd-His');
            $slug = Str::slug($reportExport->report_type);
            $fileName = "{$slug}-{$timestamp}.csv";
            $path = "report-exports/tenant-{$reportExport->tenant_id}/{$fileName}";

            Storage::disk($disk)->put($path, $csv);

            $reportExport->update([
                'status' => 'completed',
                'completed_at' => now(),
                'file_name' => $fileName,
                'file_path' => $path,
                'storage_disk' => $disk,
                'mime_type' => 'text/csv',
                'row_count' => count($report['rows']),
                'metadata' => [
                    'title' => $report['title'],
                    'columns' => $report['columns'],
                    'filters' => $report['filters'],
                ],
            ]);
        } catch (Throwable $throwable) {
            $reportExport->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }

        return $reportExport->fresh(['requester']);
    }

    public function downloadResponse(ReportExport $reportExport): StreamedResponse
    {
        return Storage::disk($reportExport->storage_disk)->download(
            $reportExport->file_path,
            $reportExport->file_name,
            ['Content-Type' => $reportExport->mime_type ?? 'text/csv'],
        );
    }

    private function extractFilters(array $payload): array
    {
        return [
            'search' => $payload['search'] ?? '',
            'filter' => $payload['filter'] ?? [],
        ];
    }

    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function toCsv(array $columns, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        fputcsv($stream, collect($columns)->pluck('label')->all());

        foreach ($rows as $row) {
            fputcsv($stream, collect($columns)->map(function (array $column) use ($row) {
                $value = $row[$column['key']] ?? null;

                if (is_bool($value)) {
                    return $value ? 'Yes' : 'No';
                }

                if (is_array($value)) {
                    return json_encode($value);
                }

                return $value;
            })->all());
        }

        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
    }
}
