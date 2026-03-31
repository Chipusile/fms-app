# Fleet Management System Module Catalogue

**Version:** 1.1  
**Last Updated:** 2026-03-30  
**Status:** Approved product and technical contract for phased delivery

Each module below captures purpose, core entities, major workflows, roles, API considerations, validation, audit scope, and key risks. Role names are defaults only; enforcement must remain permission-driven.

## 1. Authentication & Authorization

- Purpose: authenticate users and enforce policy-based access across all modules.
- Core entities: `users`, `roles`, `permissions`, `role_user`, `permission_role`, `personal_access_tokens`, `sessions`.
- Workflows: login, logout, password reset, permission resolution, session invalidation.
- Roles involved: all authenticated users, tenant admins, platform operators.
- API considerations: `/auth/login`, `/auth/logout`, `/auth/me`; versioned API; consistent JSON envelopes.
- Validation: strong password rules, login throttling, tenant-aware account status checks.
- Audit events: login success/failure, logout, password changes, role permission changes.
- Risks: privilege escalation through role-name checks, session fixation, stale permission caches.

## 2. Tenant / Company Management

- Purpose: onboard, configure, suspend, and brand organisations using the platform.
- Core entities: `tenants`, `settings`, tenant branding assets.
- Workflows: tenant creation, activation/suspension, branding update, default role seeding.
- Roles involved: super admin, tenant admin.
- API considerations: super-admin tenant administration plus tenant self-service endpoints for own profile/configuration.
- Validation: unique slug/domain, supported timezone/currency, safe branding file validation.
- Audit events: tenant created, updated, suspended, reactivated.
- Risks: tenant suspension not revoking live access, domain collisions, configuration sprawl.

## 3. User Management

- Purpose: manage users, profile attributes, and operational access within each tenant.
- Core entities: `users`, `role_user`.
- Workflows: invite/create user, activate/deactivate user, assign roles, update profile.
- Roles involved: tenant admin, fleet manager, standard user.
- API considerations: list, show, create, update, soft-delete, role sync, status update.
- Validation: tenant-scoped unique email, role assignments limited to tenant, status transitions.
- Audit events: user created, updated, deleted, roles synced, status changed.
- Risks: deleting last tenant admin, driver-linked accounts with historical trip references.

## 4. Role & Permission Management

- Purpose: make tenant access adaptable without code changes.
- Core entities: `roles`, `permissions`, `permission_role`.
- Workflows: seed default role templates, create custom roles, permission sync, protect system roles.
- Roles involved: tenant admin, super admin.
- API considerations: permission list is read-only; role mutations must stay tenant-scoped.
- Validation: unique role slug per tenant, permission IDs must exist, system roles protected.
- Audit events: role created, updated, deleted, permissions synced.
- Risks: role explosion, over-broad permissions, coupling business logic to seeded role names.

## 5. Fleet / Vehicle Management

- Purpose: maintain the source of truth for fleet assets.
- Core entities: `vehicles`, `vehicle_types`, `vehicle_documents`.
- Workflows: register vehicle, update status, attach documents, decommission/dispose vehicle.
- Roles involved: fleet manager, transport officer, compliance officer, viewer.
- API considerations: advanced filtering, status views, document expiry surfacing, import-ready payloads.
- Validation: unique registration per tenant, valid year/fuel type/status, document date rules.
- Audit events: vehicle created/updated, status changed, document uploaded/expired.
- Risks: odometer resets, duplicate VINs, disposing assets with active assignments.

## 6. Driver Management

- Purpose: manage operational drivers independently from full user accounts.
- Core entities: `drivers`, `driver_documents`.
- Workflows: register driver, link to user account optionally, license renewal tracking, deactivate driver.
- Roles involved: fleet manager, transport officer, compliance officer.
- API considerations: optional user linkage, bulk import readiness, driver availability flags.
- Validation: unique employee/licence references by tenant, expiry dates, phone formats.
- Audit events: driver created/updated/deactivated, license renewed, document uploaded.
- Risks: duplicate person records, terminated drivers with active allocations.

## 7. Department / Cost Centre Management

- Purpose: allocate operational ownership and cost attribution.
- Core entities: `departments`.
- Workflows: create department, assign manager, allocate vehicles, filter reports by department.
- Roles involved: tenant admin, fleet manager, department manager.
- API considerations: lightweight CRUD with references from trips, vehicles, and cost dashboards.
- Validation: unique code/name per tenant, manager must belong to same tenant.
- Audit events: department created, updated, deactivated.
- Risks: inactive departments linked to open assets or trips.

## 8. Vehicle Allocation Management

- Purpose: track where vehicles are assigned and to whom.
- Core entities: `vehicle_assignments`.
- Workflows: assign to driver, assign to department, release assignment, transfer ownership.
- Roles involved: fleet manager, transport officer.
- API considerations: historical records must be preserved; current allocation queries should be fast.
- Validation: no overlapping active assignments for same vehicle unless explicit shared allocation rules exist.
- Audit events: assignment created, reassigned, ended.
- Risks: conflicting assignments, missing end dates, backdated changes.

## 9. Trip / Journey Management

- Purpose: manage requests, approvals, scheduling, dispatch, and actual trip completion.
- Core entities: `trips`, `approval_instances`.
- Workflows: request trip, review/approve, assign vehicle/driver, start trip, complete trip, cancel trip.
- Roles involved: requester, approver, transport officer, fleet manager, driver.
- API considerations: approval states, availability checks, timeline events, export-ready filters.
- Validation: start/end chronology, active driver/vehicle status, approval requirements by route or purpose.
- Audit events: trip requested, approved, rejected, dispatched, completed, cancelled.
- Risks: double-booking, odometer gaps, late approvals.

## 10. Fuel Management

- Purpose: record refuelling and support efficiency, cost, and fraud detection analysis.
- Core entities: `fuel_logs`.
- Workflows: capture refuel, attach receipt, compare to odometer history, analyse consumption trends.
- Roles involved: driver, transport officer, finance officer, fleet manager.
- API considerations: filters by vehicle, driver, period, station, anomaly flags.
- Validation: litres and cost positive, odometer not regressive, vehicle must support selected fuel type.
- Audit events: fuel log created, updated, voided.
- Risks: duplicate receipts, manipulated odometer values, off-network cash purchases.

## 11. Maintenance Management

- Purpose: manage preventive and corrective maintenance across the fleet.
- Core entities: `maintenance_schedules`, `maintenance_records`, `work_orders`, `vehicle_components`.
- Workflows: trigger due maintenance, create request, approve work, execute, close, record cost and downtime.
- Roles involved: maintenance officer, fleet manager, vendor coordinator, finance officer.
- API considerations: due lists, work order lifecycles, vendor integrations, cost aggregation.
- Validation: due rules, status transitions, vendor references, component serial uniqueness where applicable.
- Audit events: maintenance scheduled, requested, approved, completed, work order closed.
- Risks: skipped service intervals, open work orders on active vehicles, cost overruns.

