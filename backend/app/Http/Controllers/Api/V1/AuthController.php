<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\AcceptInvitationRequest;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterTenantRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Authenticate a user and return a session cookie (SPA) or API token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return ApiResponse::unauthorized('Invalid credentials.');
        }

        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::logout();

            return ApiResponse::forbidden('Your account is not active.');
        }

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            Auth::logout();

            return ApiResponse::forbidden('Please verify your email address before signing in.');
        }

        if (! $user->is_super_admin && (! $user->tenant || ! $user->tenant->isActive())) {
            Auth::logout();

            return ApiResponse::forbidden('Your organisation account is not active.');
        }

        // Update login tracking
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $request->session()->regenerate();

        return ApiResponse::success(
            new UserResource($user->load('roles.permissions')),
            'Login successful.'
        );
    }

    public function register(RegisterTenantRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $tenant = Tenant::create([
                'name' => (string) $request->string('tenant_name'),
                'slug' => $this->tenantSlug($request),
                'status' => TenantStatus::Active,
                'plan_id' => 'trial',
                'trial_ends_at' => now()->addDays((int) config('fleet.plans.trial.trial_days', 14)),
                'subscription_status' => 'trialing',
                'email' => (string) $request->string('email'),
            ]);

            $role = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Tenant Admin',
                'slug' => 'tenant-admin',
                'description' => 'Full access to all tenant features',
                'is_system' => true,
            ]);

            $role->permissions()->sync(Permission::pluck('id'));

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => (string) $request->string('name'),
                'email' => (string) $request->string('email'),
                'password' => (string) $request->string('password'),
                'phone' => $request->input('phone'),
            ]);

            $user->roles()->sync([$role->id]);

            return $user->load('roles.permissions', 'tenant');
        });

        $user->sendEmailVerificationNotification();

        return ApiResponse::created(
            new UserResource($user),
            'Tenant registered successfully. Please verify your email address before signing in.'
        );
    }

    private function tenantSlug(RegisterTenantRequest $request): string
    {
        $baseSlug = $request->filled('tenant_slug')
            ? Str::slug((string) $request->string('tenant_slug'))
            : Str::slug((string) $request->string('tenant_name'));

        $slug = $baseSlug ?: Str::random(8);
        $candidate = $slug;
        $suffix = 2;

        while (Tenant::where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Log the user out (invalidate session).
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::success(message: 'Logged out successfully.');
    }

    /**
     * Get the authenticated user's profile and permissions.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles.permissions', 'tenant');

        return ApiResponse::success(new UserResource($user));
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            return ApiResponse::error(__($status), 429, code: 'PASSWORD_RESET_THROTTLED');
        }

        return ApiResponse::success(
            message: 'If that email address exists, a password reset link has been sent.'
        );
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return ApiResponse::validationError([
                'email' => [__($status)],
            ]);
        }

        return ApiResponse::success(message: 'Password reset successfully.');
    }

    public function acceptInvitation(AcceptInvitationRequest $request): JsonResponse
    {
        $invitation = UserInvitation::withoutGlobalScopes()
            ->with('user.roles.permissions', 'user.tenant')
            ->where('email', (string) $request->string('email'))
            ->where('token_hash', hash('sha256', (string) $request->string('token')))
            ->first();

        if (! $invitation || ! $invitation->isAcceptable()) {
            return ApiResponse::validationError([
                'token' => ['The invitation is invalid or has expired.'],
            ]);
        }

        $user = DB::transaction(function () use ($invitation, $request) {
            $invitation->user->forceFill([
                'password' => (string) $request->string('password'),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            $invitation->update(['accepted_at' => now()]);

            return $invitation->user->fresh(['roles.permissions', 'tenant']);
        });

        return ApiResponse::success(
            new UserResource($user),
            'Invitation accepted. You can now sign in.'
        );
    }

    public function sendEmailVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success(message: 'Email address is already verified.');
        }

        $user->sendEmailVerificationNotification();

        return ApiResponse::success(message: 'Verification link sent.');
    }

    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::withoutGlobalScopes()->findOrFail($id);

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return ApiResponse::forbidden('Invalid verification link.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            event(new Verified($user));
        }

        return ApiResponse::success(message: 'Email address verified successfully.');
    }
}
