<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportExportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'requested_by' => $this->requested_by,
            'report_type' => $this->report_type,
            'format' => $this->format,
            'status' => $this->status,
            'filters' => $this->filters,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'row_count' => $this->row_count,
            'error_message' => $this->error_message,
            'download_url' => $this->status === 'completed'
                ? route('reports.exports.download', ['reportExport' => $this->id], false)
                : null,
            'requester' => $this->whenLoaded('requester', fn () => [
                'id' => $this->requester?->id,
                'name' => $this->requester?->name,
                'email' => $this->requester?->email,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'failed_at' => $this->failed_at?->toISOString(),
        ];
    }
}
