<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OdometerReadingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'vehicle_id' => $this->vehicle_id,
            'driver_id' => $this->driver_id,
            'reading' => $this->reading,
            'source' => $this->source,
            'source_reference_id' => $this->source_reference_id,
            'recorded_at' => $this->recorded_at?->toISOString(),
            'notes' => $this->notes,
            'is_anomaly' => $this->is_anomaly,
            'resolved_at' => $this->resolved_at?->toISOString(),
            'resolved_by' => $this->resolved_by,
            'resolution_notes' => $this->resolution_notes,
            'vehicle' => $this->whenLoaded('vehicle', fn () => [
                'id' => $this->vehicle?->id,
                'registration_number' => $this->vehicle?->registration_number,
                'make' => $this->vehicle?->make,
                'model' => $this->vehicle?->model,
            ]),
            'driver' => $this->whenLoaded('driver', fn () => [
                'id' => $this->driver?->id,
                'name' => $this->driver?->name,
                'license_number' => $this->driver?->license_number,
            ]),
            'resolver' => $this->whenLoaded('resolver', fn () => [
                'id' => $this->resolver?->id,
                'name' => $this->resolver?->name,
                'email' => $this->resolver?->email,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