## 12. Compliance & Document Management

- Purpose: track time-bound legal and operational obligations.
- Core entities: `compliance_items`, `vehicle_documents`, `driver_documents`.
- Workflows: register compliance item, monitor renewals, send reminders, mark renewed or expired.
- Roles involved: compliance officer, tenant admin, fleet manager.
- API considerations: reminder lead times, dashboard counts, document storage abstraction.
- Validation: issue/expiry chronology, type-specific mandatory fields, supported attachment types.
- Audit events: compliance created, renewed, expired, document replaced.
- Risks: silent expirations, inconsistent categorisation, attachment lifecycle loss.

## 13. Inspections & Checklist Management

- Purpose: collect repeatable operational inspections and defect reporting.
- Core entities: `inspections`, checklist definitions, checklist responses.
- Workflows: pre-trip inspection, defect submission, review, close finding.
- Roles involved: driver, transport officer, maintenance officer.
- API considerations: configurable checklist templates and response payloads.
- Validation: checklist required fields, defect severity, vehicle status impacts.
- Audit events: inspection completed, defect raised, defect resolved.
- Risks: checklist drift between tenants, ignored critical defects.

## 14. Incident Management

- Purpose: record accidents, damages, breakdowns, and safety events.
- Core entities: `incidents`.
- Workflows: report incident, investigate, attach evidence, assign actions, close incident.
- Roles involved: driver, fleet manager, compliance officer, approver.
- API considerations: evidence uploads, severity workflows, claim and repair linkage.
- Validation: reported timestamps, mandatory minimum facts, severity-specific approvals.
- Audit events: incident created, escalated, resolved, closed.
- Risks: evidence handling, liability disputes, compliance reporting deadlines.

## 15. Vendors / Service Providers

- Purpose: maintain external service providers used across maintenance and compliance workflows.
- Core entities: `service_providers`.
- Workflows: create vendor, categorize services, manage contact and service status.
- Roles involved: maintenance officer, procurement admin, finance officer.
- API considerations: provider type filters, external reference IDs, soft delete.
- Validation: provider type, unique name by tenant, contact fields.
- Audit events: provider created, updated, deactivated.
- Risks: duplicate vendors, stale contact data, weak insurer/garage linkage.

## 16. Notifications & Alerts

- Purpose: deliver reminders, operational alerts, and workflow notifications.
- Core entities: `notifications`, future notification rules/configuration tables.
- Workflows: due reminder generation, approval alerts, incident escalation, digest notifications.
- Roles involved: all users based on permissions and subscriptions.
- API considerations: channel abstraction, in-app history, delivery status, retries.
- Validation: channel availability, recipient eligibility, de-duplication keys.
- Audit events: notification queued, sent, delivered, failed, acknowledged.
- Risks: noisy alerts, duplicate reminders, tenant-specific messaging rules not configurable enough.

## 17. Reports & Dashboards

- Purpose: turn operational data into KPIs, analytics, and exportable reports.
- Core entities: reporting views, exports, dashboard config.
- Workflows: view dashboards, apply filters, export CSV/Excel/PDF, schedule reports later.
- Roles involved: finance officer, fleet manager, compliance officer, tenant admin, viewer.
- API considerations: server-side filtering, pagination, export jobs, tenant-safe reporting views.
- Validation: date ranges, allowed filters, export limits.
- Audit events: report exported, dashboard config changed.
- Risks: expensive joins, blocking exports, misleading KPI definitions.

## 18. Audit Logs

- Purpose: provide forensic visibility into important changes and actor behavior.
- Core entities: `audit_logs`.
- Workflows: append on create/update/delete, filter by entity/user/date, inspect before/after payloads.
- Roles involved: compliance officer, tenant admin, platform operator.
- API considerations: immutable records, read-only endpoints, pagination, tenant-safe filtering.
- Validation: system-generated only; no manual write endpoints.
- Audit events: audit log generation itself is implicit system activity.
- Risks: sensitive data leakage into logs, excessive payload sizes.

## 19. System Settings / Configurations

- Purpose: keep configurable business behavior out of hardcoded classes where practical.
- Core entities: `settings`, future workflow/status config tables.
- Workflows: edit tenant branding, locale, reminder thresholds, feature toggles, approval defaults.
- Roles involved: tenant admin, platform operator for platform-wide settings.
- API considerations: grouped settings endpoints, typed config contracts, caching.
- Validation: schema-aware configuration values, valid enums and ranges.
- Audit events: setting changed, feature toggle updated.
- Risks: untyped JSON sprawl, missing validation, poor change visibility.

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/vehicles` | List vehicles (filterable, sortable, paginated) |
| POST | `/api/v1/vehicles` | Create vehicle |
| GET | `/api/v1/vehicles/{id}` | View vehicle with relationships |
| PUT | `/api/v1/vehicles/{id}` | Update vehicle |
| DELETE | `/api/v1/vehicles/{id}` | Soft-delete vehicle |
| GET | `/api/v1/vehicles/{id}/history` | Vehicle audit history |
| GET | `/api/v1/vehicles/{id}/assignments` | Assignment history |
| GET | `/api/v1/vehicles/{id}/trips` | Trip history |
| GET | `/api/v1/vehicles/{id}/fuel-logs` | Fuel log history |
| GET | `/api/v1/vehicles/{id}/maintenance` | Maintenance history |
| GET | `/api/v1/vehicle-types` | List vehicle types |
| POST | `/api/v1/vehicle-types` | Create vehicle type |
| PUT | `/api/v1/vehicle-types/{id}` | Update vehicle type |
| DELETE | `/api/v1/vehicle-types/{id}` | Soft-delete vehicle type |

### Validation Rules

- `registration_number`: required, string, max 20, unique per tenant
- `vehicle_type_id`: required, uuid, must exist in vehicle_types
- `make`: required, string, max 100
- `model`: required, string, max 100
- `year`: required, integer, min 1990, max current_year + 1
- `vin`: nullable, string, exactly 17 chars, unique per tenant
- `fuel_type`: required, in: petrol, diesel, electric, hybrid
- `tank_capacity_liters`: nullable, numeric, min 0
- `status`: required, in: active, inactive, maintenance, decommissioned
- `acquisition_date`: nullable, date, before or equal today
- `acquisition_cost`: nullable, numeric, min 0

### Audit Events

- `vehicle.created`, `vehicle.updated`, `vehicle.deleted`, `vehicle.status_changed`, `vehicle.decommissioned`

### Risks and Edge Cases

- Registration number format varies by country; validation should be configurable per tenant.
- VIN validation (check digit algorithm) should be optional, as not all regions use standard VINs.
- Cannot delete a vehicle with active trips or assignments; must complete or cancel first.
- Decommissioning a vehicle must trigger cancellation of future maintenance schedules and assignments.
- Odometer reading on the vehicle record is the "last known" reading; it is updated whenever a new odometer_reading is recorded.

---

## 5. Vehicle Documents

**Phase:** 1
**Priority:** High

### Purpose

Track and manage vehicle-related documents (insurance, registration, permits) with expiry monitoring and automated alerts.

### Core Entities

- `vehicle_documents`

### Major Workflows

1. **Document Upload:** User uploads document -> sets type, number, dates -> file stored in MinIO.
2. **Expiry Monitoring:** Scheduled job checks daily -> identifies expiring documents (30, 14, 7 days) -> sends notifications.
3. **Document Renewal:** User uploads new document for same type -> old document marked as expired -> new one is active.

### User Roles Involved

| Role | Access |
|---|---|
| Fleet Manager | Full CRUD on vehicle documents |
| Dispatcher | View documents |
| Driver | View documents for assigned vehicle |

### API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/vehicles/{vehicleId}/documents` | List documents for a vehicle |
| POST | `/api/v1/vehicles/{vehicleId}/documents` | Upload document |
| GET | `/api/v1/vehicle-documents/{id}` | View document details |
| PUT | `/api/v1/vehicle-documents/{id}` | Update document metadata |
| DELETE | `/api/v1/vehicle-documents/{id}` | Soft-delete document |
| GET | `/api/v1/vehicle-documents/{id}/download` | Download document file |
| GET | `/api/v1/vehicle-documents/expiring` | List all expiring documents |

