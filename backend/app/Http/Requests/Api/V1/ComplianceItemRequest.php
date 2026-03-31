<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ComplianceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'compliant_type' => ['required', Rule::in(array_keys(config('fleet.compliance_item.compliants', [])))],
            'compliant_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::in(config('fleet.compliance_item.categories', []))],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'issuer' => ['nullable', 'string', 'max:160'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'reminder_days' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tenantId = $this->user()?->tenant_id;
            $type = $this->string('compliant_type')->toString();
            $id = $this->integer('compliant_id');

            if (! $type || ! $id) {
                return;
            }

            $exists = match ($type) {
                'vehicle' => Vehicle::query()->where('tenant_id', $tenantId)->whereKey($id)->exists(),
                'driver' => Driver::query()->where('tenant_id', $tenantId)->whereKey($id)->exists(),
                default => false,
            };

            if (! $exists) {
                $validator->errors()->add('compliant_id', 'Selected compliance entity is invalid for this tenant.');
            }
        });
    }
}
