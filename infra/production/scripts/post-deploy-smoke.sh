#!/usr/bin/env bash
set -euo pipefail

APP_BASE_URL="${APP_BASE_URL:?APP_BASE_URL must be set}"

curl -fsS "${APP_BASE_URL}/" >/dev/null
curl -fsS "${APP_BASE_URL}/up" >/dev/null
curl -fsS "${APP_BASE_URL}/readyz" >/dev/null
curl -fsS -o /dev/null -w '%{http_code}' "${APP_BASE_URL}/sanctum/csrf-cookie" | grep -q '^204$'

echo "Post-deploy smoke checks passed for ${APP_BASE_URL}"
