<?php

namespace Tests\Feature\Api\Tenancy;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_listing_is_scoped_to_the_authenticated_tenant(): void
    {
        $tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

        $permission = Permission::query()->create([
            'name' => 'View Users',
            'slug' => 'users.view',
            'module' => 'users',
        ]);

        $roleA = Role::withoutGlobalScopes()->create([
            'tenant_id' => $tenantA->id,
            'name' => 'Tenant A Admin',
            'slug' => 'tenant-a-admin',
        ]);
        $roleA->permissions()->sync([$permission->id]);

        $actor = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'email' => 'admin@tenant-a.test',
        ]);
        $actor->roles()->sync([$roleA->id]);

        $visibleUser = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'email' => 'visible@tenant-a.test',
        ]);

        $hiddenUser = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'email' => 'hidden@tenant-b.test',
        ]);

        Sanctum::actingAs($actor);

        $response = $this->getJson('/api/v1/users');

        $response->assertOk();
        $response->assertJsonFragment(['email' => $visibleUser->email]);
        $response->assertJsonMissing(['email' => $hiddenUser->email]);
    }
}
