#!/bin/sh
set -eu

cd /app

if [ ! -d node_modules ] || [ -z "$(ls -A node_modules 2>/dev/null)" ] || { [ -f package-lock.json ] && [ ! -f node_modules/.package-lock.json ]; } || { [ -f package-lock.json ] && [ package-lock.json -nt node_modules/.package-lock.json ]; }; then
  npm install
fi

exec "$@"
