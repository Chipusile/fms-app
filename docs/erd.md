# Fleet Management System ERD

**Version:** 1.1  
**Last Updated:** 2026-03-30  
**Status:** Canonical schema direction for Phase 0 and Phase 1

## 1. Modeling Conventions

- tenant-owned tables carry `tenant_id`
- soft deletes apply to business-critical records
- timestamps are standard on all mutable business tables
- uniqueness is scoped by `tenant_id` where collisions across tenants are acceptable
- configuration-driven lists should use reference/config tables or seeded enums, not scattered string literals

## 2. Platform and Identity Domain

### `tenants`

Purpose: top-level organisation boundary.

Key fields:

- `name`, `slug`, `domain`
- `status`
- `settings`
- branding and contact metadata
- timezone, currency, date format

Indexes:

- unique `slug`
- partial unique `domain`
- `status`

### `users`

Purpose: authenticated people within a tenant or platform support users.

Key fields:

- `tenant_id` nullable for platform-only support accounts
- `name`, `email`, `phone`
- `password`, `email_verified_at`
- `status`, `is_super_admin`
- `last_login_at`, `last_login_ip`

Indexes:

- unique (`tenant_id`, `email`)
- (`tenant_id`, `status`)

### `roles`

Purpose: tenant-scoped permission containers.

Key fields:

- `tenant_id`
- `name`, `slug`, `description`
- `is_system`

Indexes:

- unique (`tenant_id`, `slug`)

### `permissions`

Purpose: global list of actions the platform understands.

Key fields:

- `name`
- `slug`
- `module`
- `description`

Indexes:

- unique `slug`
- `module`

### Relationship Map

- tenant `1..n` users
- tenant `1..n` roles
- role `n..n` permissions via `permission_role`
- user `n..n` roles via `role_user`

## 3. System Foundations

### `audit_logs`

Purpose: append-only trace for sensitive entity changes.

Key fields:

- `tenant_id`
- `user_id`
- `auditable_type`, `auditable_id`
- `event`
- `old_values`, `new_values`
- request metadata

Indexes:

- (`auditable_type`, `auditable_id`)
- `tenant_id`
- `user_id`
- `created_at`

### `settings`

Purpose: tenant-level configuration store for grouped settings.

Key fields:

- `tenant_id`
- `group`
- `key`
- `value`
- `description`

Indexes:

- unique (`tenant_id`, `key`)
- (`tenant_id`, `group`)

## 4. Fleet Master Data Domain

### `departments`

Purpose: cost centre, department, project, or ownership unit.

Key fields:

- `tenant_id`
- `name`, `code`
- `manager_user_id`
- `is_active`

Indexes:

- unique (`tenant_id`, `code`)
- unique (`tenant_id`, `name`)

### `vehicle_types`

Purpose: configurable vehicle categorisation per tenant.

Key fields:

- `tenant_id`
- `name`, `description`, `is_active`

Indexes:

- unique (`tenant_id`, `name`)

### `drivers`

Purpose: driver directory separate from user accounts so operational drivers can exist with or without full app login.

Key fields:

- `tenant_id`
- optional `user_id`
- `employee_number`
- `full_name`, `phone`
- `license_number`, `license_expiry_date`
- `employment_status`

Indexes:

- unique (`tenant_id`, `employee_number`)
- unique (`tenant_id`, `license_number`)

### `service_providers`

Purpose: vendors, garages, insurers, breakdown partners, tyre suppliers.

Key fields:

- `tenant_id`
- `name`, `provider_type`
- contact fields
- `status`

Indexes:

- (`tenant_id`, `provider_type`)
- unique (`tenant_id`, `name`)

### `vehicles`

Purpose: primary fleet asset registry.

Key fields:

- `tenant_id`
- `vehicle_type_id`
- `department_id`
- registration, make, model, year, vin
- fuel, odometer, acquisition, status
- metadata JSON for future extensibility

Indexes:

- unique (`tenant_id`, `registration_number`)
- partial unique (`tenant_id`, `vin`)
- (`tenant_id`, `status`)
- (`tenant_id`, `department_id`)

### `vehicle_documents`

Purpose: vehicle-specific document lifecycle.

Key fields:

- `tenant_id`
- `vehicle_id`
- `type`, `document_number`
- `issued_date`, `expiry_date`
- `status`, `file_path`

Indexes:

- (`tenant_id`, `type`)
- (`tenant_id`, `expiry_date`)

### `driver_documents`

Purpose: driver license scans, permits, identity and supporting documents.

Structure mirrors `vehicle_documents` with `driver_id`.

### `vehicle_assignments`

Purpose: historical allocation of vehicles to departments, drivers, or projects.

Key fields:

- `tenant_id`
- `vehicle_id`
- optional `driver_id`
- optional `department_id`
- optional `project_reference`
- `assigned_from`, `assigned_to`
- `status`, `assignment_reason`

Indexes:

- (`tenant_id`, `vehicle_id`, `status`)
- (`tenant_id`, `driver_id`, `status`)

## 5. Operations Domain

### `trips`

Purpose: journey requests, approvals, dispatch, and actual trip records.

Key fields:

- `tenant_id`
- `vehicle_id`, `driver_id`, `requested_by`
- route details, purpose, timestamps
- `status`
- approval and completion references

Indexes:

- (`tenant_id`, `status`)
- (`tenant_id`, `vehicle_id`, `scheduled_start_at`)

### `odometer_logs`

Purpose: trusted mileage history for usage, maintenance triggers, and anomaly detection.

Key fields:

- `tenant_id`
- `vehicle_id`
- `reading`
- `recorded_at`
- `source`

Indexes:

- (`tenant_id`, `vehicle_id`, `recorded_at`)

### `fuel_logs`

Purpose: track refuelling, cost, efficiency, and variance.

Key fields:

- `tenant_id`
- `vehicle_id`, optional `driver_id`
- `odometer_reading`
- `litres`, `unit_price`, `total_amount`
- `fuel_station`, `receipt_reference`
- `recorded_at`

Indexes:

- (`tenant_id`, `vehicle_id`, `recorded_at`)
- (`tenant_id`, `driver_id`)

### `inspections`

Purpose: daily, pre-trip, post-trip, or scheduled checklist records.

Key fields:

- `tenant_id`
- `vehicle_id`, optional `driver_id`
- `checklist_type`
- `status`, `result`
- `submitted_at`

### `incidents`

Purpose: accidents, breakdowns, damages, and safety incidents.

Key fields:

- `tenant_id`
- `vehicle_id`, optional `driver_id`
- `incident_type`
- `severity`
- `reported_at`
- `status`
- cost and liability fields

## 6. Maintenance and Compliance Domain

### `maintenance_schedules`

Purpose: preventive triggers by time, mileage, or combined policy.

### `maintenance_records`

Purpose: completed maintenance history and cost tracking.

### `work_orders`

Purpose: authorised execution records for internal or vendor maintenance work.

### `vehicle_components`

Purpose: tyre, battery, engine component, or serialised sub-asset lifecycle tracking.

### `compliance_items`

Purpose: insurance, road tax, permits, fitness, inspections, and renewal deadlines.

Common indexes across these tables:

- (`tenant_id`, `status`)
- (`tenant_id`, `vehicle_id`)
- (`tenant_id`, `due_date`) where reminder workloads need it

## 7. Workflow and Notification Domain

### `approval_definitions`

Purpose: tenant-configured approval rules by module or trigger.

### `approval_instances`

Purpose: runtime approval records connected to business entities.

### `notifications`

Purpose: in-app and outbound notification history with delivery status.

## 8. Reporting Considerations

Analytical and export-heavy views should not depend entirely on large transactional joins forever. The expected scaling path is:

1. well-indexed transactional queries
2. scheduled summary tables or materialized views
3. export/report jobs through queues
4. read replica or analytical store if usage grows

## 9. Schema Risks and Design Notes

- current scaffold implements only the identity and platform foundation physically; the rest of this ERD is the approved contract for upcoming phases
- every future migration must be checked for tenancy boundaries, composite uniqueness, and soft-delete behavior
- document tables should use storage abstraction and never bake filesystem paths into business logic

---

## Driver Management Tables

### drivers

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `user_id` | uuid | NULLABLE, FK -> users.id | Linked system user (if applicable) |
| `department_id` | uuid | NULLABLE, FK -> departments.id | |
| `employee_number` | varchar(50) | NULLABLE | |
| `first_name` | varchar(100) | NOT NULL | |
| `last_name` | varchar(100) | NOT NULL | |
| `email` | varchar(255) | NULLABLE | |
| `phone` | varchar(30) | NULLABLE | |
| `date_of_birth` | date | NULLABLE | |
| `license_number` | varchar(50) | NOT NULL | |
| `license_class` | varchar(20) | NOT NULL | e.g., A, B, C, CE |
| `license_expiry_date` | date | NOT NULL | |
| `medical_expiry_date` | date | NULLABLE | |
| `hire_date` | date | NULLABLE | |
| `status` | varchar(20) | NOT NULL, DEFAULT 'active' | active, inactive, suspended, terminated |
| `photo_path` | varchar(500) | NULLABLE | |
| `address` | text | NULLABLE | |
| `emergency_contact_name` | varchar(200) | NULLABLE | |
| `emergency_contact_phone` | varchar(30) | NULLABLE | |
| `notes` | text | NULLABLE | |
| `metadata` | jsonb | NOT NULL, DEFAULT '{}' | |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |
| `deleted_at` | timestamptz | NULLABLE | |

**Indexes:**
- `drivers_tenant_license_unique` UNIQUE on (`tenant_id`, `license_number`) WHERE `deleted_at IS NULL`
- `drivers_tenant_employee_unique` UNIQUE on (`tenant_id`, `employee_number`) WHERE `employee_number IS NOT NULL AND deleted_at IS NULL`
- `drivers_tenant_id_index` on `tenant_id`
- `drivers_user_id_index` on `user_id`
- `drivers_status_index` on (`tenant_id`, `status`)
- `drivers_license_expiry_index` on (`tenant_id`, `license_expiry_date`)
- `drivers_department_id_index` on `department_id`

