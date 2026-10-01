#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${APP_ROOT:-$PWD}"
PUBLIC_ROOT="${PUBLIC_ROOT:-$(dirname "$APP_ROOT")/public_html}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
BRANCH="${BRANCH:-main}"
INSTALL_CRON="${INSTALL_CRON:-0}"
CRON_MINUTE="${CRON_MINUTE:-*}"

say(){ printf '\n==> %s\n' "$*"; }
fail(){ printf '\nERROR: %s\n' "$*" >&2; exit 1; }

[ -d "$APP_ROOT/.git" ] || fail "APP_ROOT is not a git checkout: $APP_ROOT"
[ -f "$APP_ROOT/artisan" ] || fail "artisan not found in $APP_ROOT"
[ -f "$APP_ROOT/.env" ] || fail ".env is missing in $APP_ROOT"
mkdir -p "$PUBLIC_ROOT" "$APP_ROOT/storage/framework/cache" "$APP_ROOT/storage/framework/sessions" "$APP_ROOT/storage/framework/views" "$APP_ROOT/storage/logs" "$APP_ROOT/bootstrap/cache"
chmod -R ug+rwX "$APP_ROOT/storage" "$APP_ROOT/bootstrap/cache" || true

cd "$APP_ROOT"

say "Checking required production configuration"
APP_KEY_VALUE=$(grep -E '^APP_KEY=' .env | head -n1 | cut -d= -f2- || true)
APP_URL_VALUE=$(grep -E '^APP_URL=' .env | head -n1 | cut -d= -f2- || true)
DB_CONNECTION_VALUE=$(grep -E '^DB_CONNECTION=' .env | head -n1 | cut -d= -f2- || true)
QUEUE_CONNECTION_VALUE=$(grep -E '^QUEUE_CONNECTION=' .env | head -n1 | cut -d= -f2- || true)
[ -n "$APP_KEY_VALUE" ] || fail "APP_KEY is empty"
[[ "$APP_URL_VALUE" == https://* ]] || fail "APP_URL must use https://"
[ "$DB_CONNECTION_VALUE" = "mysql" ] || fail "DB_CONNECTION must be mysql on this deployment"
[ "$QUEUE_CONNECTION_VALUE" = "database" ] || fail "QUEUE_CONNECTION must be database"

say "Updating application code from origin/$BRANCH"
git fetch --prune origin "$BRANCH"
git checkout "$BRANCH"
git reset --hard "origin/$BRANCH"

say "Installing production dependencies"
"$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader --no-interaction

say "Preparing Laravel runtime"
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

say "Publishing Laravel public directory to $PUBLIC_ROOT"
if command -v rsync >/dev/null 2>&1; then
  rsync -a --delete --exclude='.well-known/' "$APP_ROOT/public/" "$PUBLIC_ROOT/"
else
  find "$PUBLIC_ROOT" -mindepth 1 -maxdepth 1 ! -name '.well-known' -exec rm -rf {} +
  cp -a "$APP_ROOT/public/." "$PUBLIC_ROOT/"
fi

INDEX="$PUBLIC_ROOT/index.php"
[ -f "$INDEX" ] || fail "Published index.php is missing"
python3 - "$INDEX" <<'PY'
from pathlib import Path
import sys
p=Path(sys.argv[1])
s=p.read_text()
s=s.replace("__DIR__.'/../vendor/autoload.php'", "__DIR__.'/../app/vendor/autoload.php'")
s=s.replace("__DIR__.'/../bootstrap/app.php'", "__DIR__.'/../app/bootstrap/app.php'")
p.write_text(s)
PY

say "Running production readiness checks"
"$PHP_BIN" artisan migrate:status >/dev/null
"$PHP_BIN" artisan app:health-check

if [ "$INSTALL_CRON" = "1" ]; then
  command -v crontab >/dev/null 2>&1 || fail "crontab command is unavailable"
  CRON_LINE="$CRON_MINUTE * * * * cd $APP_ROOT && $PHP_BIN artisan queue:work --stop-when-empty --tries=3 --timeout=180 >> $APP_ROOT/storage/logs/queue-cron.log 2>&1"
  (crontab -l 2>/dev/null | grep -Fv "artisan queue:work --stop-when-empty"; echo "$CRON_LINE") | crontab -
  say "Queue cron installed"
else
  say "Queue cron not changed (set INSTALL_CRON=1 to install it)"
fi

say "Deployment completed"
printf 'Public root: %s\n' "$PUBLIC_ROOT"
printf 'Readiness URL: %s/health/ready\n' "${APP_URL_VALUE%/}"
