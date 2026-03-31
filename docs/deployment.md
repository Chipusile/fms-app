# Deployment Guide

## Objective

Provide an environment-agnostic deployment blueprint for the Fleet Management System across staging and production tenants.

## Target Runtime Topology

- Laravel API running behind Nginx or an equivalent reverse proxy
- Vue SPA served as static assets through CDN, object storage, or the same reverse proxy
- PostgreSQL as the primary relational database
- Redis for cache, queue, and rate-limiter state
- S3-compatible object storage for documents and report exports
- Managed TLS termination at the edge or load balancer

## Environment Layers

- `local`: Docker-backed dependencies with host-run API and SPA
- `staging`: production-like infrastructure with masked integrations and seeded smoke tenants
- `production`: isolated managed services, backup automation, alerting, and restricted admin access

## Required Environment Variables

Backend:

- `APP_ENV`
- `APP_KEY`
- `APP_URL`
- `SESSION_DOMAIN`
- `SANCTUM_STATEFUL_DOMAINS`
- `DB_*`
- `REDIS_*`
- `QUEUE_CONNECTION`
- `FILESYSTEM_DISK`
- `AWS_*` or equivalent object-storage credentials
- `LOG_CHANNEL`

Frontend:

- `VITE_API_BASE_URL`

## Deployment Sequence

1. Build backend dependencies and frontend production assets.
2. Run database migrations before switching traffic.
3. Warm configuration, routes, and events caches on the API host.
4. Ensure queue workers are online before enabling user traffic.
5. Verify storage bucket access and export/document write permissions.
6. Run smoke checks against `/up`, login, dashboard, and a read-only report endpoint.
7. Switch traffic and monitor error rate, queue depth, and latency.

## Backend Release Commands

```bash
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan queue:restart
```

## Frontend Release Commands

```bash
cd frontend
npm ci
npm run build
```

## Queue and Scheduler

- Run a persistent queue worker with automatic restart on deployment.
- Run `php artisan schedule:run` every minute through cron or a platform scheduler.
- Ensure the Phase 4 reminder jobs and Phase 5 export jobs share Redis visibility and monitoring.

## Security Controls

- Terminate all production traffic over HTTPS.
- Keep session cookies secure, same-site aware, and environment-specific.
- Restrict super-admin access through SSO or IP/VPN controls where available.
- Rotate database and object-storage credentials through the platform secret manager.
- Keep database and object-storage resources on private networks where possible.

## Rollback Strategy

- If only the frontend regresses, redeploy the prior static bundle.
- If a backend release regresses without destructive migrations, redeploy the prior artifact and clear caches.
- If a schema change regresses, restore from backup or run a tested backward migration only if explicitly supported.
- Never rely on ad hoc manual data edits as a rollback substitute.

## Post-Deployment Verification

- `/up` returns healthy
- login succeeds for a tenant admin account
- dashboard KPIs load
- report center loads exports and datasets
- document upload/download works
- queue backlog drains normally

## Deferred Infrastructure Work

- infrastructure-as-code templates
- blue/green or canary rollout automation
- managed WAF rules and SSO federation specifics
