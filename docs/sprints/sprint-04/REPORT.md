# Sprint 4 · Report
Each task appends its section below.

## Task 01 · Review follow-ups: departure row lock, typed responses

### What was built
The departure's own row is the lock between claims and date/yacht changes (G10). Panel-read responses now have PHPDoc array shapes so Scramble emits named schemas instead of `{ [key: string]: unknown }` / `string`. JSON wire format is unchanged.

This task does **not** close Sprint 3. `hold.expired` already records as System (`History::record(..., system: true)` in `ClaimService::releaseExpiredByIds()`); observed, not changed.

Section G was already on disk from the before-task-01 copy. This task did **not** edit `docs/requirements/08-dev-decisions.md`.

### Lock order
`DepartureLocks::lock($id)` is `WHERE id = ? FOR UPDATE` (primary-key record lock, no gaps). `lockMany` unique-sorts ids ascending and locks one at a time.

| Caller | First lock | Then |
|---|---|---|
| `ClaimService::claim` | `DepartureLocks::lock` on that departure | `assertClaimable` on the fresh row (not in the past), release expired holds, insert claims |
| `ClaimService::convert` | `lockMany` of every active claim's `departure_id` | reload those claims; if still active, release + insert |
| `UpdateDeparture` | `lock` on the departure | read claims, then the date/yacht lock |
| `DeleteDeparture` | `lock` on the departure | read claims, then delete. MySQL **1451** (FK) becomes the history 409 (`HISTORY_DELETE`) |

**S→X constraint.** An insert into `cabin_claims` takes a shared lock on the parent `departures` row. Taking that insert first and then `lockForUpdate` on the same row upgrades S→X and deadlocks (1213). The departure PK lock must be first. Tasks 03–05 that call `claim()` / `convert()` / update / delete must keep this order.

### Concurrency outcomes
`tests/Concurrency/ClaimServiceConcurrencyTest.php`, session `innodb_lock_wait_timeout = 1`. The waiter is always **1205** (lock wait), never **1213** (deadlock), never a 409 / 1062 that slipped past the lock.

| Race | Observed |
|---|---|
| Two `claim()` on the same free cabin | 1205 |
| Two `claim()` over the same expired hold | 1205 |
| A holds a claim (uncommitted); B `UpdateDeparture` date change | 1205; date stays `2028-04-02` |
| A holds a date change (uncommitted); B `claim()` | 1205; no new claim |
| Two `convert()` spanning departures X and Y in opposite order | 1205, not 1213 |

Same-departure claim tests no longer accept 409 / 1062 as a pass — that would mean the second transaction saw the row and interleaved.

### `@throws CabinUnavailableException`
`InternalBlockController::store` and `CreateInternalBlock::handle` declare `@throws CabinUnavailableException`. `CabinUnavailableExceptionToResponseExtension` is registered in `config/scramble.php`. Scramble attaches the named `CabinUnavailableException` 409 (body `message` + `unavailable[]`) to `POST /rms/blocks`.

**Every task 03+ controller method that can reach `ClaimService::claim()` needs the same `@throws`.** The extension only documents routes whose method (or a called method Scramble can see) declares the exception. Without it, create-reservation / confirm-request / re-claim after hold-expired will have no 409 in `/docs/api.json`.

### `ConfigVersionDetailResource.document` — choice
**Typed as a union of the three document shapes** (PHPDoc `|` → OpenAPI `oneOf`: rates, business-rules, engine-settings). Shared by version show/store for all three kinds. Not left open.

Current-document **GET** routes are typed per kind so Larastan stays covariant with `ConfigCurrentResource`:

| Route | Resource |
|---|---|
| `GET /api/rms/rates` | `RatesCurrentResource` |
| `GET /api/rms/business-rules` | `BusinessRulesCurrentResource` |
| `GET /api/rms/engine-settings` | `EngineSettingsCurrentResource` |

The shared `ConfigCurrentResource.document` stays `array<string, mixed>` (envelope only).

### Responses now typed → `inventory.ts` (task 06)

