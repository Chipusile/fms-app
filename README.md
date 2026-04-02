# Fleet Management System

Enterprise-grade, multi-tenant Fleet Management System designed as a reusable SaaS-ready platform for multiple organisations.

## Solution Summary

- Frontend: Vue 3, TypeScript, Vite, Pinia, Vue Router, Tailwind CSS
- Backend: Laravel 13, PHP 8.3, Sanctum, API-first architecture
- Data: PostgreSQL primary target, Redis for queues/cache, S3-compatible object storage
- Delivery model: phased implementation with architecture, auditability, tenant isolation, and testability as first-class concerns

## Repository Structure

- `backend`: Laravel API and domain foundation
- `frontend`: Vue SPA and shared UI shell
- `docs`: architecture, ERD, module catalogue, roadmap, setup, and phase reports
- `infra/docker`: full local Docker topology for PostgreSQL, Redis, MinIO, Laravel, queue workers, scheduler, and the Vue SPA

## Key Documentation

- [Architecture](docs/architecture.md)
- [Module Catalogue](docs/modules.md)
- [ERD](docs/erd.md)
- [Roadmap](docs/roadmap.md)
- [Local Development](docs/local-development.md)
- [API Docs](docs/api/README.md)
- [Deployment Guide](docs/deployment.md)
- [Deployment Architecture](docs/deployment-architecture.md)
- [Deployment Runbook](docs/deployment-runbook.md)
- [Production Environment Template](docs/env-production-template.md)
- [Operations Runbook](docs/operations.md)
- [Backup and Restore](docs/backup-restore.md)
- [Rollback Plan](docs/rollback-plan.md)
- [Post-Deploy Checklist](docs/post-deploy-checklist.md)
- [Release Checklist](docs/release-checklist.md)
- [Production Readiness Report](docs/production-readiness-report.md)
- [Phase 0 Completion](docs/reports/phase-0-completion.md)
- [Phase 1 Progress](docs/reports/phase-1-progress.md)
- [Phase 2 Progress](docs/reports/phase-2-progress.md)
- [Phase 3 Progress](docs/reports/phase-3-progress.md)
- [Phase 3B Progress](docs/reports/phase-3b-progress.md)
- [Phase 4A Completion](docs/reports/phase-4a-progress.md)
- [Phase 4B Completion](docs/reports/phase-4b-progress.md)
- [Phase 5 Completion](docs/reports/phase-5-progress.md)
- [Phase 6 Completion](docs/reports/phase-6-progress.md)

## Current Delivery Status

- Phase 0: architecture, delivery roadmap, schema contract, local setup plan, and repository scaffold alignment
- Phase 1: core platform foundation substantially complete with permission-aware live admin pages, CRUD interactions, tenant filtering, profile management, API-backed validation flows, and frontend unit-test coverage
- Phase 2: complete with tenant-aware APIs and SPA flows for vehicle types, departments, drivers, service providers, vehicles, vehicle assignments, asset documents, and onboarding templates
- Phase 3: substantially complete across Phase 3A and 3B with trips, fuel logs, odometer workflows, inspection templates, inspections, incidents, approval queue handling, and in-app notifications delivered; external reminder channels and recurring jobs remain deferred
- Phase 4: complete across Phase 4A and 4B with maintenance schedules, maintenance requests, work orders, component lifecycle tracking, reminder dispatch automation, compliance register workflows, and SPA pages for maintenance/compliance management
- Phase 5: complete with analytics services, dashboard KPIs and charts, report-center datasets, advanced reporting filters, CSV export tracking, and recent export visibility in the SPA
- Phase 6: complete with security headers, endpoint throttling, permission hardening tests, OpenAPI baseline docs, deployment and operations runbooks, CI workflow refinement, accessibility shell improvements, and analytics bundle hardening
- Production hardening: complete for repository handoff with runtime hardening, release automation artifacts, production env templates, readiness checks, bootstrap command, backup/rollback scripts, and deployment runbooks; a staging dry run remains the final external gate before live rollout

## Quick Start

1. Start the full local stack in Docker:

```bash
docker compose -f infra/docker/compose.yml up -d --build
```

If your machine already has conflicting ports in use, override the host ports when starting the stack:

```bash
FMS_POSTGRES_PORT=5433 FMS_REDIS_PORT=6380 FMS_BACKEND_PORT=8001 FMS_FRONTEND_PORT=5175 docker compose -f infra/docker/compose.yml up -d --build
```

2. Run database setup inside the backend container:

```bash
docker compose -f infra/docker/compose.yml exec backend php artisan migrate --seed
```

3. Open the application:

- frontend SPA: `http://localhost:5174`
- backend health: `http://localhost:8000/up`
- MinIO console: `http://localhost:9001`

4. Useful container commands:

```bash
docker compose -f infra/docker/compose.yml logs -f backend frontend queue scheduler
docker compose -f infra/docker/compose.yml exec backend php artisan test
docker compose -f infra/docker/compose.yml exec frontend npm run test:unit:run
```

## Notes

- Docker-first local development is now the recommended path because it removes host PHP extension and PostgreSQL wiring drift.
- The Docker stack mounts [backend/.env.docker](/Users/cjsikasote/fms-app/backend/.env.docker) into the Laravel containers, so host-only values in [backend/.env](/Users/cjsikasote/fms-app/backend/.env) do not leak into container runtime.
- In Docker local mode, the SPA uses the Vite dev proxy for `/api` and `/sanctum`, so local auth stays same-origin at `http://localhost:5174`.
- Do not run host `php artisan serve`, `queue:work`, `schedule:work`, or `npm run dev` at the same time as the Docker stack on the same ports.
- The backend can still be validated on the host with `php artisan test`.
- The frontend unit harness can still be validated on the host with `npm run test:unit:run`.
- Containerized backend tests now use [backend/.env.testing](/Users/cjsikasote/fms-app/backend/.env.testing) so they do not mutate the local Docker PostgreSQL data used by the running app.
- The Vitest harness is configured for non-parallel worker execution to avoid worker startup and teardown instability in local and CI runs.
- The frontend build has been validated locally under Node 22 via `nvm`.
- PostgreSQL is the intended primary runtime database even though SQLite remains usable for fast local checks.
- The current verification baseline is `php artisan test` with 36 passing tests, `npm run type-check`, `npm run test:unit:run` with 13 passing tests, and `npm run build` under Node 22.
- The analytics stack is lazy-loaded and split into dedicated async chunks so the dashboard no longer leaves an oversized bundle warning in the production build.
