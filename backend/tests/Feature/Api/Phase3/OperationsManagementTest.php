<?php

namespace Tests\Feature\Api\Phase3;

use App\Models\Department;
use App\Models\Driver;
use App\Models\FuelLog;
use App\Models\OdometerReading;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceProvider;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperationsManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_creation_requires_approval_by_default(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createUserWithPermissions($tenant, ['trips.create', 'trips.view']);
        ['vehicle' => $vehicle, 'driver' => $driver] = $this->createOperationalContext($tenant);

        Sanctum::actingAs($actor);

        $response = $this->postJson('/api/v1/trips', [
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'purpose' => 'Deliver spares to site',
            'origin' => 'Central Warehouse',
            'destination' => 'Kasama Depot',
            'scheduled_start' => now()->addDay()->setHour(8)->toISOString(),
            'scheduled_end' => now()->addDay()->setHour(17)->toISOString(),
            'passengers' => 2,
            'notes' => 'Urgent delivery window.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.approval_required', true)
            ->assertJsonPath('data.requested_by', $actor->id);

        $this->assertDatabaseHas('trips', [
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'status' => 'requested',
            'requested_by' => $actor->id,
        ]);
    }

    public function test_trip_approval_rejects_overlapping_vehicle_and_driver_bookings(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createUserWithPermissions($tenant, ['trips.create', 'trips.view', 'trips.approve']);
        ['vehicle' => $vehicle, 'driver' => $driver] = $this->createOperationalContext($tenant);

        Sanctum::actingAs($actor);

        $firstTrip = $this->createTripViaApi($vehicle, $driver, [
            'scheduled_start' => now()->addDay()->setHour(8)->toISOString(),
            'scheduled_end' => now()->addDay()->setHour(12)->toISOString(),
            'purpose' => 'Morning dispatch',
        ]);

        $secondTrip = $this->createTripViaApi($vehicle, $driver, [
            'scheduled_start' => now()->addDay()->setHour(10)->toISOString(),
            'scheduled_end' => now()->addDay()->setHour(15)->toISOString(),
            'purpose' => 'Overlapping dispatch',
        ]);

        $this->putJson("/api/v1/trips/{$firstTrip->id}/approve", [])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $response = $this->putJson("/api/v1/trips/{$secondTrip->id}/approve", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['vehicle_id', 'driver_id']);

        $this->assertDatabaseHas('trips', [
            'id' => $secondTrip->id,
            'status' => 'requested',
        ]);
    }

    public function test_starting_and_completing_trip_records_odometer_history_and_updates_vehicle(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createUserWithPermissions($tenant, ['trips.create', 'trips.view', 'trips.update']);
        ['vehicle' => $vehicle, 'driver' => $driver] = $this->createOperationalContext($tenant, [
            'vehicle_odometer' => 20000,
        ]);

        $this->setTripApprovalRequired($tenant, false);

        Sanctum::actingAs($actor);

        $tripResponse = $this->postJson('/api/v1/trips', [
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'purpose' => 'Field support visit',
            'origin' => 'HQ',
            'destination' => 'Mongu Project',
            'scheduled_start' => now()->addHours(2)->toISOString(),
            'scheduled_end' => now()->addHours(10)->toISOString(),
        ]);

        $tripResponse->assertCreated()->assertJsonPath('data.status', 'approved');
        $tripId = (int) $tripResponse->json('data.id');

        $this->putJson("/api/v1/trips/{$tripId}/start", [
            'start_odometer' => 20045,
        ])->assertOk()->assertJsonPath('data.status', 'in_progress');

        $this->putJson("/api/v1/trips/{$tripId}/complete", [
            'end_odometer' => 20130,
            'notes' => 'Trip completed without incident.',
        ])->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('trips', [
            'id' => $tripId,
            'status' => 'completed',
            'start_odometer' => 20045,
            'end_odometer' => 20130,
        ]);

        $this->assertDatabaseHas('odometer_readings', [
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'source' => 'trip_start',
            'source_reference_id' => $tripId,
            'reading' => 20045,
        ]);

        $this->assertDatabaseHas('odometer_readings', [
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'source' => 'trip_end',
            'source_reference_id' => $tripId,
            'reading' => 20130,
        ]);

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'odometer_reading' => 20130,
        ]);
    }

    public function test_fuel_log_creation_records_odometer_and_vehicle_totals(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createUserWithPermissions($tenant, ['fuel.create', 'fuel.view']);
        [
            'vehicle' => $vehicle,
            'driver' => $driver,
            'fuelStation' => $fuelStation,
        ] = $this->createOperationalContext($tenant, [
            'vehicle_odometer' => 45000,
        ]);

        Sanctum::actingAs($actor);

        $response = $this->postJson('/api/v1/fuel-logs', [
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'service_provider_id' => $fuelStation->id,
            'reference_number' => 'FUEL-2026-001',
            'fuel_type' => 'diesel',
            'quantity_liters' => 62.5,
            'cost_per_liter' => 28.4,
            'odometer_reading' => 45120,
            'is_full_tank' => true,
            'fueled_at' => now()->toISOString(),
            'notes' => 'Highway fueling stop',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.total_cost', '1775.00')
            ->assertJsonPath('data.odometer_reading', 45120);

        $fuelLogId = (int) $response->json('data.id');

        $this->assertDatabaseHas('fuel_logs', [
            'id' => $fuelLogId,
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'total_cost' => 1775.00,
        ]);

        $this->assertDatabaseHas('odometer_readings', [
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'source' => 'fuel_log',
            'source_reference_id' => $fuelLogId,
            'reading' => 45120,
        ]);

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'odometer_reading' => 45120,
        ]);
    }

    public function test_trip_listing_is_scoped_to_authenticated_tenant(): void
    {
        [$tenantA, $tenantB] = [Tenant::factory()->create(), Tenant::factory()->create()];
        $actor = $this->createUserWithPermissions($tenantA, ['trips.view']);
        ['vehicle' => $vehicleA, 'driver' => $driverA] = $this->createOperationalContext($tenantA);
        ['vehicle' => $vehicleB, 'driver' => $driverB] = $this->createOperationalContext($tenantB);

        $visibleTrip = Trip::withoutGlobalScopes()->create([
            'tenant_id' => $tenantA->id,
            'vehicle_id' => $vehicleA->id,
            'driver_id' => $driverA->id,
            'requested_by' => $actor->id,
            'trip_number' => 'TRP-2026-00001',
            'purpose' => 'Tenant A trip',
            'origin' => 'A',
            'destination' => 'B',
            'scheduled_start' => now()->addDay(),
            'scheduled_end' => now()->addDays(2),
            'status' => 'requested',
        ]);

        $hiddenRequester = User::factory()->create(['tenant_id' => $tenantB->id]);

        $hiddenTrip = Trip::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'vehicle_id' => $vehicleB->id,
            'driver_id' => $driverB->id,
            'requested_by' => $hiddenRequester->id,
            'trip_number' => 'TRP-2026-00002',
            'purpose' => 'Tenant B trip',
            'origin' => 'C',
            'destination' => 'D',
            'scheduled_start' => now()->addDay(),
            'scheduled_end' => now()->addDays(2),
            'status' => 'requested',
        ]);

        Sanctum::actingAs($actor);

        $response = $this->getJson('/api/v1/trips');

        $response->assertOk();
        $response->assertJsonFragment(['trip_number' => $visibleTrip->trip_number]);
        $response->assertJsonMissing(['trip_number' => $hiddenTrip->trip_number]);
    }

    /**
     * @param  list<string>  $permissionSlugs
     */
    private function createUserWithPermissions(Tenant $tenant, array $permissionSlugs): User
    {
        $permissionIds = collect($permissionSlugs)
            ->map(function (string $slug): int {
                $permission = Permission::query()->firstOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => str($slug)->headline()->toString(),
                        'module' => str($slug)->before('.')->toString(),
                    ]
                );

                return $permission->id;
            });

        $role = Role::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Operations Test Role',
            'slug' => 'operations-test-role-'.uniqid(),
        ]);
        $role->permissions()->sync($permissionIds->all());

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    /**
     * @return array{vehicle: Vehicle, driver: Driver, fuelStation: ServiceProvider}
     */
    private function createOperationalContext(Tenant $tenant, array $overrides = []): array
    {
        $department = Department::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Operations',
            'code' => 'OPS-'.uniqid(),
            'status' => 'active',
        ]);

        $vehicleType = VehicleType::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Pickup',
            'code' => 'PICKUP-'.uniqid(),
            'default_fuel_type' => 'diesel',
            'is_active' => true,
        ]);

        $driver = Driver::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'name' => 'Driver '.uniqid(),
            'license_number' => 'LIC-'.uniqid(),
            'status' => 'active',
        ]);

        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'vehicle_type_id' => $vehicleType->id,
            'department_id' => $department->id,
            'registration_number' => 'REG-'.uniqid(),
            'make' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2025,
            'fuel_type' => 'diesel',
            'ownership_type' => 'owned',
            'status' => 'active',
            'odometer_reading' => $overrides['vehicle_odometer'] ?? 15000,
        ]);

        $fuelStation = ServiceProvider::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Fuel Station '.uniqid(),
            'provider_type' => 'fuel_station',
            'status' => 'active',
        ]);

        return [
            'vehicle' => $vehicle,
            'driver' => $driver,
            'fuelStation' => $fuelStation,
        ];
    }

    private function createTripViaApi(Vehicle $vehicle, Driver $driver, array $overrides = []): Trip
    {
        $payload = array_merge([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'purpose' => 'Scheduled run',
            'origin' => 'HQ',
            'destination' => 'Regional office',
            'scheduled_start' => now()->addDay()->setHour(8)->toISOString(),
            'scheduled_end' => now()->addDay()->setHour(17)->toISOString(),
        ], $overrides);

        $response = $this->postJson('/api/v1/trips', $payload);
        $response->assertCreated();

        return Trip::query()->findOrFail((int) $response->json('data.id'));
    }

    private function setTripApprovalRequired(Tenant $tenant, bool $value): void
    {
        Setting::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'key' => 'approvals.trip_approval_required',
            ],
            [
                'group' => 'approvals',
                'value' => $value,
            ]
        );
    }
}
