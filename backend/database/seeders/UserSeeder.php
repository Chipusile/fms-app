<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin (not tenant-scoped)
        User::updateOrCreate(
            ['email' => 'superadmin@fms.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'is_super_admin' => true,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]
        );

        // Create sample users for each tenant
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->createTenantUsers($tenant);
        }
    }

    private function createTenantUsers(Tenant $tenant): void
    {
        $roles = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get()->keyBy('slug');

        $users = [
            [
                'name' => 'Admin User',
                'email' => "admin@{$tenant->slug}.local",
                'role' => 'tenant-admin',
            ],
            [
                'name' => 'Fleet Manager',
                'email' => "fleet@{$tenant->slug}.local",
                'role' => 'fleet-manager',
            ],
            [
                'name' => 'Transport Officer',
                'email' => "transport@{$tenant->slug}.local",
                'role' => 'transport-officer',
            ],
            [
                'name' => 'Maintenance Officer',
                'email' => "maintenance@{$tenant->slug}.local",
                'role' => 'maintenance-officer',
            ],
            [
                'name' => 'Driver One',
                'email' => "driver1@{$tenant->slug}.local",
                'role' => 'driver',
            ],
            [
                'name' => 'Viewer User',
                'email' => "viewer@{$tenant->slug}.local",
                'role' => 'viewer',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::withoutGlobalScopes()->updateOrCreate(
                ['email' => $userData['email'], 'tenant_id' => $tenant->id],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                    'status' => UserStatus::Active,
                    'email_verified_at' => now(),
                    'tenant_id' => $tenant->id,
                ]
            );

            if (isset($roles[$userData['role']])) {
                $user->roles()->syncWithoutDetaching([$roles[$userData['role']]->id]);
            }
        }
    }
}
