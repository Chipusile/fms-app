<?php

namespace Tests\Feature\Api\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_settings_view_permission_can_list_but_not_update_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createUserWithPermissions($tenant, ['settings.view']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/settings')
            ->assertOk();

        $this->putJson('/api/v1/settings', [
            'settings' => [
                ['group' => 'general', 'key' => 'timezone', 'value' => 'Africa/Lusaka'],
            ],
        ])->assertForbidden();
    }

    public function test_user_with_settings_update_permission_can_bulk_update_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createUserWithPermissions($tenant, ['settings.view', 'settings.update']);

        Sanctum::actingAs($user);

        $this->putJson('/api/v1/settings', [
            'settings' => [
                ['group' => 'general', 'key' => 'timezone', 'value' => 'Africa/Lusaka'],
                ['group' => 'general', 'key' => 'date_format', 'value' => 'd/m/Y'],
            ],
        ])->assertOk()
            ->assertJsonPath('message', 'Settings updated successfully.');

        $this->assertDatabaseHas('settings', [
            'tenant_id' => $tenant->id,
            'group' => 'general',
            'key' => 'timezone',
        ]);

        $this->assertDatabaseHas('settings', [
            'tenant_id' => $tenant->id,
            'group' => 'general',
            'key' => 'date_format',
        ]);
    }

    /**
     * @param  list<string>  $permissionSlugs
     */
    private function createUserWithPermissions(Tenant $tenant, array $permissionSlugs): User
    {
        $permissionIds = collect($permissionSlugs)
            ->map(function (string $slug): int {
                $permission = Permission::query()->create([
                    'name' => str($slug)->headline()->toString(),
                    'slug' => $slug,
                    'module' => str($slug)->before('.')->toString(),
                ]);

                return $permission->id;
            });

        $role = Role::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Settings Manager',
            'slug' => 'settings-manager',
        ]);
        $role->permissions()->sync($permissionIds->all());

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