### driver_documents

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `driver_id` | uuid | NOT NULL, FK -> drivers.id | |
| `type` | varchar(50) | NOT NULL | license, medical_certificate, training_cert, id_document |
| `document_number` | varchar(100) | NULLABLE | |
| `file_path` | varchar(500) | NULLABLE | |
| `issued_date` | date | NULLABLE | |
| `expiry_date` | date | NULLABLE | |
| `issuing_authority` | varchar(255) | NULLABLE | |
| `status` | varchar(20) | NOT NULL, DEFAULT 'active' | active, expired, revoked |
| `notes` | text | NULLABLE | |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |
| `deleted_at` | timestamptz | NULLABLE | |

**Indexes:**
- `driver_documents_driver_id_index` on `driver_id`
- `driver_documents_expiry_index` on (`tenant_id`, `expiry_date`) WHERE `status = 'active'`

---

## Operations Tables

### departments

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `name` | varchar(255) | NOT NULL | |
| `code` | varchar(20) | NULLABLE | Short code (e.g., HR, OPS) |
| `head_user_id` | uuid | NULLABLE, FK -> users.id | Department head |
| `parent_id` | uuid | NULLABLE, FK -> departments.id | For hierarchy |
| `is_active` | boolean | NOT NULL, DEFAULT true | |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |
| `deleted_at` | timestamptz | NULLABLE | |

**Indexes:**
- `departments_tenant_name_unique` UNIQUE on (`tenant_id`, `name`) WHERE `deleted_at IS NULL`
- `departments_tenant_code_unique` UNIQUE on (`tenant_id`, `code`) WHERE `code IS NOT NULL AND deleted_at IS NULL`
- `departments_parent_id_index` on `parent_id`

### service_providers

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `name` | varchar(255) | NOT NULL | |
| `type` | varchar(50) | NOT NULL | garage, fuel_station, tyre_shop, insurance, other |
| `contact_person` | varchar(200) | NULLABLE | |
| `email` | varchar(255) | NULLABLE | |
| `phone` | varchar(30) | NULLABLE | |
| `address` | text | NULLABLE | |
| `city` | varchar(100) | NULLABLE | |
| `tax_id` | varchar(50) | NULLABLE | Tax identification number |
| `rating` | decimal(3,2) | NULLABLE | Average rating 0.00-5.00 |
| `is_active` | boolean | NOT NULL, DEFAULT true | |
| `notes` | text | NULLABLE | |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |
| `deleted_at` | timestamptz | NULLABLE | |

**Indexes:**
- `service_providers_tenant_id_index` on `tenant_id`
- `service_providers_type_index` on (`tenant_id`, `type`)

### vehicle_assignments

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `vehicle_id` | uuid | NOT NULL, FK -> vehicles.id | |
| `driver_id` | uuid | NULLABLE, FK -> drivers.id | |
| `department_id` | uuid | NULLABLE, FK -> departments.id | |
| `assigned_by` | uuid | NOT NULL, FK -> users.id | User who made the assignment |
| `start_date` | date | NOT NULL | |
| `end_date` | date | NULLABLE | NULL = open-ended |
| `start_odometer` | integer | NULLABLE | Odometer at assignment start |
| `end_odometer` | integer | NULLABLE | Odometer at assignment end |
| `status` | varchar(20) | NOT NULL, DEFAULT 'active' | active, completed, cancelled |
| `notes` | text | NULLABLE | |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |
| `deleted_at` | timestamptz | NULLABLE | |

**Indexes:**
- `vehicle_assignments_vehicle_id_index` on `vehicle_id`
- `vehicle_assignments_driver_id_index` on `driver_id`
- `vehicle_assignments_status_index` on (`tenant_id`, `status`)
- `vehicle_assignments_date_range_index` on (`vehicle_id`, `start_date`, `end_date`)

### trips

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `vehicle_id` | uuid | NOT NULL, FK -> vehicles.id | |
| `driver_id` | uuid | NOT NULL, FK -> drivers.id | |
| `requested_by` | uuid | NOT NULL, FK -> users.id | |
| `approved_by` | uuid | NULLABLE, FK -> users.id | |
| `trip_number` | varchar(30) | NOT NULL | Auto-generated reference |
| `purpose` | text | NOT NULL | |
| `origin` | varchar(255) | NOT NULL | |
| `destination` | varchar(255) | NOT NULL | |
| `scheduled_start` | timestamptz | NOT NULL | |
| `scheduled_end` | timestamptz | NOT NULL | |
| `actual_start` | timestamptz | NULLABLE | |
| `actual_end` | timestamptz | NULLABLE | |
| `start_odometer` | integer | NULLABLE | |
| `end_odometer` | integer | NULLABLE | |
| `distance_km` | decimal(10,2) | NULLABLE | Computed or manual |
| `status` | varchar(20) | NOT NULL, DEFAULT 'requested' | requested, approved, rejected, in_progress, completed, cancelled |
| `passengers` | integer | NULLABLE | Number of passengers |
| `cargo_description` | text | NULLABLE | |
| `notes` | text | NULLABLE | |
| `cancellation_reason` | text | NULLABLE | |
| `metadata` | jsonb | NOT NULL, DEFAULT '{}' | |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |
| `deleted_at` | timestamptz | NULLABLE | |

