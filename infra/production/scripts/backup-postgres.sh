#!/usr/bin/env bash
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/backups/fms}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"
TIMESTAMP="$(date +%Y%m%d%H%M%S)"
OUTPUT_FILE="${BACKUP_DIR}/fms-${TIMESTAMP}.dump"

mkdir -p "${BACKUP_DIR}"

pg_dump \
  --format=custom \
  --no-owner \
  --file="${OUTPUT_FILE}" \
  "${PGDATABASE:?PGDATABASE must be set}"

sha256sum "${OUTPUT_FILE}" > "${OUTPUT_FILE}.sha256"

find "${BACKUP_DIR}" -type f -name 'fms-*.dump' -mtime +"${RETENTION_DAYS}" -delete
find "${BACKUP_DIR}" -type f -name 'fms-*.sha256' -mtime +"${RETENTION_DAYS}" -delete

echo "Backup created at ${OUTPUT_FILE}"