### Validation Rules

- `type`: required, in: insurance, registration, permit, fitness, road_tax
- `document_number`: nullable, string, max 100
- `file`: nullable, file, mimes: pdf,jpg,jpeg,png, max 10MB
- `issued_date`: nullable, date, before or equal today
- `expiry_date`: nullable, date, after issued_date
- `issuing_authority`: nullable, string, max 255

### Audit Events

- `vehicle_document.created`, `vehicle_document.updated`, `vehicle_document.deleted`

### Risks and Edge Cases

- Large file uploads: implement chunked upload for files > 5MB.
- Document type uniqueness: a vehicle can have multiple documents of the same type (e.g., multiple insurance periods) but only one should be "active."
- Expired documents should be auto-flagged by the daily scheduler, not by user action.
- File storage failure: queue a retry; do not lose the metadata record.

---

## 6. Driver Management

**Phase:** 1
**Priority:** High

### Purpose

Manage driver profiles, qualifications, and status. Drivers may or may not be system users.

### Core Entities

- `drivers`

### Major Workflows

1. **Driver Registration:** Fleet manager creates driver profile -> optionally links to a user account.
2. **License Tracking:** System monitors license expiry dates -> sends alerts at configurable intervals.
3. **Driver Deactivation:** Manager suspends/terminates driver -> active assignments ended -> future trips reassigned.

### User Roles Involved

| Role | Access |
|---|---|
| Fleet Manager | Full CRUD on drivers |
| Dispatcher | View drivers, check availability |
| Driver | View own profile |

### API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/drivers` | List drivers (filterable by status, department, license expiry) |
| POST | `/api/v1/drivers` | Create driver |
| GET | `/api/v1/drivers/{id}` | View driver with documents and assignments |
| PUT | `/api/v1/drivers/{id}` | Update driver |
| DELETE | `/api/v1/drivers/{id}` | Soft-delete driver |
| GET | `/api/v1/drivers/{id}/assignments` | Assignment history |
| GET | `/api/v1/drivers/{id}/trips` | Trip history |
| GET | `/api/v1/drivers/{id}/incidents` | Incident history |
| GET | `/api/v1/drivers/available` | List available drivers for a date range |

### Validation Rules

- `first_name`: required, string, max 100
- `last_name`: required, string, max 100
- `license_number`: required, string, max 50, unique per tenant
- `license_class`: required, string, max 20
- `license_expiry_date`: required, date
- `email`: nullable, email
- `phone`: nullable, string, max 30
- `date_of_birth`: nullable, date, before today, driver must be >= 18 years
- `user_id`: nullable, uuid, must exist in users for this tenant

### Audit Events

- `driver.created`, `driver.updated`, `driver.deleted`, `driver.status_changed`, `driver.linked_to_user`

### Risks and Edge Cases

- A driver with an expired license should be flagged but not auto-deactivated (some jurisdictions allow grace periods).
- Linking a driver to a user account: the user must belong to the same tenant.
- Terminating a driver who has future trips scheduled: system must warn and require reassignment or cancellation.
- Medical expiry tracking is optional (nullable) since not all jurisdictions require it.

---

## 7. Driver Documents

**Phase:** 1
**Priority:** High

### Purpose

Track driver-related documents (licenses, medical certificates, training records) with expiry monitoring.

### Core Entities

- `driver_documents`

### Major Workflows

Same pattern as Vehicle Documents (see module 5), scoped to drivers instead of vehicles.

### User Roles Involved

| Role | Access |
|---|---|
| Fleet Manager | Full CRUD on driver documents |
| Driver | View own documents |

### API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/drivers/{driverId}/documents` | List documents for a driver |
| POST | `/api/v1/drivers/{driverId}/documents` | Upload document |
| GET | `/api/v1/driver-documents/{id}` | View document |
| PUT | `/api/v1/driver-documents/{id}` | Update document |
| DELETE | `/api/v1/driver-documents/{id}` | Soft-delete document |
| GET | `/api/v1/driver-documents/{id}/download` | Download file |
| GET | `/api/v1/driver-documents/expiring` | List expiring driver documents |

### Validation Rules

- `type`: required, in: license, medical_certificate, training_cert, id_document
- `document_number`: nullable, string, max 100
- `file`: nullable, file, mimes: pdf,jpg,jpeg,png, max 10MB
- `issued_date`: nullable, date
- `expiry_date`: nullable, date

### Audit Events

- `driver_document.created`, `driver_document.updated`, `driver_document.deleted`

### Risks and Edge Cases

- Same as Vehicle Documents. Additionally, medical certificate uploads may contain sensitive health information -- ensure access is restricted to authorized roles only.

---

## 8. Department Management

**Phase:** 1
**Priority:** Medium

### Purpose

Manage organizational departments for grouping vehicles and drivers. Supports hierarchical department structures.

### Core Entities

- `departments`

### Major Workflows

1. **Department Setup:** Admin creates department structure -> assigns department heads -> links to parent departments.
2. **Vehicle/Driver Assignment:** Vehicles and drivers are assigned to departments for cost allocation and access control.