**Indexes:**
- `trips_tenant_trip_number_unique` UNIQUE on (`tenant_id`, `trip_number`)
- `trips_tenant_id_index` on `tenant_id`
- `trips_vehicle_id_index` on `vehicle_id`
- `trips_driver_id_index` on `driver_id`
- `trips_status_index` on (`tenant_id`, `status`)
- `trips_scheduled_start_index` on (`tenant_id`, `scheduled_start`)
- `trips_requested_by_index` on `requested_by`

### fuel_logs

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `vehicle_id` | uuid | NOT NULL, FK -> vehicles.id | |
| `driver_id` | uuid | NULLABLE, FK -> drivers.id | |
| `trip_id` | uuid | NULLABLE, FK -> trips.id | Associated trip (optional) |
| `service_provider_id` | uuid | NULLABLE, FK -> service_providers.id | Fuel station |
| `reference_number` | varchar(50) | NULLABLE | Receipt/transaction number |
| `fuel_type` | varchar(20) | NOT NULL | petrol, diesel, electric |
| `quantity_liters` | decimal(10,2) | NOT NULL | |
| `cost_per_liter` | decimal(10,4) | NOT NULL | |
| `total_cost` | decimal(15,2) | NOT NULL | |
| `odometer_reading` | integer | NOT NULL | Reading at time of fueling |
| `is_full_tank` | boolean | NOT NULL, DEFAULT true | Full tank fill-up |
| `fueled_at` | timestamptz | NOT NULL | Timestamp of fueling |
| `receipt_path` | varchar(500) | NULLABLE | |
| `notes` | text | NULLABLE | |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |
| `deleted_at` | timestamptz | NULLABLE | |

**Indexes:**
- `fuel_logs_vehicle_id_index` on `vehicle_id`
- `fuel_logs_driver_id_index` on `driver_id`
- `fuel_logs_fueled_at_index` on (`tenant_id`, `fueled_at`)
- `fuel_logs_trip_id_index` on `trip_id`

### odometer_readings

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `vehicle_id` | uuid | NOT NULL, FK -> vehicles.id | |
| `driver_id` | uuid | NULLABLE, FK -> drivers.id | |
| `reading` | integer | NOT NULL | Reading in km |
| `source` | varchar(30) | NOT NULL | manual, trip_start, trip_end, fuel_log, inspection, gps |
| `source_id` | uuid | NULLABLE | ID of the source record |
| `recorded_at` | timestamptz | NOT NULL | |
| `notes` | text | NULLABLE | |
| `is_anomaly` | boolean | NOT NULL, DEFAULT false | Flagged if reading seems incorrect |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |

**Indexes:**
- `odometer_readings_vehicle_id_index` on (`vehicle_id`, `recorded_at`)
- `odometer_readings_tenant_recorded_index` on (`tenant_id`, `recorded_at`)
- `odometer_readings_anomaly_index` on (`tenant_id`, `is_anomaly`) WHERE `is_anomaly = true`

---

## Safety and Compliance Tables

### inspections

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `vehicle_id` | uuid | NOT NULL, FK -> vehicles.id | |
| `driver_id` | uuid | NULLABLE, FK -> drivers.id | Inspector |
| `checklist_id` | uuid | NULLABLE, FK -> checklists.id | Template used |
| `trip_id` | uuid | NULLABLE, FK -> trips.id | Associated trip |
| `type` | varchar(30) | NOT NULL | pre_trip, post_trip, scheduled, ad_hoc |
| `inspection_number` | varchar(30) | NOT NULL | |
| `status` | varchar(20) | NOT NULL, DEFAULT 'draft' | draft, in_progress, completed, failed |
| `overall_result` | varchar(20) | NULLABLE | pass, fail, conditional |
| `odometer_reading` | integer | NULLABLE | |
| `inspected_at` | timestamptz | NULLABLE | |
| `completed_at` | timestamptz | NULLABLE | |
| `notes` | text | NULLABLE | |
| `signature_path` | varchar(500) | NULLABLE | Inspector signature image |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |
| `deleted_at` | timestamptz | NULLABLE | |

**Indexes:**
- `inspections_tenant_number_unique` UNIQUE on (`tenant_id`, `inspection_number`)
- `inspections_vehicle_id_index` on `vehicle_id`
- `inspections_type_index` on (`tenant_id`, `type`)
- `inspections_status_index` on (`tenant_id`, `status`)

### inspection_items

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `inspection_id` | uuid | NOT NULL, FK -> inspections.id | |
| `checklist_item_id` | uuid | NULLABLE, FK -> checklist_items.id | Source template item |
| `category` | varchar(100) | NOT NULL | e.g., Brakes, Tyres, Lights |
| `item_name` | varchar(255) | NOT NULL | |
| `result` | varchar(20) | NOT NULL | pass, fail, na |
| `severity` | varchar(20) | NULLABLE | low, medium, high, critical |
| `notes` | text | NULLABLE | |
| `photo_paths` | jsonb | NOT NULL, DEFAULT '[]' | Array of photo paths |
| `sort_order` | integer | NOT NULL, DEFAULT 0 | |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |

