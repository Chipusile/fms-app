<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleComponentRetireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['retired', 'failed'])],
            'removed_at' => ['nullable', 'date'],
            'removed_odometer' => ['nullable', 'integer', 'min:0'],
            'removal_reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