### User Roles Involved

| Role | Access |
|---|---|
| Tenant Admin | Full CRUD on departments |
| Fleet Manager | View departments |
| Department Head | View own department and sub-departments |

### API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/departments` | List departments (flat or tree structure) |
| POST | `/api/v1/departments` | Create department |
| GET | `/api/v1/departments/{id}` | View department with vehicles and drivers |
| PUT | `/api/v1/departments/{id}` | Update department |
| DELETE | `/api/v1/departments/{id}` | Soft-delete department |
| GET | `/api/v1/departments/{id}/vehicles` | Vehicles in department |
| GET | `/api/v1/departments/{id}/drivers` | Drivers in department |
| GET | `/api/v1/departments/tree` | Full department hierarchy |

### Validation Rules

- `name`: required, string, max 255, unique per tenant
- `code`: nullable, string, max 20, unique per tenant
- `head_user_id`: nullable, uuid, must exist in users
- `parent_id`: nullable, uuid, must exist in departments, cannot be self or descendant

### Audit Events

- `department.created`, `department.updated`, `department.deleted`

### Risks and Edge Cases

- Circular parent references: validate that `parent_id` does not create a cycle.
- Deleting a department with assigned vehicles/drivers: require reassignment first or move to a default "unassigned" state.
- Depth limit on hierarchy: enforce max depth of 5 levels.

---

## 9. Vehicle Assignments

**Phase:** 2
**Priority:** High

### Purpose

Track which vehicle is assigned to which driver or department over time. Supports date-ranged assignments with overlap prevention.

### Core Entities

- `vehicle_assignments`

### Major Workflows

1. **New Assignment:** Fleet manager assigns vehicle to driver -> validates no overlap -> records start odometer.
2. **Assignment Completion:** Assignment ends -> records end odometer -> vehicle available for reassignment.
3. **Assignment Transfer:** Vehicle reassigned from one driver to another -> old assignment completed, new one created.

### User Roles Involved

| Role | Access |
|---|---|
| Fleet Manager | Full CRUD on assignments |
| Dispatcher | View assignments, create temporary assignments |
| Driver | View own assignments |

### API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/vehicle-assignments` | List assignments (filterable) |
| POST | `/api/v1/vehicle-assignments` | Create assignment |
| GET | `/api/v1/vehicle-assignments/{id}` | View assignment |
| PUT | `/api/v1/vehicle-assignments/{id}` | Update assignment |
| PUT | `/api/v1/vehicle-assignments/{id}/complete` | Complete assignment |
| DELETE | `/api/v1/vehicle-assignments/{id}` | Cancel assignment |

### Validation Rules

- `vehicle_id`: required, uuid, must exist
- `driver_id`: nullable, uuid, must exist (required if department_id is null)
- `department_id`: nullable, uuid, must exist (required if driver_id is null)
- `start_date`: required, date
- `end_date`: nullable, date, after or equal start_date
- No overlapping active assignments for the same vehicle in the same date range

### Audit Events

- `vehicle_assignment.created`, `vehicle_assignment.updated`, `vehicle_assignment.completed`, `vehicle_assignment.cancelled`

### Risks and Edge Cases

- Overlap detection: must check for existing active assignments for the same vehicle in the requested date range using a database-level constraint or application-level check with a lock.
- Open-ended assignments (null end_date): only one open-ended assignment per vehicle at a time.
- Assigning a vehicle that is in "maintenance" or "decommissioned" status should be rejected.
- Assigning to an inactive or suspended driver should be rejected.

---

## 10. Trip Management

**Phase:** 2
**Priority:** High

### Purpose

End-to-end trip lifecycle management from request to completion, including scheduling, approval, tracking, and completion.

### Core Entities

- `trips`

### Major Workflows

1. **Trip Request:** User submits trip request -> selects vehicle, driver, dates, purpose -> status = requested.
2. **Trip Approval:** Approver reviews request -> approves/rejects -> status updates -> requester notified.
3. **Trip Start:** Driver starts trip -> records actual start time and odometer -> status = in_progress.
4. **Trip Completion:** Driver ends trip -> records end time and odometer -> distance calculated -> status = completed.
5. **Trip Cancellation:** Requester or approver cancels -> reason required -> status = cancelled.

### User Roles Involved

| Role | Access |
|---|---|
| Fleet Manager | Full CRUD, approve/reject, view all trips |
| Dispatcher | Create, view, schedule trips |
| Driver | View assigned trips, start/complete trips |
| All users | Request trips for themselves |

### API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/trips` | List trips (filterable by status, vehicle, driver, date range) |
| POST | `/api/v1/trips` | Create trip request |
| GET | `/api/v1/trips/{id}` | View trip details |
| PUT | `/api/v1/trips/{id}` | Update trip |
| DELETE | `/api/v1/trips/{id}` | Cancel trip |
| PUT | `/api/v1/trips/{id}/approve` | Approve trip |
| PUT | `/api/v1/trips/{id}/reject` | Reject trip |
| PUT | `/api/v1/trips/{id}/start` | Start trip |
| PUT | `/api/v1/trips/{id}/complete` | Complete trip |
| GET | `/api/v1/trips/calendar` | Calendar view of scheduled trips |
| GET | `/api/v1/trips/{id}/fuel-logs` | Fuel logs for this trip |

### Validation Rules

- `vehicle_id`: required, uuid, must exist, must be active and available
- `driver_id`: required, uuid, must exist, must be active and available
- `purpose`: required, string, max 500
- `origin`: required, string, max 255
- `destination`: required, string, max 255
- `scheduled_start`: required, datetime, after now (for new requests)
- `scheduled_end`: required, datetime, after scheduled_start
- Start trip: `start_odometer` required, integer, >= vehicle's current odometer
- Complete trip: `end_odometer` required, integer, > start_odometer

### Audit Events

- `trip.created`, `trip.updated`, `trip.approved`, `trip.rejected`, `trip.started`, `trip.completed`, `trip.cancelled`

### Risks and Edge Cases

