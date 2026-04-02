#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${APP_ROOT:?APP_ROOT must be set}"
TARGET_RELEASE="${TARGET_RELEASE:-}"
PHP_BIN="${PHP_BIN:-php}"

CURRENT_DIR="${APP_ROOT}/current"
RELEASES_DIR="${APP_ROOT}/releases"

if [[ -z "${TARGET_RELEASE}" ]]; then
  mapfile -t releases < <(find "${RELEASES_DIR}" -mindepth 1 -maxdepth 1 -type d | sort)
  if (( ${#releases[@]} < 2 )); then
    echo "No previous release found to roll back to." >&2
    exit 1
  fi
  TARGET_RELEASE="$(basename "${releases[-2]}")"
fi

TARGET_DIR="${RELEASES_DIR}/${TARGET_RELEASE}"

if [[ ! -d "${TARGET_DIR}" ]]; then
  echo "Target release does not exist: ${TARGET_DIR}" >&2
  exit 1
fi

ln -sfn "${TARGET_DIR}" "${CURRENT_DIR}"

pushd "${CURRENT_DIR}/backend" >/dev/null
"${PHP_BIN}" artisan optimize
"${PHP_BIN}" artisan queue:restart || true
"${PHP_BIN}" artisan up || true
popd >/dev/null

echo "Rolled back to release ${TARGET_RELEASE}"
