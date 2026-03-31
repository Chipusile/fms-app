<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class OperationalSettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Tenant::query()->get() as $tenant) {
            $settings = [
                [
                    'group' => 'approvals',
                    'key' => 'approvals.trip_approval_required',
                    'value' => true,
                    'description' => 'Require trip requests to be approved before drivers can start them.',
                ],
                [
                    'group' => 'approvals',
                    'key' => 'approvals.inspection_critical_requires_review',
                    'value' => true,
                    'description' => 'Require inspections with critical defects to create an approval request for review.',
                ],
                [
                    'group' => 'approvals',
                    'key' => 'approvals.incident_review_severities',
                    'value' => ['high', 'critical'],
                    'description' => 'Incident severities that require approval review before action can proceed.',
                ],
                [
                    'group' => 'maintenance',
                    'key' => 'maintenance.reminder_days',
                    'value' => 7,
                    'description' => 'Number of days before a maintenance due date that schedules should be treated as upcoming.',
                ],
                [
                    'group' => 'maintenance',
                    'key' => 'maintenance.reminder_km_buffer',
                    'value' => 500,
                    'description' => 'Distance threshold before a maintenance due kilometre that schedules should be treated as upcoming.',
                ],
                [
                    'group' => 'notifications',
                    'key' => 'compliance.reminder_days',
                    'value' => 30,
                    'description' => 'Number of days before expiry that compliance items should be treated as expiring soon.',
                ],
                [
                    'group' => 'maintenance',
                    'key' => 'component.reminder_days',
                    'value' => 14,
                    'description' => 'Number of days before a vehicle component replacement date that reminders should start.',
                ],
                [
                    'group' => 'maintenance',
                    'key' => 'component.reminder_km_buffer',
                    'value' => 1000,
                    'description' => 'Kilometre buffer before a vehicle component replacement threshold that reminders should start.',
                ],
                [
                    'group' => 'notifications',
                    'key' => 'notifications.reminders.enabled',
                    'value' => true,
                    'description' => 'Enable automated in-app reminders for maintenance, compliance, and vehicle component thresholds.',
                ],
            ];

            foreach ($settings as $setting) {
                Setting::withoutGlobalScopes()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'key' => $setting['key'],
                    ],
                    [
                        'group' => $setting['group'],
                        'value' => $setting['value'],
                        'description' => $setting['description'],
                    ]
                );
            }
        }
    }
}