- Vehicle double-booking: check for overlapping approved/in_progress trips for the same vehicle.
- Driver double-booking: same check for driver.
- Starting a trip with a vehicle in maintenance: reject.
- Odometer discrepancy: if end_odometer - start_odometer seems unreasonable for the origin-destination pair, flag for review (don't block).
- Trip number generation: use a tenant-scoped sequence (e.g., TRP-2026-00001).
- Cancelled trips should not affect vehicle/driver availability calculations.

---

## 11. Fuel Management

**Phase:** 2
**Priority:** High

### Purpose

Record and analyze fuel consumption for fleet vehicles, tracking costs and efficiency metrics.

### Core Entities

- `fuel_logs`

### Major Workflows

1. **Fuel Log Entry:** Driver or fleet manager records fueling event -> captures quantity, cost, odometer, receipt.
2. **Consumption Analysis:** System calculates km/liter between full-tank fill-ups -> flags anomalies.
3. **Cost Reporting:** Aggregated fuel costs by vehicle, driver, department, time period.

### User Roles Involved

| Role | Access |
|---|---|
| Fleet Manager | Full CRUD, view analytics |
| Driver | Create fuel logs for assigned vehicle, view own logs |
| Viewer | View fuel logs and reports |

### API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/fuel-logs` | List fuel logs (filterable) |
| POST | `/api/v1/fuel-logs` | Create fuel log |
| GET | `/api/v1/fuel-logs/{id}` | View fuel log |
| PUT | `/api/v1/fuel-logs/{id}` | Update fuel log |
| DELETE | `/api/v1/fuel-logs/{id}` | Soft-delete fuel log |
| GET | `/api/v1/fuel-logs/summary` | Fuel consumption summary |
| POST | `/api/v1/fuel-logs/{id}/receipt` | Upload receipt |

### Validation Rules

- `vehicle_id`: required, uuid, must exist
- `fuel_type`: required, in: petrol, diesel, electric
- `quantity_liters`: required, numeric, min 0.1
- `cost_per_liter`: required, numeric, min 0
- `total_cost`: required, numeric, min 0, must equal quantity * cost_per_liter (within rounding)
- `odometer_reading`: required, integer, >= vehicle's last known reading
- `fueled_at`: required, datetime, not in the future
- `is_full_tank`: required, boolean

### Audit Events

- `fuel_log.created`, `fuel_log.updated`, `fuel_log.deleted`

### Risks and Edge Cases

- Fuel type mismatch: warn if fuel_type differs from vehicle's fuel_type (but don't block).
- Odometer reading validation: must be >= last recorded reading for this vehicle.
- Consumption calculation only works between consecutive full-tank fill-ups; partial fills break the chain.
- Receipt upload: validate as image/PDF, max 5MB.
- Currency handling: all costs stored in the tenant's configured currency.

---

## 12. Odometer Tracking

**Phase:** 2
**Priority:** Medium

### Purpose

Centralized odometer reading tracking from multiple sources, with anomaly detection.

### Core Entities

- `odometer_readings`

### Major Workflows

1. **Reading Capture:** Odometer readings are captured from fuel logs, trip starts/ends, inspections, and manual entry.
2. **Anomaly Detection:** System compares each new reading to the previous one -> flags if decrease or unreasonable increase.
3. **Vehicle Odometer Update:** Each new validated reading updates the vehicle's `odometer_reading` field.

### User Roles Involved

| Role | Access |
|---|---|
| Fleet Manager | View all readings, manage anomalies, manual entry |
| Driver | Automatic capture via trips and fuel logs |
| Mechanic | Manual entry during maintenance |

### API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/vehicles/{vehicleId}/odometer-readings` | List readings for a vehicle |
| POST | `/api/v1/odometer-readings` | Create manual reading |
| GET | `/api/v1/odometer-readings/anomalies` | List flagged anomalies |
| PUT | `/api/v1/odometer-readings/{id}/resolve-anomaly` | Mark anomaly as resolved |

### Validation Rules

- `vehicle_id`: required, uuid, must exist
- `reading`: required, integer, min 0
- `source`: required, in: manual, trip_start, trip_end, fuel_log, inspection, gps
- `recorded_at`: required, datetime

### Audit Events

- `odometer_reading.created`, `odometer_reading.anomaly_flagged`, `odometer_reading.anomaly_resolved`

### Risks and Edge Cases

- Readings arriving out of order (e.g., a fuel log from yesterday entered today after a trip today): sort by `recorded_at`, not `created_at`.
- Odometer rollover (very rare for modern vehicles but possible): allow manual override with admin approval.
- Multiple readings from different sources at similar times: deduplicate within a configurable threshold (e.g., 5 km).

---

## 13. Inspection Management

**Phase:** 3
**Priority:** High

### Purpose

Manage vehicle inspections using configurable checklists, tracking pass/fail results and generating follow-up work orders.

### Core Entities

- `inspections`, `inspection_items`, `checklists`, `checklist_items`

### Major Workflows

1. **Checklist Setup:** Fleet manager creates inspection checklists with categories and items for each vehicle type.
2. **Pre-Trip Inspection:** Driver opens inspection form -> selects checklist -> completes items with pass/fail/NA -> adds photos for failures -> signs and submits.
3. **Failed Item Follow-Up:** If critical items fail -> vehicle status set to maintenance -> work order auto-created.
4. **Inspection Review:** Fleet manager reviews inspections -> approves or requests re-inspection.

### User Roles Involved

| Role | Access |
|---|---|
| Fleet Manager | Manage checklists, view all inspections, review results |
| Driver | Perform inspections, view own inspections |
| Mechanic | View inspections related to their work orders |

### API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/inspections` | List inspections |
| POST | `/api/v1/inspections` | Create inspection |
| GET | `/api/v1/inspections/{id}` | View inspection with items |
| PUT | `/api/v1/inspections/{id}` | Update inspection |
| PUT | `/api/v1/inspections/{id}/complete` | Complete inspection |
| GET | `/api/v1/checklists` | List checklists |
| POST | `/api/v1/checklists` | Create checklist |
| GET | `/api/v1/checklists/{id}` | View checklist with items |
| PUT | `/api/v1/checklists/{id}` | Update checklist |
| DELETE | `/api/v1/checklists/{id}` | Soft-delete checklist |
| POST | `/api/v1/checklists/{id}/items` | Add item to checklist |
| PUT | `/api/v1/checklist-items/{id}` | Update checklist item |
| DELETE | `/api/v1/checklist-items/{id}` | Remove checklist item |

### Validation Rules

- Inspection: `vehicle_id` required; `type` required (pre_trip, post_trip, scheduled, ad_hoc); `checklist_id` nullable
- Inspection items: `result` required (pass, fail, na); `notes` required if result is fail; `photo_paths` required if checklist item has `requires_photo = true` and result is fail
- Complete inspection: all required items must have a result

### Audit Events

- `inspection.created`, `inspection.completed`, `inspection.item_failed`
- `checklist.created`, `checklist.updated`, `checklist.deleted`

### Risks and Edge Cases

- Offline inspection capability: design the API to accept bulk inspection item submissions (driver may complete offline and sync later).
- Checklist versioning: when a checklist is updated, existing in-progress inspections should use the version at the time of creation.
- Photo upload size: limit to 5MB per photo, max 3 photos per item.
- Critical failure automation: define which severity levels trigger automatic vehicle status changes.

---

## 14. Incident Management

**Phase:** 3
**Priority:** High

### Purpose

