# Backup and Restore

## Backup Policy

Database:

- nightly full PostgreSQL backup
- pre-deploy backup before any release that may run migrations
- minimum 30-day retention
- quarterly restore drill

Object storage:

- enable bucket versioning
- replicate or back up to a second location
- expire temporary report exports by lifecycle policy if business retention allows

Redis:

- treat as rebuildable runtime state
- do not use Redis snapshots as the primary business-data recovery plan

## Backup Commands

Use the checked-in script:

```bash
PGDATABASE=fms \
PGUSER=fms_app \
PGPASSWORD='<secret>' \
BACKUP_DIR=/var/backups/fms \
bash infra/production/scripts/backup-postgres.sh
```

## Restore Rules

- restore into an isolated environment first
- validate tenant isolation and record counts before touching production
- restore object-storage artifacts when documents or report exports are impacted
- only cut production over after smoke checks pass

## Restore Command

```bash
PGDATABASE=fms_restore \
PGUSER=fms_app \
PGPASSWORD='<secret>' \
BACKUP_FILE=/var/backups/fms/fms-<timestamp>.dump \
bash infra/production/scripts/restore-postgres.sh
```

## Backup Verification

- verify the `.sha256` checksum file against the backup artifact
- run a restore drill on a schedule, not only during incidents
- confirm login, dashboard, report exports, and document downloads against the restored dataset
