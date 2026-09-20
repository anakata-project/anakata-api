# Task 03 · anakata-api · Cabin claims, holds, availability, calendar and layout
**Repo:** anakata-api · **Sprint:** 3 (read `README.md` in this folder first)
**Needs:** task 02.

## Goal
The double-booking guard. One `cabin_claims` table records every occupant of every cabin on every departure. The **database** refuses two active claims on the same cabin and departure, and every availability figure in the system is computed from it. Holds are claims with an expiry, released by a job. This task also adds the departure locks and the calendar and yacht-layout read models.

## Read first
- `docs/requirements/08-dev-decisions.md`: **F1, F2, F3, F9**, D5, D6
- `docs/requirements/03-business-rules.md` §4.4 (never overbook), §10 (< 30 s to the public site), R-B2, TEC-004
- `docs/requirements/04-booking-engine-contract.md` sync rules 1–2 (no overbooking, "Limited Availability — Contact Us")
- `prototype/rms_index.html`: `occ`, `inv`, `engLabel`, `invBar`, `chartered`, `renderCal`, `cellFor`, `renderYacht`, `cabHtml`, and the Calendar legend and notice
- `app/Services/References/ReferenceService.php`: the single-statement locking pattern and why (Sprint 1 task 05)

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Table `cabin_claims`:**

   | Column | Notes |
   |---|---|
   | `id` | |
   | `departure_id` FK, `cabin_id` FK | `restrictOnDelete` both |
   | `holder_type`, `holder_id` | morph: `internal_block` now; `booking`, `checkout_session` later |
   | `kind` | enum `BLOCK` / `HOLD` / `BOOKING` |
   | `hold_type` | nullable enum `WEB` / `REQUEST` / `AGENCY` / `CHARTER_QUOTE`, only for `HOLD` |
   | `expires_at` | timestamp, nullable; required for `HOLD`, null otherwise |
   | `released_at`, `release_reason` | nullable; reason enum `RELEASED` / `EXPIRED` / `CONVERTED` / `CANCELLED` / `MOVED` |
   | `active_key` | **generated stored column**: `IF(released_at IS NULL, CONCAT(departure_id, '-', cabin_id), NULL)`, with a **unique index** |
   | audit columns, timestamps | |

   - Indexes: `(departure_id, released_at)`, `(holder_type, holder_id)`, `(kind, expires_at)`.
   - Model `CabinClaim`. Claims are **never deleted**: releasing sets `released_at`. Add a guard like `ChangeHistory` (`delete()` throws). Also add a MySQL `BEFORE DELETE` trigger, since claims are part of the audit trail.
   - `CabinClaim` isn't a history subject; history is written against the holder.
2. **`App\Services\Inventory\ClaimService`**, the only writer of claims:
   ```php
   claim(Departure $departure, Collection $cabins, Model $holder, ClaimKind $kind,
         ?HoldType $holdType = null, ?CarbonInterface $expiresAt = null): Collection   // all-or-nothing
   release(Model $holder, ReleaseReason $reason, ?Collection $cabins = null): int
   convert(Model $fromHolder, Model $toHolder, ClaimKind $kind): Collection         // hold → booking later; same cabins
   releaseExpired(): int
   ```
   - It must run inside the caller's transaction, with the same guard as `History` / `ReferenceService`.
   - **Claiming** inserts one row per cabin, in cabin `sort` order (a fixed lock order).
     - On a unique violation of `active_key`, look at the conflicting active claim. If it's a `HOLD` whose `expires_at` has passed, release it (`EXPIRED`) and retry **once**.
     - Otherwise throw `CabinUnavailableException` (HTTP 409) carrying the list of `{ cabin, held_by: kind + holder reference }`. Nothing is written, all-or-nothing.
     - Under concurrency, MySQL's unique-index check is the guarantee; don't pre-check with a SELECT and trust it.
   - `claim` refuses:
     - a departure in the past
     - a `HOLD` without an expiry, or with an expiry in the past
     - a non-`HOLD` with an expiry
   - Every claim or release dispatches `AvailabilityChanged($departureIds)` (`ShouldDispatchAfterCommit`). No listener yet; the engine push subscribes in the engine sprint.
3. **The release job.** Console command `inventory:release-expired-holds` calls `releaseExpired()` in batches of 500, each batch in its own transaction.
   - Each released hold writes a history row on its holder (`hold.expired`, actor System).
   - Schedule it **every minute** in `routes/console.php`, `withoutOverlapping()`. The prototype's "nightly sweep" is covered by the same schedule.
   - Log the count at `info` when > 0.