| PHP / OpenAPI | `inventory.ts` it replaces |
|---|---|
| `DepartureResource` (`availability`, `locks`, `rates`) | `Departure`, `DepartureListItem`, `DepartureLayout`, `Availability`, `AvailabilityCounts`, `CabinAvailability`, `DepartureLocks`, `EngineLabel` |
| `DepartureController::index` `#[DocumentedResponse]` `meta.kpis` | `DepartureKpis` |
| `DepartureMutationResource` (`$wrap = null`, `warnings` next to fields) | `DepartureMutationResponse` |
| `GenerateSeasonResource` | `GenerateSeasonResult` |
| `CalendarGridResource` | `CalendarGrid`, `CalendarDeparture`, `CalendarRow`, `CalendarCell` |
| `InternalBlockResource` (`claims`, `holder.detail`) | `InternalBlock`, `BlockClaim` |
| `CabinUnavailableException` 409 component | `CabinUnavailableError`, `CabinUnavailableItem` |
| `ClaimSummary` / `holder` (including `detail`) on departure + calendar | `ClaimSummary`, `ClaimHolder` |
| `YachtResource.cabins` | `Yacht` overlay, `Cabin` |
| `ItineraryDefaultsResource` | `ItineraryDefaults` |
| `RatesCurrentResource` / `BusinessRulesCurrentResource` / `EngineSettingsCurrentResource` | current-config document (and registry rows on business-rules) |
| `ConfigVersionDetailResource.document` (`oneOf`) | version show/store document |

### Keep (task 06 must not delete)
- **`ItineraryPair`** — PHPDoc tuple `list<array{0: string, 1: string}>`; Scramble still types `day_plan` / `facts` / `faqs` as `string[]`.
- **Closed enum unions** Scramble still emits as `string`: `CabinCategory`, `CabinState`, `ClaimKind`, `HoldType`, `EngineLabelCode`, `EngineLabelTone` (and any `status` / `reason` that is not the PHP enum schema).
- **Overlays that only narrow** a generated resource (`Itinerary` status / pairs / completeness, `hero_image_url: string | null`).
- **`ConfigVersionDetailResource.document` is the `oneOf` union**, not an open object — do not delete a hand-written config document type *only* because this field used to be untyped; retire it only if the generated `oneOf` is usable.

### Wire format (unchanged)
`CalendarGridResource`, `GenerateSeasonResource` and `DepartureMutationResource` set `$wrap = null`. Feature tests still assert today's top-level keys: calendar `departures` / `rows` (no `data`), generate-season `created` / `skipped`, create/update `warnings` next to the departure fields.

### Proposed G10 wording (not applied)
The on-disk G10 only names `claim` and `UpdateDeparture`. If you want the implemented rule on the page:

> **The departure row is the lock.** `ClaimService::claim`, `convert`, `UpdateDeparture` and `DeleteDeparture` lock the departure's own row (primary key, `FOR UPDATE`) first — `lockMany` in ascending id order when more than one departure is involved — then read claims or insert. The departure lock must be first: an insert into `cabin_claims` takes a shared lock on the parent and an S→X upgrade deadlocks. The date/yacht lock and new claims can never interleave. MySQL 1451 on delete becomes the history 409. This is a record lock, not a gap lock.

### Files touched
- `app/Support/Inventory/DepartureLocks.php` (`lock`, `lockMany`)
- `app/Services/Inventory/ClaimService.php`
- `app/Actions/Departures/UpdateDeparture.php`, `DeleteDeparture.php`
- `app/Actions/Blocks/CreateInternalBlock.php` (`@throws`)
- `app/Http/Controllers/Rms/InternalBlockController.php` (`@throws`)
- `app/Http/Controllers/Rms/DepartureController.php`, `CalendarController.php`, `RatesController.php`
- `app/Http/Resources/Rms/DepartureResource.php`, `InternalBlockResource.php`, `YachtResource.php`, `ItineraryDefaultsResource.php`
- `app/Http/Resources/Rms/CalendarGridResource.php`, `GenerateSeasonResource.php`, `DepartureMutationResource.php` (new)
- `app/Http/Resources/Rms/ConfigCurrentResource.php`, `ConfigVersionDetailResource.php`, `RatesCurrentResource.php` (new), `BusinessRulesCurrentResource.php`, `EngineSettingsCurrentResource.php`
- `app/Support/OpenApi/CabinUnavailableExceptionToResponseExtension.php` (new)
- `config/scramble.php`
- PHPDoc on config documents, `Registry`, `Defaults`, `DepartureSnapshot`
- `tests/Concurrency/ClaimServiceConcurrencyTest.php`
- `tests/Feature/OpenApi/PanelResponseSchemasTest.php` (new)
- `tests/Feature/Inventory/AvailabilityEndpointsTest.php`, `DepartureEndpointsTest.php`, `GenerateSeasonTest.php` (top-level keys)
- `docs/sprints/sprint-04/REPORT.md`

