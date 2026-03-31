<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class VehicleComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;
        $componentId = $this->route('vehicleComponent')?->id;

        return [
            'vehicle_id' => [
                'required',
                Rule::exists('vehicles', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'service_provider_id' => [
                'nullable',
                Rule::exists('service_providers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'component_type' => ['required', Rule::in(config('fleet.vehicle_component.types', []))],
            'position_code' => ['nullable', 'string', 'max:40'],
            'brand' => ['nullable', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:80'],
            'serial_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('vehicle_components', 'serial_number')
                    ->ignore($componentId)
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'status' => ['required', Rule::in(config('fleet.vehicle_component.statuses', []))],
            'condition_status' => ['required', Rule::in(config('fleet.vehicle_component.condition_statuses', []))],
            'installed_at' => ['nullable', 'date'],
            'installed_odometer' => ['nullable', 'integer', 'min:0'],
            'expected_life_days' => ['nullable', 'integer', 'min:1'],
            'expected_life_km' => ['nullable', 'integer', 'min:1'],
            'reminder_days' => ['nullable', 'integer', 'min:1'],
            'reminder_km' => ['nullable', 'integer', 'min:1'],
            'warranty_expiry_date' => ['nullable', 'date'],
            'last_inspected_at' => ['nullable', 'date'],
            'removed_at' => ['nullable', 'date', 'after_or_equal:installed_at'],
            'removed_odometer' => ['nullable', 'integer', 'min:0'],
            'removal_reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('expected_life_days') && ! $this->filled('expected_life_km')) {
                $validator->errors()->add('expected_life_days', 'At least one replacement lifecycle threshold must be configured.');
            }
        });
    }
}
