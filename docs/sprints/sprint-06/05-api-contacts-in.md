# Task 05 · anakata-api · Contacts In
**Repo:** anakata-api · **Sprint:** 6 · **Needs:** task 02.

## Goal
The RMS view of who is arriving: the contacts behind bookings and requests with where each booking stands, and the top ten guest nationalities. The CRM (Sprint 9) builds full profiles on the same `contacts` table (G1); this is the RMS window onto it.

## Read first
- `docs/requirements/08-dev-decisions.md`: **I3**, and G1, A2, B8, B9
- `01-functional-spec.md` §8 (Contacts In: "guests by nationality (top 10) — the KPI, and the driver of the PNG fee category")
- `prototype/rms_index.html`: `v-contacts` (the notice, the two panels and their columns), `renderContactsIn`, `renderNat`
- The Sprint 4 open question on contact search visibility (task 03), which is still open

## Do
1. **`GET /api/rms/contacts-in?from&to`** — one row per booking or request, prototype columns: contact (name, TRAVEL ADVISOR for requests from an advisor), segment, source (main channel / channel of origin), booking reference, status, value (`charges_total` from task 04, not the cruise total — say which in the REPORT), owner.
   - Window on the departure date, Galápagos calendar days, like the bookings list.
   - Visibility: the bookings index rule (own-records unless `bookings.view_all`). Reuse the index's scope; do not write a second one.
   - Soft-deleted bookings excluded (G8), as elsewhere.
   - Paginated; query-count test.
2. **`GET /api/rms/contacts-in/nationalities?from&to`** — the top ten nationalities by number of guests on bookings in the window, each `{ nationality, country_name, guests, bookings }`, plus `unknown` (guests without a nationality) and `total_guests`.
   - One aggregate query over `guests.nationality` (plain text, I3 — this is why it isn't encrypted).
   - Same visibility and window as the list. Cancelled and released bookings excluded; record it.
   - Country names: the API sends them. Add an ISO-3166 name lookup in the API (a small data file or a well-maintained package after the compatibility check) rather than copying the prototype's `COUNTRIES` list into the panel. Task 02's nationality validation uses the same list.
3. **Sensitive data.** `nationality` is in `SensitiveFields`. That is correct for the CRM section, and this is an RMS route, so the aggregate is allowed here. Add a test that the same payload routed through the CRM guard would be stripped, so Sprint 9 cannot move this endpoint into the CRM section without noticing.
4. **Resources** with full PHPDoc, `$wrap = null`, added to `PanelResponseSchemasTest`.

## Don't
- Don't build CRM profiles, identity resolution or segmentation (Sprint 9).
- Don't return any guest's DOB, passport or notes from these endpoints.
- Don't decide the Sprint 4 contact-search question here; record it as still open.

## Checks
- `composer check`.
- Visibility: a Sales Exec without `bookings.view_all` sees only her rows and only her guests in the aggregate.
- Window: a booking departing outside the window is excluded from both endpoints.
- The aggregate: ten rows at most, ordered by guests then name, `unknown` counted separately.

## Report
Append **Task 05**: the two endpoints, which value the list shows, the visibility rule reused, where country names now come from, the CRM-guard test, and the still-open contact-search question. Git commands listed, not run.
