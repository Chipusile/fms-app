# Local Development Setup

**Version:** 1.0  
**Last Updated:** 2026-03-30

## Phase Objective

Provide a reproducible local development workflow with a Docker-first path that eliminates host-specific PHP, PostgreSQL, Redis, and Sanctum configuration drift.

## Scope In

- full Docker topology for PostgreSQL, Redis, MinIO, backend, queue, scheduler, and frontend
- secondary host-based development commands for targeted debugging
- environment variable expectations
- verification steps

## Scope Out

- production deployment manifests
- autoscaling and managed cloud service specifics

## Assumptions

- Docker Desktop or compatible Docker Engine is available
- `backend/.env.example` exists in the repository and can seed `backend/.env` automatically when containers start
- PHP and Node are optional on the host unless the developer chooses the secondary host-mode workflow

## Proposed Implementation Approach

- run the entire local application stack through Docker Compose by default
- keep the backend and frontend source bind-mounted so code changes still reflect immediately
- keep host-mode available as a fallback, not the primary path

## Folder Structure Touched

- `infra/docker/compose.yml`
- `backend/Dockerfile`
- `backend/docker/entrypoint.sh`
- `backend/.env.docker`
- `frontend/Dockerfile`
- `frontend/docker/entrypoint.sh`
- `backend/.env.example`
- `backend/.env.testing`
- `backend/config/cors.php`
- `frontend/vite.config.ts`

## Commands To Run

### Recommended: full Docker stack

```bash
docker compose -f infra/docker/compose.yml up -d --build
```

If host ports are already in use, remap them when starting the stack:

```bash
FMS_POSTGRES_PORT=5433 FMS_REDIS_PORT=6380 FMS_BACKEND_PORT=8001 FMS_FRONTEND_PORT=5175 docker compose -f infra/docker/compose.yml up -d --build
```

Initialize the database after the containers are up:

```bash
docker compose -f infra/docker/compose.yml exec backend php artisan migrate --seed
```

The Dockerized Laravel services use `backend/.env.docker` automatically. Keep `backend/.env` for host-mode only.
Containerized test runs use `backend/.env.testing` so test execution stays isolated from the local PostgreSQL runtime data.

Useful runtime commands:

```bash
docker compose -f infra/docker/compose.yml logs -f backend frontend queue scheduler
docker compose -f infra/docker/compose.yml exec backend php artisan test
docker compose -f infra/docker/compose.yml exec frontend npm run test:unit:run
```

Stop the stack:

```bash
docker compose -f infra/docker/compose.yml down
```

### Secondary: host-mode fallback

Run only the infrastructure in Docker:

```bash
docker compose -f infra/docker/compose.yml up -d postgres redis minio minio-init
```

Then run Laravel and Vite on the host with `backend/.env` pointing to the mapped host ports.

## Verification

- frontend SPA: `http://localhost:5174`
- backend health: `http://localhost:8000/up`
- local SPA auth/API traffic runs through the Vite dev proxy at `http://localhost:5174`
- frontend unit tests in container: `docker compose -f infra/docker/compose.yml exec frontend npm run test:unit:run`
- backend tests in container: `docker compose -f infra/docker/compose.yml exec backend php artisan test`
- MinIO console: `http://localhost:9001`
- PostgreSQL default host mapping: `127.0.0.1:5432`
- Redis default host mapping: `127.0.0.1:6379`
- queue worker and scheduler run as dedicated containers and should stay healthy during local testing

## Risks

- the first Docker build installs PHP and npm dependencies and will be slower than subsequent starts
- if the backend container is recreated after major dependency changes, `composer install` may run again inside the container
- host-mode remains available, but it is easier to misconfigure than the Docker-first path