**Indexes:**
- `inspection_items_inspection_id_index` on `inspection_id`
- `inspection_items_result_index` on (`inspection_id`, `result`)

### checklists

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `name` | varchar(255) | NOT NULL | |
| `type` | varchar(30) | NOT NULL | pre_trip, post_trip, maintenance, safety |
| `vehicle_type_id` | uuid | NULLABLE, FK -> vehicle_types.id | Specific to a vehicle type |
| `is_active` | boolean | NOT NULL, DEFAULT true | |
| `version` | integer | NOT NULL, DEFAULT 1 | |
| `description` | text | NULLABLE | |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |
| `deleted_at` | timestamptz | NULLABLE | |

**Indexes:**
- `checklists_tenant_type_index` on (`tenant_id`, `type`)
- `checklists_vehicle_type_id_index` on `vehicle_type_id`

### checklist_items

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `checklist_id` | uuid | NOT NULL, FK -> checklists.id | |
| `category` | varchar(100) | NOT NULL | |
| `item_name` | varchar(255) | NOT NULL | |
| `description` | text | NULLABLE | |
| `is_required` | boolean | NOT NULL, DEFAULT true | |
| `requires_photo` | boolean | NOT NULL, DEFAULT false | |
| `sort_order` | integer | NOT NULL, DEFAULT 0 | |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |

**Indexes:**
- `checklist_items_checklist_id_index` on (`checklist_id`, `sort_order`)

### incidents

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `vehicle_id` | uuid | NOT NULL, FK -> vehicles.id | |
| `driver_id` | uuid | NULLABLE, FK -> drivers.id | |
| `trip_id` | uuid | NULLABLE, FK -> trips.id | |
| `reported_by` | uuid | NOT NULL, FK -> users.id | |
| `incident_number` | varchar(30) | NOT NULL | |
| `type` | varchar(50) | NOT NULL | accident, breakdown, theft, vandalism, traffic_violation, other |
| `severity` | varchar(20) | NOT NULL | minor, moderate, major, critical |
| `title` | varchar(255) | NOT NULL | |
| `description` | text | NOT NULL | |
| `location` | varchar(255) | NULLABLE | |
| `latitude` | decimal(10,8) | NULLABLE | |
| `longitude` | decimal(11,8) | NULLABLE | |
| `occurred_at` | timestamptz | NOT NULL | |
| `reported_at` | timestamptz | NOT NULL | |
| `status` | varchar(20) | NOT NULL, DEFAULT 'reported' | reported, investigating, resolved, closed |
| `resolution` | text | NULLABLE | |
| `estimated_cost` | decimal(15,2) | NULLABLE | |
| `actual_cost` | decimal(15,2) | NULLABLE | |
| `insurance_claim_number` | varchar(100) | NULLABLE | |
| `police_report_number` | varchar(100) | NULLABLE | |
| `photo_paths` | jsonb | NOT NULL, DEFAULT '[]' | |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |
| `deleted_at` | timestamptz | NULLABLE | |

**Indexes:**
- `incidents_tenant_number_unique` UNIQUE on (`tenant_id`, `incident_number`)
- `incidents_vehicle_id_index` on `vehicle_id`
- `incidents_driver_id_index` on `driver_id`
- `incidents_status_index` on (`tenant_id`, `status`)
- `incidents_severity_index` on (`tenant_id`, `severity`)
- `incidents_occurred_at_index` on (`tenant_id`, `occurred_at`)

### compliance_items

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `compliant_type` | varchar(50) | NOT NULL | vehicle, driver, organization |
| `compliant_id` | uuid | NOT NULL | Polymorphic: vehicle or driver ID |
| `category` | varchar(100) | NOT NULL | e.g., emission_test, road_worthiness, insurance |
| `name` | varchar(255) | NOT NULL | |
| `status` | varchar(20) | NOT NULL, DEFAULT 'pending' | compliant, non_compliant, pending, expired |
| `issued_date` | date | NULLABLE | |
| `expiry_date` | date | NULLABLE | |
| `document_path` | varchar(500) | NULLABLE | |
| `reference_number` | varchar(100) | NULLABLE | |
| `notes` | text | NULLABLE | |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |
| `deleted_at` | timestamptz | NULLABLE | |

**Indexes:**
- `compliance_items_compliant_index` on (`compliant_type`, `compliant_id`)
- `compliance_items_expiry_index` on (`tenant_id`, `expiry_date`) WHERE `status IN ('compliant', 'pending')`
- `compliance_items_status_index` on (`tenant_id`, `status`)

---

## Workflow Tables

### approval_requests

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `approvable_type` | varchar(100) | NOT NULL | Polymorphic model class |
| `approvable_id` | uuid | NOT NULL | Polymorphic model ID |
| `requested_by` | uuid | NOT NULL, FK -> users.id | |
| `type` | varchar(50) | NOT NULL | trip_request, vehicle_disposal, maintenance_request, etc. |
| `status` | varchar(20) | NOT NULL, DEFAULT 'pending' | pending, approved, rejected, cancelled |
| `current_step` | integer | NOT NULL, DEFAULT 1 | |
| `total_steps` | integer | NOT NULL | |
| `notes` | text | NULLABLE | |
| `completed_at` | timestamptz | NULLABLE | |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |
| `deleted_at` | timestamptz | NULLABLE | |

