# Task 04 · anakata-api · The CRM read API: timeline, activity, sync and field ownership
**Repo:** anakata-api · **Sprint:** 9 · **Needs:** task 03.

## Goal
The three CRM screens this sprint builds read from here: a contact's timeline, the Web & Engine Activity stream, and Sync & Field Ownership — which, in this system, is about the ownership contract and the health of the jobs and side effects that keep it true, not an event bus (L5).

## Read first
- `docs/requirements/08-dev-decisions.md`: **L5, L9**, and A4, B9
- `07-three-system-integration-contract.md` §3 (ownership matrix), §4 (event catalogue), §6, §7 (scheduled jobs)
- `prototype/crm_index.html`: `openContact` (the drawer), `renderActivity` and its KPIs, `v-sync`, `OWNERSHIP`, `EVENTCAT`, `renderSync`, `BUS`, `replay`
- `routes/console.php` (every scheduled command), Horizon's failed jobs, deliveries (J5)

## Do
1. **Contact timeline** — `GET /api/crm/contacts/{contact}/timeline`, newest first, paginated, merged from sources the CRM may read, each item `{ at, kind, title, detail, link }` with wording built in the API:
   - booking milestones from `change_history` on the contact's bookings (created, requested, confirmed, fully paid, cancelled, moved) — the event and the `after.what` sentence, never before/after values;
   - payments as one line each (kind, amount, status) — amounts only, no gateway details;
   - document deliveries (kind, status, to);
   - consents (document, version, source — accepted or withdrawn);
   - behavioural events stitched to the contact (task 03), with itinerary and departure names resolved;
   - merges (task 02).
   Nothing sensitive: the guard walk from task 01 applies here too.
2. **Web & Engine Activity** — `GET /api/crm/activity?from&to&name&identified`: the event stream (time, event, contact name or "anonymous", detail built from the params, and which side it touches — "RMS + CRM" for the hold and request events, "CRM" otherwise, as the prototype's last column), paginated. `meta.kpis` computed in SQL: events today (Galápagos), identified, anonymous, inventory-touching, and the web hold minutes from the business rules — the prototype's five KPIs, none computed in the panel.
3. **Sync & Field Ownership (L5)** — one endpoint per section:
   - `GET /api/crm/sync/ownership` — the ownership matrix as a PHP registry (object, field group, system of record, visible in, rule), written from doc 07 §3 **as this system actually implements it**: "Mirrored to" becomes "Read by", the CRM's rows say "read directly from the booking tables", and each row names where it lives in the code where that helps. It is documentation served by the API so the panel copies nothing.
   - `GET /api/crm/sync/jobs` — every scheduled command (hold release, overdue flags, retention, documents due, events retention, the Stripe checkout expiry check, reconciliation if scheduled…) with its cadence, last run start and finish, outcome and a short output, next run. Add a `scheduled_runs` table written by the scheduler's `before`/`after`/`onFailure` hooks for every command in `routes/console.php`, so this is real, not assumed.
   - `GET /api/crm/sync/failures` — failed side effects that need a person: Horizon failed jobs (listener or job class, when, the exception's first line) and FAILED deliveries. `POST /api/crm/sync/failures/{id}/retry` for a failed job (Admin — new permission `sync.retry`), through Horizon's retry; FAILED deliveries retry through Sprint 7's resend, which already records a new delivery.
   - `GET /api/crm/sync/identity` — the merge log from task 02 (who, when, reason, undone or not).
   - `GET /api/crm/sync/events` — the event catalogue as implemented: the domain events that exist (Sprint 7's `BookingStatusChanged`, `PaymentSettled`, `BookingChargesChanged`, `AvailabilityChanged`, `ConfigPublished`…) and the behavioural event names from task 03, each with its producer and what listens to it. The prototype's "live bus with replay" has no equivalent in one application (B9); record that the failures section is its honest replacement.
4. **Permissions:** everything reads with `panel.crm`; retry needs `sync.retry`.
5. **Resources** typed, into the CRM schema test.

## Don't
- Don't invent a bus, a mirror or a replay of domain events.
- Don't expose before/after values, gateway details or any sensitive field on the timeline.
- Don't hard-code job health; record it.

## Checks
- `composer check`.
- Timeline: each source appears with the right wording; ordering; pagination; the sensitive-field walk; a merged contact's timeline includes the loser's history.
- Activity: filters; KPIs agree with the list; Galápagos "today".
- Jobs: a command that runs records start, finish and outcome; a failing one records the failure; the list shows every scheduled command (compare with the schedule, so a new command without a hook fails the test).
- Failures: a failed job appears and retries; a FAILED delivery appears.

## Report
Append **Task 04**: the timeline sources and what is excluded, the activity KPIs, what Sync & Field Ownership shows instead of a bus and why, the `scheduled_runs` hooks, and failures with retry. Git commands listed, not run.
