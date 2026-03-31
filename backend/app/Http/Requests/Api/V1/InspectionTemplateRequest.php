<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InspectionTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;
        $templateId = $this->route('inspectionTemplate')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('inspection_templates', 'code')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($templateId),
            ],
            'description' => ['nullable', 'string'],
            'applies_to' => ['required', Rule::in(config('fleet.inspection_template.applies_to'))],
            'status' => ['required', Rule::in(config('fleet.inspection_template.statuses'))],
            'requires_review_on_critical' => ['required', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.title' => ['required', 'string', 'max:160'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.response_type' => ['required', Rule::in(config('fleet.inspection_template.response_types'))],
            'items.*.is_required' => ['required', 'boolean'],
            'items.*.triggers_defect_on_fail' => ['required', 'boolean'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
