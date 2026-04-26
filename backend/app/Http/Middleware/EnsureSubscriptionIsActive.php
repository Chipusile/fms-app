<?php

namespace App\Http\Middleware;

use App\Http\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->is_super_admin || ! $user->tenant) {
            return $next($request);
        }

        if (! $user->tenant->hasActiveSubscription()) {
            return ApiResponse::error(
                'Your subscription is not active.',
                402,
                code: 'SUBSCRIPTION_INACTIVE'
            );
        }

        return $next($request);
    }
}