**Indexes:**
- `approval_requests_approvable_index` on (`approvable_type`, `approvable_id`)
- `approval_requests_status_index` on (`tenant_id`, `status`)
- `approval_requests_requested_by_index` on `requested_by`

### approval_steps

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `approval_request_id` | uuid | NOT NULL, FK -> approval_requests.id | |
| `step_number` | integer | NOT NULL | |
| `approver_id` | uuid | NOT NULL, FK -> users.id | |
| `status` | varchar(20) | NOT NULL, DEFAULT 'pending' | pending, approved, rejected, skipped |
| `decision_at` | timestamptz | NULLABLE | |
| `comments` | text | NULLABLE | |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |

**Indexes:**
- `approval_steps_request_id_index` on (`approval_request_id`, `step_number`)
- `approval_steps_approver_index` on (`approver_id`, `status`)

---

## Maintenance Tables

### maintenance_schedules

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `vehicle_id` | uuid | NULLABLE, FK -> vehicles.id | NULL = applies to vehicle type |
| `vehicle_type_id` | uuid | NULLABLE, FK -> vehicle_types.id | |
| `name` | varchar(255) | NOT NULL | e.g., Oil Change, Tyre Rotation |
| `description` | text | NULLABLE | |
| `interval_km` | integer | NULLABLE | Mileage-based interval |
| `interval_days` | integer | NULLABLE | Time-based interval |
| `last_performed_at` | timestamptz | NULLABLE | |
| `last_performed_km` | integer | NULLABLE | |
| `next_due_at` | date | NULLABLE | Computed next due date |
| `next_due_km` | integer | NULLABLE | Computed next due odometer |
| `priority` | varchar(20) | NOT NULL, DEFAULT 'medium' | low, medium, high, critical |
| `estimated_duration_hours` | decimal(5,2) | NULLABLE | |
| `estimated_cost` | decimal(15,2) | NULLABLE | |
| `is_active` | boolean | NOT NULL, DEFAULT true | |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |
| `deleted_at` | timestamptz | NULLABLE | |

**Indexes:**
- `maintenance_schedules_vehicle_id_index` on `vehicle_id`
- `maintenance_schedules_next_due_index` on (`tenant_id`, `next_due_at`) WHERE `is_active = true`
- `maintenance_schedules_next_due_km_index` on (`tenant_id`, `next_due_km`) WHERE `is_active = true`

### maintenance_records

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `vehicle_id` | uuid | NOT NULL, FK -> vehicles.id | |
| `work_order_id` | uuid | NULLABLE, FK -> work_orders.id | |
| `maintenance_schedule_id` | uuid | NULLABLE, FK -> maintenance_schedules.id | |
| `service_provider_id` | uuid | NULLABLE, FK -> service_providers.id | |
| `type` | varchar(30) | NOT NULL | preventive, corrective, emergency |
| `description` | text | NOT NULL | |
| `odometer_reading` | integer | NULLABLE | |
| `started_at` | timestamptz | NULLABLE | |
| `completed_at` | timestamptz | NULLABLE | |
| `labor_cost` | decimal(15,2) | NULLABLE | |
| `parts_cost` | decimal(15,2) | NULLABLE | |
| `total_cost` | decimal(15,2) | NULLABLE | |
| `invoice_number` | varchar(100) | NULLABLE | |
| `invoice_path` | varchar(500) | NULLABLE | |
| `notes` | text | NULLABLE | |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |
| `deleted_at` | timestamptz | NULLABLE | |

**Indexes:**
- `maintenance_records_vehicle_id_index` on `vehicle_id`
- `maintenance_records_work_order_id_index` on `work_order_id`
- `maintenance_records_completed_at_index` on (`tenant_id`, `completed_at`)

### work_orders

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `vehicle_id` | uuid | NOT NULL, FK -> vehicles.id | |
| `service_provider_id` | uuid | NULLABLE, FK -> service_providers.id | |
| `assigned_to` | uuid | NULLABLE, FK -> users.id | Assigned mechanic/user |
| `created_by` | uuid | NOT NULL, FK -> users.id | |
| `work_order_number` | varchar(30) | NOT NULL | |
| `title` | varchar(255) | NOT NULL | |
| `description` | text | NULLABLE | |
| `priority` | varchar(20) | NOT NULL, DEFAULT 'medium' | low, medium, high, urgent |
| `status` | varchar(20) | NOT NULL, DEFAULT 'open' | open, in_progress, on_hold, completed, cancelled |
| `type` | varchar(30) | NOT NULL | preventive, corrective, inspection, recall |
| `estimated_hours` | decimal(5,2) | NULLABLE | |
| `actual_hours` | decimal(5,2) | NULLABLE | |
| `estimated_cost` | decimal(15,2) | NULLABLE | |
| `actual_cost` | decimal(15,2) | NULLABLE | |
| `started_at` | timestamptz | NULLABLE | |
| `completed_at` | timestamptz | NULLABLE | |
| `due_date` | date | NULLABLE | |
| `notes` | text | NULLABLE | |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |
| `deleted_at` | timestamptz | NULLABLE | |

