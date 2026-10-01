#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-}"
CURL_BIN="${CURL_BIN:-curl}"

[ -n "$BASE_URL" ] || { echo "BASE_URL is required" >&2; exit 2; }
BASE_URL="${BASE_URL%/}"

check_json(){
  local path="$1" expected="$2"
  local code body
  body=$(mktemp)
  trap 'rm -f "$body"' RETURN
  code=$($CURL_BIN -sS -o "$body" -w '%{http_code}' --connect-timeout 10 --max-time 30 "$BASE_URL$path")
  if [ "$code" != "$expected" ]; then
    echo "$path returned HTTP $code; expected $expected" >&2
    cat "$body" >&2 || true
    return 1
  fi
  php -r '$d=json_decode(file_get_contents($argv[1]),true); if(!is_array($d)){fwrite(STDERR,"invalid JSON\n"); exit(1);} echo $d["status"]??"ok",PHP_EOL;' "$body"
}

echo "Checking liveness..."
check_json /health/live 200

echo "Checking readiness..."
check_json /health/ready 200

echo "Checking dashboard response..."
code=$($CURL_BIN -sS -o /dev/null -w '%{http_code}' --connect-timeout 10 --max-time 30 "$BASE_URL/")
case "$code" in
  200|302) ;;
  *) echo "/ returned HTTP $code" >&2; exit 1 ;;
esac

echo "Post-deploy smoke checks passed for $BASE_URL"
