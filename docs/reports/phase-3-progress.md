# Phase 3A Progress Report

## 1. Phase Objective

Begin operations management on top of the completed Phase 2 master-data contract by implementing the first operational execution slice: trip lifecycle, fuel logging, and odometer capture.

## 2. Scope In

- trip request, approval, rejection, start, completion, and cancellation lifecycle
- tenant-aware trip list and form workflows
- odometer reading capture from trips, fuel logs, and manual entry
- fuel log CRUD with operational cost and odometer validation
- support-data endpoints required by operational forms
- tenant-configured trip approval requirement setting
- backend and frontend regression coverage for the first operational slice

## 3. Scope Out

- inspections and checklist workflows
- incidents and accident case management
- generic multi-step approval engine beyond trip approval behavior
- notification delivery engine and unread inbox UI
- calendar scheduling views and dispatch board UX
- analytics-heavy summaries beyond basic operational lists

## 4. Assumptions

- Phase 3 should be delivered incrementally because trips, fuel, inspections, incidents, approvals, and notifications are too broad to land safely in one pass
- the current codebase standard remains integer primary keys, even where earlier design notes referenced UUIDs
- trip approval should be configuration-driven through tenant settings rather than hardcoded globally
- odometer history is append-only, with anomaly resolution instead of destructive correction

## 5. Dependencies

- Phase 2 vehicle, driver, department, assignment, and vendor modules must remain the single source of truth for operational references
- RBAC permissions for `trips.*`, `fuel.*`, and new odometer permissions must resolve through the existing tenant role model
- vehicle assignment and vehicle status data must be available to validate trip and fuel workflows

## 6. Risks

- trip overlap validation can become inconsistent if approval and start/complete actions do not use the same availability rules
- odometer updates can drift if trip, fuel, and manual readings do not share one consistency service
- driver self-service trip actions depend on optional `drivers.user_id` linkage, which is not guaranteed for all tenants
- notification and approval requirements are broader than this slice, so the implementation must avoid painting the future engine into a corner

## 7. Proposed Implementation Approach

- create an operations foundation migration for `trips`, `fuel_logs`, and `odometer_readings`
- centralize odometer recording and vehicle-last-reading refresh in a reusable service
- implement trip workflow actions as explicit endpoints instead of overloading generic update logic
- expose SPA support-data endpoints for vehicles, drivers, vendors, and approval settings to keep forms thin
- add only the first operational UI pages now: trips, fuel logs, and odometer anomaly management

## 8. File and Folder Structure To Create or Modify

- `backend/config/fleet.php`
- `backend/database/migrations/*` for operational tables
- `backend/app/Models/*` for trips, fuel logs, and odometer readings
- `backend/app/Services/*` for trip and odometer workflow logic
- `backend/app/Http/Requests/Api/V1/*` for operational validation
- `backend/app/Http/Resources/Api/V1/*` for trip, fuel, and odometer payloads
- `backend/app/Http/Controllers/Api/V1/*` for operations endpoints
- `backend/app/Policies/*` for operational authorization
- `backend/database/seeders/*` for default operational settings and sample activity
- `backend/tests/Feature/Api/Phase3/*`
- `frontend/src/modules/trips/*`
- `frontend/src/modules/fuel-logs/*`
- `frontend/src/modules/odometer/*`
- `frontend/src/router/index.ts`
- `frontend/src/components/layout/AppSidebar.vue`
- `frontend/src/lib/fleet-options.ts`
- `frontend/src/types/index.ts`

## 9. Code To Generate

- operational database schema and tenant-aware models
- trip workflow API endpoints and list/form UI
- fuel log API endpoints and list/form UI
- odometer service, anomaly handling endpoints, and anomaly list/manual entry UI
- tenant default setting seeding for trip approval behavior

## 10. Commands To Run

```bash
cd backend
php artisan test
php artisan migrate:fresh --seed --database=sqlite

cd frontend
npm run type-check
npm run test:unit:run
source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && npm run build
```

## 11. Tests To Write

- trip tenant isolation and lifecycle transitions
- trip overlap and approval requirement validation
- odometer capture and anomaly resolution behavior
- fuel log validation and vehicle odometer refresh behavior
- frontend route and navigation regression coverage where new operations modules are exposed

## 12. Acceptance Criteria

- trips can be requested, approved or rejected, started, completed, and cancelled through tenant-safe APIs
- fuel logs persist only for the current tenant and validate against related vehicle data
- odometer readings are captured consistently from operational workflows and anomalies can be reviewed
- operational pages are reachable in the SPA shell with permission-aware navigation
- backend tests, frontend type-check, frontend unit tests, and a production frontend build all pass

## 13. Completion Summary

Phase 3A is delivered.

Completed in this slice:

- trip request, approval, rejection, start, completion, and cancellation APIs
- tenant-safe trip, fuel log, and odometer models with shared workflow services
- support-data endpoints and SPA screens for trips, fuel logs, and odometer anomaly/manual-reading management
- tenant-seeded trip approval setting and odometer permissions
- backend regression tests for trip approval behavior, overlap protection, odometer capture, fuel logging, and tenant isolation
- frontend navigation, unit coverage, type-safe modules, and Node 22 production build verification

What remains in broader Phase 3:

- inspections and checklist workflows
- incidents and accident reporting
- richer configurable approval orchestration beyond trip approval
- notification/reminder delivery flows and inbox UX
- broader operational dashboards beyond list-based workflow management

Verification completed for this slice:

```bash
cd backend
php artisan test

cd frontend
npm run type-check
npm run test:unit:run
source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && npm run build
```
