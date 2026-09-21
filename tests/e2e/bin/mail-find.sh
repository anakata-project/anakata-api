#!/usr/bin/env bash
# Find a Mailpit message by recipient and subject. Print subject, recipients,
# attachment names; optionally print the first PDF attachment's sha256.
set -euo pipefail

# shellcheck source=./_lib.sh
. "$(cd "$(dirname "$0")" && pwd)/_lib.sh"

to=""
subject=""
want_sha=""

while [ "$#" -gt 0 ]; do
  case "$1" in
    --to)
      to="${2:-}"
      shift 2
      ;;
    --subject)
      subject="${2:-}"
      shift 2
      ;;
    --sha256)
      want_sha="1"
      shift
      ;;
    *)
      die "usage: mail-find.sh --to <email> --subject <substring> [--sha256]"
      ;;
  esac
done

if [ -z "${to}" ] || [ -z "${subject}" ]; then
  die "usage: mail-find.sh --to <email> --subject <substring> [--sha256]"
fi

query="$(printf '%s' "${to}" | jq -sRr @uri)"
deadline=$((SECONDS + 30))
id=""

say "Waiting up to 30s for mail to ${to} subject ${subject}"

while [ "${SECONDS}" -lt "${deadline}" ]; do
  payload="$(curl -sS "http://localhost:8025/api/v1/search?query=to:${query}&limit=50" || true)"
  if [ -n "${payload}" ]; then
    id="$(printf '%s' "${payload}" | jq -r --arg email "${to}" --arg subject "${subject}" '
      [.messages[]? | select(
        (([.To[]?.Address] | map(ascii_downcase) | index($email | ascii_downcase)) != null)
        and (.Subject | test($subject; "i"))
      )] | sort_by(.Created) | reverse | .[0].ID // empty
    ')"
    if [ -n "${id}" ] && [ "${id}" != "null" ]; then
      break
    fi
  fi
  sleep 1
done

if [ -z "${id}" ] || [ "${id}" = "null" ]; then
  printf 'no mail for %s subject %s\n' "${to}" "${subject}" >&2
  exit 1
fi

msg="$(curl -sS "http://localhost:8025/api/v1/message/${id}")"

printf 'Subject: %s\n' "$(printf '%s' "${msg}" | jq -r '.Subject')"
printf 'To: %s\n' "$(printf '%s' "${msg}" | jq -r '[.To[]?.Address] | join(", ")')"
printf 'Cc: %s\n' "$(printf '%s' "${msg}" | jq -r '[.Cc[]?.Address] | join(", ")')"
printf 'Attachments:\n'
printf '%s' "${msg}" | jq -r '.Attachments[]?.FileName // empty' | sed 's/^/  /'

if [ -n "${want_sha}" ]; then
  part="$(printf '%s' "${msg}" | jq -r '
    [.Attachments[]? | select((.FileName // "") | test("\\.pdf$"; "i"))]
    | .[0].PartID // empty
  ')"
  if [ -z "${part}" ] || [ "${part}" = "null" ]; then
    die "no PDF attachment on message ${id}"
  fi
  sha="$(curl -sS "http://localhost:8025/api/v1/message/${id}/part/${part}" | sha256sum | awk '{print $1}')"
  printf 'SHA256: %s\n' "${sha}"
fi
