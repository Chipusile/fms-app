<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'vehicle_type_id' => $this->vehicle_type_id,
            'department_id' => $this->department_id,
            'registration_number' => $this->registration_number,
            'asset_tag' => $this->asset_tag,
            'vin' => $this->vin,
            'make' => $this->make,
            'model' => $this->model,
            'year' => $this->year,
            'color' => $this->color,
            'fuel_type' => $this->fuel_type,
            'transmission_type' => $this->transmission_type,
            'ownership_type' => $this->ownership_type,
            'status' => $this->status,
            'seating_capacity' => $this->seating_capacity,
            'tank_capacity_liters' => $this->tank_capacity_liters,
            'odometer_reading' => $this->odometer_reading,
            'acquisition_date' => optional($this->acquisition_date)->toDateString(),
            'acquisition_cost' => $this->acquisition_cost,
            'notes' => $this->notes,
            'type' => $this->whenLoaded('type', fn () => [
                'id' => $this->type?->id,
                'name' => $this->type?->name,
                'code' => $this->type?->code,
            ]),
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department?->id,
                'name' => $this->department?->name,
                'code' => $this->department?->code,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
