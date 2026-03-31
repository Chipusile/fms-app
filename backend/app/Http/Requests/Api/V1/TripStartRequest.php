<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class TripStartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_odometer' => ['required', 'integer', 'min:0'],
            'actual_start' => ['nullable', 'date'],
        ];
    }
}
