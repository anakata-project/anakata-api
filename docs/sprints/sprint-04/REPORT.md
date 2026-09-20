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
