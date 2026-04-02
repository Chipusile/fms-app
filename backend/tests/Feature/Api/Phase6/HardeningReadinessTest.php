<?php

namespace Tests\Feature\Api\Phase6;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HardeningReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited_after_repeated_failed_attempts(): void
    {
        $tenant = Tenant::factory()->create();

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'rate-limit@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
                ->postJson('/api/v1/auth/login', [
                    'email' => 'rate-limit@example.com',
                    'password' => 'wrong-password',
                ])
                ->assertUnauthorized();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'rate-limit@example.com',
                'password' => 'wrong-password',
            ])
            ->assertStatus(429);
    }

    public function test_api_responses_include_security_headers(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createUserWithPermissions($tenant, ['reports.view']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/support-data')
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('X-Request-Id');
    }

    public function test_readiness_endpoint_reports_healthy_dependencies(): void
    {
        $this->getJson('/readyz')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.cache', 'ok');
    }

    public function test_report_export_requires_export_permission_even_when_report_view_is_granted(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createUserWithPermissions($tenant, ['reports.view']);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/reports/exports', [
            'type' => 'fleet-overview',
            'format' => 'csv',
        ])->assertForbidden();
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
            'name' => 'Phase 6 Hardening Tester '.uniqid(),
            'slug' => 'phase-6-hardening-tester-'.uniqid(),
        ]);
        $role->permissions()->sync($permissionIds->all());

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
