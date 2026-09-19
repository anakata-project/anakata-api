#!/usr/bin/env bash
# Stop preview servers and the compose stack. Volumes kept unless --wipe.
set -euo pipefail

# shellcheck source=./_lib.sh
. "$(cd "$(dirname "$0")" && pwd)/_lib.sh"

wipe=0
if [ "${1:-}" = "--wipe" ]; then
  wipe=1
  require_destructive_ok "down.sh --wipe"
fi

say "down.sh  COMPOSE_PROJECT_NAME=${COMPOSE_PROJECT_NAME}"

stop_pid() {
  local name="$1"
  local pidfile="${E2E_LOG_DIR}/${name}.pid"
  if [ ! -f "${pidfile}" ]; then
    return 0
  fi
  local pid
  pid="$(cat "${pidfile}")"
  if [ -n "${pid}" ] && kill -0 "${pid}" 2>/dev/null; then
    say "Stopping ${name} (pid ${pid})"
    kill "${pid}" 2>/dev/null || true
    local i
    for i in $(seq 1 10); do
      kill -0 "${pid}" 2>/dev/null || break
      sleep 1
    done
    if kill -0 "${pid}" 2>/dev/null; then
      kill -9 "${pid}" 2>/dev/null || true
    fi
  fi
  rm -f "${pidfile}"
}

stop_pid panel
stop_pid engine

if [ "${wipe}" -eq 1 ]; then
  say "docker compose down -v (wiping volumes for ${COMPOSE_PROJECT_NAME})"
  compose down -v
else
  say "docker compose down (volumes kept)"
  compose down
fi

say "down.sh done"