Record, track, and resolve fleet incidents including accidents, breakdowns, theft, and traffic violations.

### Core Entities

- `incidents`

### Major Workflows

1. **Incident Reporting:** User reports incident -> captures type, severity, description, photos, location.
2. **Investigation:** Fleet manager reviews -> assigns investigator -> updates status to "investigating."
3. **Resolution:** Investigation completed -> resolution documented -> costs recorded -> insurance claim tracked.
4. **Closure:** Final review -> incident closed -> analytics updated.

### User Roles Involved

| Role | Access |
|---|---|
| Fleet Manager | Full CRUD, investigate, close incidents |
| Driver | Report incidents, view own incidents |
| Tenant Admin | View all incidents, access analytics |

### API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/incidents` | List incidents (filterable by type, severity, status, date range) |
| POST | `/api/v1/incidents` | Report incident |
| GET | `/api/v1/incidents/{id}` | View incident details |
| PUT | `/api/v1/incidents/{id}` | Update incident |
| PUT | `/api/v1/incidents/{id}/investigate` | Move to investigating |
| PUT | `/api/v1/incidents/{id}/resolve` | Resolve incident |
| PUT | `/api/v1/incidents/{id}/close` | Close incident |
| POST | `/api/v1/incidents/{id}/photos` | Upload incident photos |
| GET | `/api/v1/incidents/summary` | Incident analytics summary |

### Validation Rules

- `vehicle_id`: required, uuid, must exist
- `type`: required, in: accident, breakdown, theft, vandalism, traffic_violation, other
- `severity`: required, in: minor, moderate, major, critical
- `title`: required, string, max 255
- `description`: required, string
- `occurred_at`: required, datetime, not in the future
- `location`: nullable, string, max 255
- Resolution: `resolution` required text; `actual_cost` nullable numeric

### Audit Events

- `incident.reported`, `incident.updated`, `incident.investigating`, `incident.resolved`, `incident.closed`

### Risks and Edge Cases

- Incident report timing: allow reporting up to 72 hours after occurrence (configurable).
- Photo evidence: essential for insurance claims, encourage but don't require.
- Cost tracking: distinguish between estimated and actual costs; actual may change during investigation.
- Repeat incidents for same driver: surface this pattern in analytics for management review.
- Privacy: incident details involving injuries may require restricted access.

---

## 15. Approval Workflows

**Phase:** 3
**Priority:** Medium

### Purpose

Configurable multi-step approval chains for trip requests, vehicle disposals, maintenance requests, and other approvable actions.

### Core Entities

- `approval_requests`, `approval_steps`

### Major Workflows

1. **Approval Chain Setup:** Admin configures approval chains per type -> defines steps with approver roles.
2. **Request Submission:** Action requiring approval -> system creates approval_request -> first step approver notified.
3. **Step Approval:** Approver approves -> next step activated -> next approver notified -> repeat until all steps complete.
4. **Step Rejection:** Any approver rejects -> entire request rejected -> requester notified.
5. **Auto-Escalation:** If step not actioned within configurable time -> escalate to next approver or manager.

### User Roles Involved

| Role | Access |
|---|---|
| Tenant Admin | Configure approval workflows |
| Approvers | View and action pending approvals |
| Requesters | Submit and track their requests |

### API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/approvals` | List approval requests (my pending, my submitted, all) |
| GET | `/api/v1/approvals/{id}` | View approval request with steps |
| PUT | `/api/v1/approvals/{id}/approve` | Approve current step |
| PUT | `/api/v1/approvals/{id}/reject` | Reject request |
| PUT | `/api/v1/approvals/{id}/cancel` | Cancel request (requester only) |
| GET | `/api/v1/approvals/pending` | List my pending approvals |

### Validation Rules

- Approve: optional `comments` text
- Reject: `comments` required (must explain reason)
- Cancel: optional `comments`
- Only the designated approver for the current step can approve/reject

### Audit Events

- `approval.requested`, `approval.step_approved`, `approval.step_rejected`, `approval.completed`, `approval.cancelled`, `approval.escalated`

### Risks and Edge Cases

- Approver unavailability: implement delegation or escalation rules.
- Race condition: if two people try to approve simultaneously, only one should succeed (use optimistic locking on `current_step`).
- Approval chain changes: changes to the chain configuration should not affect in-flight requests.
- Self-approval prevention: requester cannot approve their own request.

---

## 16. Maintenance Scheduling

**Phase:** 4
**Priority:** High

### Purpose

Define and manage preventive maintenance schedules based on time intervals and/or mileage thresholds, with automated reminders.

### Core Entities

- `maintenance_schedules`

### Major Workflows

1. **Schedule Creation:** Fleet manager defines maintenance schedule -> sets interval (km and/or days) -> assigns to vehicle or vehicle type.
2. **Due Date Calculation:** System recalculates next due date/km after each maintenance completion or odometer update.
3. **Reminder Generation:** Scheduled job runs daily -> checks for upcoming maintenance -> sends notifications at configurable thresholds (e.g., 7 days, 500 km before due).
4. **Work Order Generation:** When maintenance is due -> auto-generate work order (or notify for manual creation).

### User Roles Involved

| Role | Access |
|---|---|
| Fleet Manager | Full CRUD on schedules |
| Mechanic | View schedules for assigned vehicles |
| Driver | View upcoming maintenance for assigned vehicle |

### API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/maintenance-schedules` | List schedules (filterable by vehicle, type, due date) |
| POST | `/api/v1/maintenance-schedules` | Create schedule |
| GET | `/api/v1/maintenance-schedules/{id}` | View schedule |
| PUT | `/api/v1/maintenance-schedules/{id}` | Update schedule |
| DELETE | `/api/v1/maintenance-schedules/{id}` | Soft-delete schedule |
| GET | `/api/v1/maintenance-schedules/upcoming` | List upcoming maintenance (next 30 days / 1000 km) |
| GET | `/api/v1/maintenance-schedules/overdue` | List overdue maintenance |

### Validation Rules

- `name`: required, string, max 255
- `vehicle_id` or `vehicle_type_id`: at least one required
- `interval_km`: nullable, integer, min 100
- `interval_days`: nullable, integer, min 1
- At least one of `interval_km` or `interval_days` is required
- `priority`: required, in: low, medium, high, critical

### Audit Events

- `maintenance_schedule.created`, `maintenance_schedule.updated`, `maintenance_schedule.deleted`, `maintenance_schedule.due_triggered`

### Risks and Edge Cases

- Dual triggers (km AND days): maintenance is due when EITHER threshold is reached, not both.
- Vehicle type schedules: when a vehicle-type schedule exists and a vehicle has no specific schedule, the type-level schedule applies.
- Recalculation after maintenance: must update `last_performed_at`, `last_performed_km`, `next_due_at`, and `next_due_km`.
- Inactive schedules should not trigger reminders.
- Decommissioned vehicles should have their schedules auto-deactivated.

