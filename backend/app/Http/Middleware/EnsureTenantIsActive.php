<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Http\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the authenticated user's tenant is active.
 * Super admins bypass this check.
 */
class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->is_super_admin) {
            return $next($request);
        }

        if (! $user->tenant || $user->tenant->status !== TenantStatus::Active) {
            return ApiResponse::forbidden('Your organisation account is not active. Please contact your administrator.');
        }

        return $next($request);
    }
}
