# Production Environment Template

Use the checked-in examples as templates only:

- Backend: `backend/.env.production.example`
- Frontend: `frontend/.env.production.example`

Do not deploy with `.env.example`, `.env.docker`, or any seeded local credentials.

## Secret Strategy

- Store production secrets in the platform secret manager or encrypted CI/CD environment secrets.
- Keep the live backend env file outside the release directory at `shared/backend/.env`.
- Never commit real values for `APP_KEY`, database credentials, Redis credentials, SMTP credentials, or object-storage credentials.
- Rotate secrets on engineer offboarding, infrastructure replacement, or suspected compromise.

## Backend Variables That Must Be Reviewed

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://fms.example.com`
- `FRONTEND_URL=https://fms.example.com`
- `CORS_ALLOWED_ORIGINS=https://fms.example.com`
- `TRUSTED_PROXIES`
- `TRUSTED_HOSTS`
- `SESSION_SECURE_COOKIE=true`
- `QUEUE_CONNECTION=redis`
- `CACHE_STORE=redis`
- `FILESYSTEM_DISK=s3`
- `LOG_STACK=stderr_json,slack`
- `SANCTUM_STATEFUL_DOMAINS=fms.example.com`

## Frontend Variables That Must Be Reviewed

- `VITE_API_BASE_URL=/api/v1`
- `VITE_CSRF_URL=/sanctum/csrf-cookie`
- `VITE_APP_VERSION=<release identifier>`
- `VITE_ENABLE_SOURCEMAPS=false` unless there is a protected error-monitoring workflow

## Production Bootstrap

Do not run `php artisan migrate --seed` in production. That seeds sample tenants, users, and fleet data.

Use this instead after migrations:

```bash
php artisan platform:bootstrap ops@example.com "Platform Administrator" --password='strong-random-password'
```

That command:

- seeds global permissions
- creates or updates the initial super admin
- avoids sample tenants and sample operational data

Create real tenants after first login.