4. **Availability: `App\Services\Inventory\Availability`.**
   - `forDepartures(Collection $departures): array` (one query for all claims of those departures, no N+1) gives, per departure:
     - `cabins`: for each of the 9 cabins in order, `{ cabin: { code, label, category }, state: FREE|HELD|SOLD|BLOCKED, claim: { kind, hold_type, expires_at, holder: { type, id, reference\|null, label } } \| null }`
       - `HOLD` → `HELD`, `BOOKING` → `SOLD`, `BLOCK` → `BLOCKED`
       - an expired but not yet released hold counts as **`FREE`** (the next claim releases it)
     - `counts`: `{ sold, held, blocked, free, suites_free, owner_free: bool }`
     - `engine_label`: `{ code, text, tone }`, following `engLabel` in order:
       1. `HIDDEN` status, or itinerary not `PUBLISHED` → `NOT_SHOWN` "NOT SHOWN"
       2. chartered → `CHARTERED` "CHARTERED — NOT SHOWN" (true when every cabin is `SOLD` under one holder; never true until Sprint 4)
       3. `CLOSED` → "CLOSED — ENQUIRE"
       4. `CHARTER` → "PRIVATE CHARTER ONLY"
       5. `free == 0` and `held > 0` → **`LIMITED`** "LIMITED AVAILABILITY" (F9)
       6. `free == 0` → "FULL · WAITLIST" if `waitlist_enabled`, else "FULL"
       7. `free ≤ urgency_threshold` → "ONLY N CABIN(S) LEFT"
       8. otherwise "AVAILABLE"

       The tones map to the prototype pill classes: `wait`, `comp`, `pend`, `canc`, `hold`, `conf`.
   - A pure function, unit-tested per rule, with the precedence above.
5. **API.**
   - `GET /api/rms/departures` and `/{departure}` now include `availability` (`counts` and `engine_label` in the list; everything in the detail). Add a `with_cabins=1` flag to the list for the calendar.
   - **KPIs** on the list response, `meta.kpis`, computed over the filtered set:
     - `on_sale_on_engine`: `ON_SALE` and label not `NOT_SHOWN` / `CHARTERED`
     - `cabins_bookable`: free cabins on those departures
     - `showing_only_n_left`
     - `full`

     These are the prototype's four KPI definitions (`renderDeps`).
   - `GET /api/rms/calendar?from&to&yacht_id` → `{ departures: [ { id, reference, date, yacht, itinerary, festive, status } ], rows: [ { yacht, cabin, cells: { [departure_id]: { state, claim } } } ] }`. This is the grid of cabins × departures for both yachts, in the prototype's layout order: yachts in code order, cabins by `sort`. Default range: today → +6 months; max 18 months.
   - `GET /api/rms/itineraries` rows gain `live_departures_count`: departures of the itinerary whose engine label isn't `NOT_SHOWN` / `CHARTERED`. It feeds the itinerary card ("2 live departures"); compute it with the same `Availability` service, in one pass.
   - `GET /api/rms/departures/{departure}/layout` → the departure plus its 9 cabins with state and claim summary, for the deck plan. Party details come in Sprint 4 through the same `claim.holder`.
   - All read endpoints need `panel.rms`.
6. **Departure locks (prototype `editDep` / `delDep`):**
   - `UpdateDeparture` refuses to change `date` or `yacht_id` while any active `HOLD` or `BOOKING` claim exists: 409 "Date and yacht are locked — {n} cabin(s) sold or held on this departure. Move guests with "Move to another departure" on each booking first." Blocks don't lock the date; they move with the departure.
   - `DeleteDeparture` refuses while **any** active claim exists, blocks included: 409 naming the counts ("2 blocked, 1 held").
   - The detail response carries `locks: { date_and_yacht: bool, delete: bool, reason: string|null }` so the panel disables the right controls.
7. **Tests**, the core of the sprint:
   - **The database guarantee:** insert two active claims for the same departure/cabin **through the query builder**, bypassing `ClaimService`; the second insert fails on the unique index. Release the first (set `released_at`); now a new active claim succeeds.
   - Claims are all-or-nothing: claiming S1–S3 when S2 is taken writes nothing and reports S2 in the exception.
   - Expired hold: claim over an expired hold → the old claim is released `EXPIRED` and the new one succeeds. The job releases expired holds in batches and writes `hold.expired` history. Unexpired holds are untouched.
   - Concurrency, same method as the Sprint 1 reference test (`TruncatingTestCase`, a second connection, `innodb_lock_wait_timeout = 1`): two transactions claim the same cabin; the second gets a lock wait (1205) or duplicate (1062), **never** a second active row. Report which error occurred.
   - `convert()` moves claims between holders atomically (test with two test holders).
   - Availability states, counts and every `engine_label` rule, including the precedence.
   - Departure locks and the delete guard.
   - KPIs over a filtered list.
   - Calendar and layout shapes; no N+1 (assert the query count for 16 departures).
   - Delete of a claim → throws (model and trigger).
   - Use a small **test-only holder model** (a test migration, as in Sprint 2) for the hold and booking cases, since bookings don't exist yet.

## Out of scope
Holds placed by anything real: requests, agency holds and web checkout (Sprint 4 and the engine sprint). Business-hours expiry (Sprint 4: TEC-004's "business hours" and the near-term/long-lead boundary are open questions, see the report). The engine push.

## Acceptance criteria
- [ ] The raw-SQL double-claim test fails at the database.
- [ ] The job is scheduled every minute and releases expired holds.
- [ ] `GET /api/rms/calendar` and `/layout` return the grid for the demo seed (all cabins `FREE`, except the demo block after task 04).
- [ ] `composer check` passes.
- [ ] A "Task 03" section in `REPORT.md` covering:
  - the generated-column DDL
  - the concurrency test outcome
  - the label precedence table
  - under **Open questions** for Sprint 4: (a) what "business hours" means for TEC-004 holds (days, hours, Galápagos public holidays?), and (b) where "near-term" ends and "long-lead" begins (48 business hours vs 5 business days)
