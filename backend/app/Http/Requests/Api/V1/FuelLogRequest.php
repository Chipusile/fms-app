<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ServiceProvider;
use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FuelLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'vehicle_id' => [
                'required',
                Rule::exists('vehicles', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'driver_id' => [
                'nullable',
                Rule::exists('drivers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'trip_id' => [
                'nullable',
                Rule::exists('trips', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'service_provider_id' => [
                'nullable',
                Rule::exists('service_providers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'reference_number' => ['nullable', 'string', 'max:50'],
            'fuel_type' => ['required', Rule::in(config('fleet.vehicle.fuel_types'))],
            'quantity_liters' => ['required', 'numeric', 'min:0.01'],
            'cost_per_liter' => ['required', 'numeric', 'min:0'],
            'odometer_reading' => ['required', 'integer', 'min:0'],
            'is_full_tank' => ['required', 'boolean'],
            'fueled_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('trip_id')) {
                $trip = Trip::query()->find($this->input('trip_id'));

                if ($trip && (int) $trip->vehicle_id !== (int) $this->input('vehicle_id')) {
                    $validator->errors()->add('trip_id', 'The selected trip is not associated with the selected vehicle.');
                }
            }

            if ($this->filled('service_provider_id')) {
                $serviceProvider = ServiceProvider::query()->find($this->input('service_provider_id'));

                if ($serviceProvider && $serviceProvider->provider_type !== 'fuel_station') {
                    $validator->errors()->add('service_provider_id', 'The selected service provider must be a fuel station.');
                }
            }
        });
    }
}