### Deviations
- `meta.kpis` is typed with `#[DocumentedResponse]` on `index`. Scramble's `additional()` inference left it as `string`.
- Kind-specific current resources instead of one typed `ConfigCurrentResource.document` — Larastan rejects a child `toArray` whose `document` is narrower than the parent.
- `DeleteDeparture` catches `\Throwable` and then checks `QueryException` 1451 — Larastan `catch.neverThrown` on a bare `QueryException`.
- `YachtResource` no longer `instanceof Cabin` (always true for the relation).

### Open questions
None.

### Notes for later
- Task 03+ endpoints that reach `claim()`: add `@throws CabinUnavailableException` on the controller method (same as block create).
- Task 06: regenerate types; delete superseded `inventory.ts` rows using the map above; keep the "Keep" list.
- Optional: replace on-disk G10 with the wording above.
- Concurrency fixtures use `2028-04-02` / `2028-04-09` so they do not collide with the demo 2027 season.

### Quality
anakata-api: `composer check` inside Docker — 380 tests (2667 assertions; all five G10 races observed 1205), Pint, Larastan OK.

### Git commands for the user

Do **not** run these in the agent.

`docs/requirements/08-dev-decisions.md` (section G copy) is in the working tree from the before-task-01 step. This task did not edit it; add it in its own commit if it is not committed yet.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Support/Inventory/DepartureLocks.php \
  app/Support/Inventory/DepartureSnapshot.php \
  app/Services/Inventory/ClaimService.php \
  app/Actions/Departures/UpdateDeparture.php \
  app/Actions/Departures/DeleteDeparture.php \
  app/Actions/Blocks/CreateInternalBlock.php \
  app/Http/Controllers/Rms/InternalBlockController.php \
  app/Http/Controllers/Rms/DepartureController.php \
  app/Http/Controllers/Rms/CalendarController.php \
  app/Http/Controllers/Rms/RatesController.php \
  app/Http/Resources/Rms/DepartureResource.php \
  app/Http/Resources/Rms/InternalBlockResource.php \
  app/Http/Resources/Rms/YachtResource.php \
  app/Http/Resources/Rms/ItineraryDefaultsResource.php \
  app/Http/Resources/Rms/CalendarGridResource.php \
  app/Http/Resources/Rms/GenerateSeasonResource.php \
  app/Http/Resources/Rms/DepartureMutationResource.php \
  app/Http/Resources/Rms/ConfigCurrentResource.php \
  app/Http/Resources/Rms/ConfigVersionDetailResource.php \
  app/Http/Resources/Rms/RatesCurrentResource.php \
  app/Http/Resources/Rms/BusinessRulesCurrentResource.php \
  app/Http/Resources/Rms/EngineSettingsCurrentResource.php \
  app/Support/OpenApi/CabinUnavailableExceptionToResponseExtension.php \
  app/Support/BusinessRules/Registry.php \
  app/Support/Config/Documents/BusinessRulesDocument.php \
  app/Support/Config/Documents/EngineSettingsDocument.php \
  app/Support/Itineraries/Defaults.php \
  config/scramble.php \
  tests/Concurrency/ClaimServiceConcurrencyTest.php \
  tests/Feature/OpenApi/PanelResponseSchemasTest.php \
  tests/Feature/Inventory/AvailabilityEndpointsTest.php \
  tests/Feature/Inventory/DepartureEndpointsTest.php \
  tests/Feature/Inventory/GenerateSeasonTest.php \
  docs/sprints/sprint-04/REPORT.md
