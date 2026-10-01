#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${APP_ROOT:-$PWD}"
PHP_BIN="${PHP_BIN:-php}"

cd "$APP_ROOT"

printf 'Laravel: '
"$PHP_BIN" artisan --version
printf '\nMigration status:\n'
"$PHP_BIN" artisan migrate:status
printf '\nApplication readiness:\n'
"$PHP_BIN" artisan app:health-check
printf '\nPublic readiness endpoint:\n'
APP_URL_VALUE=$(grep -E '^APP_URL=' .env | head -n1 | cut -d= -f2- || true)
if [ -n "$APP_URL_VALUE" ] && command -v curl >/dev/null 2>&1; then
  curl --fail --silent --show-error --max-time 15 "${APP_URL_VALUE%/}/health/ready"
  printf '\n'
else
  printf 'Skipped: APP_URL or curl unavailable\n'
fi
