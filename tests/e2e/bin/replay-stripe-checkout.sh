#!/usr/bin/env bash
# Replay checkout.session.completed twice for an OPEN payment link (FakeStripe / empty keys).
# Usage: tests/e2e/bin/replay-stripe-checkout.sh ANK-2026-0022
set -euo pipefail

# shellcheck source=./_lib.sh
. "$(cd "$(dirname "$0")" && pwd)/_lib.sh"

reference="${1:-}"
if [ -z "${reference}" ]; then
  die "usage: replay-stripe-checkout.sh <booking-or-request-reference>"
fi

say "anakata:replay-stripe-checkout ${reference}"
in_app "php artisan anakata:replay-stripe-checkout $(printf '%q' "${reference}")"
