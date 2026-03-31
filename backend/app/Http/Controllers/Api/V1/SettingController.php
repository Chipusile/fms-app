<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Resources\Api\V1\SettingResource;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Get all settings for the current tenant, optionally filtered by group.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        $settings = Setting::query()
            ->when($request->input('group'), fn ($q, $group) => $q->where('group', $group))
            ->orderBy('group')
            ->orderBy('key')
            ->get();

        return ApiResponse::success(SettingResource::collection($settings));
    }

    /**
     * Bulk update settings. Expects an array of { key, value, group? } objects.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $this->authorize('updateAny', Setting::class);

        $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'max:255'],
            'settings.*.value' => ['present'],
            'settings.*.group' => ['nullable', 'string', 'max:50'],
        ]);

        foreach ($request->input('settings') as $setting) {
            Setting::setValue(
                $setting['key'],
                $setting['value'],
                $setting['group'] ?? null
            );
        }

        return ApiResponse::success(message: 'Settings updated successfully.');
    }
}
