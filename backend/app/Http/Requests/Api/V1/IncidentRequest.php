<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncidentRequest extends FormRequest
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
            'assigned_to' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'incident_type' => ['required', Rule::in(config('fleet.incident.types'))],
            'severity' => ['required', Rule::in(config('fleet.incident.severities'))],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'reported_at' => ['nullable', 'date', 'before_or_equal:now'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'immediate_action' => ['nullable', 'string'],
            'injury_count' => ['nullable', 'integer', 'min:0'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
