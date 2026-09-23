#!/usr/bin/env bash
# Portal setup for e2e scenarios. Existing actions only, inside the app container.
# Usage:
#   setup.sh portal-user <AG-reference>
#   setup.sh portal-invite <AG-reference>
#   setup.sh portal-suspend <AG-reference>
#   setup.sh portal-resume <AG-reference>
#   setup.sh agency-over-cap <AG-reference>
set -euo pipefail

# shellcheck source=./_lib.sh
. "$(cd "$(dirname "$0")" && pwd)/_lib.sh"

PASSWORD="password"
ACTOR_EMAIL="carolina@anakata.test"

usage() {
  die "usage: setup.sh portal-user|portal-invite|portal-suspend|portal-resume|agency-over-cap <AG-reference>"
}

run_tinker() {
  local code="$1"
  local payload
  payload="$(printf '%s' "${code}" | base64 | tr -d '\n')"
  in_app "php artisan tinker --execute=\"eval(base64_decode('${payload}'));\""
}

json_line() {
  local output="$1"
  printf '%s\n' "${output}" | grep -E '^E2E_JSON:' | tail -n 1 | sed 's/^E2E_JSON://'
}

require_ref() {
  local ref="${1:-}"
  if ! printf '%s' "${ref}" | grep -Eq '^AG-[0-9]+$'; then
    die "agency must be a reference like AG-001"
  fi
  printf '%s' "${ref}"
}

portal_email() {
  local kind="$1"
  local ref="$2"
  printf '%s' "${kind}-$(printf '%s' "${ref}" | tr '[:upper:]' '[:lower:]')@portal.test"
}

# Shared prelude: $agency and $actor. Reference is interpolated only after require_ref.
php_prelude() {
  local ref="$1"
  cat <<PHP
\$agency = App\\Models\\Agency::query()->where('reference', '${ref}')->first();
if (! \$agency instanceof App\\Models\\Agency) {
    echo "E2E_JSON:".json_encode(['error' => 'unknown agency ${ref}'], JSON_UNESCAPED_SLASHES)."\\n";
    return;
}
\$actor = App\\Models\\User::query()->where('email', '${ACTOR_EMAIL}')->first();
if (! \$actor instanceof App\\Models\\User) {
    echo "E2E_JSON:".json_encode(['error' => 'missing ${ACTOR_EMAIL}'], JSON_UNESCAPED_SLASHES)."\\n";
    return;
}
PHP
}

emit_user() {
  cat <<'PHP'
echo "E2E_JSON:".json_encode([
    'email' => $user->email,
    'status' => $user->status->value,
    'accepted' => $user->accepted_at !== null,
], JSON_UNESCAPED_SLASHES)."\n";
PHP
}

ensure_user() {
  local ref="$1"
  local email="$2"
  local name="$3"
  php_prelude "${ref}"
  cat <<PHP
\$email = '${email}';
\$user = App\\Models\\AgencyUser::query()->where('email', \$email)->first();
if (\$user instanceof App\\Models\\AgencyUser && (int) \$user->agency_id !== (int) \$agency->id) {
    echo "E2E_JSON:".json_encode(['error' => 'email belongs to another agency'], JSON_UNESCAPED_SLASHES)."\\n";
    return;
}
if (! \$user instanceof App\\Models\\AgencyUser) {
    app(App\\Actions\\Agencies\\CreateAgencyUser::class)->handle(\$agency, [
        'name' => '${name}',
        'email' => \$email,
    ], \$actor);
    \$user = App\\Models\\AgencyUser::query()->where('email', \$email)->first();
}
if (! \$user instanceof App\\Models\\AgencyUser) {
    echo "E2E_JSON:".json_encode(['error' => 'user was not created'], JSON_UNESCAPED_SLASHES)."\\n";
    return;
}
PHP
}

invite_user() {
  cat <<'PHP'
if ($user->status === App\Enums\AgencyUserStatus::Disabled) {
    echo "E2E_JSON:".json_encode(['error' => 'user is disabled', 'email' => $user->email], JSON_UNESCAPED_SLASHES)."\n";
    return;
}
app(App\Actions\Agencies\InviteAgencyUser::class)->handle($user, $actor, false);
$user->refresh();
PHP
  emit_user
}

