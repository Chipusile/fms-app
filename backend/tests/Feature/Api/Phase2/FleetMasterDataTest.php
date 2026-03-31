<?php

namespace Tests\Feature\Api\Phase2;

use App\Models\AssetDocument;
use App\Models\Department;
use App\Models\Driver;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FleetMasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_listing_is_scoped_to_the_authenticated_tenant(): void
    {
        [$tenantA, $tenantB] = [Tenant::factory()->create(), Tenant::factory()->create()];
        $actor = $this->createUserWithPermissions($tenantA, ['vehicles.view']);

        $typeA = VehicleType::withoutGlobalScopes()->create([
            'tenant_id' => $tenantA->id,
            'name' => 'Pickup',
            'code' => 'PICKUP',
            'is_active' => true,
        ]);

        $typeB = VehicleType::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'SUV',
            'code' => 'SUV',
            'is_active' => true,
        ]);

        $visibleVehicle = Vehicle::withoutGlobalScopes()->create([
            'tenant_id' => $tenantA->id,
            'vehicle_type_id' => $typeA->id,
            'registration_number' => 'TEN-A-001',
            'make' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2024,
            'fuel_type' => 'diesel',
            'ownership_type' => 'owned',
            'status' => 'active',
        ]);

        $hiddenVehicle = Vehicle::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'vehicle_type_id' => $typeB->id,
            'registration_number' => 'TEN-B-001',
            'make' => 'Ford',
            'model' => 'Ranger',
            'year' => 2024,
            'fuel_type' => 'diesel',
            'ownership_type' => 'owned',
            'status' => 'active',
        ]);

        Sanctum::actingAs($actor);

        $response = $this->getJson('/api/v1/vehicles');

        $response->assertOk();
        $response->assertJsonFragment(['registration_number' => $visibleVehicle->registration_number]);
        $response->assertJsonMissing(['registration_number' => $hiddenVehicle->registration_number]);
    }

    public function test_authorized_user_can_create_vehicle_for_their_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createUserWithPermissions($tenant, ['vehicles.create', 'vehicles.view']);

        $department = Department::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Operations',
            'code' => 'OPS',
            'status' => 'active',
        ]);

        $vehicleType = VehicleType::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Pickup',
            'code' => 'PICKUP',
            'default_fuel_type' => 'diesel',
            'is_active' => true,
        ]);

        Sanctum::actingAs($actor);

        $response = $this->postJson('/api/v1/vehicles', [
            'vehicle_type_id' => $vehicleType->id,
            'department_id' => $department->id,
            'registration_number' => 'ABC-1234',
            'asset_tag' => 'AST-001',
            'make' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2025,
            'fuel_type' => 'diesel',
            'transmission_type' => 'manual',
            'ownership_type' => 'owned',
            'status' => 'active',
            'odometer_reading' => 15000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.registration_number', 'ABC-1234');

        $this->assertDatabaseHas('vehicles', [
            'tenant_id' => $tenant->id,
            'registration_number' => 'ABC-1234',
        ]);
    }

    public function test_vehicle_assignment_prevents_multiple_active_assignments_for_same_vehicle(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createUserWithPermissions($tenant, ['vehicles.view', 'vehicles.assign']);

        $department = Department::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Operations',
            'code' => 'OPS',
            'status' => 'active',
        ]);

        $vehicleType = VehicleType::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Pickup',
            'code' => 'PICKUP',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'vehicle_type_id' => $vehicleType->id,
            'registration_number' => 'ABC-1234',
            'make' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2024,
            'fuel_type' => 'diesel',
            'ownership_type' => 'owned',
            'status' => 'active',
        ]);

        $driver = Driver::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'name' => 'Driver One',
            'license_number' => 'LIC-001',
            'status' => 'active',
        ]);

        VehicleAssignment::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'department_id' => $department->id,
            'assignment_type' => 'driver',
            'status' => 'active',
            'assigned_from' => now()->subDay()->toDateString(),
        ]);

        Sanctum::actingAs($actor);

        $response = $this->postJson('/api/v1/vehicle-assignments', [
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'department_id' => $department->id,
            'assignment_type' => 'driver',
            'status' => 'active',
            'assigned_from' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['vehicle_id']);
    }

    public function test_releasing_assignment_clears_vehicle_department_when_no_other_active_assignment(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createUserWithPermissions($tenant, ['vehicles.view', 'vehicles.assign']);

        $department = Department::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Operations',
            'code' => 'OPS',
            'status' => 'active',
        ]);

        $vehicleType = VehicleType::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Pickup',
            'code' => 'PICKUP',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'vehicle_type_id' => $vehicleType->id,
            'department_id' => $department->id,
            'registration_number' => 'ABC-4200',
            'make' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2025,
            'fuel_type' => 'diesel',
            'ownership_type' => 'owned',
            'status' => 'active',
        ]);

        $driver = Driver::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'name' => 'Driver One',
            'license_number' => 'LIC-420',
            'status' => 'active',
        ]);

        $assignment = VehicleAssignment::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'department_id' => $department->id,
            'assignment_type' => 'driver',
            'status' => 'active',
            'assigned_from' => now()->subDay()->toDateString(),
        ]);

        Sanctum::actingAs($actor);

        $response = $this->putJson("/api/v1/vehicle-assignments/{$assignment->id}", [
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'department_id' => $department->id,
            'assignment_type' => 'driver',
            'status' => 'released',
            'assigned_from' => $assignment->assigned_from->toDateString(),
            'assigned_to' => now()->toDateString(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'released');

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'department_id' => null,
        ]);
    }

    public function test_authorized_user_can_upload_asset_document_for_vehicle(): void
    {
        Storage::fake('local');

        $tenant = Tenant::factory()->create();
        $actor = $this->createUserWithPermissions($tenant, ['documents.view', 'documents.create']);

        $vehicleType = VehicleType::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Pickup',
            'code' => 'PICKUP',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'vehicle_type_id' => $vehicleType->id,
            'registration_number' => 'DOC-001',
            'make' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2025,
            'fuel_type' => 'diesel',
            'ownership_type' => 'owned',
            'status' => 'active',
        ]);

        Sanctum::actingAs($actor);

        $response = $this->post('/api/v1/asset-documents', [
            'documentable_type' => 'vehicle',
            'documentable_id' => $vehicle->id,
            'name' => 'Insurance certificate',
            'document_type' => 'insurance',
            'document_number' => 'INS-2026-001',
            'status' => 'active',
            'file' => UploadedFile::fake()->create('insurance.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.documentable_type', 'vehicle')
            ->assertJsonPath('data.file_name', 'insurance.pdf');

        /** @var AssetDocument $document */
        $document = AssetDocument::query()->firstOrFail();

        Storage::disk('local')->assertExists($document->file_path);

        $this->assertDatabaseHas('asset_documents', [
            'tenant_id' => $tenant->id,
            'documentable_id' => $vehicle->id,
            'document_type' => 'insurance',
            'file_name' => 'insurance.pdf',
        ]);
    }

    public function test_import_templates_endpoint_returns_vehicle_and_driver_templates(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createUserWithPermissions($tenant, ['vehicles.view']);

        Sanctum::actingAs($actor);

        $response = $this->getJson('/api/v1/import-templates');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['resource' => 'vehicles'])
            ->assertJsonFragment(['resource' => 'drivers']);
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
            'name' => 'Fleet Master Data Tester',
            'slug' => 'fleet-master-data-tester-'.uniqid(),
        ]);
        $role->permissions()->sync($permissionIds->all());

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
