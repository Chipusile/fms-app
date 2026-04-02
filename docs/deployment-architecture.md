# Deployment Architecture

## Recommendation

Use a VM-based deployment on Ubuntu LTS with:

- Nginx serving the Vue SPA from `frontend/dist`
- Nginx routing `/api`, `/sanctum`, `/up`, and `/readyz` to Laravel
- PHP-FPM 8.3 for the Laravel runtime
- PostgreSQL as the system of record
- Redis for cache, sessions, queues, and scheduler overlap locks
- Object storage for documents and report exports
- Supervisor or systemd for queue workers
- Cron for `php artisan schedule:run`

This is the strongest option for the current codebase because it keeps the runtime simple, aligns with Laravel operational conventions, and reduces day-two operational overhead for a small delivery team.

## Why VM-Based Is Recommended

- The application already assumes standard Laravel and SPA deployment patterns.
- Stateful SPA auth is simplest when Nginx serves the frontend and proxies the backend on the same origin.
- PHP-FPM plus Nginx is operationally simpler than managing production containers on a single VM.
- Queue, scheduler, storage, and release rollback are easier to reason about with a release-directory layout.
- The current repository already has local Docker for development; production does not need to inherit that complexity.

## Docker-Based Production Option

Docker-based production is viable if the team already operates container infrastructure well.

Benefits:

- immutable images
- stronger parity between environments
- easier horizontal scaling if the platform is already container-native

Trade-offs:

- more moving parts for PHP-FPM, Nginx, scheduler, queue worker, health probes, and secrets delivery
- more operational overhead for persistent storage, logs, and release rollback
- more complexity than the current team needs for a single VM or a small VM fleet

## Recommended Topology

- `fms.example.com`
  - serves the SPA from `frontend/dist`
  - proxies `/api/*`, `/sanctum/*`, `/up`, `/readyz` to Laravel
- PostgreSQL on the same VM only for small deployments, otherwise managed or separate-host
- Redis on the same VM only for small deployments, otherwise managed or separate-host
- S3-compatible object storage for documents and report exports

## Release Layout

- `/var/www/fms-app/current`
- `/var/www/fms-app/releases/<release-id>`
- `/var/www/fms-app/shared/backend/.env`
- `/var/www/fms-app/shared/backend/storage`
- `/var/www/fms-app/shared/backend/bootstrap/cache`

The release symlink model is what enables fast rollback without restoring code by hand.

## Runtime Separation

- `local`: Docker services plus host tooling
- `staging`: production-like VM or isolated environment with masked integrations
- `production`: locked-down VM or VM set with managed backups, alerting, and strict secret management

## Key Infrastructure Files

- Nginx example: `infra/production/nginx/fms-app.conf`
- Supervisor worker example: `infra/production/supervisor/fms-queue.conf`
- Systemd worker example: `infra/production/systemd/fms-queue.service`
- Scheduler cron example: `infra/production/cron/fms-scheduler`
- Deploy scripts: `infra/production/scripts/`
