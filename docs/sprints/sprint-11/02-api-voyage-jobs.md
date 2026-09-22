# Task 02 · anakata-api · Voyage status and the scheduled jobs
**Repo:** anakata-api · **Sprint:** 11 · **Needs:** task 01.

## Goal
Voyages move to ON BOARD and COMPLETED by themselves, and every job in doc 07 §7 exists in the form this system needs (N2, N3), with its runs visible on Sync & Field Ownership.

## Read first
- `docs/requirements/08-dev-decisions.md`: **N2, N3**, N1, A4, B9, G5, H5, H6, J5, J7, K2, L2, M2
- doc 07 §7 (scheduled jobs)
- `Transitions`, `TransitionBooking`, `AnakataSchedule`, the `scheduled_runs` hooks and the Sync jobs endpoint (Sprint 9 task 04, Sprint 10 task 01), `anakata:documents-due`, the Stripe events table and the payments ledger, `DocumentPlan`, business rules `alerts.low_occupancy_pct` / `_days_before`

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Voyage status (N2).** `anakata:voyage-status`, daily at 00:15 Galápagos time. Through `TransitionBooking` with a system actor ("System · voyage status") and the normal history and events:
   - FULLY_PAID → ON_BOARD on the departure date;
   - ON_BOARD → COMPLETED on the return date;
   - a booking still CONFIRMED (with or without the OVERDUE flag) on its departure date is not moved; raise CRITICAL alert CONFIRMED_AT_DEPARTURE (audience `bookings.overdue_decision` and `payments.record`), resolved when it leaves CONFIRMED.
   Idempotent: a re-run on the same day changes nothing. Charter bookings follow the same rule. Every move emits the existing `BookingStatusChanged`, so documents, tasks and the CRM projection follow without new code.
2. **Ledger integrity (replaces "ledger reconcile").** `anakata:ledger-check`, nightly 02:00. Compares: settled card payments with their Stripe events (amount, currency, status); each booking's `paid` figure with the sum of its settled payments through the same SQL the booking uses; refunds executed with their Stripe refund events. Any difference raises CRITICAL LEDGER_DRIFT (audience `payments.record` and `refunds.approve`), listing the booking, the two figures and the source. It never writes a payment. Resolves when the next run finds no difference for that booking.
3. **Commission leakage scan.** `anakata:commission-scan`, nightly 02:30. Raises WARN COMMISSION_LEAKAGE (audience `agencies.manage`) for: a sold booking whose channel of origin is a trade value (Travel Advisor, Luxury Agency, Host Agency, Consortia, Tour Operator, Luxury Tour Operator, DMC, Incoming Operator — one list in the scan, from `ChannelOfOrigin`), or whose booking request was marked travel advisor, with no agency; an approved agency whose commission is above the cap with a booking not held or not approved; an approved agency with no payment terms. One alert per finding; resolves when the finding disappears.
4. **Occupancy check.** `anakata:occupancy-check`, daily 07:00. For open departures at or within `alerts.low_occupancy_days_before` days whose sold cabins are below `alerts.low_occupancy_pct` percent of sellable cabins (computed availability, not a new count), raise INFO LOW_OCCUPANCY (audience `departures.manage`, `rates.manage`) with the percentage; resolves when it rises above the threshold or the departure sails.
5. **Document version check.** `anakata:document-check`, hourly. For each document the plan sends automatically (J7), if its latest issued version has no SENT delivery and no delivery in progress, re-queue the send through the existing send action with its J5 idempotency key; if that is not possible (no recipient, a blocked reason), raise WARN DELIVERY_FAILED instead. Never issues a new version.
6. **Jobs catalogue.** Extend the Sync jobs payload with a PHP registry mapping doc 07 §7 to this system: each row names the doc's job, the command that implements it (or "not needed"), and a sentence. Hold expiry → `inventory:release-expired-holds`. Segment recompute → "Not needed: segments are derived in SQL (L2)". Consent sweep → "Not needed: consent is read at send time from one register (M2)". Ledger reconcile → `anakata:ledger-check`, with the sentence that drift is reported and never corrected. The panel already renders jobs from the API; no panel change is needed beyond showing the new rows.
7. **Registration.** All commands in `AnakataSchedule` with Galápagos time zones, `withoutOverlapping`, `onOneServer`, and the run hooks. Extend the Sprint 9 test that fails when a scheduled command has no hook.
8. **Alert kinds.** Add CONFIRMED_AT_DEPARTURE, LEDGER_DRIFT, COMMISSION_LEAKAGE and LOW_OCCUPANCY to the task 01 registry with their audiences, conditions and resolving facts.

## Don't
- Don't correct a ledger difference, change a payment or execute a refund.
- Don't move a CONFIRMED booking to ON BOARD.
- Don't issue a document version from the version check.

## Checks
- `composer check`.
- Voyage status on the departure and return dates, across the Galápagos midnight, a re-run, and a CONFIRMED booking at departure (alert, no move). The events and history the transition already writes.
- Ledger check: a seeded clean ledger raises nothing; a tampered payment in the test database raises LEDGER_DRIFT; fixing it resolves it.
- Leakage findings, occupancy at the boundaries, the version check re-queue and its alert path.
- The catalogue lists every doc 07 §7 row; the run hooks test.

## Report
Append **Task 02**: each command with cadence and what it reads and writes, the catalogue as built, the alert kinds added, and the tests. Git commands listed, not run.
