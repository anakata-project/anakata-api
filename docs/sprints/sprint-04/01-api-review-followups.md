# Task 01 · anakata-api · Review follow-ups: departure row lock, typed responses
**Repo:** anakata-api · **Sprint:** 4 (read `README.md` in this folder first)

## Goal
Two things before bookings are built on top of Sprint 3:
- The departure's own row becomes the lock between claims and date changes (G10).
- Every response the panel uses gets a PHPDoc array shape, so Scramble generates its type and `anakata-ui` can delete most of its hand-written `inventory.ts` (Sprint 3 task 05 note).

## Read first
- `docs/requirements/08-dev-decisions.md`: F1, F3, **G10**
- `app/Services/Inventory/ClaimService.php`, `app/Actions/Departures/UpdateDeparture.php`, `app/Support/Inventory/DepartureLocks.php`
- Sprint 3 `REPORT.md`: task 05 "Notes for later" (the list of hand-written types and why), task 03 (the gap-lock reasoning)
- Scramble's documentation on array shapes in `JsonResource::toArray()` PHPDoc (check the installed version's docs, not memory)

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **The departure row lock (G10).**
   - At the start of `ClaimService::claim()` (after the transaction guard), lock the departure row: `Departure::query()->whereKey($departure->id)->lockForUpdate()->first()`. Use the fresh row for the "not in the past" check.
   - `convert()` locks each affected departure row, in ascending id order.
   - `UpdateDeparture`: lock the departure row first, then read the claims, then decide the date/yacht lock. `DeleteDeparture`: the same.
   - This is a primary-key lock, a record lock with no gaps, so it doesn't bring back the Sprint 3 gap-lock problem. It serialises claims **per departure**, which is intended at this volume.
   - Also catch a foreign-key violation (MySQL 1451) on the departure delete and return the history 409 message, so a race can never surface as a 500.
   - **Tests** (in `tests/Concurrency/`, with the same method as before):
     - Transaction A locks the departure (claims a cabin, not committed); transaction B's `UpdateDeparture` date change waits (1205 with the short timeout) instead of passing the check.
     - The same with A changing the date and B claiming.
     - Report the outcomes.
2. **Typed responses.** Add PHPDoc array shapes (`@return array{…}`) to every resource or response the panel reads that Scramble currently emits as untyped. At least:
   - `DepartureResource` (with `availability`, `locks`, `rates`, `warnings` on mutations)
   - `InternalBlockResource`, `ItineraryDefaults` / its resource
   - the calendar response, the generate-season response
   - `ClaimSummary` / `holder` (including `detail`)
   - `CabinUnavailableException::render()`
   - any Sprint 2 config resource still untyped (`ConfigCurrentResource` document, registry rows)

   Where a controller returns a raw `JsonResponse`, introduce a small resource or a documented response class so Scramble can read the shape. Check `/docs/api.json` after each change. The goal is **no `{ [key: string]: unknown }`** for anything the panel reads.
3. **Tests:** the existing feature tests stay green. Add one test that fetches `/docs/api.json` and asserts that the schemas for departures, blocks, calendar and claims have `properties` (not an empty object), so a regression is caught.

## Out of scope
Bookings (tasks 03–05). Regenerating the UI types (task 06).

## Acceptance criteria
- [ ] Both concurrency tests show the second transaction waiting, never interleaving.
- [ ] `/docs/api.json` types every panel-read response.
- [ ] `composer check` passes.
- [ ] A "Task 01" section in `REPORT.md` covering:
  - the lock order
  - the concurrency outcomes
  - the list of responses now typed, mapped to the `inventory.ts` types they replace (task 06 uses this list)