accept_from_mail() {
  local email="$1"
  local mail_out accept_url token
  say "Waiting for the portal invitation to ${email}"
  mail_out="$("${E2E_BIN_DIR}/mail-latest.sh" "${email}")"
  printf '%s\n' "${mail_out}"
  if ! printf '%s\n' "${mail_out}" | grep -q 'Subject: Set your Anakata portal password'; then
    die "Mailpit subject was not 'Set your Anakata portal password'. Horizon must be running so the invite job is sent."
  fi
  accept_url="$(printf '%s\n' "${mail_out}" | grep -oE 'https?://[^[:space:]]+/accept\?[^[:space:]]+' | head -n 1 || true)"
  if [ -z "${accept_url}" ]; then
    die "invitation mail had no /accept link"
  fi
  token="$(ACCEPT_URL="${accept_url}" python3 - <<'PY'
import os
from urllib.parse import parse_qs, urlparse
query = parse_qs(urlparse(os.environ["ACCEPT_URL"]).query)
print(query.get("token", [""])[0])
PY
)"
  if [ -z "${token}" ]; then
    die "invitation link had no token"
  fi
  local token_b64
  token_b64="$(printf '%s' "${token}" | base64 | tr -d '\n')"
  local code
  code="$(cat <<PHP
\$token = base64_decode('${token_b64}');
\$email = '${email}';
\$store = app('session')->driver();
\$store->start();
\$request = Illuminate\\Http\\Request::create('/api/portal/auth/accept', 'POST');
\$request->setLaravelSession(\$store);
app()->instance('request', \$request);
\$user = app(App\\Actions\\Portal\\AcceptAgencyInvitation::class)->handle(\$request, \$email, \$token, '${PASSWORD}');
echo "E2E_JSON:".json_encode([
    'email' => \$user->email,
    'status' => \$user->status->value,
    'accepted' => \$user->accepted_at !== null,
], JSON_UNESCAPED_SLASHES)."\\n";
PHP
)"
  local output line
  output="$(run_tinker "${code}")"
  printf '%s\n' "${output}"
  line="$(json_line "${output}")"
  if [ -z "${line}" ]; then
    die "accept did not return E2E_JSON"
  fi
  printf '%s\n' "${line}" | grep -q '"status":"ACTIVE"' || die "accept did not leave the user ACTIVE: ${line}"
}

append_php() {
  local base="$1"
  local extra="$2"
  printf '%s\n%s\n' "${base}" "${extra}"
}

cmd_portal_user() {
  local ref email name code output line status
  ref="$(require_ref "${1:-}")"
  email="$(portal_email "e2e-portal" "${ref}")"
  name="E2E Portal ${ref}"
  code="$(ensure_user "${ref}" "${email}" "${name}")"
  code="$(append_php "${code}" "$(cat <<'PHP'
if ($user->status === App\Enums\AgencyUserStatus::Active && $user->accepted_at !== null && $user->password !== null) {
    echo "E2E_JSON:".json_encode([
        'email' => $user->email,
        'status' => 'ACTIVE',
        'accepted' => true,
        'idempotent' => true,
    ], JSON_UNESCAPED_SLASHES)."\n";
    return;
}
PHP
)")"
  code="$(append_php "${code}" "$(invite_user)")"
  output="$(run_tinker "${code}")"
  printf '%s\n' "${output}"
  line="$(json_line "${output}")"
  if [ -z "${line}" ]; then
    die "portal-user did not return E2E_JSON"
  fi
  if printf '%s' "${line}" | grep -q '"error"'; then
    die "${line}"
  fi
  status="$(printf '%s' "${line}" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("status",""))')"
  if [ "${status}" = "ACTIVE" ]; then
    printf 'email: %s\npassword: %s\nstatus: active\n' "${email}" "${PASSWORD}"
    return 0
  fi
  accept_from_mail "${email}"
  printf 'email: %s\npassword: %s\nstatus: active\n' "${email}" "${PASSWORD}"
}

