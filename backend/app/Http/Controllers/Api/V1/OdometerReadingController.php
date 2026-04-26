<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\ManualOdometerReadingRequest;
use App\Http\Requests\Api\V1\ResolveOdometerAnomalyRequest;
use App\Http\Resources\Api\V1\OdometerReadingResource;
use App\Models\Driver;
use App\Models\OdometerReading;
use App\Models\Vehicle;
use App\Services\Operations\OdometerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OdometerReadingController extends Controller
{
    public function __construct(
        private readonly OdometerService $odometerService,
    ) {}

    public function supportData(Request $request): JsonResponse
    {
        if (! $request->user()?->hasAnyPermission(['odometer.view', 'odometer.create', 'odometer.update'])) {
            return ApiResponse::forbidden('You do not have permission to access odometer support data.');
        }

        return ApiResponse::success([
            'sources' => config('fleet.odometer.sources', []),
            'vehicles' => Vehicle::query()
                ->where('status', 'active')
                ->orderBy('registration_number')
                ->get(['id', 'registration_number', 'make', 'model', 'odometer_reading'])
                ->map(fn (Vehicle $vehicle) => [
                    'id' => $vehicle->id,
                    'label' => $vehicle->registration_number,
                    'secondary' => trim($vehicle->make.' '.$vehicle->model),
                    'odometer_reading' => $vehicle->odometer_reading,
                ]),
            'drivers' => Driver::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'license_number'])
                ->map(fn (Driver $driver) => [
                    'id' => $driver->id,
                    'label' => $driver->name,
                    'secondary' => $driver->license_number,
                ]),
        ]);
    }

    public function byVehicle(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('viewAny', OdometerReading::class);

        $readings = OdometerReading::query()
            ->with(['vehicle', 'driver', 'resolver'])
            ->where('vehicle_id', $vehicle->id)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request, 15));

        return ApiResponse::success(
            OdometerReadingResource::collection($readings),
            meta: [
                'current_page' => $readings->currentPage(),
                'last_page' => $readings->lastPage(),
                'per_page' => $readings->perPage(),
                'total' => $readings->total(),
            ]
        );
    }

    public function anomalies(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OdometerReading::class);

        $readings = OdometerReading::query()
            ->with(['vehicle', 'driver', 'resolver'])
            ->where('is_anomaly', true)
            ->when(! $request->boolean('include_resolved'), fn ($query) => $query->whereNull('resolved_at'))
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request, 15));

        return ApiResponse::success(
            OdometerReadingResource::collection($readings),
            meta: [
                'current_page' => $readings->currentPage(),
                'last_page' => $readings->lastPage(),
                'per_page' => $readings->perPage(),
                'total' => $readings->total(),
            ]
        );
    }

    public function store(ManualOdometerReadingRequest $request): JsonResponse
    {
        $this->authorize('create', OdometerReading::class);

        $vehicle = Vehicle::query()->findOrFail($request->integer('vehicle_id'));

        $reading = $this->odometerService->record(
            vehicle: $vehicle,
            reading: $request->integer('reading'),
            source: 'manual',
            sourceReferenceId: null,
            recordedAt: $request->input('recorded_at'),
            driverId: $request->integer('driver_id') ?: null,
            notes: $request->string('notes')->toString() ?: null,
        );

        return ApiResponse::created(
            new OdometerReadingResource($reading->load(['vehicle', 'driver', 'resolver'])),
            'Odometer reading recorded successfully.'
        );
    }

    public function resolveAnomaly(ResolveOdometerAnomalyRequest $request, OdometerReading $odometerReading): JsonResponse
    {
        $this->authorize('resolve', $odometerReading);

        if (! $odometerReading->is_anomaly) {
            throw ValidationException::withMessages([
                'odometer_reading' => ['Only readings flagged as anomalies can be resolved.'],
            ]);
        }

        $odometerReading = $this->odometerService->resolve(
            $odometerReading,
            $request->user(),
            $request->string('resolution_notes')->toString() ?: null,
        );

        return ApiResponse::success(new OdometerReadingResource($odometerReading), 'Odometer anomaly resolved successfully.');
    }
}
