<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'department_id' => $this->department_id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'employee_number' => $this->employee_number,
            'license_number' => $this->license_number,
            'license_class' => $this->license_class,
            'license_expiry_date' => optional($this->license_expiry_date)->toDateString(),
            'phone' => $this->phone,
            'email' => $this->email,
            'hire_date' => optional($this->hire_date)->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department?->id,
                'name' => $this->department?->name,
                'code' => $this->department?->code,
            ]),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
