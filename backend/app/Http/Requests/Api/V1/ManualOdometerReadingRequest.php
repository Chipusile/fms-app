<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualOdometerReadingRequest extends FormRequest
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
            'reading' => ['required', 'integer', 'min:0'],
            'recorded_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
