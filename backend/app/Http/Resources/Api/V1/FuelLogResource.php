<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FuelLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'vehicle_id' => $this->vehicle_id,
            'driver_id' => $this->driver_id,
            'trip_id' => $this->trip_id,
            'service_provider_id' => $this->service_provider_id,
            'reference_number' => $this->reference_number,
            'fuel_type' => $this->fuel_type,
            'quantity_liters' => $this->quantity_liters,
            'cost_per_liter' => $this->cost_per_liter,
            'total_cost' => $this->total_cost,
            'odometer_reading' => $this->odometer_reading,
            'is_full_tank' => $this->is_full_tank,
            'fueled_at' => $this->fueled_at?->toISOString(),
            'notes' => $this->notes,
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
            'trip' => $this->whenLoaded('trip', fn () => [
                'id' => $this->trip?->id,
                'trip_number' => $this->trip?->trip_number,
                'status' => $this->trip?->status,
            ]),
            'service_provider' => $this->whenLoaded('serviceProvider', fn () => [
                'id' => $this->serviceProvider?->id,
                'name' => $this->serviceProvider?->name,
                'provider_type' => $this->serviceProvider?->provider_type,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
