<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,  // Must be first — roles reference permissions
            TenantSeeder::class,      // Creates tenants with default roles
            OperationalSettingSeeder::class,
            UserSeeder::class,        // Creates users and assigns roles
            FleetMasterDataSeeder::class,
        ]);
    }
}
