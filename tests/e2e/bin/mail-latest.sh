#!/usr/bin/env bash
# Newest Mailpit message to an address: subject + every http(s) link.
set -euo pipefail

# shellcheck source=./_lib.sh
. "$(cd "$(dirname "$0")" && pwd)/_lib.sh"

email="${1:-}"
if [ -z "${email}" ]; then
  die "usage: mail-latest.sh <email>"
fi

query="$(printf '%s' "${email}" | jq -sRr @uri)"
deadline=$((SECONDS + 30))
id=""

say "Waiting up to 30s for mail to ${email}"

while [ "${SECONDS}" -lt "${deadline}" ]; do
  payload="$(curl -sS "http://localhost:8025/api/v1/search?query=to:${query}&limit=50" || true)"
  if [ -n "${payload}" ]; then
    id="$(printf '%s' "${payload}" | jq -r --arg email "${email}" '
      [.messages[]? | select(
        ([.To[]?.Address] | map(ascii_downcase) | index($email | ascii_downcase)) != null
      )] | sort_by(.Created) | reverse | .[0].ID // empty
    ')"
    if [ -n "${id}" ] && [ "${id}" != "null" ]; then
      break
    fi
  fi
  sleep 1
done

if [ -z "${id}" ] || [ "${id}" = "null" ]; then
  printf 'no mail for %s\n' "${email}" >&2
  exit 1
fi

msg="$(curl -sS "http://localhost:8025/api/v1/message/${id}")"
subject="$(printf '%s' "${msg}" | jq -r '.Subject')"

printf 'Subject: %s\n' "${subject}"
printf 'Links:\n'
printf '%s' "${msg}" | jq -r '.Text // "" , .HTML // ""' \
  | sed 's/&amp;/\&/g' \
  | grep -oE 'https?://[^[:space:]"<>]+' \
  | sed -E 's/[].,);]+$//' \
  | grep -v 'w3.org' \
  | awk '!seen[$0]++' \
  || true
