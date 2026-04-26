<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    protected function perPage(Request $request, int $default = 15, int $max = 100): int
    {
        return min($max, max(1, (int) $request->input('per_page', $default)));
    }

    /**
     * @param  array<int, string>  $allowed
     */
    protected function sortColumn(Request $request, array $allowed, string $default): string
    {
        $sort = (string) $request->input('sort', $default);

        return in_array($sort, $allowed, true) ? $sort : $default;
    }

    protected function sortDirection(Request $request, string $default = 'asc'): string
    {
        $direction = strtolower((string) $request->input('direction', $default));

        return $direction === 'desc' ? 'desc' : 'asc';
    }

    protected function enforceTenantPlanLimit(Request $request, string $resource, int $currentCount): void
    {
        $tenant = $request->user()?->tenant;
        $limit = $tenant?->planLimit($resource);

        if ($limit !== null && $currentCount >= $limit) {
            throw ValidationException::withMessages([
                $resource => ["Your current plan allows up to {$limit} {$resource}."],
            ]);
        }
    }
}
