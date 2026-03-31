<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;
        $vehicleId = $this->route('vehicle')?->id;

        return [
            'vehicle_type_id' => [
                'required',
                Rule::exists('vehicle_types', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'registration_number' => [
                'required',
                'string',
                'max:30',
                Rule::unique('vehicles', 'registration_number')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($vehicleId),
            ],
            'asset_tag' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('vehicles', 'asset_tag')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($vehicleId),
            ],
            'vin' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('vehicles', 'vin')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($vehicleId),
            ],
            'make' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'min:1990', 'max:'.(now()->year + 1)],
            'color' => ['nullable', 'string', 'max:50'],
            'fuel_type' => ['required', Rule::in(config('fleet.vehicle.fuel_types'))],
            'transmission_type' => ['nullable', Rule::in(config('fleet.vehicle.transmission_types'))],
            'ownership_type' => ['required', Rule::in(config('fleet.vehicle.ownership_types'))],
            'status' => ['required', Rule::in(config('fleet.vehicle.statuses'))],
            'seating_capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'tank_capacity_liters' => ['nullable', 'numeric', 'min:0'],
            'odometer_reading' => ['nullable', 'integer', 'min:0'],
            'acquisition_date' => ['nullable', 'date', 'before_or_equal:today'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
