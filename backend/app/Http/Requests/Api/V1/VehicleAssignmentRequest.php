<?php

namespace App\Http\Requests\Api\V1;

use App\Models\VehicleAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;
        $assignmentId = $this->route('vehicle_assignment')?->id ?? $this->route('vehicleAssignment')?->id;

        return [
            'vehicle_id' => [
                'required',
                Rule::exists('vehicles', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'driver_id' => [
                'nullable',
                Rule::exists('drivers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'assignment_type' => ['required', Rule::in(config('fleet.vehicle_assignment.types'))],
            'status' => ['required', Rule::in(config('fleet.vehicle_assignment.statuses'))],
            'assigned_from' => ['required', 'date'],
            'assigned_to' => ['nullable', 'date', 'after_or_equal:assigned_from'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('driver_id') && ! $this->filled('department_id')) {
                $validator->errors()->add('driver_id', 'A driver or department assignment target is required.');
            }

            if ($validator->errors()->isNotEmpty() || ! $this->filled('vehicle_id') || $this->input('status') !== 'active') {
                return;
            }

            $assignmentId = $this->route('vehicle_assignment')?->id ?? $this->route('vehicleAssignment')?->id;

            $hasOverlap = VehicleAssignment::query()
                ->where('vehicle_id', $this->input('vehicle_id'))
                ->where('status', 'active')
                ->when($assignmentId, fn ($query) => $query->whereKeyNot($assignmentId))
                ->exists();

            if ($hasOverlap) {
                $validator->errors()->add('vehicle_id', 'The selected vehicle already has an active assignment.');
            }
        });
    }
}
