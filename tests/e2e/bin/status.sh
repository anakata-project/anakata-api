#!/usr/bin/env bash
# One line per service. Exit 0 only if everything is up.
set -euo pipefail

# shellcheck source=./_lib.sh
. "$(cd "$(dirname "$0")" && pwd)/_lib.sh"

ok=0
fail=0

line() {
  local name="$1"
  local state="$2"
  local extra="${3:-}"
  if [ "${state}" = "up" ]; then
    printf '  %-10s up    %s\n' "${name}" "${extra}"
    ok=$((ok + 1))
  else
    printf '  %-10s DOWN  %s\n' "${name}" "${extra}"
    fail=$((fail + 1))
  fi
}

probe_http() {
  local url="$1"
  local code
  code="$(curl -sS -o /tmp/e2e-status-body -w '%{http_code}' --connect-timeout 2 "${url}" 2>/dev/null || true)"
  if [ -z "${code}" ]; then
    echo 000
  else
    echo "${code}"
  fi
}

echo "e2e status  project=${COMPOSE_PROJECT_NAME}"

health_code="$(probe_http http://localhost:8000/api/health)"
if [ "${health_code}" = "200" ] && [ -f /tmp/e2e-status-body ]; then
  line api up "$(tr -d '\n' </tmp/e2e-status-body)"
else
  line api down "HTTP ${health_code}"
fi

if in_app "php artisan horizon:status" >/dev/null 2>&1; then
  line horizon up
else
  line horizon down
fi

panel_code="$(probe_http http://localhost:3001/login)"
if [ "${panel_code}" = "200" ]; then
  line panel up "http://localhost:3001/login"
else
  line panel down "HTTP ${panel_code}"
fi

engine_code="$(probe_http http://localhost:3000/)"
if [ "${engine_code}" = "200" ]; then
  line engine up "http://localhost:3000/"
else
  line engine down "HTTP ${engine_code}"
fi

mail_code="$(probe_http http://localhost:8025/livez)"
if [ "${mail_code}" = "200" ]; then
  line mailpit up "http://localhost:8025"
else
  line mailpit down "HTTP ${mail_code}"
fi

if compose ps --format '{{.Name}} {{.Status}}' 2>/dev/null | grep -q .; then
  echo "  containers:"
  compose ps --format '    {{.Name}}  {{.Status}}'
else
  line compose down "no containers"
fi

if [ "${fail}" -eq 0 ]; then
  echo "ALL UP"
  exit 0
fi

echo "NOT ALL UP (${fail} down)"
exit 1
