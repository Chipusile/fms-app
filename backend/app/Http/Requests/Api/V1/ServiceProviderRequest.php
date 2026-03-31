<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;
        $serviceProviderId = $this->route('service_provider')?->id ?? $this->route('serviceProvider')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('service_providers', 'name')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($serviceProviderId),
            ],
            'provider_type' => ['required', Rule::in(config('fleet.service_provider.types'))],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(config('fleet.service_provider.statuses'))],
            'notes' => ['nullable', 'string'],
        ];
    }
}
