# Phase 3B Progress Report

## 1. Phase Objective

Extend operations management beyond trips and fuel by delivering checklist-driven inspections, incident reporting, a reusable approval queue foundation, and in-app notifications.

## 2. Scope In

- inspection template and checklist definition management
- vehicle inspection execution with checklist responses and defect flags
- incident reporting with severity, operational context, and lifecycle tracking
- approval request queue for incident and inspection escalations
- in-app notification inbox with read and acknowledge actions
- tenant-configured approval triggers for critical inspections and high-severity incidents
- backend and frontend regression coverage for the Phase 3B slice

## 3. Scope Out

- SMS, email, or push notification delivery channels
- multi-step approval chains with dynamic routing builders
- insurer claims workflows and legal case management depth
- recurring reminder jobs for compliance and maintenance due dates
- dispatch board, map tracking, or calendar visual scheduling

## 4. Assumptions

- Phase 3 should continue to ship in bounded slices to avoid destabilising the already working trip and fuel workflows
- approval orchestration should become reusable without forcing a rewrite of the Phase 3A trip lifecycle
- notifications should start as tenant-safe in-app records before external channels are added
- inspection checklist definitions must remain tenant-configurable and should not be hardcoded per organisation

## 5. Dependencies

- vehicles, drivers, trips, and departments from Phases 2 and 3A remain the source of truth for operational references
- tenant settings stay the mechanism for configuration-driven approval behavior
- RBAC must remain permission-based rather than coupling logic to seeded role names

## 6. Risks

- a generic approval queue can become over-engineered if it tries to solve every future approval case immediately
- inspections and incidents both create operational events, so notifications must not duplicate excessively
- checklist data can become hard to query if responses are stored without clear structure
- incident severity and inspection defect escalation rules must remain configurable enough for reuse across tenants

## 7. Proposed Implementation Approach

- add a Phase 3B migration for inspection templates, inspections, incidents, approval requests, and user notifications
- centralize approval creation/decision behavior in a service instead of duplicating it in controllers
- centralize notification creation in a service so inspections, incidents, and approvals share one path
- expose support-data endpoints for inspections and incidents so the frontend stays thin
- deliver list/form/inbox workflows in the SPA shell using the existing shared layout, table, filter, alert, and section components

## 8. File and Folder Structure To Create or Modify

- `backend/config/fleet.php`
- `backend/database/migrations/*` for Phase 3B operational tables
- `backend/app/Models/*` for inspection, incident, approval, and notification entities
- `backend/app/Services/*` for approvals and notifications
- `backend/app/Http/Requests/Api/V1/*`
- `backend/app/Http/Resources/Api/V1/*`
- `backend/app/Http/Controllers/Api/V1/*`
- `backend/app/Policies/*`
- `backend/database/seeders/*`
- `backend/tests/Feature/Api/Phase3/*`
- `frontend/src/modules/inspection-templates/*`
- `frontend/src/modules/inspections/*`
- `frontend/src/modules/incidents/*`
- `frontend/src/modules/approvals/*`
- `frontend/src/modules/notifications/*`
- `frontend/src/router/index.ts`
- `frontend/src/components/layout/AppSidebar.vue`
- `frontend/src/lib/fleet-options.ts`
- `frontend/src/types/index.ts`

## 9. Code To Generate

- tenant-aware inspection template and execution schema
- tenant-aware incident API and UI
- approval request queue API and decision workflow
- in-app notification inbox API and UI
- seed defaults for operational approval settings and permissions

## 10. Commands To Run

```bash
cd backend
php artisan test

cd frontend
npm run type-check
npm run test:unit:run
source ~/.nvm/nvm.sh && nvm use 22 >/dev/null && npm run build
```

## 11. Tests To Write

- inspection and incident tenant isolation
- inspection escalation and incident approval request creation
- notification creation and read acknowledgment behavior
- approval decision permissions and state transitions
- frontend navigation and page-level type safety for new modules

## 12. Acceptance Criteria

- tenants can define inspection templates and execute inspections against vehicles
- incidents can be reported and tracked with tenant-safe filtering and workflow states
- escalated inspections and incidents create approval requests that authorized users can decide
- operational events create in-app notifications that recipients can read and acknowledge
- backend tests, frontend type-check, frontend unit tests, and production build all pass

## 13. Completion Summary

Phase 3B is delivered.

Completed in this slice:

- inspection template CRUD and checklist definition management
- inspection execution with structured responses, defect severity capture, and closure workflow
- incident reporting, update, resolution, and closure workflows
- reusable approval queue APIs and SPA decision workflow for inspection and incident reviews
- tenant-scoped in-app notification inbox with read and acknowledge actions
- frontend shell integration for inspection templates, inspections, incidents, approvals, and notifications
- backend regression coverage for inspection escalation, incident approval decisions, and notification inbox behavior
- frontend navigation regression coverage, type-safe modules, and Node 22 production build verification

What remains outside this slice:

- external notification channels such as email, SMS, and push delivery
- recurring reminder scheduling jobs for due maintenance and compliance actions
- multi-step approval routing and escalation builders
- deeper claims, legal, and insurer case-management workflows

Verification completed for this slice:

```bash
cd backend
php artisan test

cd frontend
source ~/.nvm/nvm.sh
nvm use 22 >/dev/null
npm run type-check
npm run test:unit:run
npm run build
```