**Indexes:**
- `work_orders_tenant_number_unique` UNIQUE on (`tenant_id`, `work_order_number`)
- `work_orders_vehicle_id_index` on `vehicle_id`
- `work_orders_status_index` on (`tenant_id`, `status`)
- `work_orders_priority_index` on (`tenant_id`, `priority`) WHERE `status NOT IN ('completed', 'cancelled')`
- `work_orders_assigned_to_index` on `assigned_to`
- `work_orders_due_date_index` on (`tenant_id`, `due_date`) WHERE `status NOT IN ('completed', 'cancelled')`

### work_order_items

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `work_order_id` | uuid | NOT NULL, FK -> work_orders.id | |
| `component_id` | uuid | NULLABLE, FK -> components.id | |
| `description` | varchar(255) | NOT NULL | |
| `type` | varchar(20) | NOT NULL | labor, part, consumable, external_service |
| `quantity` | decimal(10,2) | NOT NULL, DEFAULT 1 | |
| `unit` | varchar(20) | NOT NULL, DEFAULT 'each' | each, hours, liters, meters |
| `unit_cost` | decimal(15,2) | NOT NULL | |
| `total_cost` | decimal(15,2) | NOT NULL | |
| `status` | varchar(20) | NOT NULL, DEFAULT 'pending' | pending, completed, cancelled |
| `notes` | text | NULLABLE | |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |

**Indexes:**
- `work_order_items_work_order_id_index` on `work_order_id`
- `work_order_items_component_id_index` on `component_id`

### components

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `name` | varchar(255) | NOT NULL | e.g., Brake Pad Set, Oil Filter |
| `part_number` | varchar(100) | NULLABLE | |
| `category` | varchar(100) | NOT NULL | engine, brakes, suspension, electrical, body, tyres |
| `unit` | varchar(20) | NOT NULL, DEFAULT 'each' | |
| `unit_cost` | decimal(15,2) | NULLABLE | Default cost |
| `description` | text | NULLABLE | |
| `is_active` | boolean | NOT NULL, DEFAULT true | |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |
| `deleted_at` | timestamptz | NULLABLE | |

**Indexes:**
- `components_tenant_part_number_unique` UNIQUE on (`tenant_id`, `part_number`) WHERE `part_number IS NOT NULL AND deleted_at IS NULL`
- `components_category_index` on (`tenant_id`, `category`)

---

## System Tables

### audit_logs

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NULLABLE, FK -> tenants.id | NULL for platform-level events |
| `user_id` | uuid | NULLABLE, FK -> users.id | NULL for system events |
| `auditable_type` | varchar(100) | NOT NULL | Model class name |
| `auditable_id` | uuid | NOT NULL | Model ID |
| `event` | varchar(20) | NOT NULL | created, updated, deleted, restored, login, etc. |
| `old_values` | jsonb | NULLABLE | Previous values (for updates) |
| `new_values` | jsonb | NULLABLE | New values (for creates/updates) |
| `ip_address` | varchar(45) | NULLABLE | |
| `user_agent` | text | NULLABLE | |
| `url` | varchar(500) | NULLABLE | Request URL |
| `created_at` | timestamptz | NOT NULL | |

**Indexes:**
- `audit_logs_auditable_index` on (`auditable_type`, `auditable_id`)
- `audit_logs_tenant_user_index` on (`tenant_id`, `user_id`)
- `audit_logs_tenant_event_index` on (`tenant_id`, `event`)
- `audit_logs_created_at_index` on `created_at`

**Note:** This table uses append-only writes. No updates or deletes. Consider partitioning by `created_at` monthly for performance at scale.

### settings

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NULLABLE, FK -> tenants.id | NULL for global settings |
| `group` | varchar(100) | NOT NULL | e.g., general, notifications, fleet, maintenance |
| `key` | varchar(100) | NOT NULL | |
| `value` | jsonb | NOT NULL | |
| `created_at` | timestamptz | NOT NULL | |
| `updated_at` | timestamptz | NOT NULL | |

**Indexes:**
- `settings_tenant_group_key_unique` UNIQUE on (`tenant_id`, `group`, `key`)
- `settings_tenant_group_index` on (`tenant_id`, `group`)

### notifications

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | uuid | PK | |
| `tenant_id` | uuid | NOT NULL, FK -> tenants.id | |
| `user_id` | uuid | NOT NULL, FK -> users.id | Recipient |
| `type` | varchar(100) | NOT NULL | Notification class name |
| `channel` | varchar(20) | NOT NULL | database, mail, push |
| `title` | varchar(255) | NOT NULL | |
| `body` | text | NOT NULL | |
| `data` | jsonb | NOT NULL, DEFAULT '{}' | Additional structured data |
| `notifiable_type` | varchar(100) | NULLABLE | Related model type |
| `notifiable_id` | uuid | NULLABLE | Related model ID |
| `read_at` | timestamptz | NULLABLE | |
| `sent_at` | timestamptz | NULLABLE | |
| `created_at` | timestamptz | NOT NULL | |

