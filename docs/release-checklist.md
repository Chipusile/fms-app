# Production Release Checklist

## Code and Quality

- all Phase 0-5 acceptance criteria remain satisfied
- `php artisan test` passes
- `npm run type-check` passes
- `npm run test:unit:run` passes
- `npm run build` passes on the supported Node runtime

## Security and Permissions

- login throttle is verified
- report export permissions are verified
- tenant isolation tests are green
- security headers are present on API responses
- super-admin access list is reviewed

## Data and Runtime

- database backup is current and restorable
- object storage bucket exists and is writable
- queue workers are healthy
- scheduler is active
- environment secrets are present and rotated as required

## Deployment

- migration plan is reviewed
- rollback path is identified
- staging smoke test passed on the release candidate
- release notes are prepared
- monitoring dashboards are open during the change window

## Post-Release

- health endpoint responds normally
- login, dashboard, and report center smoke checks pass
- no abnormal 4xx/5xx spike appears after release
- queue backlog remains within expected bounds
- incident channel is updated with release status
