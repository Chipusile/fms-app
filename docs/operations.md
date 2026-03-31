# Operations Runbook

## Backup Strategy

Database:

- Nightly full PostgreSQL backups
- Point-in-time recovery enabled where the platform supports WAL archiving
- Minimum 30-day retention for production
- Backup verification by scheduled restore drills in a non-production environment

Object Storage:

- Versioning enabled for the document and export buckets
- Daily replication or backup to a second region or account where required
- Lifecycle rules to expire obsolete temporary report exports

Redis:

- Treat Redis as rebuildable operational state
- Do not rely on Redis as the sole source of truth for business records

## Restore Strategy

1. Identify the desired recovery timestamp and impacted tenant scope.
2. Restore the PostgreSQL backup into an isolated recovery environment first.
3. Validate tenant data integrity before any production cutover.
4. Restore object-storage artifacts if documents or report exports are affected.
5. Repoint the application only after smoke verification passes.

## Observability Baseline

Logs:

- structured JSON application logs in production
- request correlation IDs across API, queue jobs, and export generation
- explicit logging for authorization failures, export failures, and reminder dispatch failures

Metrics:

- API latency and error rate
- queue depth and job failure count
- report export throughput and duration
- document storage failures
- login throttling and authentication failure count

Alerts:

- sustained 5xx error rate
- queue retry storm
- database connection saturation
- export job failure spike
- missing scheduler heartbeat

## Performance Watchlist

- request-time analytics aggregation for very large tenants
- dashboard chart bundle size and route load time
- list endpoints with expensive relational filters
- bulk document downloads or repeated CSV generation

## Security Watchlist

- repeated failed login attempts
- cross-tenant query leakage
- oversized file uploads or unsupported MIME types
- export abuse by privileged users
- stale super-admin accounts

## Manual Recovery Checks

- confirm the affected tenant can still log in
- verify dashboard data loads for a safe read-only user
- verify report exports can be created and downloaded
- verify queue workers resume processing after restart

## Ownership Model

- product delivery owns feature correctness and release coordination
- platform/devops owns runtime health, backups, restore drills, and secret rotation
- QA owns regression evidence and sign-off records
- security owns periodic access review and release gate review
