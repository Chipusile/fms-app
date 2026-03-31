<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->is_super_admin === true
            || $user?->hasPermission('users.update') === true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;
        $tenantId = $this->user()->tenant_id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users')->where('tenant_id', $tenantId)->ignore($userId),
            ],
            'password' => ['sometimes', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => [
                Rule::exists('roles', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
