# Task 02 · anakata-api · The portal API: rates, availability, bookings, commissions
**Repo:** anakata-api · **Sprint:** 13 · **Needs:** task 01.

## Goal
Exactly what the RMS preview promises an agent will see, served to the agent — and nothing else (P3, P9).

## Read first
- `docs/requirements/08-dev-decisions.md`: **P3, P9**, P1, and N9 (the preview is the contract), H8, L9
- doc 01 §5.5; the prototype `openAgency` portal preview
- `PortalPreview`, `Agency::netOf`, `Availability` and the engine feed labels, the commission accrual SQL and payouts (Sprint 11 task 06)

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **One scope, everywhere.** A base controller or trait resolves the signed-in agency and every query filters on it. A reference or id belonging to another agency is 404 (P3). One test signs in as agency A and asks for every one of B's ids.
2. **Endpoints** under `/api/portal` (`portal.auth`):
   - `GET /me` — the agency (name, reference, commission percent, payment terms, status), the user, and whether materials exist;
   - `GET /rates` — net rates per published year for Suite per person, Owner's Suite per person and Charter per week, from `Agency::netOf`. No public rate in the payload;
   - `GET /availability?from=&to=&yacht=&itinerary=` — departures the agency can sell, with date, yacht, itinerary, the engine label (AVAILABLE / LIMITED / SOLD OUT — the same labels the public sees, not cabin counts, unless the client says otherwise), and the net per-person rate for that departure's year;
   - `GET /bookings` — their bookings: reference, departure date, itinerary, status, lead guest name only, net due, and the deposit or balance state in words. No payment rows, no guest fields, no documents (P9);
   - `GET /commissions` — reference, frozen rate, amount, payable date, accrual status, and the payout's date and reference when paid. No bank details;
   - `GET /sales-materials` — task 04 fills this; here it returns an empty list with its note.
3. **Same figures, one source.** Net rates, net due and commissions come from the same helpers the RMS preview uses; a test compares the portal payload with `GET /api/rms/agencies/{id}/portal-preview` for the same agency and fails on any difference. That keeps the preview honest for good.
4. **No leakage.** One test walks every portal response and fails on a published rate, a passport, a date of birth, a nationality, a guest email or a payment row.
5. **Cost and shape.** Paginated lists with the same meta as the rest of the API; no N+1 over departures or bookings.

## Don't
- Don't invent a figure the RMS preview does not show.
- Don't return a 403 where a 404 hides the existence of another agency's record.
- Don't include documents or passenger data.

## Checks
- `composer check`; the cross-agency walk; the preview-equality test; the leakage walk; pagination and query counts.

## Report
Append **Task 02**: the endpoints and their fields, the shared helpers, the equality and leakage tests. Git commands listed, not run.