**Indexes:**
- `notifications_user_unread_index` on (`user_id`, `created_at`) WHERE `read_at IS NULL`
- `notifications_tenant_user_index` on (`tenant_id`, `user_id`)
- `notifications_notifiable_index` on (`notifiable_type`, `notifiable_id`)

---

## Relationship Summary

```
tenants
  |-- has many --> users
  |-- has many --> vehicles
  |-- has many --> drivers
  |-- has many --> departments
  |-- has many --> vehicle_types
  |-- has many --> service_providers
  |-- has many --> settings
  |-- has many --> audit_logs
  |-- has many --> notifications

users
  |-- belongs to --> tenant
  |-- many to many --> roles (via role_user)
  |-- has many --> trips (as requested_by)
  |-- has many --> approval_steps (as approver)
  |-- has one --> driver (optional link)

roles
  |-- many to many --> permissions (via permission_role)
  |-- many to many --> users (via role_user)

vehicles
  |-- belongs to --> tenant
  |-- belongs to --> vehicle_type
  |-- belongs to --> department (optional)
  |-- has many --> vehicle_documents
  |-- has many --> vehicle_assignments
  |-- has many --> trips
  |-- has many --> fuel_logs
  |-- has many --> odometer_readings
  |-- has many --> inspections
  |-- has many --> incidents
  |-- has many --> maintenance_schedules
  |-- has many --> maintenance_records
  |-- has many --> work_orders
  |-- has many --> compliance_items (polymorphic)

drivers
  |-- belongs to --> tenant
  |-- belongs to --> user (optional)
  |-- belongs to --> department (optional)
  |-- has many --> driver_documents
  |-- has many --> vehicle_assignments
  |-- has many --> trips
  |-- has many --> fuel_logs
  |-- has many --> inspections
  |-- has many --> incidents
  |-- has many --> compliance_items (polymorphic)

trips
  |-- belongs to --> vehicle
  |-- belongs to --> driver
  |-- belongs to --> user (requested_by)
  |-- has many --> fuel_logs
  |-- has one --> inspection (pre-trip)
  |-- has one --> inspection (post-trip)
  |-- has many --> incidents

work_orders
  |-- belongs to --> vehicle
  |-- belongs to --> service_provider (optional)
  |-- belongs to --> user (assigned_to)
  |-- has many --> work_order_items
  |-- has one --> maintenance_record

inspections
  |-- belongs to --> vehicle
  |-- belongs to --> driver
  |-- belongs to --> checklist (template)
  |-- has many --> inspection_items

approval_requests
  |-- belongs to --> approvable (polymorphic: trip, work_order, etc.)
  |-- has many --> approval_steps
```

---

## Indexing Strategy

### General Principles

1. **Every foreign key is indexed.** PostgreSQL does not auto-index foreign keys.
2. **Composite indexes for common query patterns.** Queries that filter by `tenant_id + status` or `tenant_id + date` get composite indexes.
3. **Partial indexes for active subsets.** For example, `WHERE status = 'active'` or `WHERE deleted_at IS NULL` to reduce index size.
4. **Unique constraints include `tenant_id`.** Ensures uniqueness is scoped to the tenant (e.g., `registration_number` is unique per tenant, not globally).
5. **Covering indexes for list queries.** Frequently accessed list queries may use covering indexes to avoid table lookups.
6. **No over-indexing.** Indexes are added based on actual query patterns, not speculatively. Monitor `pg_stat_user_indexes` for unused indexes.

### Index Naming Convention

```
{table}_{columns}_index        -- Standard index
{table}_{columns}_unique       -- Unique constraint
{table}_{columns}_partial      -- Partial index (add WHERE description in migration comment)
```

### Performance Monitoring

- Use `EXPLAIN ANALYZE` on all queries during development.
- Monitor slow queries via `pg_stat_statements`.
- Review index usage via `pg_stat_user_indexes` monthly.
- Add indexes reactively based on actual slow-query evidence, not preemptively.

---

## Multi-Tenancy Notes

### Tenant-Scoped Tables (require `tenant_id`)

All tables except `tenants`, `roles`, `permissions`, `permission_role`.

### Tenant-Scoping Enforcement

| Layer | Mechanism |
|---|---|
| **Application** | `BelongsToTenant` trait adds global scope and auto-sets `tenant_id` |
| **Database** | Foreign key constraint to `tenants.id`; composite unique indexes include `tenant_id` |
| **Testing** | Integration tests verify that cross-tenant data leakage is impossible |
| **CI** | Migration linter checks for missing `tenant_id` on new tables |

### Cross-Tenant Queries

Only the `super_admin` role can bypass tenant scoping. This is done by explicitly removing the global scope:

```php
Vehicle::withoutGlobalScope('tenant')->get(); // Only in superadmin context
```

This pattern is restricted to specific admin controllers and requires the `super_admin` role check.

### Data Migration Between Tenants

Data cannot be moved between tenants through the application. Any tenant data migration requires a direct database operation with superadmin approval and full audit logging.
