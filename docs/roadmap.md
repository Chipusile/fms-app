# Fleet Management System Roadmap

**Version:** 1.1  
**Last Updated:** 2026-03-30

## Delivery Principles

- phase work is additive and verifiable
- architecture decisions are documented before major implementation
- test coverage grows with each phase
- tenant isolation, security, and auditability are not deferred

## Phase 0. Discovery, Architecture, and Foundation

- Objective: define the product and engineering contract before scaling implementation.
- Scope in: problem framing, personas, requirements, module catalogue, ERD, tenancy and RBAC decisions, risk register, folder structure, local setup plan, root scaffold alignment.
- Scope out: complete business module implementations.
- Acceptance criteria: architecture docs approved, ERD defined, roadmap documented, repo scaffold aligned, local dependency plan in place.

## Phase 1. Core Platform Foundation

- Objective: establish secure tenancy-aware application foundations.
- Scope in: authentication, tenant/company model, user management, roles/permissions, audit trail, settings framework, shared layout and UI primitives, seeders, backend tests.
- Scope out: full fleet operations workflows.
- Acceptance criteria: clean migrations and seeds, policy-based access control, reusable app shell, baseline tests for auth/authorization/tenancy.

## Phase 2. Fleet Master Data

- Objective: stand up reusable core fleet entities.
- Scope in: vehicles, vehicle types, departments, drivers, vendors, documents, assignment foundation, import-ready structures.
- Scope out: trip approval orchestration and complex analytics.
- Acceptance criteria: tenant-safe CRUD, import templates, relationship integrity, module-level tests.

## Phase 3. Operations Management

- Objective: manage day-to-day fleet movement and operating records.
- Scope in: trips, assignments, odometer logs, fuel logs, inspections, incidents, approval workflows, notifications.
- Scope out: full maintenance planning depth.
- Acceptance criteria: end-to-end operational workflows, approval routing, reminder generation, regression tests.

## Phase 4. Maintenance and Compliance

- Objective: manage asset health and legal readiness.
- Scope in: preventive maintenance, requests, work orders, maintenance history, component lifecycle, insurance and licensing renewals, compliance dashboard.
- Scope out: advanced forecasting analytics.
- Acceptance criteria: due-date reminders, maintenance and compliance lifecycle coverage, cost traceability.

## Phase 5. Reporting, Analytics, and Dashboards

- Objective: expose actionable KPIs and exportable insights.
- Scope in: operational dashboard, cost dashboard, utilisation, maintenance, compliance, fuel analysis, incident trends, exports, advanced filtering.
- Scope out: enterprise data warehouse patterns unless volume justifies them.
- Acceptance criteria: tenant-safe dashboards, export jobs, documented KPI definitions.

## Phase 6. Hardening and Enterprise Readiness

- Objective: prepare for controlled production rollout.
- Scope in: QA pass, security review, performance review, accessibility, responsive review, API documentation, deployment docs, CI/CD definition, backup and restore strategy, observability recommendations.
- Scope out: unrelated product expansion.
- Acceptance criteria: release checklist complete, critical gaps addressed, operational runbook available.
