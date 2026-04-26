<?php

use App\Models\Driver;
use App\Models\ServiceProvider;
use App\Models\Vehicle;

return [
    'plans' => [
        'trial' => [
            'name' => 'Trial',
            'trial_days' => 14,
            'limits' => [
                'vehicles' => 5,
                'users' => 3,
                'drivers' => 5,
            ],
        ],
        'starter' => [
            'name' => 'Starter',
            'limits' => [
                'vehicles' => 25,
                'users' => 10,
                'drivers' => 25,
            ],
        ],
        'pro' => [
            'name' => 'Pro',
            'limits' => [
                'vehicles' => null,
                'users' => null,
                'drivers' => null,
            ],
        ],
    ],
    'vehicle' => [
        'fuel_types' => ['petrol', 'diesel', 'electric', 'hybrid'],
        'transmission_types' => ['manual', 'automatic', 'semi_automatic'],
        'ownership_types' => ['owned', 'leased', 'rented'],
        'statuses' => ['active', 'inactive', 'maintenance', 'decommissioned'],
    ],
    'vehicle_type' => [
        'statuses' => ['active', 'inactive'],
    ],
    'department' => [
        'statuses' => ['active', 'inactive'],
    ],
    'driver' => [
        'statuses' => ['active', 'inactive', 'on_leave', 'suspended'],
    ],
    'service_provider' => [
        'types' => ['garage', 'insurer', 'fuel_station', 'tyre_shop', 'towing', 'inspection_center', 'other'],
        'statuses' => ['active', 'inactive'],
    ],
    'vehicle_assignment' => [
        'types' => ['driver', 'department', 'pool'],
        'statuses' => ['active', 'released'],
    ],
    'trip' => [
        'statuses' => ['requested', 'approved', 'rejected', 'in_progress', 'completed', 'cancelled'],
        'number_prefix' => 'TRP',
    ],
    'inspection_template' => [
        'statuses' => ['active', 'inactive'],
        'applies_to' => ['vehicle'],
        'response_types' => ['pass_fail', 'boolean', 'text', 'number'],
    ],
    'inspection' => [
        'statuses' => ['completed', 'requires_action', 'reviewed', 'closed'],
        'results' => ['pass', 'fail'],
        'defect_severities' => ['minor', 'major', 'critical'],
        'number_prefix' => 'INSP',
    ],
    'incident' => [
        'types' => ['accident', 'damage', 'breakdown', 'theft', 'safety', 'other'],
        'severities' => ['low', 'medium', 'high', 'critical'],
        'statuses' => ['reported', 'under_review', 'action_required', 'resolved', 'closed', 'rejected'],
        'number_prefix' => 'INC',
    ],
    'approval_request' => [
        'types' => ['inspection_review', 'incident_review'],
        'statuses' => ['pending', 'approved', 'rejected', 'cancelled'],
    ],
    'user_notification' => [
        'types' => [
            'approval_pending',
            'approval_decided',
            'inspection_submitted',
            'incident_reported',
            'work_order_assigned',
            'maintenance_request_submitted',
            'maintenance_request_decided',
            'maintenance_due',
            'compliance_expiring',
            'component_due_replacement',
            'report_export_failed',
        ],
        'statuses' => ['unread', 'read', 'acknowledged'],
    ],
    'maintenance_schedule' => [
        'types' => ['preventive', 'inspection_follow_up', 'corrective'],
        'statuses' => ['active', 'paused', 'completed'],
    ],
    'work_order' => [
        'types' => ['preventive', 'inspection_follow_up', 'corrective'],
        'priorities' => ['low', 'medium', 'high', 'critical'],
        'statuses' => ['open', 'in_progress', 'completed', 'cancelled'],
        'number_prefix' => 'WO',
    ],
    'maintenance_record' => [
        'types' => ['preventive', 'inspection_follow_up', 'corrective'],
    ],
    'maintenance_request' => [
        'types' => ['preventive', 'corrective', 'breakdown', 'inspection_follow_up', 'component_replacement'],
        'priorities' => ['low', 'medium', 'high', 'critical'],
        'statuses' => ['submitted', 'approved', 'rejected', 'converted', 'cancelled'],
        'number_prefix' => 'MR',
    ],
    'vehicle_component' => [
        'types' => ['tyre', 'battery', 'tracker', 'other'],
        'statuses' => ['active', 'due_replacement', 'retired', 'failed'],
        'condition_statuses' => ['good', 'watch', 'critical', 'retired'],
        'number_prefix' => 'CMP',
    ],
    'compliance_item' => [
        'categories' => ['insurance', 'road_tax', 'fitness', 'permit', 'registration', 'license', 'inspection'],
        'statuses' => ['valid', 'expiring_soon', 'expired', 'waived', 'renewed'],
        'compliants' => [
            'vehicle' => Vehicle::class,
            'driver' => Driver::class,
        ],
    ],
    'odometer' => [
        'sources' => ['manual', 'trip_start', 'trip_end', 'fuel_log', 'inspection', 'maintenance', 'gps'],
        'max_daily_distance_km' => 1200,
    ],
    'asset_document' => [
        'statuses' => ['active', 'expired', 'replaced'],
        'types' => ['registration', 'insurance', 'license', 'inspection', 'permit', 'contract', 'other'],
        'documentables' => [
            'vehicle' => Vehicle::class,
            'driver' => Driver::class,
            'service_provider' => ServiceProvider::class,
        ],
        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'],
        'max_upload_kb' => 10240,
        'download_url_ttl_minutes' => env('ASSET_DOCUMENT_DOWNLOAD_URL_TTL_MINUTES', 10),
        'scan' => [
            'enabled' => (bool) env('ASSET_DOCUMENT_SCAN_ENABLED', false),
            'command' => env('ASSET_DOCUMENT_SCAN_COMMAND', 'clamscan'),
            'timeout_seconds' => (int) env('ASSET_DOCUMENT_SCAN_TIMEOUT_SECONDS', 120),
        ],
    ],
    'bulk_import' => [
        'templates' => [
            'vehicles' => [
                'label' => 'Vehicles bulk onboarding',
                'description' => 'Use this template to register tenant vehicle assets in a controlled batch before operational workflows begin.',
                'filename' => 'vehicle-import-template.csv',
                'columns' => [
                    'registration_number',
                    'asset_tag',
                    'vin',
                    'vehicle_type_code',
                    'department_code',
                    'make',
                    'model',
                    'year',
                    'color',
                    'fuel_type',
                    'transmission_type',
                    'ownership_type',
                    'status',
                    'seating_capacity',
                    'tank_capacity_liters',
                    'odometer_reading',
                    'acquisition_date',
                    'acquisition_cost',
                    'notes',
                ],
                'sample_row' => [
                    'registration_number' => 'BBA-4201',
                    'asset_tag' => 'VH-001',
                    'vin' => '1HGBH41JXMN109186',
                    'vehicle_type_code' => 'PICKUP',
                    'department_code' => 'OPS',
                    'make' => 'Toyota',
                    'model' => 'Hilux',
                    'year' => '2025',
                    'color' => 'White',
                    'fuel_type' => 'diesel',
                    'transmission_type' => 'manual',
                    'ownership_type' => 'owned',
                    'status' => 'active',
                    'seating_capacity' => '5',
                    'tank_capacity_liters' => '80',
                    'odometer_reading' => '18500',
                    'acquisition_date' => '2025-01-15',
                    'acquisition_cost' => '580000.00',
                    'notes' => 'Phase 2 onboarding sample',
                ],
                'notes' => [
                    'vehicle_type_code must match an existing tenant vehicle type code.',
                    'department_code is optional but must match an existing tenant department when provided.',
                    'fuel_type, transmission_type, ownership_type, and status must use allowed configuration values.',
                    'registration_number must be unique within the tenant.',
                ],
            ],
            'drivers' => [
                'label' => 'Drivers bulk onboarding',
                'description' => 'Use this template to register operational drivers before assignments, trips, inspections, and fuel workflows go live.',
                'filename' => 'driver-import-template.csv',
                'columns' => [
                    'name',
                    'employee_number',
                    'department_code',
                    'license_number',
                    'license_class',
                    'license_expiry_date',
                    'phone',
                    'email',
                    'hire_date',
                    'status',
                    'notes',
                ],
                'sample_row' => [
                    'name' => 'Mwansa Phiri',
                    'employee_number' => 'DRV-2026-001',
                    'department_code' => 'OPS',
                    'license_number' => 'LIC-00981',
                    'license_class' => 'C1',
                    'license_expiry_date' => '2027-08-31',
                    'phone' => '+260970000001',
                    'email' => 'mwansa.phiri@example.com',
                    'hire_date' => '2026-02-01',
                    'status' => 'active',
                    'notes' => 'Night shift certified',
                ],
                'notes' => [
                    'department_code is optional but must match an existing tenant department when provided.',
                    'license_number must be unique within the tenant.',
                    'status must match the configured driver lifecycle values.',
                    'Existing user linkage is intentionally excluded from the import template and should be reconciled after onboarding.',
                ],
            ],
        ],
    ],
    'reports' => [
        'types' => [
            'fleet-overview' => [
                'label' => 'Fleet overview',
                'description' => 'Fleet asset inventory, status, department ownership, and open operational exposure.',
            ],
            'vehicle-utilization' => [
                'label' => 'Vehicle utilization',
                'description' => 'Trip volumes, completed distance, and vehicle activity over the selected period.',
            ],
            'fuel-consumption' => [
                'label' => 'Fuel consumption',
                'description' => 'Fuel volumes, spend, and refueling patterns by vehicle.',
            ],
            'maintenance-cost' => [
                'label' => 'Maintenance cost',
                'description' => 'Maintenance volume, cost composition, and downtime by vehicle.',
            ],
            'compliance-status' => [
                'label' => 'Compliance status',
                'description' => 'Renewal posture and expiry exposure for compliance-controlled records.',
            ],
            'incident-summary' => [
                'label' => 'Incident summary',
                'description' => 'Incident counts, severity distribution, cost exposure, and closure posture.',
            ],
        ],
        'export_formats' => ['csv'],
        'export_statuses' => ['queued', 'processing', 'completed', 'failed'],
        'export_disk' => env('REPORT_EXPORT_DISK', env('FILESYSTEM_DISK', 'local')),
        'export_queue' => env('REPORT_EXPORT_QUEUE', 'reports'),
        'default_month_window' => 6,
        'default_day_window' => 30,
        'max_export_rows' => 5000,
    ],
];
