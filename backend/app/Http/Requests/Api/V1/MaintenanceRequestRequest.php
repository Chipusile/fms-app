<?php

namespace App\Http\Requests\Api\V1;

use App\Models\MaintenanceSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MaintenanceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'maintenance_schedule_id' => [
                'nullable',
                Rule::exists('maintenance_schedules', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'vehicle_id' => [
                'required',
                Rule::exists('vehicles', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'service_provider_id' => [
                'nullable',
                Rule::exists('service_providers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'title' => ['required', 'string', 'max:160'],
            'request_type' => ['required', Rule::in(config('fleet.maintenance_request.types', []))],
            'priority' => ['required', Rule::in(config('fleet.maintenance_request.priorities', []))],
            'needed_by' => ['nullable', 'date'],
            'odometer_reading' => ['nullable', 'integer', 'min:0'],
            'description' => ['required', 'string'],
            'review_notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('maintenance_schedule_id')) {
                return;
            }

            $schedule = MaintenanceSchedule::query()->find($this->integer('maintenance_schedule_id'));

            if ($schedule && $schedule->vehicle_id !== $this->integer('vehicle_id')) {
                $validator->errors()->add('vehicle_id', 'Selected vehicle must match the linked maintenance schedule.');
            }
        });
    }
}
