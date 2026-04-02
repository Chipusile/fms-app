<?php

namespace Tests\Feature\Console;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionBootstrapCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_bootstrap_command_seeds_permissions_and_creates_super_admin(): void
    {
        $this->artisan('platform:bootstrap', [
            'email' => 'ops@example.com',
            'name' => 'Operations Admin',
            '--password' => 'StrongPassword!234',
        ])
            ->expectsOutput('Platform bootstrap complete.')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'ops@example.com',
            'is_super_admin' => true,
        ]);

        $this->assertGreaterThan(0, Permission::query()->count());
        $this->assertNotNull(User::withoutGlobalScopes()->where('email', 'ops@example.com')->first());
    }
}
