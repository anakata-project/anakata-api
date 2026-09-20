# Task 02 · anakata-api · Departures and "generate season"
**Repo:** anakata-api · **Sprint:** 3 (read `README.md` in this folder first)
**Needs:** task 01.

## Goal
A departure is one yacht sailing one Sunday. The RMS creates it on its own or a season at a time, and sets the itinerary, the status on the engine and what the guest sees. Its availability is **not** stored; task 03 computes it from claims. Task 03 also adds the date/yacht lock and the delete guard.

## Read first
- `docs/requirements/08-dev-decisions.md`: B3 (references), **F5, F6, F7**
- `docs/requirements/02-data-model.md`: Departure; `01-functional-spec.md` §15; `03-business-rules.md` OPS-001 (Sunday → Sunday, 7 nights), OPS-006
- `prototype/rms_index.html`: `editDep`, `saveDep`, `delDep`, `genSeason`, `runSeason`, `DSTAT`, `ENG_SEQ`
- `docs/requirements/examples/seed-data.json` → `departures`
- `app/Services/References/ReferenceService.php` (`ReferenceType::Departure`, `ensureAtLeast`)

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Table `departures`:**
   - `id`, `reference` (string, unique; `DEP-001`, from `ReferenceService`)
   - `date` (`DATE`, `CalendarDate` cast), `yacht_id` FK
   - `itinerary_id` FK (`restrictOnDelete`)
   - `status` (enum `ON_SALE` / `CLOSED` / `HIDDEN` / `CHARTER`; labels from `DSTAT`: On sale · Closed to sale · Hidden · Charter only)
   - `urgency_threshold` (tinyint 0–9, default 3), `waitlist_enabled` (bool, default true), `public_note` (string 40, nullable), `festive` (bool)
   - audit columns, timestamps
   - unique `(yacht_id, date)`

   Model `Departure`, with morph alias `departure`. `return_date` is derived (date + 7 days, OPS-001), never stored.
2. **Validation and Actions.**
   - The date must be a Sunday: "Anakata sails Sunday → Sunday. {date} is not a Sunday." (prototype wording).
   - One departure per yacht per date: "{YACHT} already has a departure on {date} ({DEP-NNN})." as 422 on `date`.
   - Status in the enum; threshold 0–9; note max 40.
   - **Festive twin warning (F7).** When the other yacht has a departure on the same date with a different `festive`, the create/update response includes `warnings: ["ANATIVA's departure on 19 Dec 2027 is not festive."]`. It doesn't block.
   - The festive flag and the itinerary are independent fields. A festive departure normally uses the festive itinerary; warn (don't block) when `festive` is true and the itinerary isn't flagged festive, or the reverse.
   - Actions: `CreateDeparture` (draws `ReferenceType::Departure` inside its transaction), `UpdateDeparture`, `DeleteDeparture` (the guard comes in task 03; for now it deletes), `GenerateSeason`.
   - History: `departure.created`, `departure.updated` (diff), `departure.status_changed` (a status-only change, recorded separately so the history reads well), `departure.deleted`.
3. **Generate season** (`runSeason`), input `{ from, to, yacht_ids[], pattern: ALT|WEST|NORTH, festive_window: bool, status: CLOSED|ON_SALE }`:
   - The first Sunday on or after `from`, then weekly up to `to`, inclusive.
   - Pattern `ALT`: alternate the WEST and NORTH itineraries by week, with the two yachts on **opposite** routes the same week. Mirror `runSeason`'s `(w + yi) % 2` exactly, including which yacht starts on which route.
   - Festive window 15 Dec – 2 Jan inclusive: use the itinerary coded `FEST`, `festive = true`.
   - Resolve itineraries by code. If `WEST`, `NORTH` or (when needed) `FEST` doesn't exist, return 422 naming the missing code.
   - Existing (yacht, date) pairs are **skipped**, not errors.
   - It returns `{ created: [...references], skipped: [{ yacht, date }] }`.
   - One transaction, one `departure.created` history row per departure, with `context` noting "generate season".
   - Max range 18 months → 422 beyond that.
4. **Endpoints** under `/api/rms/departures`. Viewing needs `panel.rms`; changes need `departures.manage`.

   | Method · path | Does |
   |---|---|
   | `GET /` | list with filters `from`, `to` (dates), `yacht_id`, `status`; ordered by date then yacht; paginated (default 100, max 500). Each row: departure fields, `return_date`, yacht, itinerary `{ id, code, name, status, festive }`, and a `rates` hint `{ year, suite_from: int\|null }` from `CurrentConfig::rates()` (`null` = no rates for that year, the prototype's "⚠ No 2031 rates") |
   | `GET /{departure}` | one departure (task 03 adds inventory) |
   | `POST /` | create |
   | `PATCH /{departure}` | update |
   | `DELETE /{departure}` | delete |
   | `POST /generate-season` | as above |
   | `GET /{departure}/history` | history |

   Task 03 adds `availability` to the list and detail responses; leave a clear seam (a resource that task 03 extends).
5. **Demo seed (F6).** `DemoInventorySeeder` adds the 16 seed-data departures (local/testing only), keeping their references `DEP-001`…`DEP-016`. Then call `ReferenceService::ensureAtLeast(Departure, 16)` so the next is `DEP-017`. Idempotent by (yacht, date). Test the key mapping against the JSON (`di` is the prototype's date index; use `date`).
6. **Itinerary delete guard.** Now that departures exist: `DELETE /api/rms/itineraries/{id}` returns 409 "Used by {n} departures" when any departure references it. Add the test deferred in task 01.
7. **Tests:**
   - Sunday rule; uniqueness message; the festive-twin and festive/itinerary warnings
   - generate season: the ALT pattern for two yachts over 6 weeks (assert the itinerary codes week by week against a hand-written table from `runSeason`), the festive window (a range crossing 15 Dec and 2 Jan), skipping existing pairs, missing itinerary code → 422, range > 18 months → 422
   - references continue from `DEP-017` after the demo seed
   - the list filters and the `rates` hint (a 2031 departure → `suite_from: null`)
   - permissions (Lucía read-only; Mateo writes)
   - history events, including one per generated departure

## Out of scope
Availability, locks, the delete guard for claims (task 03). The engine feed (engine sprint).

## Acceptance criteria
- [ ] The demo seed gives 16 departures across 7 Nov – 26 Dec 2027. The next created departure is `DEP-017`.
- [ ] Generating 2 Jan – 26 Mar 2028 for both yachts, ALT, festive window off, creates 26 departures with the yachts on opposite routes each week. With the festive window on, the two 2 Jan departures use `FEST` instead.
- [ ] `composer check` passes.
- [ ] A "Task 02" section in `REPORT.md` covering:
  - the ALT table used in the test
  - the warning texts
  - any `runSeason` behaviour that was unclear
