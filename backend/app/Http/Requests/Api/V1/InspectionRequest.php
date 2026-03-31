<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'inspection_template_id' => [
                'required',
                Rule::exists('inspection_templates', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
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
            'performed_at' => ['required', 'date', 'before_or_equal:now'],
            'odometer_reading' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'responses' => ['required', 'array', 'min:1'],
            'responses.*.template_item_id' => ['required', 'integer'],
            'responses.*.response_value' => ['present'],
            'responses.*.is_pass' => ['nullable', 'boolean'],
            'responses.*.defect_severity' => ['nullable', Rule::in(config('fleet.inspection.defect_severities'))],
            'responses.*.defect_summary' => ['nullable', 'string'],
            'responses.*.notes' => ['nullable', 'string'],
        ];
    }
}
