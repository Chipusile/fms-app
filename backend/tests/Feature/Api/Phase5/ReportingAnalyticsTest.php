<?php

namespace Tests\Feature\Api\Phase5;

use App\Models\ComplianceItem;
use App\Models\Department;
use App\Models\Driver;
use App\Models\FuelLog;
use App\Models\Incident;
use App\Models\MaintenanceRecord;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleComponent;
use App\Models\VehicleType;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportingAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_endpoint_returns_tenant_scoped_metrics_and_highlights(): void
    {
        [$tenantA, $tenantB] = [Tenant::factory()->create(), Tenant::factory()->create()];
        $actor = $this->createUserWithPermissions($tenantA, ['reports.view']);
        ['vehicle' => $vehicleA, 'driver' => $driverA, 'garage' => $garageA] = $this->createReportingContext($tenantA, [
            'registration_number' => 'ABC-1001',
            'odometer' => 64000,
        ]);
        ['vehicle' => $vehicleB, 'driver' => $driverB, 'garage' => $garageB] = $this->createReportingContext($tenantB, [
            'registration_number' => 'XYZ-9009',
            'odometer' => 52000,
        ]);

        Trip::withoutGlobalScopes()->create([
            'tenant_id' => $tenantA->id,
            'vehicle_id' => $vehicleA->id,
            'driver_id' => $driverA->id,
            'requested_by' => $actor->id,
            'trip_number' => 'TRP-001',
            'purpose' => 'Client delivery',
            'origin' => 'Depot',
            'destination' => 'Site A',
            'scheduled_start' => now()->subDays(2),
            'scheduled_end' => now()->subDays(2)->addHours(5),
            'actual_start' => now()->subDays(2),
            'actual_end' => now()->subDays(2)->addHours(5),
            'start_odometer' => 63500,
            'end_odometer' => 63920,
            'distance_km' => 420,
            'status' => 'completed',
        ]);

        Trip::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'vehicle_id' => $vehicleB->id,
            'driver_id' => $driverB->id,
            'requested_by' => $this->createUserWithPermissions($tenantB, ['reports.view'])->id,
            'trip_number' => 'TRP-002',
            'purpose' => 'Other tenant trip',
            'origin' => 'Yard',
            'destination' => 'Site B',
            'scheduled_start' => now()->subDay(),
            'scheduled_end' => now()->subDay()->addHours(3),
            'status' => 'completed',
            'distance_km' => 180,
        ]);

        FuelLog::withoutGlobalScopes()->create([
            'tenant_id' => $tenantA->id,
            'vehicle_id' => $vehicleA->id,
            'driver_id' => $driverA->id,
            'service_provider_id' => $garageA->id,
            'fuel_type' => 'diesel',
            'quantity_liters' => 60,
            'cost_per_liter' => 25,
            'total_cost' => 1500,
            'odometer_reading' => 63920,
            'is_full_tank' => true,
            'fueled_at' => now()->subDay(),
        ]);

        MaintenanceRecord::withoutGlobalScopes()->create([
            'tenant_id' => $tenantA->id,
            'vehicle_id' => $vehicleA->id,
            'service_provider_id' => $garageA->id,
            'summary' => 'Brake service completed',
            'maintenance_type' => 'corrective',
            'completed_at' => now()->subDays(4),
            'odometer_reading' => 63400,
            'downtime_hours' => 3,
            'labor_cost' => 600,
            'parts_cost' => 900,
            'total_cost' => 1500,
        ]);

        WorkOrder::withoutGlobalScopes()->create([
            'tenant_id' => $tenantA->id,
            'vehicle_id' => $vehicleA->id,
            'service_provider_id' => $garageA->id,
            'work_order_number' => 'WO-001',
            'title' => 'Pending inspection',
            'maintenance_type' => 'corrective',
            'priority' => 'high',
            'status' => 'open',
            'opened_at' => now()->subDay(),
        ]);

        ComplianceItem::withoutGlobalScopes()->create([
            'tenant_id' => $tenantA->id,
            'compliant_type' => Vehicle::class,
            'compliant_id' => $vehicleA->id,
            'title' => 'Insurance renewal',
            'category' => 'insurance',
            'expiry_date' => now()->addDays(5)->toDateString(),
            'reminder_days' => 30,
            'status' => 'expiring_soon',
        ]);

        VehicleComponent::withoutGlobalScopes()->create([
            'tenant_id' => $tenantA->id,
            'vehicle_id' => $vehicleA->id,
            'service_provider_id' => $garageA->id,
            'component_number' => 'CMP-001',
            'component_type' => 'battery',
            'brand' => 'Bosch',
            'status' => 'active',
            'condition_status' => 'watch',
            'installed_at' => now()->subMonths(10)->toDateString(),
            'installed_odometer' => 55000,
            'expected_life_days' => 365,
            'expected_life_km' => 10000,
            'reminder_days' => 14,
            'reminder_km' => 1500,
            'next_replacement_at' => now()->addDays(7)->toDateString(),
            'next_replacement_km' => 65000,
        ]);

        Incident::withoutGlobalScopes()->create([
            'tenant_id' => $tenantA->id,
            'vehicle_id' => $vehicleA->id,
            'driver_id' => $driverA->id,
            'reported_by' => $actor->id,
            'incident_number' => 'INC-001',
            'incident_type' => 'accident',
            'severity' => 'critical',
            'status' => 'reported',
            'occurred_at' => now()->subDays(3),
            'reported_at' => now()->subDays(3),
            'description' => 'Critical front-end collision',
            'estimated_cost' => 4200,
        ]);

        Sanctum::actingAs($actor);

        $response = $this->getJson('/api/v1/reports/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.metrics.0.label', 'Active fleet assets')
            ->assertJsonPath('data.metrics.0.value', 1)
            ->assertJsonPath('data.metrics.1.value', 1)
            ->assertJsonPath('data.metrics.2.value', 1500)
            ->assertJsonPath('data.highlights.top_utilization_vehicles.0.label', 'ABC-1001')
            ->assertJsonMissing(['label' => 'XYZ-9009']);
    }

    public function test_vehicle_utilization_report_filters_to_selected_vehicle(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createUserWithPermissions($tenant, ['reports.view']);
        ['vehicle' => $vehicleA, 'driver' => $driverA] = $this->createReportingContext($tenant, [
            'registration_number' => 'FLEET-001',
            'odometer' => 32000,
        ]);
        ['vehicle' => $vehicleB, 'driver' => $driverB] = $this->createReportingContext($tenant, [
            'registration_number' => 'FLEET-002',
            'odometer' => 28000,
        ]);

        foreach ([[$vehicleA, $driverA, 260], [$vehicleB, $driverB, 140]] as [$vehicle, $driver, $distance]) {
            Trip::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'requested_by' => $actor->id,
                'trip_number' => 'TRP-'.uniqid(),
                'purpose' => 'Scheduled route',
                'origin' => 'Depot',
                'destination' => 'Branch',
                'scheduled_start' => now()->subDays(5),
                'scheduled_end' => now()->subDays(5)->addHours(4),
                'actual_start' => now()->subDays(5),
                'actual_end' => now()->subDays(5)->addHours(4),
                'status' => 'completed',
                'distance_km' => $distance,
            ]);
        }

        Sanctum::actingAs($actor);

        $response = $this->getJson("/api/v1/reports/vehicle-utilization?filter[vehicle_id]={$vehicleA->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.registration_number', 'FLEET-001')
            ->assertJsonPath('data.rows.0.total_distance_km', 260);
    }

    public function test_report_export_can_be_created_and_downloaded_as_csv(): void
    {
        Storage::fake('local');
        config([
            'queue.default' => 'sync',
            'fleet.reports.export_disk' => 'local',
        ]);

        $tenant = Tenant::factory()->create();
        $actor = $this->createUserWithPermissions($tenant, ['reports.view', 'reports.export']);
        ['vehicle' => $vehicle, 'driver' => $driver, 'garage' => $garage] = $this->createReportingContext($tenant, [
            'registration_number' => 'CSV-100',
        ]);

        FuelLog::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'service_provider_id' => $garage->id,
            'fuel_type' => 'diesel',
            'quantity_liters' => 50,
            'cost_per_liter' => 20,
            'total_cost' => 1000,
            'odometer_reading' => 10100,
            'is_full_tank' => false,
            'fueled_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($actor);

        $createResponse = $this->postJson('/api/v1/reports/exports', [
            'type' => 'fuel-consumption',
            'format' => 'csv',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.report_type', 'fuel-consumption');

        $exportId = (int) $createResponse->json('data.id');

        $showResponse = $this->getJson("/api/v1/reports/exports/{$exportId}");

        $showResponse->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.row_count', 1);

        $downloadResponse = $this->get("/api/v1/reports/exports/{$exportId}/download");

        $downloadResponse->assertOk();
        $this->assertStringContainsString('text/csv', (string) $downloadResponse->headers->get('content-type'));
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
            'name' => 'Phase 5 Tester',
            'slug' => 'phase-5-tester-'.uniqid(),
        ]);
        $role->permissions()->sync($permissionIds->all());

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    /**
     * @param  array{registration_number?: string, odometer?: int}  $overrides
     * @return array{vehicle: Vehicle, driver: Driver, garage: ServiceProvider}
     */
    private function createReportingContext(Tenant $tenant, array $overrides = []): array
    {
        $department = Department::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Operations '.uniqid(),
            'code' => 'OPS-'.uniqid(),
            'status' => 'active',
        ]);

        $vehicleType = VehicleType::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'SUV '.uniqid(),
            'code' => 'SUV-'.uniqid(),
            'is_active' => true,
        ]);

        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'vehicle_type_id' => $vehicleType->id,
            'department_id' => $department->id,
            'registration_number' => $overrides['registration_number'] ?? 'REG-'.uniqid(),
            'make' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2025,
            'fuel_type' => 'diesel',
            'ownership_type' => 'owned',
            'status' => 'active',
            'odometer_reading' => $overrides['odometer'] ?? 10000,
        ]);

        $driver = Driver::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'name' => 'Reporting Driver '.uniqid(),
            'license_number' => 'LIC-'.uniqid(),
            'status' => 'active',
        ]);

        $garage = ServiceProvider::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Reporting Garage '.uniqid(),
            'provider_type' => 'garage',
            'status' => 'active',
        ]);

        return compact('vehicle', 'driver', 'garage');
    }
}
