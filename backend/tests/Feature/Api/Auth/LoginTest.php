<?php

namespace Tests\Feature\Api\Auth;

use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_rejects_user_with_suspended_tenant(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => TenantStatus::Suspended,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'driver@example.com',
            'password' => Hash::make('password123'),
            'status' => UserStatus::Active,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('message', 'Your organisation account is not active.');
    }

    public function test_spa_login_persists_session_for_follow_up_profile_request(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => TenantStatus::Active,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'status' => UserStatus::Active,
        ]);

        $headers = [
            'Accept' => 'application/json',
            'Origin' => 'http://localhost:5174',
            'Referer' => 'http://localhost:5174/',
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        $csrfResponse = $this->withHeaders($headers)->get('/sanctum/csrf-cookie');

        $csrfResponse->assertNoContent();

        $csrfCookie = $this->cookieFromResponse($csrfResponse->headers->getCookies(), 'XSRF-TOKEN');
        $sessionCookie = $this->cookieFromResponse($csrfResponse->headers->getCookies(), config('session.cookie'));

        $this->assertNotNull($csrfCookie);
        $this->assertNotNull($sessionCookie);

        $loginResponse = $this->withHeaders([
            ...$headers,
            'X-XSRF-TOKEN' => urldecode($csrfCookie->getValue()),
        ])
            ->withCookie($csrfCookie->getName(), $csrfCookie->getValue())
            ->withCookie($sessionCookie->getName(), $sessionCookie->getValue())
            ->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'password123',
            ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $authenticatedSessionCookie = $this->cookieFromResponse(
            $loginResponse->headers->getCookies(),
            config('session.cookie')
        ) ?? $sessionCookie;

        $this->withHeaders($headers)
            ->withCookie($authenticatedSessionCookie->getName(), $authenticatedSessionCookie->getValue())
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    /**
     * @param  list<Cookie>  $cookies
     */
    private function cookieFromResponse(array $cookies, string $name): ?Cookie
    {
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        return null;
    }
}
