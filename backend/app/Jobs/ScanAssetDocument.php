<?php

namespace App\Jobs;

use App\Models\AssetDocument;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ScanAssetDocument implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $assetDocumentId,
    ) {}

    public function handle(): void
    {
        $assetDocument = AssetDocument::withoutGlobalScopes()->find($this->assetDocumentId);

        if (! $assetDocument || ! $assetDocument->file_path) {
            return;
        }

        if (! config('fleet.asset_document.scan.enabled')) {
            $assetDocument->update([
                'scan_status' => 'clean',
                'scanned_at' => now(),
                'scan_error' => null,
            ]);

            return;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'asset-scan-');

        try {
            file_put_contents(
                $temporaryPath,
                Storage::disk($assetDocument->storage_disk)->get($assetDocument->file_path)
            );

            $result = Process::timeout((int) config('fleet.asset_document.scan.timeout_seconds', 120))
                ->run([config('fleet.asset_document.scan.command', 'clamscan'), '--no-summary', $temporaryPath]);

            match ($result->exitCode()) {
                0 => $assetDocument->update([
                    'scan_status' => 'clean',
                    'scanned_at' => now(),
                    'scan_error' => null,
                ]),
                1 => $assetDocument->update([
                    'scan_status' => 'infected',
                    'scanned_at' => now(),
                    'scan_error' => trim($result->output()."\n".$result->errorOutput()) ?: 'Malware detected.',
                ]),
                default => $assetDocument->update([
                    'scan_status' => 'failed',
                    'scanned_at' => now(),
                    'scan_error' => trim($result->output()."\n".$result->errorOutput()) ?: 'Document scan failed.',
                ]),
            };
        } finally {
            if ($temporaryPath && file_exists($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        AssetDocument::withoutGlobalScopes()
            ->whereKey($this->assetDocumentId)
            ->update([
                'scan_status' => 'failed',
                'scanned_at' => now(),
                'scan_error' => $exception->getMessage(),
            ]);
    }
}
