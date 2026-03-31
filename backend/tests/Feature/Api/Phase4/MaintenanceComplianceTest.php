<?php

namespace Tests\Feature\Api\Phase4;

use App\Models\ComplianceItem;
use App\Models\Department;
use App\Models\Driver;
use App\Models\MaintenanceSchedule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_create_maintenance_schedule_for_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createUserWithPermissions($tenant, ['maintenance.view', 'maintenance.create']);
        ['vehicle' => $vehicle, 'garage' => $garage] = $this->createMaintenanceContext($tenant);

        Sanctum::actingAs($actor);

        $response = $this->postJson('/api/v1/maintenance-schedules', [
            'vehicle_id' => $vehicle->id,
            'service_provider_id' => $garage->id,
            'title' => 'Quarterly service plan',
            'schedule_type' => 'preventive',
            'status' => 'active',
            'interval_days' => 90,
            'interval_km' => 5000,
            'reminder_days' => 10,
            'reminder_km' => 750,
            'last_performed_at' => now()->subDays(20)->toISOString(),
            'last_performed_km' => 21000,
            'notes' => 'Standard quarterly service cycle.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Quarterly service plan')
            ->assertJsonPath('data.next_due_km', 26000);

        $this->assertDatabaseHas('maintenance_schedules', [
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'title' => 'Quarterly service plan',
            'next_due_km' => 26000,
        ]);
    }

    public function test_work_order_completion_creates_maintenance_record_and_updates_schedule_and_vehicle(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createUserWithPermissions($tenant, ['maintenance.view', 'maintenance.create', 'maintenance.update']);
        $assignee = $this->createUserWithPermissions($tenant, ['maintenance.view', 'maintenance.update', 'notifications.view', 'notifications.update']);
        ['vehicle' => $vehicle, 'garage' => $garage] = $this->createMaintenanceContext($tenant, [
            'vehicle_status' => 'active',
            'vehicle_odometer' => 45000,
        ]);

        $schedule = MaintenanceSchedule::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'service_provider_id' => $garage->id,
            'title' => 'Major service',
            'schedule_type' => 'preventive',
            'status' => 'active',
            'interval_days' => 180,
            'interval_km' => 10000,
            'reminder_days' => 14,
            'reminder_km' => 1000,
            'last_performed_at' => now()->subDays(120),
            'last_performed_km' => 36000,
            'next_due_at' => now()->addDays(60),
            'next_due_km' => 46000,
        ]);

        Sanctum::actingAs($actor);

        $createResponse = $this->postJson('/api/v1/work-orders', [
            'maintenance_schedule_id' => $schedule->id,
            'vehicle_id' => $vehicle->id,
            'service_provider_id' => $garage->id,
            'assigned_to' => $assignee->id,
            'title' => 'Major service work order',
            'maintenance_type' => 'preventive',
            'priority' => 'high',
            'estimated_cost' => 6200,
            'notes' => 'Include brake and suspension inspection.',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.status', 'open');

        $workOrderId = (int) $createResponse->json('data.id');

        $this->assertDatabaseHas('user_notifications', [
            'tenant_id' => $tenant->id,
            'user_id' => $assignee->id,
            'type' => 'work_order_assigned',
        ]);

        $this->putJson("/api/v1/work-orders/{$workOrderId}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->putJson("/api/v1/work-orders/{$workOrderId}/complete", [
            'completed_at' => now()->toISOString(),
            'odometer_reading' => 46120,
            'downtime_hours' => 5.5,
            'labor_cost' => 1800,
            'parts_cost' => 2200,
            'actual_cost' => 4000,
            'resolution_notes' => 'Service completed and road tested.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.actual_cost', '4000.00');

        $this->assertDatabaseHas('maintenance_records', [
            'tenant_id' => $tenant->id,
            'work_order_id' => $workOrderId,
            'vehicle_id' => $vehicle->id,
            'total_cost' => 4000,
        ]);

        $this->assertDatabaseHas('maintenance_schedules', [
            'id' => $schedule->id,
            'last_performed_km' => 46120,
            'next_due_km' => 56120,
        ]);

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'status' => 'active',
            'odometer_reading' => 46120,
        ]);

        $this->assertDatabaseHas('odometer_readings', [
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'source' => 'maintenance',
            'source_reference_id' => $workOrderId,
            'reading' => 46120,
        ]);
    }

    public function test_compliance_dashboard_and_expiring_list_are_scoped_to_authenticated_tenant(): void
    {
        [$tenantA, $tenantB] = [Tenant::factory()->create(), Tenant::factory()->create()];
        $actor = $this->createUserWithPermissions($tenantA, ['compliance.view']);
        ['vehicle' => $vehicleA, 'driver' => $driverA] = $this->createMaintenanceContext($tenantA);
        ['vehicle' => $vehicleB, 'driver' => $driverB] = $this->createMaintenanceContext($tenantB);

        ComplianceItem::withoutGlobalScopes()->create([
            'tenant_id' => $tenantA->id,
            'compliant_type' => Vehicle::class,
            'compliant_id' => $vehicleA->id,
            'title' => 'Fleet insurance 2026',
            'category' => 'insurance',
            'expiry_date' => now()->addDays(12)->toDateString(),
            'reminder_days' => 30,
            'status' => 'valid',
        ]);

        ComplianceItem::withoutGlobalScopes()->create([
            'tenant_id' => $tenantA->id,
            'compliant_type' => Driver::class,
            'compliant_id' => $driverA->id,
            'title' => 'Driver license renewal',
            'category' => 'license',
            'expiry_date' => now()->subDays(3)->toDateString(),
            'reminder_days' => 30,
            'status' => 'valid',
        ]);

        ComplianceItem::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'compliant_type' => Vehicle::class,
            'compliant_id' => $vehicleB->id,
            'title' => 'Other tenant permit',
            'category' => 'permit',
            'expiry_date' => now()->addDays(5)->toDateString(),
            'reminder_days' => 30,
            'status' => 'valid',
        ]);

        Sanctum::actingAs($actor);

        $dashboardResponse = $this->getJson('/api/v1/compliance-items/dashboard');

        $dashboardResponse->assertOk()
            ->assertJsonPath('data.totals.all', 2)
            ->assertJsonPath('data.totals.expired', 1)
            ->assertJsonPath('data.totals.expiring_soon', 1);

        $expiringResponse = $this->getJson('/api/v1/compliance-items/expiring');

        $expiringResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Fleet insurance 2026')
            ->assertJsonMissing(['title' => 'Other tenant permit']);
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
            'name' => 'Phase 4 Tester',
            'slug' => 'phase-4-tester-'.uniqid(),
        ]);
        $role->permissions()->sync($permissionIds->all());

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    /**
     * @param  array{vehicle_status?: string, vehicle_odometer?: int}  $overrides
     * @return array{vehicle: Vehicle, driver: Driver, garage: ServiceProvider}
     */
    private function createMaintenanceContext(Tenant $tenant, array $overrides = []): array
    {
        $department = Department::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Operations',
            'code' => 'OPS',
            'status' => 'active',
        ]);

        $vehicleType = VehicleType::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'SUV',
            'code' => 'SUV-'.$tenant->id.'-'.uniqid(),
            'is_active' => true,
        ]);

        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'vehicle_type_id' => $vehicleType->id,
            'department_id' => $department->id,
            'registration_number' => 'REG-'.uniqid(),
            'make' => 'Toyota',
            'model' => 'Fortuner',
            'year' => 2025,
            'fuel_type' => 'diesel',
            'ownership_type' => 'owned',
            'status' => $overrides['vehicle_status'] ?? 'active',
            'odometer_reading' => $overrides['vehicle_odometer'] ?? 12000,
        ]);

        $driver = Driver::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'name' => 'Maintenance Driver',
            'license_number' => 'LIC-'.uniqid(),
            'status' => 'active',
        ]);

        $garage = ServiceProvider::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Prime Garage '.uniqid(),
            'provider_type' => 'garage',
            'status' => 'active',
        ]);

        return compact('vehicle', 'driver', 'garage');
    }
}
