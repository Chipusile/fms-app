# Post-Deploy Checklist

## Application Health

- homepage loads over HTTPS
- `/up` returns healthy
- `/readyz` returns healthy
- no stack traces or debug pages are visible

## Authentication and Access

- login works for a tenant admin
- login works for the platform super admin
- role-based navigation changes correctly between admin and viewer roles
- unauthorized actions return 403 and do not render privileged data

## API and Data

- API requests return 2xx for normal dashboard and list pages
- database connectivity is stable
- tenant-scoped data remains isolated
- audit logs record create and update actions

## Background Work

- queue workers are running
- no failed jobs spike after deploy
- scheduler runs without lock contention errors
- report export jobs complete and download correctly

## Storage and Notifications

- document upload works
- document download works
- object storage writes succeed
- in-app notifications can be retrieved and acknowledged

## Reporting

- dashboard cards and charts load
- report center loads support data
- CSV export can be generated and downloaded

## Infrastructure

- Nginx config is active and serving the SPA fallback correctly
- SSL certificate is valid and not near expiry
- logs are writing in the expected location or stream
- queue and scheduler logs are visible to operations
