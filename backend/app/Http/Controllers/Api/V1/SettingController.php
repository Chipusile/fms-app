<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SettingKey;
use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Resources\Api\V1\SettingResource;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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

        $validator = Validator::make($request->all(), [
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', Rule::in(array_map(
                fn (SettingKey $key) => $key->value,
                SettingKey::cases()
            ))],
            'settings.*.value' => ['present'],
            'settings.*.group' => ['nullable', 'string', 'max:50'],
        ]);

        $validator->after(function ($validator) use ($request) {
            foreach ($request->input('settings', []) as $index => $setting) {
                $settingKey = SettingKey::tryFrom($setting['key'] ?? '');

                if (! $settingKey) {
                    continue;
                }

                try {
                    $settingKey->normalize($setting['value'] ?? null);
                } catch (\InvalidArgumentException $exception) {
                    $validator->errors()->add("settings.{$index}.value", $exception->getMessage());
                }
            }
        });

        $validator->validate();

        foreach ($request->input('settings') as $setting) {
            $settingKey = SettingKey::from($setting['key']);

            Setting::setValue(
                $settingKey->value,
                $setting['value'],
                $settingKey->group()
            );
        }

        return ApiResponse::success(message: 'Settings updated successfully.');
    }
}
