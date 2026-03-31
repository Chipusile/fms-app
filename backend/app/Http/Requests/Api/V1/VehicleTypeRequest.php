<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;
        $vehicleTypeId = $this->route('vehicle_type')?->id ?? $this->route('vehicleType')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('vehicle_types', 'name')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($vehicleTypeId),
            ],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('vehicle_types', 'code')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($vehicleTypeId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'default_fuel_type' => ['nullable', Rule::in(config('fleet.vehicle.fuel_types'))],
            'default_service_interval_km' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
