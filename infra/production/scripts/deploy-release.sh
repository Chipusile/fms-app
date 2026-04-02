#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${APP_ROOT:?APP_ROOT must be set}"
RELEASE_ARCHIVE="${RELEASE_ARCHIVE:?RELEASE_ARCHIVE must be set}"
RELEASE_ID="${RELEASE_ID:-$(date +%Y%m%d%H%M%S)}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-true}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

RELEASES_DIR="${APP_ROOT}/releases"
SHARED_DIR="${APP_ROOT}/shared"
CURRENT_DIR="${APP_ROOT}/current"
RELEASE_DIR="${RELEASES_DIR}/${RELEASE_ID}"

mkdir -p "${RELEASES_DIR}" "${SHARED_DIR}/backend"
mkdir -p "${SHARED_DIR}/backend/storage/app" "${SHARED_DIR}/backend/storage/framework/cache" "${SHARED_DIR}/backend/storage/framework/sessions" "${SHARED_DIR}/backend/storage/framework/views" "${SHARED_DIR}/backend/storage/logs"
mkdir -p "${SHARED_DIR}/backend/bootstrap/cache"

if [[ ! -f "${SHARED_DIR}/backend/.env" ]]; then
  echo "Missing shared backend env file at ${SHARED_DIR}/backend/.env" >&2
  exit 1
fi

rm -rf "${RELEASE_DIR}"
mkdir -p "${RELEASE_DIR}"
tar -xzf "${RELEASE_ARCHIVE}" -C "${RELEASE_DIR}"

ln -sfn "${SHARED_DIR}/backend/.env" "${RELEASE_DIR}/backend/.env"
rm -rf "${RELEASE_DIR}/backend/storage"
ln -sfn "${SHARED_DIR}/backend/storage" "${RELEASE_DIR}/backend/storage"
mkdir -p "${RELEASE_DIR}/backend/bootstrap"
rm -rf "${RELEASE_DIR}/backend/bootstrap/cache"
ln -sfn "${SHARED_DIR}/backend/bootstrap/cache" "${RELEASE_DIR}/backend/bootstrap/cache"

pushd "${RELEASE_DIR}/backend" >/dev/null
"${COMPOSER_BIN}" install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

if [[ "${RUN_MIGRATIONS}" == "true" ]]; then
  "${PHP_BIN}" artisan migrate --force
fi

"${PHP_BIN}" artisan optimize
popd >/dev/null

ln -sfn "${RELEASE_DIR}" "${CURRENT_DIR}"

pushd "${CURRENT_DIR}/backend" >/dev/null
"${PHP_BIN}" artisan queue:restart || true
"${PHP_BIN}" artisan up || true
popd >/dev/null

echo "Deployment completed for release ${RELEASE_ID}"
