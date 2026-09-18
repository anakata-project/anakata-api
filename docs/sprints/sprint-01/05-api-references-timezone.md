# Task 05 · anakata-api · Reference sequence service and business time zone
**Repo:** anakata-api · **Sprint:** 1 (read `README.md` in this folder first)
**Needs:** task 02.

## Goal
One service issues every business reference (`ANK-2026-0005`, `ANK-R-2026-0041`, `DEP-012`…). There are no gaps from races and no duplicates. The year comes from the business time zone. Nothing in the app derives a reference from an `id`.

## Read first
- `.cursor/rules/laravel.mdc`: "Databases" (business references are separate unique columns from a sequence service)
- `docs/requirements/08-dev-decisions.md`: B3 (formats), D6 (time zone)
- `docs/requirements/02-data-model.md`: every `id` column with a format (bookings, requests, payments `ANK-…-D01/B01/R01`, groups `GRP-NNN`)
- `docs/requirements/examples/seed-data.json`: the existing references (bookings up to `ANK-2026-0019`, request `ANK-R-2026-0041`, `DEP-001…`, `GRP-007`, `OF-00N`, `AG-00N`)

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **`App\Enums\ReferenceType`**, with its format and scope:

   | Case | Format | Counter scope |
   |---|---|---|
   | `Booking` | `ANK-{YYYY}-{NNNN}` | per year |
   | `Request` | `ANK-R-{YYYY}-{NNNN}` | per year, own counter |
   | `Departure` | `DEP-{NNN}` | global |
   | `Group` | `GRP-{NNN}` | global |
   | `Offer` | `OF-{NNN}` | global |
   | `Agency` | `AG-{NNN}` | global |

   - Numbers are zero-padded to the width shown and grow past it; they never wrap (`ANK-2026-10000`).
   - Payment references are separate (step 3).
2. **`reference_sequences` table:**
   - Columns: `id`, `scope` (string, unique; e.g. `booking:2026`, `departure`, `payment:ANK-2026-0005:D`), `last_value` (unsigned int), timestamps.
   - No audit columns and no history: this is infrastructure. Exclude it from the `HasAuditColumns` arch test with a comment.
3. **`App\Services\References\ReferenceService`:**
   - `next(ReferenceType $type, ?CarbonInterface $at = null): string`.
     - The year is `$at` (default now) converted to `config('anakata.business_timezone')`. A booking created at 2026-12-31 23:30 Galápagos time is a 2026 booking, even though it is 2027 in UTC. Test exactly that.
     - Implementation: inside the caller's transaction, `SELECT … FOR UPDATE` the scope row, creating it if missing (handle the insert race with a unique-key retry), then increment.
     - If a number is drawn and the caller's transaction rolls back, the counter rolls back too. Document that gaps are therefore impossible from rollbacks.
   - `nextPayment(string $bookingRef, PaymentRefKind $kind): string` with `PaymentRefKind` = `Deposit (D)`, `Balance (B)`, `Refund (R)`, `Extras (X)`, `Other (O)`. It returns `ANK-2026-0005-D01`, `-D02`… per booking and kind.
     - `X` and `O` are our assumption; doc 02 only names D, B and R. Flag it in the report.
   - `ensureAtLeast(ReferenceType $type, int $value, ?int $year = null): void`. Seeders call it after importing `seed-data.json` so new numbers continue after the imported ones. It never lowers a counter.
   - Throws if called outside a DB transaction, like `History` in task 02.
4. **Business time zone helper.** `App\Support\BusinessTime` has:
   - `zone(): string`
   - `now(): CarbonImmutable` in the business zone
   - `year(CarbonInterface $at): int`
   - `toBusiness(CarbonInterface $utc): CarbonImmutable`
   - `config('app.timezone')` stays `UTC`; add a test asserting it, so no one changes it by accident.
   - The API always serialises datetimes as UTC ISO-8601 with `Z`. Add an arch or unit test on `serializeDate` (or the global `Date::serializeUsing`) to guarantee it.
5. **No references are used yet.** The first consumer is Sprint 3 or 4. Leave a short "References" subsection in `laravel.mdc`:
   - the column naming (`reference`, unique, string)
   - the service is the only generator
   - drawn inside the creating Action's transaction
   - never derived from `id`
6. **Tests:**
   - Every format, including padding growth past 9999.
   - The per-year reset: the first 2027 booking is `ANK-2027-0001`.
   - Separate booking and request counters.
   - The Galápagos year-boundary case.
   - Payment suffixes per booking and kind.
   - `ensureAtLeast` never lowers a counter.
   - Rollback: draw a number inside a transaction that rolls back, and the next draw reuses the number.
   - Called outside a transaction → throws.
   - A concurrency smoke test: two DB connections (`DB::connection('mysql')` plus a second named connection to the same test DB) draw in interleaved transactions; the second blocks until the first commits and gets the next number. If it can't be made reliable in Pest, explain why in the report and keep the unique-index guarantee instead.

## Out of scope
Business-hours calculation (the hold deadlines in Sprint 3/4 will need it; `BusinessTime` is where it will go). Whether a request keeps its `ANK-R-` reference or gets a new `ANK-` reference when it becomes a booking: decided in the bookings sprint. Add it to "Open questions" in the report.

## Acceptance criteria
- [ ] `ReferenceService` issues every format in the table, per scope, with no duplicates and no gaps after a rollback.
- [ ] The Galápagos year boundary is handled and tested.
- [ ] `composer check` passes.
- [ ] A "Task 05" section in `REPORT.md` covering:
  - the X/O assumption
  - the request → booking reference question
  - the concurrency test outcome
