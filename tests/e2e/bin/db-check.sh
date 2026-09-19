#!/usr/bin/env bash
# Read-only artisan tinker expression. Prints JSON.
set -euo pipefail

# shellcheck source=./_lib.sh
. "$(cd "$(dirname "$0")" && pwd)/_lib.sh"

expr="${1:-}"
if [ -z "${expr}" ]; then
  die "usage: db-check.sh '<php expression>'"
fi

lower="$(printf '%s' "${expr}" | tr '[:upper:]' '[:lower:]')"
if printf '%s' "${lower}" | grep -Eq '(^|[^a-z_])(save|update|delete|create|insert|truncate)([^a-z_]|$)'; then
  die "refusing write expression (contains save/update/delete/create/insert/truncate)"
fi
if printf '%s' "${expr}" | grep -Fq 'DB::statement'; then
  die "refusing write expression (contains DB::statement)"
fi

compose exec -T app php artisan tinker --execute="echo json_encode((${expr}), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);"
