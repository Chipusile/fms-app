<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AssetDocument;
use App\Models\Driver;
use App\Models\ServiceProvider;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'documentable_type' => AssetDocument::resolveDocumentableAlias($this->documentable_type),
            'documentable_id' => $this->documentable_id,
            'name' => $this->name,
            'document_type' => $this->document_type,
            'document_number' => $this->document_number,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'has_file' => filled($this->file_path),
            'download_url' => $this->file_path ? route('asset-documents.download', $this->resource) : null,
            'issue_date' => optional($this->issue_date)->toDateString(),
            'expiry_date' => optional($this->expiry_date)->toDateString(),
            'status' => $this->status,
            'metadata' => $this->metadata,
            'notes' => $this->notes,
            'documentable' => $this->whenLoaded('documentable', function (): ?array {
                $documentable = $this->documentable;

                if (! $documentable) {
                    return null;
                }

                return match ($this->documentable_type) {
                    Vehicle::class => [
                        'id' => $documentable->id,
                        'label' => $documentable->registration_number,
                        'secondary' => trim($documentable->make.' '.$documentable->model),
                    ],
                    Driver::class => [
                        'id' => $documentable->id,
                        'label' => $documentable->name,
                        'secondary' => $documentable->license_number,
                    ],
                    ServiceProvider::class => [
                        'id' => $documentable->id,
                        'label' => $documentable->name,
                        'secondary' => $documentable->provider_type,
                    ],
                    default => [
                        'id' => $documentable->id,
                        'label' => method_exists($documentable, '__toString') ? (string) $documentable : class_basename($this->documentable_type),
                        'secondary' => null,
                    ],
                };
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
