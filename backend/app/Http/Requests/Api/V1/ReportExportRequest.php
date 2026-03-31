<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'type' => ['required', Rule::in(array_keys(config('fleet.reports.types', [])))],
            'format' => ['required', Rule::in(config('fleet.reports.export_formats', ['csv']))],
            'search' => ['nullable', 'string', 'max:100'],
            'filter' => ['nullable', 'array'],
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
