<?php

namespace Tests\Feature\Api\Phase3;

use App\Models\ApprovalRequest;
use App\Models\Department;
use App\Models\Driver;
use App\Models\InspectionTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperationalGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_create_inspection_template_for_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createUserWithPermissions($tenant, [
            'inspection-templates.view',
            'inspection-templates.create',
        ]);

        Sanctum::actingAs($actor);

        $response = $this->postJson('/api/v1/inspection-templates', [
            'name' => 'Daily pre-trip checklist',
            'code' => 'PRETRIP',
            'description' => 'Driver daily vehicle readiness inspection',
            'applies_to' => 'vehicle',
            'status' => 'active',
            'requires_review_on_critical' => true,
            'items' => [
                [
                    'title' => 'Brakes operational',
                    'response_type' => 'pass_fail',
                    'is_required' => true,
                    'triggers_defect_on_fail' => true,
                ],
                [
                    'title' => 'Tyre pressure notes',
                    'response_type' => 'text',
                    'is_required' => false,
                    'triggers_defect_on_fail' => false,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'PRETRIP')
            ->assertJsonCount(2, 'data.items');

        $this->assertDatabaseHas('inspection_templates', [
            'tenant_id' => $tenant->id,
            'code' => 'PRETRIP',
        ]);
    }

    public function test_critical_inspection_creates_approval_request_and_notifications(): void
    {
        $tenant = Tenant::factory()->create();
        $inspector = $this->createUserWithPermissions($tenant, ['inspections.create', 'inspections.view']);
        $approver = $this->createUserWithPermissions($tenant, ['approvals.view', 'approvals.decide', 'notifications.view', 'notifications.update']);
        ['vehicle' => $vehicle, 'driver' => $driver] = $this->createOperationalContext($tenant);

        $template = InspectionTemplate::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Critical defect template',
            'code' => 'CRIT-INSP',
            'applies_to' => 'vehicle',
            'status' => 'active',
            'requires_review_on_critical' => true,
        ]);
        $item = $template->items()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Brake system safe',
            'response_type' => 'pass_fail',
            'is_required' => true,
            'triggers_defect_on_fail' => true,
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($inspector);

        $response = $this->postJson('/api/v1/inspections', [
            'inspection_template_id' => $template->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'performed_at' => now()->toISOString(),
            'odometer_reading' => 15500,
            'notes' => 'Brake warning light on.',
            'responses' => [
                [
                    'template_item_id' => $item->id,
                    'response_value' => false,
                    'is_pass' => false,
                    'defect_severity' => 'critical',
                    'defect_summary' => 'Brake warning light and weak pedal pressure.',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'requires_action')
            ->assertJsonPath('data.critical_defects', 1);

        $inspectionId = (int) $response->json('data.id');

        $this->assertDatabaseHas('approval_requests', [
            'tenant_id' => $tenant->id,
            'approvalable_type' => 'App\\Models\\Inspection',
            'approvalable_id' => $inspectionId,
            'approval_type' => 'inspection_review',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'tenant_id' => $tenant->id,
            'user_id' => $approver->id,
            'type' => 'approval_pending',
            'status' => 'unread',
        ]);
    }

    public function test_high_severity_incident_creates_approval_request_and_approver_can_approve_it(): void
    {
        $tenant = Tenant::factory()->create();
        $reporter = $this->createUserWithPermissions($tenant, ['incidents.create', 'incidents.view', 'notifications.view', 'notifications.update']);
        $approver = $this->createUserWithPermissions($tenant, ['approvals.view', 'approvals.decide', 'notifications.view', 'notifications.update']);
        ['vehicle' => $vehicle, 'driver' => $driver] = $this->createOperationalContext($tenant);

        Sanctum::actingAs($reporter);

        $createResponse = $this->postJson('/api/v1/incidents', [
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'incident_type' => 'accident',
            'severity' => 'critical',
            'occurred_at' => now()->subHour()->toISOString(),
            'location' => 'Great North Road',
            'description' => 'Collision with roadside barrier.',
            'immediate_action' => 'Vehicle secured and recovery requested.',
            'injury_count' => 0,
            'estimated_cost' => 12000,
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.status', 'under_review');

        /** @var ApprovalRequest $approval */
        $approval = ApprovalRequest::withoutGlobalScopes()->firstOrFail();

        Sanctum::actingAs($approver);

        $decisionResponse = $this->putJson("/api/v1/approvals/{$approval->id}/approve", [
            'decision_notes' => 'Operational review completed. Proceed with incident action plan.',
        ]);

        $decisionResponse->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('incidents', [
            'id' => $approval->approvalable_id,
            'status' => 'action_required',
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'tenant_id' => $tenant->id,
            'user_id' => $reporter->id,
            'type' => 'approval_decided',
        ]);
    }

    public function test_notification_inbox_is_scoped_to_authenticated_user_and_supports_status_updates(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createUserWithPermissions($tenant, ['notifications.view', 'notifications.update']);
        $otherUser = $this->createUserWithPermissions($tenant, ['notifications.view', 'notifications.update']);

        UserNotification::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'type' => 'incident_reported',
            'title' => 'Incident raised',
            'body' => 'A new incident was reported.',
            'status' => 'unread',
        ]);

        UserNotification::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $otherUser->id,
            'type' => 'approval_pending',
            'title' => 'Approval pending',
            'body' => 'Review requested.',
            'status' => 'unread',
        ]);

        Sanctum::actingAs($user);

        $listResponse = $this->getJson('/api/v1/notifications');

        $listResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.unread_count', 1);

        $notificationId = (int) $listResponse->json('data.0.id');

        $this->putJson("/api/v1/notifications/{$notificationId}/mark-read")
            ->assertOk()
            ->assertJsonPath('data.status', 'read');

        $this->putJson("/api/v1/notifications/{$notificationId}/acknowledge")
            ->assertOk()
            ->assertJsonPath('data.status', 'acknowledged');
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
            'name' => 'Operational Governance Tester',
            'slug' => 'operational-governance-tester-'.uniqid(),
        ]);
        $role->permissions()->sync($permissionIds->all());

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    /**
     * @return array{vehicle: Vehicle, driver: Driver}
     */
    private function createOperationalContext(Tenant $tenant): array
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
            'odometer_reading' => 15000,
        ]);

        return [
            'vehicle' => $vehicle,
            'driver' => $driver,
        ];
    }
}
