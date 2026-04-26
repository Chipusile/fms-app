<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->is_super_admin === true
            || $user?->hasPermission('users.create') === true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->where('tenant_id', $tenantId),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => [
                Rule::exists('roles', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
