<?php

namespace Tests\Feature\Api\Authorization;

use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_seeders_produce_platform_basics(): void
    {
        $this->seed();

        $this->assertGreaterThan(0, Permission::count());
        $this->assertGreaterThan(0, Tenant::count());
        $this->assertTrue(User::query()->where('is_super_admin', true)->exists());
    }
}