---

## 17. Work Orders

**Phase:** 4
**Priority:** High

### Purpose

Track maintenance tasks from creation to completion, including parts, labor, costs, and service provider management.

### Core Entities

- `work_orders`, `work_order_items`, `components`, `maintenance_records`

### Major Workflows

1. **Work Order Creation:** Fleet manager creates work order -> assigns to mechanic or service provider -> adds line items.
2. **Work Execution:** Mechanic updates progress -> marks items complete -> records actual hours.
3. **Cost Tracking:** Parts and labor costs tracked per item -> total computed -> compared to estimate.
4. **Completion:** All items done -> work order completed -> maintenance record created -> vehicle status updated -> schedule recalculated.

### User Roles Involved

| Role | Access |
|---|---|
| Fleet Manager | Full CRUD, assign, approve costs |
| Mechanic | View assigned work orders, update progress, complete items |
| Service Provider (future) | View assigned work orders, update status |

### API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/work-orders` | List work orders |
| POST | `/api/v1/work-orders` | Create work order |
| GET | `/api/v1/work-orders/{id}` | View work order with items |
| PUT | `/api/v1/work-orders/{id}` | Update work order |
| PUT | `/api/v1/work-orders/{id}/assign` | Assign to user/provider |
| PUT | `/api/v1/work-orders/{id}/start` | Start work |
| PUT | `/api/v1/work-orders/{id}/complete` | Complete work order |
| PUT | `/api/v1/work-orders/{id}/cancel` | Cancel work order |
| POST | `/api/v1/work-orders/{id}/items` | Add line item |
| PUT | `/api/v1/work-order-items/{id}` | Update line item |
| DELETE | `/api/v1/work-order-items/{id}` | Remove line item |
| GET | `/api/v1/components` | List components |
| POST | `/api/v1/components` | Create component |
| PUT | `/api/v1/components/{id}` | Update component |

### Validation Rules

- Work order: `vehicle_id` required; `title` required, max 255; `priority` required; `type` required
- Line item: `description` required; `type` required; `quantity` required, min 0.01; `unit_cost` required, min 0
- Complete: all items must have a status; `actual_hours` recommended

### Audit Events

- `work_order.created`, `work_order.assigned`, `work_order.started`, `work_order.completed`, `work_order.cancelled`
- `work_order_item.added`, `work_order_item.updated`, `work_order_item.completed`

### Risks and Edge Cases

- Completing a work order must set the vehicle status back to "active" (if it was "maintenance").
- Cost overruns: track variance between estimated and actual; alert if > configurable threshold (e.g., 20%).
- Parts availability: in initial phases, parts are informational only (no inventory management).
- Work order number generation: tenant-scoped sequence (WO-2026-00001).

---

## 18. Service Provider Management

**Phase:** 4
**Priority:** Medium

### Purpose

Registry of external service providers (garages, fuel stations, tyre shops) used for maintenance and fueling.

### Core Entities

- `service_providers`

### Major Workflows

1. **Provider Registration:** Fleet manager adds provider -> captures contact info, type, location.
2. **Provider Selection:** When creating work orders or fuel logs -> select from registered providers.
3. **Performance Tracking:** Aggregate cost and quality data per provider for comparison.

### User Roles Involved

| Role | Access |
|---|---|
| Fleet Manager | Full CRUD on service providers |
| Mechanic | View providers |
| Driver | View fuel station providers |

### API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/service-providers` | List providers (filterable by type, city) |
| POST | `/api/v1/service-providers` | Create provider |
| GET | `/api/v1/service-providers/{id}` | View provider with work order/fuel log history |
| PUT | `/api/v1/service-providers/{id}` | Update provider |
| DELETE | `/api/v1/service-providers/{id}` | Soft-delete provider |
| GET | `/api/v1/service-providers/{id}/work-orders` | Work orders with this provider |
| GET | `/api/v1/service-providers/{id}/stats` | Performance statistics |

### Validation Rules

- `name`: required, string, max 255
- `type`: required, in: garage, fuel_station, tyre_shop, insurance, other
- `email`: nullable, email
- `phone`: nullable, string, max 30

### Audit Events

- `service_provider.created`, `service_provider.updated`, `service_provider.deleted`

### Risks and Edge Cases

- Deactivating a provider: should not affect existing work orders or historical data.
- Provider rating: calculated from work order feedback, not manually set.

---

## 19. Compliance Management

**Phase:** 5
**Priority:** Medium

### Purpose

Track regulatory compliance items for vehicles, drivers, and the organization, with automated expiry alerting.

### Core Entities

- `compliance_items`

### Major Workflows

1. **Compliance Item Tracking:** Fleet manager creates compliance item -> links to vehicle or driver -> sets expiry.
2. **Status Monitoring:** Daily job evaluates all items -> marks expired items -> sends alerts for upcoming expirations.
3. **Compliance Reporting:** Dashboard shows compliance status across fleet -> highlights non-compliant entities.

### User Roles Involved

| Role | Access |
|---|---|
| Fleet Manager | Full CRUD on compliance items |
| Tenant Admin | View compliance dashboard |
| Driver | View own compliance items |

### API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/compliance-items` | List compliance items (filterable by status, category, entity) |
| POST | `/api/v1/compliance-items` | Create compliance item |
| GET | `/api/v1/compliance-items/{id}` | View compliance item |
| PUT | `/api/v1/compliance-items/{id}` | Update compliance item |
| DELETE | `/api/v1/compliance-items/{id}` | Soft-delete |
| GET | `/api/v1/compliance-items/dashboard` | Compliance status summary |
| GET | `/api/v1/compliance-items/expiring` | Items expiring within configurable window |

### Validation Rules

- `compliant_type`: required, in: vehicle, driver, organization
- `compliant_id`: required, uuid, must exist for the given type
- `category`: required, string, max 100
- `name`: required, string, max 255
- `expiry_date`: nullable, date

### Audit Events

- `compliance_item.created`, `compliance_item.updated`, `compliance_item.expired`, `compliance_item.renewed`

### Risks and Edge Cases

- Polymorphic relationship: ensure the `compliant_type` + `compliant_id` combination is validated against the actual model.
- Auto-expiry: the daily job must mark items as expired, not rely on user action.
- Compliance items may overlap with vehicle/driver documents; provide clear guidance on when to use which.

---

## 20. Reporting and Analytics

**Phase:** 5
**Priority:** Medium

### Purpose

Dashboards, reports, and data exports providing operational insights into fleet performance, costs, and utilization.

### Core Entities

No dedicated tables; reads from all other tables.

### Major Workflows

