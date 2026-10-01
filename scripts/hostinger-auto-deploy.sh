#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${APP_ROOT:-$PWD}"
BRANCH="${BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"
LOG_FILE="${LOG_FILE:-$APP_ROOT/storage/logs/auto-deploy.log}"
STAMP_DIR="${STAMP_DIR:-$APP_ROOT/storage/app/deploy}"
STAMP_FILE="$STAMP_DIR/$BRANCH.sha"
LOCK_FILE="${LOCK_FILE:-$APP_ROOT/storage/app/deploy.lock}"

mkdir -p "$(dirname "$LOG_FILE")" "$STAMP_DIR" "$(dirname "$LOCK_FILE")"

exec >>"$LOG_FILE" 2>&1
printf '\n[%s] auto-deploy check\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"

cd "$APP_ROOT"
[ -d .git ] || { echo "git checkout missing"; exit 1; }
[ -f scripts/deploy-hostinger.sh ] || { echo "deploy script missing"; exit 1; }

run_deploy(){
  git fetch --quiet origin "$BRANCH"
  remote_sha=$(git rev-parse "origin/$BRANCH")
  deployed_sha=$(cat "$STAMP_FILE" 2>/dev/null || true)

  if [ "$remote_sha" = "$deployed_sha" ]; then
    echo "already deployed: $remote_sha"
    exit 0
  fi

  echo "deploying $remote_sha"
  APP_ROOT="$APP_ROOT" BRANCH="$BRANCH" INSTALL_CRON=0 bash scripts/deploy-hostinger.sh
  printf '%s\n' "$remote_sha" > "$STAMP_FILE"
  echo "deployment recorded: $remote_sha"
}

if command -v flock >/dev/null 2>&1; then
  exec 9>"$LOCK_FILE"
  if ! flock -n 9; then
    echo "another deployment is running; skipping"
    exit 0
  fi
  run_deploy
else
  LOCK_DIR="${LOCK_FILE}.d"
  if ! mkdir "$LOCK_DIR" 2>/dev/null; then
    echo "another deployment is running; skipping"
    exit 0
  fi
  trap 'rmdir "$LOCK_DIR" 2>/dev/null || true' EXIT
  run_deploy
fi
