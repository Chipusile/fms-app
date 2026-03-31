<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'filter.date_from' => ['nullable', 'date'],
            'filter.date_to' => ['nullable', 'date', 'after_or_equal:filter.date_from'],
            'filter.vehicle_id' => [
                'nullable',
                Rule::exists('vehicles', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'filter.department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'filter.status' => ['nullable', 'string', 'max:50'],
            'filter.category' => ['nullable', Rule::in(config('fleet.compliance_item.categories', []))],
            'filter.severity' => ['nullable', Rule::in(config('fleet.incident.severities', []))],
        ];
    }
}
