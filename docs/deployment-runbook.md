# Deployment Runbook

## Server Prerequisites

- Ubuntu 24.04 LTS or current Ubuntu LTS
- Nginx
- PHP 8.3 with `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo_pgsql`, `redis`, `tokenizer`, `xml`
- Composer 2
- Node 22.x for build jobs if building on host
- PostgreSQL client tools
- Redis
- Supervisor or systemd
- TLS certificate management via Let’s Encrypt or platform equivalent

## Directory Layout

```text
/var/www/fms-app/
  current -> /var/www/fms-app/releases/<release-id>
  releases/
  shared/
    backend/.env
    backend/storage/
    backend/bootstrap/cache/
```

## Initial Server Setup

1. Create the deployment directories.
2. Copy `backend/.env.production.example` to `/var/www/fms-app/shared/backend/.env` and replace placeholders with real values.
3. Create the PostgreSQL database and least-privilege application user.
4. Create Redis credentials or ACLs if used.
5. Create the S3 bucket or compatible object-storage bucket for documents and exports.
6. Install the Nginx config from `infra/production/nginx/fms-app.conf`.
7. Install the queue worker config from either:
   - `infra/production/supervisor/fms-queue.conf`, or
   - `infra/production/systemd/fms-queue.service`
8. Install the cron entry from `infra/production/cron/fms-scheduler`.

## Database Creation Example

```sql
CREATE ROLE fms_app LOGIN PASSWORD 'replace-this';
CREATE DATABASE fms OWNER fms_app;
GRANT CONNECT ON DATABASE fms TO fms_app;
```

Apply stricter grants according to your PostgreSQL policy.

## Manual Deployment Steps

1. Back up the database.
2. Copy the release archive to the server.
3. Run the deploy script:

```bash
APP_ROOT=/var/www/fms-app \
RELEASE_ARCHIVE=/tmp/fms-release-<sha>.tgz \
RELEASE_ID=<sha> \
RUN_MIGRATIONS=true \
bash infra/production/scripts/deploy-release.sh
```

4. Verify queue workers restarted cleanly.
5. Run the smoke checks.

## GitHub Actions Deployment

Use `.github/workflows/deploy.yml`.

Required repository or environment secrets:

- `DEPLOY_SSH_HOST`
- `DEPLOY_SSH_PORT`
- `DEPLOY_SSH_USER`
- `DEPLOY_SSH_KEY`
- `DEPLOY_PATH`
- `APP_BASE_URL`

Use GitHub Environments so `production` requires manual approval before the deploy job starts.

## Nginx Notes

- Serve `frontend/dist` as the public root.
- Proxy Laravel through PHP-FPM for `/api`, `/sanctum`, `/up`, and `/readyz`.
- Keep TLS termination at Nginx or the load balancer.
- Add SPA fallback with `try_files $uri $uri/ /index.html`.

## PHP-FPM Notes

- Run PHP-FPM as a non-root user such as `www-data`.
- Ensure `storage` and `bootstrap/cache` are writable by the PHP-FPM user.
- Restart PHP-FPM only when PHP configuration changes; normal application deploys should not require it.

## Queue Workers

Recommended command:

```bash
php artisan queue:work redis --sleep=3 --tries=3 --timeout=120 --max-time=3600
```

Restart workers on every deploy with:

```bash
php artisan queue:restart
```

## Scheduler

Install:

```cron
* * * * * www-data cd /var/www/fms-app/current/backend && /usr/bin/php artisan schedule:run >> /var/log/fms/scheduler.log 2>&1
```

## SSL

- Redirect HTTP to HTTPS.
- Enable HSTS only after HTTPS is stable.
- Ensure `SESSION_SECURE_COOKIE=true`.
- Confirm Laravel receives the forwarded HTTPS headers from the trusted proxy.

## Post-Deploy Smoke Checks

Run:

```bash
APP_BASE_URL=https://fms.example.com bash infra/production/scripts/post-deploy-smoke.sh
```

Then manually verify login, dashboards, report exports, file uploads, and audit logging.

## Rollback

Code rollback only:

```bash
APP_ROOT=/var/www/fms-app bash infra/production/scripts/rollback-release.sh
```

This does not reverse destructive database migrations. Take a backup before every migration-bearing release.
