<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;
        $driverId = $this->route('driver')?->id;

        return [
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
                Rule::unique('drivers', 'user_id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($driverId),
            ],
            'name' => ['required', 'string', 'max:120'],
            'employee_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('drivers', 'employee_number')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($driverId),
            ],
            'license_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('drivers', 'license_number')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($driverId),
            ],
            'license_class' => ['nullable', 'string', 'max:30'],
            'license_expiry_date' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'hire_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(config('fleet.driver.statuses'))],
            'notes' => ['nullable', 'string'],
        ];
    }
}
