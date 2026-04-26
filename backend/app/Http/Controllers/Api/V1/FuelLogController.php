<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\FuelLogRequest;
use App\Http\Resources\Api\V1\FuelLogResource;
use App\Models\Driver;
use App\Models\FuelLog;
use App\Models\ServiceProvider;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\Operations\OdometerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FuelLogController extends Controller
{
    public function __construct(
        private readonly OdometerService $odometerService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FuelLog::class);

        $allowedSorts = ['fueled_at', 'created_at', 'total_cost', 'quantity_liters'];
        $sort = in_array($request->input('sort'), $allowedSorts, true) ? $request->input('sort') : 'fueled_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        $fuelLogs = FuelLog::query()
            ->with(['vehicle', 'driver', 'trip', 'serviceProvider'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search')->trim().'%';

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('reference_number', 'ilike', $search)
                        ->orWhere('notes', 'ilike', $search)
                        ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('registration_number', 'ilike', $search))
                        ->orWhereHas('driver', fn ($driverQuery) => $driverQuery->where('name', 'ilike', $search))
                        ->orWhereHas('trip', fn ($tripQuery) => $tripQuery->where('trip_number', 'ilike', $search))
                        ->orWhereHas('serviceProvider', fn ($providerQuery) => $providerQuery->where('name', 'ilike', $search));
                });
            })
            ->when($request->filled('filter.vehicle_id'), fn ($query, $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($request->filled('filter.driver_id'), fn ($query, $driverId) => $query->where('driver_id', $driverId))
            ->when($request->filled('filter.trip_id'), fn ($query, $tripId) => $query->where('trip_id', $tripId))
            ->when($request->filled('filter.service_provider_id'), fn ($query, $providerId) => $query->where('service_provider_id', $providerId))
            ->when($request->filled('filter.fuel_type'), fn ($query, $fuelType) => $query->where('fuel_type', $fuelType))
            ->orderBy($sort, $direction)
            ->paginate($this->perPage($request, 15));

        return ApiResponse::success(
            FuelLogResource::collection($fuelLogs),
            meta: [
                'current_page' => $fuelLogs->currentPage(),
                'last_page' => $fuelLogs->lastPage(),
                'per_page' => $fuelLogs->perPage(),
                'total' => $fuelLogs->total(),
            ]
        );
    }

    public function supportData(Request $request): JsonResponse
    {
        if (! $request->user()?->hasAnyPermission(['fuel.view', 'fuel.create', 'fuel.update'])) {
            return ApiResponse::forbidden('You do not have permission to access fuel support data.');
        }

        return ApiResponse::success([
            'fuel_types' => config('fleet.vehicle.fuel_types', []),
            'vehicles' => Vehicle::query()
                ->where('status', 'active')
                ->orderBy('registration_number')
                ->get(['id', 'registration_number', 'make', 'model', 'fuel_type', 'odometer_reading'])
                ->map(fn (Vehicle $vehicle) => [
                    'id' => $vehicle->id,
                    'label' => $vehicle->registration_number,
                    'secondary' => trim($vehicle->make.' '.$vehicle->model),
                    'fuel_type' => $vehicle->fuel_type,
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
            'service_providers' => ServiceProvider::query()
                ->where('status', 'active')
                ->where('provider_type', 'fuel_station')
                ->orderBy('name')
                ->get(['id', 'name', 'provider_type'])
                ->map(fn (ServiceProvider $provider) => [
                    'id' => $provider->id,
                    'label' => $provider->name,
                    'secondary' => $provider->provider_type,
                ]),
            'trips' => Trip::query()
                ->whereIn('status', ['approved', 'in_progress', 'completed'])
                ->orderByDesc('scheduled_start')
                ->get(['id', 'trip_number', 'vehicle_id', 'driver_id', 'status'])
                ->map(fn (Trip $trip) => [
                    'id' => $trip->id,
                    'label' => $trip->trip_number,
                    'secondary' => $trip->status,
                    'vehicle_id' => $trip->vehicle_id,
                    'driver_id' => $trip->driver_id,
                ]),
        ]);
    }

    public function store(FuelLogRequest $request): JsonResponse
    {
        $this->authorize('create', FuelLog::class);

        $fuelLog = DB::transaction(function () use ($request) {
            $vehicle = Vehicle::query()->findOrFail($request->integer('vehicle_id'));

            $fuelLog = FuelLog::create($this->payload($request));

            $this->odometerService->record(
                vehicle: $vehicle,
                reading: $request->integer('odometer_reading'),
                source: 'fuel_log',
                sourceReferenceId: $fuelLog->id,
                recordedAt: $request->input('fueled_at'),
                driverId: $request->integer('driver_id') ?: null,
                notes: 'Captured from fuel log.',
            );

            return $fuelLog;
        });

        return ApiResponse::created(
            new FuelLogResource($fuelLog->load(['vehicle', 'driver', 'trip', 'serviceProvider'])),
            'Fuel log created successfully.'
        );
    }

    public function show(FuelLog $fuelLog): JsonResponse
    {
        $this->authorize('view', $fuelLog);

        return ApiResponse::success(new FuelLogResource($fuelLog->load(['vehicle', 'driver', 'trip', 'serviceProvider'])));
    }

    public function update(FuelLogRequest $request, FuelLog $fuelLog): JsonResponse
    {
        $this->authorize('update', $fuelLog);

        DB::transaction(function () use ($request, $fuelLog) {
            $vehicle = Vehicle::query()->findOrFail($request->integer('vehicle_id'));

            $fuelLog->update($this->payload($request));

            $this->odometerService->record(
                vehicle: $vehicle,
                reading: $request->integer('odometer_reading'),
                source: 'fuel_log',
                sourceReferenceId: $fuelLog->id,
                recordedAt: $request->input('fueled_at'),
                driverId: $request->integer('driver_id') ?: null,
                notes: 'Captured from fuel log.',
            );
        });

        return ApiResponse::success(
            new FuelLogResource($fuelLog->fresh()->load(['vehicle', 'driver', 'trip', 'serviceProvider'])),
            'Fuel log updated successfully.'
        );
    }

    public function destroy(FuelLog $fuelLog): JsonResponse
    {
        $this->authorize('delete', $fuelLog);

        DB::transaction(function () use ($fuelLog) {
            $vehicle = $fuelLog->vehicle()->first();

            $fuelLog->delete();

            if ($vehicle) {
                $this->odometerService->deleteSourceReading($vehicle, 'fuel_log', $fuelLog->id);
            }
        });

        return ApiResponse::noContent('Fuel log deleted successfully.');
    }

    private function payload(FuelLogRequest $request): array
    {
        $payload = $request->validated();
        $payload['total_cost'] = round(
            (float) $payload['quantity_liters'] * (float) $payload['cost_per_liter'],
            2
        );

        return $payload;
    }
}