git commit -m "$(cat <<'EOF'
Lock the departure row before claims and type panel responses.

G10 serialises claim and date changes on a PK lock; Scramble now
emits the shapes the panel already reads.
EOF
)"
```

## Task 02 · Business hours for holds

### What was built
G5 business-hours values are now data on the business-rules document. Fresh installs get them in **v1** via `initial()`. Databases that already had a published document get **v2** from a DML-only shape-change migration, published by System through `ConfigPublisher`. `App\Support\BusinessHours` turns a request time into a hold expiry.

`approval_reference` is `varchar(255)` / HTTP `max:255`; history `reason` is `text`. `ConfigPublisher` has no extra max. The Sprint 4 string is ~120 characters (including `–` and `≤`) and fits; it is stored as both `approval_reference` and the history reason:

`Sprint 4: holds business hours added (defaults Mon–Fri 09:00–18:00, near-term ≤ 120 days, source TEC-004 pending client)`

### Definitions (from the `BusinessHours` docblock)

A business window is [start, end) on a business day that isn't a holiday, in Galápagos time.

addBusinessHours(CarbonInterface $from, int $hours): CarbonImmutable: count only time inside business windows. A start outside a window begins at the next window's opening.

endOfNthBusinessDay(CarbonInterface $from, int $n): CarbonImmutable: the closing time of the n-th business day after the day of $from. The day of $from never counts, even if it's a business day.

holdExpiry(CarbonInterface $requestedAt, CalendarDate $departureDate, BusinessRulesDocument $rules): { expires_at, rule: NEAR_TERM|LONG_LEAD }:
- near-term when the departure is ≤ near_term_max_days days after the request's Galápagos date → addBusinessHours(requestedAt, holds.near_term_business_hours) (48)
- otherwise → endOfNthBusinessDay(requestedAt, holds.long_lead_business_days) (5)

All results are returned in UTC.

The constructor rejects empty `business_days`, a start/end that is not valid `H:i`, or `start >= end`. Both walkers stop after 366 days and throw. `fromArray()` stays lenient; the calculator does not trust it.

The 120-day rule compares **whole days between two Y-m-d values** (request converted to Galápagos first). It never compares the departure date's midnight instant to a GALT instant.

### Worked examples

GALT = `Pacific/Galapagos` (UTC−6). Window Mon–Fri [09:00, 18:00).

| Case | Result (UTC) |
|---|---|
| Tue 2026-09-22 10:00 GALT + 48 business hours (Tue 8h + Wed–Fri 27h + Mon 9h + Tue 4h) | 2026-09-29 19:00 (Tue 13:00 GALT) |
| Fri 2026-09-25 17:00 GALT + 48h (weekend carry) | 2026-10-05 17:00 (Mon 11:00 GALT) |
| Sat 2026-09-26 12:00 GALT + 1h (snaps to Mon 09:00) | 2026-09-28 16:00 (Mon 10:00 GALT) |
| Wed 2026-09-23 holiday; Tue 10:00 GALT + 48h | 2026-09-30 19:00 (Wed 13:00 GALT) |
| Long-lead from Wed 2026-09-23 (Thu, Fri, Mon, Tue, Wed) | 2026-10-01 00:00 (Wed 18:00 GALT) |
| Wed 2026-09-23 23:30 GALT (Thu 05:30 UTC) long-lead — Galápagos day is still Wednesday | 2026-10-01 00:00 |
| Saturday request, `endOfNthBusinessDay(1)` — Monday is day 1 | 2026-09-29 00:00 (Mon 18:00 GALT) |
| Mon 09:00 GALT + 9h | 2026-09-22 00:00 (Mon 18:00 GALT, same day) |
| Mon 18:00 GALT + 1h (snaps to next opening) | 2026-09-22 16:00 (Tue 10:00 GALT) |
| Request Galápagos date 2026-09-22, departure 2027-01-20 (120 calendar days) | `NEAR_TERM` |
| Same request, departure 2027-01-21 (121 days) | `LONG_LEAD` |
| Request 2026-09-22 23:30 GALT (05:30 next day UTC), same 120 / 121 departures | still that Galápagos date |

### Registry count

**50** (was 45). `here` 25, `other_pages` 15, `locked` 10, `differs_or_flagged` 11. Five new PENDING CLIENT rows in Holds & service levels: business days, start, end, holidays, near-term window. Source display: `Not defined in v5 — default`.

### Files touched
- `app/Services/Config/ConfigPublisher.php` (`?User $actor`, `system: true` when null)
- `app/Support/Config/Documents/HoldsRules.php`, `BusinessRulesDocument.php`, `BusinessRulesConstraint.php`
- `app/Support/BusinessRules/Registry.php`
- `app/Support/BusinessHours.php`, `app/Support/HoldExpiry.php` (new)
- `app/Http/Resources/Rms/BusinessRulesCurrentResource.php`, `ConfigVersionDetailResource.php`
- `database/migrations/2026_09_20_200019_add_holds_business_hours_to_business_rules.php` (new; DML only)
- `tests/Unit/Support/BusinessHoursTest.php` (new)
- `tests/Feature/Config/AddHoldsBusinessHoursMigrationTest.php` (new)
- `tests/Feature/Config/ConfigPublisherTest.php`, `BusinessRulesDocumentTest.php`, `BusinessRulesEndpointsTest.php`, `BusinessRulesSeederTest.php`
- `docs/sprints/sprint-04/REPORT.md`

### Deviations
- `holds.holidays` is validated as `present` (not `required`). Laravel's `required` treats `[]` as empty, so the default empty list would fail `anakata:config-verify` and the seeder.
- `holdExpiry` takes `CarbonImmutable $departureDate` (Y-m-d, same as the `CalendarDate` cast's `get()`). `CalendarDate` is a cast, not a type.
- Result is `HoldExpiry` (`expiresAt`, `rule` `NEAR_TERM`/`LONG_LEAD`), not an invented booking enum.

### Open questions
The three client questions from the sprint README are still open (G5 / PENDING CLIENT):
1. Which days and hours are business hours?
2. Which public holidays?
3. From how many days before departure is a request near-term (default 120)?

Shape migrations publish through `ConfigPublisher`, which validates against current `rules()`. A DB that is behind this and a later shape migration will fail here, because the merged document lacks the later required path. This affects only stale dev/e2e DBs today (`migrate:fresh --seed` fixes it). The policy is to be decided before go-live: fold shape migrations into `initial()` while no production exists, or keep replay-in-order only.

### Notes for later
- Task 05 wires `holdExpiry` into request create.
- Task 06 OpenAPI types pick up the new `holds.*` fields from the resource PHPDoc.
- Task 11: add the five paths to `tests/e2e/fixtures/reference-values.md`.

### Quality
anakata-api: `composer check` inside Docker — 401 tests (2750 assertions), Pint, Larastan OK.

### Git commands for the user

Do **not** run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Services/Config/ConfigPublisher.php \
  app/Support/Config/Documents/HoldsRules.php \
  app/Support/Config/Documents/BusinessRulesDocument.php \
  app/Support/Config/Documents/BusinessRulesConstraint.php \
  app/Support/BusinessRules/Registry.php \
  app/Support/BusinessHours.php \
  app/Support/HoldExpiry.php \
  app/Http/Resources/Rms/BusinessRulesCurrentResource.php \
  app/Http/Resources/Rms/ConfigVersionDetailResource.php \
  database/migrations/2026_09_20_200019_add_holds_business_hours_to_business_rules.php \
  tests/Unit/Support/BusinessHoursTest.php \
  tests/Feature/Config/AddHoldsBusinessHoursMigrationTest.php \
  tests/Feature/Config/ConfigPublisherTest.php \
  tests/Feature/Config/BusinessRulesDocumentTest.php \
  tests/Feature/Config/BusinessRulesEndpointsTest.php \
  tests/Feature/Config/BusinessRulesSeederTest.php \
  docs/sprints/sprint-04/REPORT.md
git commit -m "$(cat <<'EOF'
Add business hours to the rules document and hold calculator.

TEC-004 windows are now data (G5); expiry is counted in Galápagos
business time instead of wall-clock hours.
EOF
)"
```
