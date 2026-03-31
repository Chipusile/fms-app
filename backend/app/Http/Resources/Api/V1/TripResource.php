<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'vehicle_id' => $this->vehicle_id,
            'driver_id' => $this->driver_id,
            'requested_by' => $this->requested_by,
            'approved_by' => $this->approved_by,
            'trip_number' => $this->trip_number,
            'purpose' => $this->purpose,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'scheduled_start' => $this->scheduled_start?->toISOString(),
            'scheduled_end' => $this->scheduled_end?->toISOString(),
            'actual_start' => $this->actual_start?->toISOString(),
            'actual_end' => $this->actual_end?->toISOString(),
            'start_odometer' => $this->start_odometer,
            'end_odometer' => $this->end_odometer,
            'distance_km' => $this->distance_km,
            'status' => $this->status,
            'passengers' => $this->passengers,
            'cargo_description' => $this->cargo_description,
            'notes' => $this->notes,
            'rejection_reason' => $this->rejection_reason,
            'cancellation_reason' => $this->cancellation_reason,
            'metadata' => $this->metadata,
            'approval_required' => (bool) ($this->metadata['approval_required'] ?? true),
            'vehicle' => $this->whenLoaded('vehicle', fn () => [
                'id' => $this->vehicle?->id,
                'registration_number' => $this->vehicle?->registration_number,
                'make' => $this->vehicle?->make,
                'model' => $this->vehicle?->model,
            ]),
            'driver' => $this->whenLoaded('driver', fn () => [
                'id' => $this->driver?->id,
                'user_id' => $this->driver?->user_id,
                'name' => $this->driver?->name,
                'license_number' => $this->driver?->license_number,
            ]),
            'requester' => $this->whenLoaded('requester', fn () => [
                'id' => $this->requester?->id,
                'name' => $this->requester?->name,
                'email' => $this->requester?->email,
            ]),
            'approver' => $this->whenLoaded('approver', fn () => [
                'id' => $this->approver?->id,
                'name' => $this->approver?->name,
                'email' => $this->approver?->email,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
