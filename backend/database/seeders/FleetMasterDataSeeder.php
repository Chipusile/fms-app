<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Driver;
use App\Models\Role;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;

class FleetMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Tenant::all() as $tenant) {
            $this->seedVehicleTypes($tenant);
            $this->seedDepartments($tenant);
            $this->seedDrivers($tenant);
            $this->seedServiceProviders($tenant);
            $this->seedVehicles($tenant);
            $this->seedAssignments($tenant);
        }
    }

    private function seedVehicleTypes(Tenant $tenant): void
    {
        $types = [
            ['name' => 'Pickup', 'code' => 'PICKUP', 'default_fuel_type' => 'diesel', 'default_service_interval_km' => 10000],
            ['name' => 'SUV', 'code' => 'SUV', 'default_fuel_type' => 'petrol', 'default_service_interval_km' => 10000],
            ['name' => 'Light Truck', 'code' => 'LTRUCK', 'default_fuel_type' => 'diesel', 'default_service_interval_km' => 15000],
        ];

        foreach ($types as $type) {
            VehicleType::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $type['code']],
                [...$type, 'tenant_id' => $tenant->id, 'is_active' => true]
            );
        }
    }

    private function seedDepartments(Tenant $tenant): void
    {
        $manager = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', "admin@{$tenant->slug}.local")
            ->first();

        $departments = [
            ['name' => 'Operations', 'code' => 'OPS'],
            ['name' => 'Logistics', 'code' => 'LOG'],
            ['name' => 'Administration', 'code' => 'ADM'],
        ];

        foreach ($departments as $departmentData) {
            Department::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $departmentData['code']],
                [
                    ...$departmentData,
                    'tenant_id' => $tenant->id,
                    'manager_user_id' => $manager?->id,
                    'status' => 'active',
                ]
            );
        }
    }

    private function seedDrivers(Tenant $tenant): void
    {
        $driverUsers = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('email', [
                "driver1@{$tenant->slug}.local",
                "transport@{$tenant->slug}.local",
            ])
            ->get()
            ->keyBy('email');

        $operationsDepartment = Department::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('code', 'OPS')
            ->first();

        $drivers = [
            [
                'email' => "driver1@{$tenant->slug}.local",
                'name' => 'Driver One',
                'employee_number' => strtoupper($tenant->slug).'-DRV-001',
                'license_number' => strtoupper($tenant->slug).'-LIC-001',
            ],
            [
                'email' => "transport@{$tenant->slug}.local",
                'name' => 'Transport Officer',
                'employee_number' => strtoupper($tenant->slug).'-DRV-002',
                'license_number' => strtoupper($tenant->slug).'-LIC-002',
            ],
        ];

        foreach ($drivers as $driverData) {
            Driver::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'license_number' => $driverData['license_number']],
                [
                    'tenant_id' => $tenant->id,
                    'department_id' => $operationsDepartment?->id,
                    'user_id' => $driverUsers[$driverData['email']]?->id,
                    'name' => $driverData['name'],
                    'employee_number' => $driverData['employee_number'],
                    'license_number' => $driverData['license_number'],
                    'license_class' => 'C1',
                    'license_expiry_date' => now()->addYear()->toDateString(),
                    'phone' => '+260970000001',
                    'email' => $driverData['email'],
                    'hire_date' => now()->subMonths(8)->toDateString(),
                    'status' => 'active',
                ]
            );
        }
    }

    private function seedServiceProviders(Tenant $tenant): void
    {
        $providers = [
            ['name' => 'Prime Fleet Garage', 'provider_type' => 'garage'],
            ['name' => 'Shield Insurance Brokers', 'provider_type' => 'insurer'],
            ['name' => 'FuelNet Station', 'provider_type' => 'fuel_station'],
        ];

        foreach ($providers as $provider) {
            ServiceProvider::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $provider['name']],
                [
                    ...$provider,
                    'tenant_id' => $tenant->id,
                    'contact_person' => 'Operations Desk',
                    'phone' => '+260960000001',
                    'email' => str($provider['name'])->slug('-').'@example.test',
                    'status' => 'active',
                ]
            );
        }
    }

    private function seedVehicles(Tenant $tenant): void
    {
        $operationsDepartment = Department::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('code', 'OPS')
            ->first();

        $vehicleType = VehicleType::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('code', 'PICKUP')
            ->first();

        $vehicles = [
            [
                'registration_number' => strtoupper(substr($tenant->slug, 0, 3)).'-1001',
                'make' => 'Toyota',
                'model' => 'Hilux',
                'year' => now()->year - 1,
                'fuel_type' => 'diesel',
                'ownership_type' => 'owned',
                'status' => 'active',
                'odometer_reading' => 18500,
            ],
            [
                'registration_number' => strtoupper(substr($tenant->slug, 0, 3)).'-1002',
                'make' => 'Ford',
                'model' => 'Ranger',
                'year' => now()->year - 2,
                'fuel_type' => 'diesel',
                'ownership_type' => 'leased',
                'status' => 'active',
                'odometer_reading' => 30200,
            ],
        ];

        foreach ($vehicles as $index => $vehicleData) {
            Vehicle::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'registration_number' => $vehicleData['registration_number']],
                [
                    ...$vehicleData,
                    'tenant_id' => $tenant->id,
                    'vehicle_type_id' => $vehicleType?->id,
                    'department_id' => $operationsDepartment?->id,
                    'asset_tag' => strtoupper($tenant->slug).'-AST-00'.($index + 1),
                    'transmission_type' => 'manual',
                    'seating_capacity' => 5,
                    'tank_capacity_liters' => 80,
                    'acquisition_date' => now()->subYears(2)->toDateString(),
                    'acquisition_cost' => 42000 + ($index * 3000),
                ]
            );
        }
    }

    private function seedAssignments(Tenant $tenant): void
    {
        $vehicle = Vehicle::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->first();

        $driver = Driver::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->first();

        $department = Department::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('code', 'OPS')
            ->first();

        if (! $vehicle || ! $driver || ! $department) {
            return;
        }

        VehicleAssignment::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenant->id,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'department_id' => $department->id,
                'assignment_type' => 'driver',
                'status' => 'active',
                'assigned_from' => now()->subDays(7)->toDateString(),
            ]
        );
    }
}
