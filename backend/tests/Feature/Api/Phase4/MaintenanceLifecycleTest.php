<?php

namespace Tests\Feature\Api\Phase4;

use App\Models\ComplianceItem;
use App\Models\Department;
use App\Models\Driver;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vehicle;
use App\Models\VehicleComponent;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_maintenance_request_notifies_only_same_tenant_approvers(): void
    {
        [$tenantA, $tenantB] = [Tenant::factory()->create(), Tenant::factory()->create()];
        $requester = $this->createUserWithPermissions($tenantA, ['maintenance.create']);
        $approverA = $this->createUserWithPermissions($tenantA, ['maintenance.approve']);
        $approverB = $this->createUserWithPermissions($tenantB, ['maintenance.approve']);
        ['vehicle' => $vehicle] = $this->createMaintenanceContext($tenantA);

        Sanctum::actingAs($requester);

        $response = $this->postJson('/api/v1/maintenance-requests', [
            'vehicle_id' => $vehicle->id,
            'title' => 'Replace worn front tyres',
            'request_type' => 'component_replacement',
            'priority' => 'high',
            'needed_by' => now()->addDays(3)->toDateString(),
            'odometer_reading' => 25500,
            'description' => 'Front tyres have reached wear threshold.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'submitted');

        $requestId = (int) $response->json('data.id');

        $this->assertDatabaseHas('user_notifications', [
            'tenant_id' => $tenantA->id,
            'user_id' => $approverA->id,
            'type' => 'maintenance_request_submitted',
            'related_id' => $requestId,
        ]);

        $this->assertDatabaseMissing('user_notifications', [
            'tenant_id' => $tenantB->id,
            'user_id' => $approverB->id,
            'type' => 'maintenance_request_submitted',
        ]);
    }

    public function test_approved_maintenance_request_can_be_converted_to_linked_work_order(): void
    {
        $tenant = Tenant::factory()->create();
        $requester = $this->createUserWithPermissions($tenant, ['maintenance.create']);
        $approver = $this->createUserWithPermissions($tenant, ['maintenance.view', 'maintenance.approve']);
        $assignee = $this->createUserWithPermissions($tenant, ['maintenance.update']);
        ['vehicle' => $vehicle, 'garage' => $garage] = $this->createMaintenanceContext($tenant, [
            'vehicle_odometer' => 40200,
        ]);

        $maintenanceRequest = MaintenanceRequest::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'service_provider_id' => $garage->id,
            'requested_by' => $requester->id,
            'request_number' => 'MR-0001',
            'title' => 'Brake service request',
            'request_type' => 'corrective',
            'priority' => 'high',
            'status' => 'submitted',
            'needed_by' => now()->addDays(5)->toDateString(),
            'requested_at' => now(),
            'odometer_reading' => 40200,
            'description' => 'Brake pads need replacement and caliper inspection.',
        ]);

        Sanctum::actingAs($approver);

        $this->putJson("/api/v1/maintenance-requests/{$maintenanceRequest->id}/approve", [
            'review_notes' => 'Approved for immediate workshop execution.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $response = $this->putJson("/api/v1/maintenance-requests/{$maintenanceRequest->id}/convert", [
            'assigned_to' => $assignee->id,
            'service_provider_id' => $garage->id,
            'title' => 'Brake service work order',
            'due_date' => now()->addDays(2)->toDateString(),
            'estimated_cost' => 1800,
            'notes' => 'Prioritize safety inspection before release.',
            'review_notes' => 'Converted after approval.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'converted')
            ->assertJsonPath('data.work_order.status', 'open');

        $workOrderId = (int) $response->json('data.work_order.id');

        $this->assertDatabaseHas('work_orders', [
            'tenant_id' => $tenant->id,
            'id' => $workOrderId,
            'maintenance_request_id' => $maintenanceRequest->id,
            'vehicle_id' => $vehicle->id,
            'assigned_to' => $assignee->id,
            'title' => 'Brake service work order',
        ]);

        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $maintenanceRequest->id,
            'status' => 'converted',
            'reviewed_by' => $approver->id,
        ]);
    }

    public function test_due_soon_and_overdue_component_endpoints_are_scoped_to_authenticated_tenant(): void
    {
        [$tenantA, $tenantB] = [Tenant::factory()->create(), Tenant::factory()->create()];
        $actor = $this->createUserWithPermissions($tenantA, ['maintenance.view']);
        ['vehicle' => $vehicleA, 'garage' => $garageA] = $this->createMaintenanceContext($tenantA, [
            'vehicle_odometer' => 52000,
        ]);
        ['vehicle' => $vehicleB, 'garage' => $garageB] = $this->createMaintenanceContext($tenantB, [
            'vehicle_odometer' => 61000,
        ]);

        VehicleComponent::withoutGlobalScopes()->create([
            'tenant_id' => $tenantA->id,
            'vehicle_id' => $vehicleA->id,
            'service_provider_id' => $garageA->id,
            'component_number' => 'CMP-A-001',
            'component_type' => 'tyre',
            'brand' => 'Goodyear',
            'status' => 'active',
            'condition_status' => 'watch',
            'installed_at' => now()->subMonths(8)->toDateString(),
            'installed_odometer' => 46000,
            'expected_life_days' => 365,
            'expected_life_km' => 12000,
            'reminder_days' => 21,
            'reminder_km' => 1000,
            'next_replacement_at' => now()->addDays(5)->toDateString(),
            'next_replacement_km' => 52800,
        ]);

        VehicleComponent::withoutGlobalScopes()->create([
            'tenant_id' => $tenantA->id,
            'vehicle_id' => $vehicleA->id,
            'service_provider_id' => $garageA->id,
            'component_number' => 'CMP-A-002',
            'component_type' => 'battery',
            'brand' => 'Bosch',
            'status' => 'active',
            'condition_status' => 'critical',
            'installed_at' => now()->subYears(2)->toDateString(),
            'installed_odometer' => 30000,
            'expected_life_days' => 730,
            'expected_life_km' => 22000,
            'reminder_days' => 14,
            'reminder_km' => 800,
            'next_replacement_at' => now()->subDays(2)->toDateString(),
            'next_replacement_km' => 51000,
        ]);

        VehicleComponent::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'vehicle_id' => $vehicleB->id,
            'service_provider_id' => $garageB->id,
            'component_number' => 'CMP-B-001',
            'component_type' => 'tracker',
            'brand' => 'Teltonika',
            'status' => 'active',
            'condition_status' => 'critical',
            'installed_at' => now()->subYear()->toDateString(),
            'installed_odometer' => 56000,
            'expected_life_days' => 365,
            'expected_life_km' => 4000,
            'reminder_days' => 7,
            'reminder_km' => 500,
            'next_replacement_at' => now()->subDay()->toDateString(),
            'next_replacement_km' => 60000,
        ]);

        Sanctum::actingAs($actor);

        $dueSoonResponse = $this->getJson('/api/v1/vehicle-components/due-soon');
        $overdueResponse = $this->getJson('/api/v1/vehicle-components/overdue');

        $dueSoonResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.component_number', 'CMP-A-001')
            ->assertJsonMissing(['component_number' => 'CMP-B-001']);

        $overdueResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.component_number', 'CMP-A-002')
            ->assertJsonMissing(['component_number' => 'CMP-B-001']);
    }

    public function test_reminder_dispatch_command_creates_and_deduplicates_due_notifications(): void
    {
        $tenant = Tenant::factory()->create();
        $recipient = $this->createUserWithPermissions($tenant, ['maintenance.update', 'compliance.update']);
        ['vehicle' => $vehicle, 'driver' => $driver, 'garage' => $garage] = $this->createMaintenanceContext($tenant, [
            'vehicle_odometer' => 71000,
        ]);

        MaintenanceSchedule::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'service_provider_id' => $garage->id,
            'title' => 'Six month service',
            'schedule_type' => 'preventive',
            'status' => 'active',
            'interval_days' => 180,
            'interval_km' => 10000,
            'reminder_days' => 10,
            'reminder_km' => 1000,
            'last_performed_at' => now()->subMonths(5),
            'last_performed_km' => 62000,
            'next_due_at' => now()->addDays(4),
            'next_due_km' => 71800,
        ]);

        ComplianceItem::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'compliant_type' => Driver::class,
            'compliant_id' => $driver->id,
            'title' => 'Professional permit renewal',
            'category' => 'permit',
            'expiry_date' => now()->addDays(6)->toDateString(),
            'reminder_days' => 30,
            'status' => 'valid',
        ]);

        VehicleComponent::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'service_provider_id' => $garage->id,
            'component_number' => 'CMP-R-001',
            'component_type' => 'battery',
            'brand' => 'Exide',
            'status' => 'active',
            'condition_status' => 'watch',
            'installed_at' => now()->subYear()->toDateString(),
            'installed_odometer' => 60000,
            'expected_life_days' => 400,
            'expected_life_km' => 13000,
            'reminder_days' => 14,
            'reminder_km' => 1000,
            'next_replacement_at' => now()->addDays(7)->toDateString(),
            'next_replacement_km' => 71900,
        ]);

        Artisan::call('fleet:dispatch-reminders', ['--tenant' => $tenant->id]);
        Artisan::call('fleet:dispatch-reminders', ['--tenant' => $tenant->id]);

        $this->assertSame(1, UserNotification::query()->where('tenant_id', $tenant->id)->where('type', 'maintenance_due')->count());
        $this->assertSame(1, UserNotification::query()->where('tenant_id', $tenant->id)->where('type', 'compliance_expiring')->count());
        $this->assertSame(1, UserNotification::query()->where('tenant_id', $tenant->id)->where('type', 'component_due_replacement')->count());

        $this->assertDatabaseHas('user_notifications', [
            'tenant_id' => $tenant->id,
            'user_id' => $recipient->id,
            'type' => 'maintenance_due',
            'status' => 'unread',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'tenant_id' => $tenant->id,
            'user_id' => $recipient->id,
            'type' => 'compliance_expiring',
            'status' => 'unread',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'tenant_id' => $tenant->id,
            'user_id' => $recipient->id,
            'type' => 'component_due_replacement',
            'status' => 'unread',
        ]);
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
            'name' => 'Phase 4B Tester',
            'slug' => 'phase-4b-tester-'.uniqid(),
        ]);
        $role->permissions()->sync($permissionIds->all());

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    /**
     * @param  array{vehicle_odometer?: int}  $overrides
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
            'status' => 'active',
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
