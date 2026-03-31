<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TripRequest extends FormRequest
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
                'required',
                Rule::exists('drivers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'purpose' => ['required', 'string', 'max:500'],
            'origin' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'scheduled_start' => ['required', 'date'],
            'scheduled_end' => ['required', 'date', 'after:scheduled_start'],
            'passengers' => ['nullable', 'integer', 'min:1', 'max:500'],
            'cargo_description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
