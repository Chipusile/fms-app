<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplianceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'compliant_type' => $this->compliantTypeKey(),
            'compliant_id' => $this->compliant_id,
            'title' => $this->title,
            'category' => $this->category,
            'reference_number' => $this->reference_number,
            'issuer' => $this->issuer,
            'issue_date' => $this->issue_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'reminder_days' => $this->reminder_days,
            'status' => $this->status,
            'last_reminded_at' => $this->last_reminded_at?->toISOString(),
            'renewed_at' => $this->renewed_at?->toISOString(),
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'days_until_expiry' => $this->expiry_date ? now()->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false) : null,
            'compliant' => $this->whenLoaded('compliant', fn () => [
                'id' => $this->compliant?->id,
                'label' => $this->compliantLabel(),
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function compliantTypeKey(): ?string
    {
        foreach (config('fleet.compliance_item.compliants', []) as $key => $class) {
            if ($class === $this->compliant_type) {
                return (string) $key;
            }
        }

        return null;
    }

    private function compliantLabel(): string
    {
        return match ($this->compliant_type) {
            config('fleet.compliance_item.compliants.vehicle') => $this->compliant?->registration_number ?? 'Vehicle',
            config('fleet.compliance_item.compliants.driver') => $this->compliant?->name ?? 'Driver',
            default => 'Entity',
        };
    }
}
