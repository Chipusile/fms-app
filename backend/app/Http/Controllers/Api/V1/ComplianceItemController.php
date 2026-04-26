<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\ComplianceItemRequest;
use App\Http\Resources\Api\V1\ComplianceItemResource;
use App\Models\ComplianceItem;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Services\Maintenance\ComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplianceItemController extends Controller
{
    public function __construct(
        private readonly ComplianceService $complianceService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ComplianceItem::class);

        $this->complianceService->refreshStatuses($request->user()?->tenant_id);

        $items = ComplianceItem::query()
            ->with('compliant')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search')->trim().'%';

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('title', 'ilike', $search)
                        ->orWhere('reference_number', 'ilike', $search)
                        ->orWhere('issuer', 'ilike', $search);
                });
            })
            ->when($request->filled('filter.status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->filled('filter.category'), fn ($query, $category) => $query->where('category', $category))
            ->when($request->filled('filter.compliant_type'), function ($query, $type) {
                $class = config("fleet.compliance_item.compliants.{$type}");

                if ($class) {
                    $query->where('compliant_type', $class);
                }
            })
            ->orderBy('expiry_date')
            ->orderBy('title')
            ->paginate($this->perPage($request, 15));

        return ApiResponse::success(
            ComplianceItemResource::collection($items),
            meta: [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ]
        );
    }

    public function supportData(Request $request): JsonResponse
    {
        if (! $request->user()?->hasAnyPermission(['compliance.view', 'compliance.create', 'compliance.update'])) {
            return ApiResponse::forbidden('You do not have permission to access compliance support data.');
        }

        return ApiResponse::success([
            'categories' => config('fleet.compliance_item.categories', []),
            'statuses' => config('fleet.compliance_item.statuses', []),
            'compliant_types' => array_keys(config('fleet.compliance_item.compliants', [])),
            'vehicles' => Vehicle::query()
                ->orderBy('registration_number')
                ->get(['id', 'registration_number', 'make', 'model'])
                ->map(fn (Vehicle $vehicle) => [
                    'id' => $vehicle->id,
                    'label' => $vehicle->registration_number,
                    'secondary' => trim($vehicle->make.' '.$vehicle->model),
                ]),
            'drivers' => Driver::query()
                ->orderBy('name')
                ->get(['id', 'name', 'license_number'])
                ->map(fn (Driver $driver) => [
                    'id' => $driver->id,
                    'label' => $driver->name,
                    'secondary' => $driver->license_number,
                ]),
        ]);
    }

    public function dashboard(): JsonResponse
    {
        $this->authorize('viewAny', ComplianceItem::class);

        return ApiResponse::success([
            ...$this->complianceService->dashboard(),
            'expiring_items' => ComplianceItemResource::collection($this->complianceService->expiringItems(limit: 5)),
        ]);
    }

    public function expiring(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ComplianceItem::class);

        $days = $request->filled('days') ? $request->integer('days') : null;

        return ApiResponse::success(
            ComplianceItemResource::collection($this->complianceService->expiringItems($days, 20))
        );
    }

    public function store(ComplianceItemRequest $request): JsonResponse
    {
        $this->authorize('create', ComplianceItem::class);

        $item = $this->complianceService->create($request->validated());

        return ApiResponse::created(new ComplianceItemResource($item), 'Compliance item created successfully.');
    }

    public function show(ComplianceItem $complianceItem): JsonResponse
    {
        $this->authorize('view', $complianceItem);

        $this->complianceService->refreshStatuses($complianceItem->tenant_id);

        return ApiResponse::success(new ComplianceItemResource($complianceItem->fresh()->load('compliant')));
    }

    public function update(ComplianceItemRequest $request, ComplianceItem $complianceItem): JsonResponse
    {
        $this->authorize('update', $complianceItem);

        $item = $this->complianceService->update($complianceItem, $request->validated());

        return ApiResponse::success(new ComplianceItemResource($item), 'Compliance item updated successfully.');
    }

    public function destroy(ComplianceItem $complianceItem): JsonResponse
    {
        $this->authorize('delete', $complianceItem);

        $complianceItem->delete();

        return ApiResponse::noContent('Compliance item deleted successfully.');
    }
}
