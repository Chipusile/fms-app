#!/usr/bin/env bash
set -euo pipefail

BACKUP_FILE="${BACKUP_FILE:?BACKUP_FILE must be set}"

if [[ ! -f "${BACKUP_FILE}" ]]; then
  echo "Backup file not found: ${BACKUP_FILE}" >&2
  exit 1
fi

pg_restore \
  --clean \
  --if-exists \
  --no-owner \
  --dbname="${PGDATABASE:?PGDATABASE must be set}" \
  "${BACKUP_FILE}"

echo "Restore completed from ${BACKUP_FILE}"
