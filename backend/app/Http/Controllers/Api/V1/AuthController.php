<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
}