1. **Dashboard Rendering:** User opens dashboard -> API returns pre-aggregated metrics -> frontend renders charts.
2. **Report Generation:** User selects report type and parameters -> system generates report -> available as on-screen view or downloadable (CSV, PDF).
3. **Scheduled Reports:** Admin configures scheduled report -> system generates and emails on schedule.

### Key Reports

| Report | Description | Key Metrics |
|---|---|---|
| Fleet Overview | Current fleet status summary | Total vehicles by status, type, department |
| Vehicle Utilization | How much each vehicle is used | Trips, distance, days active/idle |
| Fuel Consumption | Fuel efficiency analysis | Liters, cost, km/liter per vehicle |
| Maintenance Cost | Maintenance spending analysis | Cost per vehicle, preventive vs corrective ratio |
| Driver Performance | Driver activity and incidents | Trips, distance, incidents, inspections |
| Compliance Status | Current compliance posture | Items by status, expiring soon, non-compliant |
| Trip Analysis | Trip patterns and costs | Trips by purpose, department, distance |
| Incident Summary | Incident trends | Count by type, severity, resolution time |

### User Roles Involved

| Role | Access |
|---|---|
| Tenant Admin | All reports |
| Fleet Manager | All operational reports |
| Department Head | Department-scoped reports |
| Viewer | Permitted report types |

### API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/reports/dashboard` | Dashboard metrics |
| GET | `/api/v1/reports/fleet-overview` | Fleet overview report |
| GET | `/api/v1/reports/vehicle-utilization` | Vehicle utilization |
| GET | `/api/v1/reports/fuel-consumption` | Fuel consumption |
| GET | `/api/v1/reports/maintenance-cost` | Maintenance costs |
| GET | `/api/v1/reports/driver-performance` | Driver performance |
| GET | `/api/v1/reports/compliance-status` | Compliance status |
| GET | `/api/v1/reports/trip-analysis` | Trip analysis |
| GET | `/api/v1/reports/incident-summary` | Incident summary |
| POST | `/api/v1/reports/export` | Generate export (CSV/PDF) -- returns job ID |
| GET | `/api/v1/reports/export/{jobId}` | Check export status / download |

### Validation Rules

- All reports: `date_from` and `date_to` optional, valid dates, date_from <= date_to
- Filters: `vehicle_id`, `driver_id`, `department_id` all optional, valid UUIDs
- Export: `format` required, in: csv, pdf; `type` required (report name)

### Audit Events

- `report.generated`, `report.exported`

### Risks and Edge Cases

- Performance: complex aggregation queries on large datasets; use materialized views or pre-computed summaries for dashboards.
- Export queue: large exports must be async; user downloads from a temporary signed URL.
- Time zones: all date-based reports must respect the tenant's configured timezone.
- Empty data: reports must handle zero-data states gracefully with appropriate messaging.

---

## 21. Notifications

**Phase:** 5
**Priority:** Medium

### Purpose

Multi-channel notification delivery for system events, alerts, and reminders.

### Core Entities

- `notifications`

### Notification Types

| Category | Events |
|---|---|
| Document Expiry | Vehicle/driver document expiring in 30/14/7/1 days |
| Maintenance | Maintenance due, work order assigned, work order completed |
| Trips | Trip approved/rejected, trip starting soon, trip completed |
| Incidents | New incident reported, incident status changed |
| Approvals | Approval requested, approval completed |
| Compliance | Compliance item expiring, status changed |
| System | Password reset, welcome email, account changes |

### Channels

| Channel | Implementation |
|---|---|
| In-app | Database notification, real-time via WebSocket (future) |
| Email | Laravel Mail via SMTP/SES |
| Push | Web push notifications (future phase) |

### User Roles Involved

| Role | Access |
|---|---|
| All users | View own notifications, mark as read |
| Tenant Admin | Configure notification preferences |

### API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/notifications` | List my notifications (paginated) |
| GET | `/api/v1/notifications/unread-count` | Get unread count |
| PUT | `/api/v1/notifications/{id}/read` | Mark as read |
| PUT | `/api/v1/notifications/read-all` | Mark all as read |
| DELETE | `/api/v1/notifications/{id}` | Delete notification |
| GET | `/api/v1/notification-preferences` | Get my preferences |
| PUT | `/api/v1/notification-preferences` | Update my preferences |

### Validation Rules

- Mark as read: notification must belong to current user
- Preferences: valid JSON object with boolean flags per notification type per channel

### Audit Events

- Notifications themselves are not audited (they are a form of audit). Preference changes are audited.

### Risks and Edge Cases

- Notification volume: batch notifications for the same event type (e.g., don't send 50 individual emails for 50 expiring documents; send one summary).
- Email delivery failures: retry with exponential backoff, max 3 attempts.
- User preference honoring: always check preferences before sending.
- Notification cleanup: purge read notifications older than 90 days.

---

## 22. Settings and Configuration

**Phase:** 6
**Priority:** Low

### Purpose

System-wide and tenant-specific configuration management, including feature flags and user preferences.

### Core Entities

- `settings`

### Setting Groups

| Group | Examples |
|---|---|
| `general` | timezone, currency, date_format, language |
| `fleet` | default_fuel_type, odometer_unit (km/miles), registration_format |
| `notifications` | expiry_warning_days, maintenance_reminder_days |
| `maintenance` | auto_create_work_orders, cost_overrun_threshold_percent |
| `approvals` | trip_approval_required, trip_approval_chain |
| `security` | session_timeout_minutes, ip_allowlist, password_min_length |
| `features` | module_trips_enabled, module_inspections_enabled, etc. |

### User Roles Involved

| Role | Access |
|---|---|
| Tenant Admin | Manage tenant settings |
| Super Admin | Manage global settings |

### API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/settings` | Get all settings for current tenant (merged with global defaults) |
| GET | `/api/v1/settings/{group}` | Get settings for a specific group |
| PUT | `/api/v1/settings/{group}` | Update settings for a group |
| GET | `/api/v1/settings/global` | Get global defaults (superadmin only) |
| PUT | `/api/v1/settings/global/{group}` | Update global defaults (superadmin only) |

### Validation Rules

- Each setting key has its own validation rule defined in a settings schema.
- Type checking: numeric settings must be numeric, boolean must be boolean, etc.
- Range checking: e.g., `session_timeout_minutes` min 5, max 1440.
- Feature flags: boolean only.

### Audit Events

- `settings.updated` (captures group, changed keys, old and new values)

### Risks and Edge Cases

- Settings cache: all settings are cached in Redis; cache must be invalidated on update.
- Merge strategy: tenant settings override global defaults; use a layered approach.
- Feature flags controlling module visibility: if a module is disabled, its routes should return 403, not 404.
- Migration of settings: when new settings are introduced in code, a seeder must add defaults.
