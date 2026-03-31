<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class TripCompleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'end_odometer' => ['required', 'integer', 'min:0'],
            'actual_end' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
