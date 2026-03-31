<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MaintenanceScheduleRequest extends FormRequest
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
            'service_provider_id' => [
                'nullable',
                Rule::exists('service_providers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'title' => ['required', 'string', 'max:160'],
            'schedule_type' => ['required', Rule::in(config('fleet.maintenance_schedule.types', []))],
            'status' => ['required', Rule::in(config('fleet.maintenance_schedule.statuses', []))],
            'interval_days' => ['nullable', 'integer', 'min:1'],
            'interval_km' => ['nullable', 'integer', 'min:1'],
            'reminder_days' => ['nullable', 'integer', 'min:1'],
            'reminder_km' => ['nullable', 'integer', 'min:1'],
            'last_performed_at' => ['nullable', 'date', 'before_or_equal:now'],
            'last_performed_km' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('interval_days') && ! $this->filled('interval_km')) {
                $validator->errors()->add('interval_days', 'At least one schedule interval must be configured.');
            }
        });
    }
}
