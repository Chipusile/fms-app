<?php

namespace App\Services\Maintenance;

use App\Models\ComplianceItem;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ComplianceService
{
    public function create(array $payload): ComplianceItem
    {
        $attributes = $this->buildAttributes($payload);

        return ComplianceItem::create($attributes)->load('compliant');
    }

    public function update(ComplianceItem $complianceItem, array $payload): ComplianceItem
    {
        $attributes = $this->buildAttributes($payload, $complianceItem);

        $complianceItem->update($attributes);

        return $complianceItem->fresh(['compliant']);
    }

    public function refreshStatuses(?int $tenantId = null): void
    {
        ComplianceItem::query()
            ->when($tenantId, fn (Builder $query) => $query->where('tenant_id', $tenantId))
            ->get()
            ->each(function (ComplianceItem $item): void {
                $computed = $this->determineStatus($item->expiry_date, $item->reminder_days, $item->status, $item->tenant_id);

                if ($computed !== $item->status) {
                    $item->update(['status' => $computed]);
                }
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(?int $tenantId = null): array
    {
        $this->refreshStatuses($tenantId);

        $items = ComplianceItem::query()
            ->when($tenantId, fn (Builder $query) => $query->where('tenant_id', $tenantId))
            ->get();
        $statusCounts = $items->groupBy('status')->map->count();
        $categoryCounts = $items->groupBy('category')->map->count();

        return [
            'totals' => [
                'all' => $items->count(),
                'valid' => $statusCounts->get('valid', 0),
                'expiring_soon' => $statusCounts->get('expiring_soon', 0),
                'expired' => $statusCounts->get('expired', 0),
                'waived' => $statusCounts->get('waived', 0),
            ],
            'by_category' => $categoryCounts->sortKeys()->all(),
            'entity_mix' => [
                'vehicles' => $items->where('compliant_type', config('fleet.compliance_item.compliants.vehicle'))->count(),
                'drivers' => $items->where('compliant_type', config('fleet.compliance_item.compliants.driver'))->count(),
            ],
        ];
    }

    /**
     * @return Collection<int, ComplianceItem>
     */
    public function expiringItems(?int $days = null, int $limit = 10, ?int $tenantId = null): Collection
    {
        $this->refreshStatuses($tenantId);

        $window = $days ?? (int) ($tenantId
            ? Setting::getTenantValue($tenantId, 'compliance.reminder_days', 30)
            : Setting::getValue('compliance.reminder_days', 30));
        $cutoff = now()->copy()->addDays($window)->endOfDay();

        return ComplianceItem::query()
            ->with('compliant')
            ->when($tenantId, fn (Builder $query) => $query->where('tenant_id', $tenantId))
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $cutoff)
            ->whereDate('expiry_date', '>=', now()->startOfDay())
            ->whereIn('status', ['valid', 'expiring_soon'])
            ->orderBy('expiry_date')
            ->take($limit)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAttributes(array $payload, ?ComplianceItem $existing = null): array
    {
        $typeMap = config('fleet.compliance_item.compliants', []);
        $compliantTypeKey = $payload['compliant_type'] ?? $this->compliantKey($existing?->compliant_type);
        $resolvedType = $typeMap[$compliantTypeKey] ?? $existing?->compliant_type;
        $issueDate = isset($payload['issue_date']) ? Carbon::parse($payload['issue_date'])->toDateString() : $existing?->issue_date?->toDateString();
        $expiryDate = isset($payload['expiry_date']) ? Carbon::parse($payload['expiry_date'])->toDateString() : $existing?->expiry_date?->toDateString();
        $previousExpiryDate = $existing?->expiry_date?->toDateString();

        return [
            'compliant_type' => $resolvedType,
            'compliant_id' => $payload['compliant_id'] ?? $existing?->compliant_id,
            'title' => $payload['title'] ?? $existing?->title,
            'category' => $payload['category'] ?? $existing?->category,
            'reference_number' => array_key_exists('reference_number', $payload) ? $payload['reference_number'] : $existing?->reference_number,
            'issuer' => array_key_exists('issuer', $payload) ? $payload['issuer'] : $existing?->issuer,
            'issue_date' => $issueDate,
            'expiry_date' => $expiryDate,
            'reminder_days' => $payload['reminder_days'] ?? $existing?->reminder_days,
            'status' => $this->determineStatus(
                $expiryDate ? Carbon::parse($expiryDate) : null,
                $payload['reminder_days'] ?? $existing?->reminder_days,
                $payload['status'] ?? $existing?->status,
                $existing?->tenant_id
            ),
            'renewed_at' => $this->shouldMarkRenewed($previousExpiryDate, $expiryDate, $existing)
                ? now()
                : $existing?->renewed_at,
            'notes' => array_key_exists('notes', $payload) ? $payload['notes'] : $existing?->notes,
            'metadata' => $existing?->metadata,
        ];
    }

    private function determineStatus(?Carbon $expiryDate, ?int $reminderDays, ?string $currentStatus = null, ?int $tenantId = null): string
    {
        if ($currentStatus === 'waived') {
            return 'waived';
        }

        if (! $expiryDate) {
            return 'valid';
        }

        if ($expiryDate->isPast()) {
            return 'expired';
        }

        $daysWindow = $reminderDays ?? (int) ($tenantId
            ? Setting::getTenantValue($tenantId, 'compliance.reminder_days', 30)
            : Setting::getValue('compliance.reminder_days', 30));

        if ($expiryDate->lessThanOrEqualTo(now()->copy()->addDays($daysWindow)->endOfDay())) {
            return 'expiring_soon';
        }

        return 'valid';
    }

    private function shouldMarkRenewed(?string $previousExpiryDate, ?string $nextExpiryDate, ?ComplianceItem $existing): bool
    {
        if (! $existing || ! $previousExpiryDate || ! $nextExpiryDate) {
            return false;
        }

        return Carbon::parse($nextExpiryDate)->greaterThan(Carbon::parse($previousExpiryDate));
    }

    private function compliantKey(?string $modelClass): ?string
    {
        if (! $modelClass) {
            return null;
        }

        foreach (config('fleet.compliance_item.compliants', []) as $key => $class) {
            if ($class === $modelClass) {
                return (string) $key;
            }
        }

        return null;
    }
}