cmd_portal_invite() {
  local ref email name code output line
  ref="$(require_ref "${1:-}")"
  email="$(portal_email "e2e-invite" "${ref}")"
  name="E2E Invite ${ref}"
  code="$(ensure_user "${ref}" "${email}" "${name}")"
  code="$(append_php "${code}" "$(cat <<'PHP'
if ($user->status === App\Enums\AgencyUserStatus::Active && $user->accepted_at !== null) {
    echo "E2E_JSON:".json_encode([
        'error' => 'that invite address is already active; reset the database for a fresh invitation',
        'email' => $user->email,
    ], JSON_UNESCAPED_SLASHES)."\n";
    return;
}
PHP
)")"
  code="$(append_php "${code}" "$(invite_user)")"
  output="$(run_tinker "${code}")"
  printf '%s\n' "${output}"
  line="$(json_line "${output}")"
  if [ -z "${line}" ]; then
    die "portal-invite did not return E2E_JSON"
  fi
  if printf '%s' "${line}" | grep -q '"error"'; then
    die "${line}"
  fi
  say "Waiting for the portal invitation to ${email}"
  local mail_out accept_url
  mail_out="$("${E2E_BIN_DIR}/mail-latest.sh" "${email}")"
  printf '%s\n' "${mail_out}"
  if ! printf '%s\n' "${mail_out}" | grep -q 'Subject: Set your Anakata portal password'; then
    die "Mailpit subject was not 'Set your Anakata portal password'. Horizon must be running so the invite job is sent."
  fi
  accept_url="$(printf '%s\n' "${mail_out}" | grep -oE 'https?://[^[:space:]]+/accept\?[^[:space:]]+' | head -n 1 || true)"
  if [ -z "${accept_url}" ]; then
    die "invitation mail had no /accept link"
  fi
  printf 'email: %s\nsubject: Set your Anakata portal password\naccept: %s\n' "${email}" "${accept_url}"
}

cmd_portal_access() {
  local action="$1"
  local ref="$2"
  local reason code output line
  ref="$(require_ref "${ref}")"
  if [ "${action}" = "suspend" ]; then
    reason="E2E portal suspend"
  else
    reason="E2E portal resume"
  fi
  code="$(php_prelude "${ref}")"
  if [ "${action}" = "suspend" ]; then
    code="$(append_php "${code}" "$(cat <<PHP
\$agency = app(App\\Actions\\Agencies\\SuspendAgencyPortal::class)->handle(\$agency, '${reason}', \$actor);
PHP
)")"
  else
    code="$(append_php "${code}" "$(cat <<PHP
\$agency = app(App\\Actions\\Agencies\\ResumeAgencyPortal::class)->handle(\$agency, '${reason}', \$actor);
PHP
)")"
  fi
  code="$(append_php "${code}" "$(cat <<'PHP'
echo "E2E_JSON:".json_encode([
    'reference' => $agency->reference,
    'suspended' => $agency->isPortalSuspended(),
], JSON_UNESCAPED_SLASHES)."\n";
PHP
)")"
  output="$(run_tinker "${code}")"
  printf '%s\n' "${output}"
  line="$(json_line "${output}")"
  if [ -z "${line}" ] || printf '%s' "${line}" | grep -q '"error"'; then
    die "portal-${action} failed: ${line:-no E2E_JSON}"
  fi
  printf '%s\n' "${line}"
}

cmd_agency_over_cap() {
  local ref code output line
  ref="$(require_ref "${1:-}")"
  code="$(php_prelude "${ref}")"
  code="$(append_php "${code}" "$(cat <<'PHP'
$cap = app(App\Services\Config\CurrentConfig::class)->businessRules()->commission->capPct;
$current = (int) $agency->commission_pct;
if ($current > $cap) {
    echo "E2E_JSON:".json_encode([
        'reference' => $agency->reference,
        'commission_pct' => $current,
        'cap_pct' => $cap,
        'changed' => false,
    ], JSON_UNESCAPED_SLASHES)."\n";
    return;
}
$next = $cap + 3;
app(App\Actions\Agencies\UpdateAgency::class)->handle($agency, ['commission_pct' => $next], $actor);
$agency->refresh();
echo "E2E_JSON:".json_encode([
    'reference' => $agency->reference,
    'commission_pct' => (int) $agency->commission_pct,
    'cap_pct' => $cap,
    'changed' => true,
], JSON_UNESCAPED_SLASHES)."\n";
PHP
)")"
  output="$(run_tinker "${code}")"
  printf '%s\n' "${output}"
  line="$(json_line "${output}")"
  if [ -z "${line}" ] || printf '%s' "${line}" | grep -q '"error"'; then
    die "agency-over-cap failed: ${line:-no E2E_JSON}"
  fi
  printf '%s\n' "${line}"
}

command="${1:-}"
shift || true

case "${command}" in
  portal-user) cmd_portal_user "${1:-}" ;;
  portal-invite) cmd_portal_invite "${1:-}" ;;
  portal-suspend) cmd_portal_access suspend "${1:-}" ;;
  portal-resume) cmd_portal_access resume "${1:-}" ;;
  agency-over-cap) cmd_agency_over_cap "${1:-}" ;;
  *) usage ;;
esac
