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

## Task 03 · Contacts, groups, bookings, pricing at sale

### What was built
The RMS can quote and create reservations: one cabin, several cabins as a group, or a whole-yacht charter. Each booking is priced by `CabinPricer` from the published rates, frozen at sale, and occupies its cabins through `ClaimService` in one transaction. The unique active-claim index is still the last word on availability.

`POST /api/rms/bookings/quote` writes nothing. `POST /api/rms/bookings` (`CreateReservation`) locks first, re-quotes, resolves the contact, draws references, creates the booking(s), then claims. A conflict is the Sprint 3 `CabinUnavailableException` 409; the transaction rolls back bookings, claims and sequence draws.

Reads: `GET /api/rms/bookings` (filters, own-records, paginated), show, history, `GET /api/rms/groups?departure_id=`, `GET /api/rms/contacts?q=` (top 10). Policy: view needs `bookings.view_all` or ownership; `can_act` is the own-records rule.

Morph aliases: `contact`, `group`, `booking`. Soft-delete column on `bookings` only (G8); no delete action this task.

### Lock order
`CreateReservation` (and the demo seeder) lock **before** any quote write or insert. Quote itself takes no lock.

| Step | What | How |
|---|---|---|
| 1 | departure(s) | `DepartureLocks::lock` / `lockMany` (PK `FOR UPDATE`) |
| 2 | contact row | `ResolveContact`: `INSERT … ON DUPLICATE KEY UPDATE id = id` (X on the unique email, or a new row). No email → ordinary insert. Empty-field fill is `UPDATE` by primary key only |
| 3 | GRP counter | `ReferenceService::next(Group)` only when a new group is created |
| 4 | ANK counter | `ReferenceService::next(Booking)` per booking, then `claim()` |

Create then calls `ClaimService::claim`, which takes the departure PK lock again (already held in this transaction).

**S→X.** An insert into `cabin_claims` takes a shared lock on the parent `departures` row. Taking that insert first and then `FOR UPDATE` on the same row upgrades S→X and deadlocks (1213). The departure lock stays first. Contact upsert never does find-then-insert: a `SELECT` that takes S on the unique email, then an insert that needs X, is the same upgrade. `INSERT … ON DUPLICATE KEY UPDATE id = id` creates the row or takes X on the existing one directly.

### ResolveContact (G1)
Email is normalised in the Action (`Contact::normalizeEmail`) before the query builder — the builder bypasses the model mutator.

With an email: (a) insert-or-no-op on the unique email; (b) `SELECT` by that normalised email; (c) if the row already existed, `UPDATE` by primary key only the fields that are empty and have a non-empty incoming value, and record `contact.updated` with exactly those fields' before/after. A new row records `contact.created`. No email → always create + `contact.created`.

**Created detection.** Laravel does not set PDO `CLIENT_FOUND_ROWS` / `MYSQL_ATTR_FOUND_ROWS` on the mysql connection (`config('database.connections.mysql.options')`). A no-op `id = id` duplicate therefore returns **0** affected rows, not 2. Created = affected === 1; existing = anything else. Asserted in `ContactResolutionTest`.

### Groups (G2)
Prototype `saveNew` / `nbAddCab`: a new `GRP` is created only when there are **more than one** cabin, or the actor joins a **visible** `existing_group_id` on the same departure (`Group::visibleTo` — same own-records rule as `GET /groups`). A single-cabin `{ name }` is ignored. A charter has no group (one booking, nine claims). Existing group on another departure, or a group the actor cannot see → 422.

### Channel enums
Values are the prototype `#newmodal` strings (en-dashes, not hyphens).

**`MainChannel` (8), labels = values:**

`D2C` · `B2B` · `B2B – Travel Advisor` · `B2B – Tour Operator` · `B2B – Corporate` · `Wholesale / Distribution` · `Partners` · `Other`

Segment (`Booking::segment` / prototype `seg`): charter by type; else B2B when the main channel starts with `B2B`, `Wholesale` or is `Partners`.

**`ChannelOfOrigin` (40)** with `group()`:

| Group (`ChannelOfOriginGroup`) | Values |
|---|---|
| Direct | Hotel Website Inquiry, Hotel Booking Engine, Phone, Email, WhatsApp |
| Marketing | Hotel Social, Organic Search, Paid Search, Paid Ads, AI / LLM, Email Marketing, Referral |
| Trade, corporate & groups | Travel Advisor, Luxury Agency, Host Agency, Consortia, Tour Operator, Luxury Tour Operator, DMC, Incoming Operator, Wholesaler, Corporate Direct, Corporate Travel Agency, Business Travel, MICE, Group |
| Distribution, partners & other | GDS, CRS, Switch, Hotel Partner, Airline, Credit Card, Membership Club, Affiliate, Influencer, Brand Partnership, Complimentary, Owner, Staff, Unknown |

### Seed map
`ChannelSeedMap::fromPrototype` (asserted in `DemoBookingsSeederTest`):

| Seed `chan` | `main_channel` | `channel_of_origin` |
|---|---|---|
| `WEB_DIRECT` | D2C | Hotel Booking Engine |
| `INBOUND` | D2C | Email |
| `AGENCY` | B2B – Travel Advisor | Travel Advisor |
| `CHARTER_DIRECT` | D2C | Email |

Eleven seed bookings (the two `REQUESTED` skipped for task 05). Owner by first name. ANAMARA departure on the seed date index (`dep`). `OVERDUE` → `CONFIRMED` (the flag is Sprint 5 / G6). GRP-007 with its coordinator. Claims through `ClaimService` (10 cabin + 9 charter). `ensureAtLeast` Group 7 / Booking 19 @ 2026 so the next draws are `GRP-008` and `ANK-2026-0020`. Idempotent.

### Seed-total vs calculator
`DemoBookingsSeeder::$priceDifferences` records `{ reference, seed_total, calculator_total }` whenever they differ. After a fresh seed the list is **empty** — all 11 stored totals match `CabinPricer` (including festive charter `ANK-2026-0012` at 211,500). The seed data and the rules agree on these fixtures.

### `balance` placeholder (G6)
`Booking::balance()` returns `total`. One method; Sprint 5 changes this one place. `deposit_amount` uses `Rounding::halfUp` (same as `Quote`). `balance_due_date` is the departure date minus frozen `balance_days`. `allowed_transitions` is `[]` until task 04. `Booking::holdExpired()` is `false` until task 05.

### `holdsInventory` (G9)
`BookingStatus::holdsInventory()` is false only for `RELEASED`, `CANCELLED`, `CANCELLED_POSTPAID`. Create writes `PENDING_PAYMENT` + `BOOKING` claims. The seeder claims only when the status holds inventory. Task 04 reuses this for transitions.

### Concurrency
`tests/Concurrency/CreateReservationConcurrencyTest.php`, session `innodb_lock_wait_timeout = 1`.

| Race | Observed |
|---|---|
| Two creates on the same departure, different cabins, first holds the departure lock uncommitted | waiter **1205**, never 1213 |
| Two creates on the same cabin after the first commits | second **409**, one booking / one claim |

### `@throws CabinUnavailableException`
`BookingController::store` and `CreateReservation::handle` declare it (task 01 rule). Scramble attaches the named 409 to `POST /rms/bookings`.

### Responses typed (task 06)
`BookingResource`, `ReservationQuoteResource`, `ReservationCreatedResource`, `GroupResource`, `ContactResource`. Claim `holder.detail` is a PHPDoc union: block `{ reason, reason_label }` **or** booking `{ status, type, segment, display_reference, owner_id, owner_name, party_label, hold_expired }`. Availability `morphWith`s `Booking::owner`.

### Files touched
- `app/Enums/PreferredChannel.php`, `BookingType.php`, `BookingSegment.php`, `BookingStatus.php`, `MainChannel.php`, `ChannelOfOrigin.php`, `ChannelOfOriginGroup.php` (new)
- `database/migrations/2026_09_20_200020_create_contacts_table.php`, `200021_create_groups_table.php`, `200022_create_bookings_table.php` (new)
- `app/Models/Contact.php`, `Group.php`, `Booking.php` (new)
- `database/factories/ContactFactory.php`, `GroupFactory.php`, `BookingFactory.php` (new)
- `app/Actions/Contacts/ResolveContact.php`, `app/Actions/Bookings/CreateReservation.php` (new)
- `app/Services/Pricing/ReservationQuoter.php`, `ReservationQuote.php`, `QuotedParty.php` (new)
- `app/Support/Bookings/ReservationCreated.php`, `ChannelSeedMap.php` (new)
- `app/Http/Controllers/Rms/BookingController.php`, `GroupController.php`, `ContactController.php` (new)
- `app/Http/Requests/Rms/QuoteReservationRequest.php`, `StoreReservationRequest.php`, `IndexBookingsRequest.php`, `IndexGroupsRequest.php`, `IndexContactsRequest.php` (new)
- `app/Http/Resources/Rms/BookingResource.php`, `ReservationQuoteResource.php`, `ReservationCreatedResource.php`, `GroupResource.php`, `ContactResource.php` (new)
- `app/Policies/BookingPolicy.php` (new)
- `app/Providers/AppServiceProvider.php` (morph map)
- `app/Services/Inventory/Availability.php` (booking `holder.detail`, `morphWith`)
- `app/Http/Resources/Rms/DepartureResource.php`, `CalendarGridResource.php`, `app/Support/Inventory/DepartureSnapshot.php` (detail union)
- `routes/api/rms.php`
- `database/seeders/DemoBookingsSeeder.php` (new), `DatabaseSeeder.php`
- `tests/Feature/Bookings/*`, `tests/Support/Bookings/ReservationFixtures.php`, `tests/Concurrency/CreateReservationConcurrencyTest.php` (new)
- `tests/Feature/OpenApi/PanelResponseSchemasTest.php`
- `docs/sprints/sprint-04/REPORT.md`

### Deviations
- Create locks the departure **before** the quote (task text listed resolve-contact first). Quote is still server-side and never trusted; a client-sent `total` is ignored.
- Contact upsert is insert-or-no-op, not find-then-update/insert (G10 / S→X).
- New group only when there are several cabins or a visible `existing_group_id` — not whenever `{ name }` is sent (prototype).
- `existing_group_id` uses the same visibility as `GET /groups`, not “any group on the departure”.
- Charter ignores `group`.
- Seed `OVERDUE` stored as `CONFIRMED`.
- `ReservationCreated` is an Eloquent `Collection` so `load()` works after create.

### Open questions
None.

### Notes for later
- **Task 05 — occupancy:** add `Booking::occupiesInventory(): bool` = `status->holdsInventory() && ! holdExpired()`. G9: an expired request hold frees the cabin without cancelling the request. Release/claim paths must use `occupiesInventory()`, not `holdsInventory()` alone.
- Task 04 fills `allowedTransitions()`.
- Task 05 seeds the two `REQUESTED` bookings and implements `holdExpired()` from the hold claim.
- Task 06: regenerate types; add the five new resource schemas and the booking `holder.detail` union.
- Task 11: seeded bookings table from `seed-data.json` + the channel map above; calculator totals (no seed/calculator diffs).

### Quality
anakata-api: `composer check` inside Docker — 436 tests (2931 assertions; create-reservation races observed 1205 and 409), Pint, Larastan OK.

### Git commands for the user

Do **not** run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Enums/PreferredChannel.php \
  app/Enums/BookingType.php \
  app/Enums/BookingSegment.php \
  app/Enums/BookingStatus.php \
  app/Enums/MainChannel.php \
  app/Enums/ChannelOfOrigin.php \
  app/Enums/ChannelOfOriginGroup.php \
  database/migrations/2026_09_20_200020_create_contacts_table.php \
  database/migrations/2026_09_20_200021_create_groups_table.php \
  database/migrations/2026_09_20_200022_create_bookings_table.php \
  app/Models/Contact.php \
  app/Models/Group.php \
  app/Models/Booking.php \
  database/factories/ContactFactory.php \
  database/factories/GroupFactory.php \
  database/factories/BookingFactory.php \
  app/Actions/Contacts/ResolveContact.php \
  app/Actions/Bookings/CreateReservation.php \
  app/Services/Pricing/ReservationQuoter.php \
  app/Services/Pricing/ReservationQuote.php \
  app/Services/Pricing/QuotedParty.php \
  app/Support/Bookings/ReservationCreated.php \
  app/Support/Bookings/ChannelSeedMap.php \
  app/Http/Controllers/Rms/BookingController.php \
  app/Http/Controllers/Rms/GroupController.php \
  app/Http/Controllers/Rms/ContactController.php \
  app/Http/Requests/Rms/QuoteReservationRequest.php \
  app/Http/Requests/Rms/StoreReservationRequest.php \
  app/Http/Requests/Rms/IndexBookingsRequest.php \
  app/Http/Requests/Rms/IndexGroupsRequest.php \
  app/Http/Requests/Rms/IndexContactsRequest.php \
  app/Http/Resources/Rms/BookingResource.php \
  app/Http/Resources/Rms/ReservationQuoteResource.php \
  app/Http/Resources/Rms/ReservationCreatedResource.php \
  app/Http/Resources/Rms/GroupResource.php \
  app/Http/Resources/Rms/ContactResource.php \
  app/Http/Resources/Rms/DepartureResource.php \
  app/Http/Resources/Rms/CalendarGridResource.php \
  app/Policies/BookingPolicy.php \
  app/Providers/AppServiceProvider.php \
  app/Services/Inventory/Availability.php \
  app/Support/Inventory/DepartureSnapshot.php \
  routes/api/rms.php \
  database/seeders/DemoBookingsSeeder.php \
  database/seeders/DatabaseSeeder.php \
  tests/Feature/Bookings \
  tests/Support/Bookings \
  tests/Concurrency/CreateReservationConcurrencyTest.php \
  tests/Feature/OpenApi/PanelResponseSchemasTest.php \
  docs/sprints/sprint-04/REPORT.md
git commit -m "$(cat <<'EOF'
Add contacts, groups and reservation create with frozen prices.

Quote and create lock the departure first, upsert the contact
without an S→X upgrade, and occupy cabins in one transaction.
EOF
)"
```

## Task 04 · Status transitions, date/cabin move, deletion, audit

### What was built
A booking changes status only through `App\Support\Bookings\Transitions`. Date or cabin moves re-quote at current rates; staff confirm the new total. Admins soft-delete with a reason. `GET /api/rms/bookings/audit` lists deletions and released requests.

Every mutating Action (`TransitionBooking`, `MoveBooking`, `DeleteBooking`, `UpdateBooking`) goes through `BookingMutationLock::acquire`: lock departure row(s) ascending, lock the booking PK, then if the locked `departure_id` differs from the plain-read id → 409 `"This booking changed — reload and try again."` Preview is read-only and takes no lock.

### Transition table

| From | To |
|---|---|
| `REQUESTED` | `PENDING_PAYMENT`, `CONFIRMED`, `RELEASED`, `CANCELLED` |
| `PENDING_PAYMENT` | `CONFIRMED`, `CANCELLED` |
| `CONFIRMED` | `FULLY_PAID`, `CANCELLED` |
| `FULLY_PAID` | `ON_BOARD`, `CANCELLED_POSTPAID` |
| `ON_BOARD` | `COMPLETED` |
| `COMPLETED`, `CANCELLED`, `CANCELLED_POSTPAID`, `RELEASED` | — |
| `OVERDUE`, `ON_HOLD_AGENCY`, `WAITLISTED` | — (Sprint 5 / G7; not accepted as `to`) |

`BookingStatus::allowedTransitions()` delegates to `Transitions::targets()`. `allowed_transitions` on the resource is table ∩ date guards, and is empty when the actor lacks `bookings.change_status` or fails own-records.

### Reason rules
Required (trim; blank counts as missing) for `CANCELLED`, `CANCELLED_POSTPAID`, `FULLY_PAID`, `RELEASED`. Optional otherwise.

Illegal `to` after the locks → 422 on `to` listing the **currently** legal targets (`Allowed: none.` when empty).

### Date guards (Galápagos `Y-m-d`)
- `ON_BOARD` only when today ≥ departure date
- `COMPLETED` only when today ≥ **return date**

**Return date.** `Departure::returnDate()` uses `itinerary.nights` when `> 0`, else `Departure::DEFAULT_NIGHTS = 7`. No new column.

### Claims (G9)
- `REQUESTED` → `PENDING_PAYMENT` / `CONFIRMED`: active HOLD → `convert(…, BOOKING)`; no active claim → `claim(…, BOOKING)`. Taken cabin → 409 `"The cabin was taken after this request's hold expired."`
- Confirming a request with `reference === null` draws `ANK-` and keeps `request_reference`
- → `RELEASED` / `CANCELLED` / `CANCELLED_POSTPAID` → `release()` then `// TODO(Sprint 5): penalty, refund request, and client notification (G6).` in `TransitionBooking::applyClaims`, immediately after `release`
- `RELEASED` history event is `booking.released` (`what`: `"Request released — hold returned to inventory"`). Other transitions: `booking.status_changed`. `FULLY_PAID` with `balance() > 0` appends ` (marked manually — USD … not in the payments record)`

### Move rules
1. Plain-read old `departure_id`
2. `BookingMutationLock::acquire` → `lockMany([old, new])` then booking row; stale departure → 409 reload sentence
3. **Then** group check, quote, availability, `confirm_total` compare, release, claim

- Same departure + same cabin(s) → 422 `"Nothing to move."` (preview and move)
- Target date must be **after** Galápagos today
- `REQUESTED` with no active claim → 422 `"This request's hold has expired — confirm or release it first."` Active HOLD still moves and keeps `expires_at`
- Group on another departure → 409 `"This booking belongs to {GRP-NNN} — moving a group to another departure isn't supported yet."` Cabin change on the same departure is allowed
- Charter sends `departure_id` only; nine claims move
- Stale `confirm_total` → 409 after locks
- After a move: `rates_version_id` is the **new** published rates version; `deposit_pct` and `balance_days` stay from the sale
- Festive 7 Nov → 19 Dec 2027, 2 AD, fee 0: `confirm_total` = 28,100; `festive_changes: true`; old cabin `FREE`, new cabin `SOLD`

### FIN-006
Read `CurrentConfig::businessRules()->modificationFeeUsd` (`modification_fee_usd` on the document; **path exists; seed 0**). If `> 0`, append `{ code: modification_fee, label: "Modification fee (FIN-006)", amount }` into `new_total` / `difference` on preview **and** move. If `0`, no line. Never hard-coded as “no fee”.

### Lock order
`CreateReservation` is unchanged: **departure → contact → counters** (no booking row yet).

This task’s writers: **departure(s) → booking → (contact if any) → reference counters**. Transition always locks the departure, even when claims are not touched.

| Caller | First lock | Then |
|---|---|---|
| `BookingMutationLock::acquire` | `DepartureLocks::lockMany` (unique, ascending) | `Booking` PK `lockForUpdate`; stale `departure_id` → 409 |
| `TransitionBooking` / `DeleteBooking` / `UpdateBooking` | booking’s departure | booking row |
| `MoveBooking` | old + new departures | booking row, then quote / `confirm_total` / claims |
| `MoveBooking::preview` | none | — |

### Concurrency
`tests/Concurrency/BookingMutationConcurrencyTest.php`, session `innodb_lock_wait_timeout = 1`.

A runs the real `TransitionBooking` inside an outer `beginTransaction()` (`Action::transaction()` is a savepoint, so the departure + booking locks stay held). B’s `DeleteBooking` on the same booking waits **1205**, never **1213**. After A rolls back the booking is still there (B never wrote). A’s locks are not simulated by hand.

### Audit
`GET /api/rms/bookings/audit` (`bookings.view_all`). Events `booking.deleted` and `booking.released`, newest first. `from` / `to` are Galápagos calendar days converted to UTC instants (`startOfDay` / `endOfDay` in `Pacific/Galapagos`, then UTC). Soft-deleted rows 404 on show / history / transition / move / patch; audit still lists them.

### PATCH
Notes change → `booking.updated`. Owner change → `booking.owner_changed` (active RMS user). Both in one request → two history rows. No-op → no row. Still takes the locks.

### DELETE
204. Manager 403 (`bookings.delete` is Admin). Claims released. Reason required.

### Endpoints

| Method | Path | Auth |
|---|---|---|
| `GET` | `/api/rms/bookings/audit` | `viewAudit` |
| `POST` | `/api/rms/bookings/{booking}/transition` | `changeStatus` |
| `POST` | `/api/rms/bookings/{booking}/move/preview` | `move` |
| `POST` | `/api/rms/bookings/{booking}/move` | `move` |
| `PATCH` | `/api/rms/bookings/{booking}` | `update` / `reassign` |
| `DELETE` | `/api/rms/bookings/{booking}` | `delete` |

`audit` is registered before `{booking}`. `@throws CabinUnavailableException` on `transition` and `move`.

### Files touched
- `app/Support/Bookings/Transitions.php`, `BookingMutationLock.php` (new)
- `app/Actions/Bookings/TransitionBooking.php`, `MoveBooking.php`, `DeleteBooking.php`, `UpdateBooking.php` (new)
- `app/Http/Requests/Rms/TransitionBookingRequest.php`, `PreviewMoveBookingRequest.php`, `MoveBookingRequest.php`, `DeleteBookingRequest.php`, `UpdateBookingRequest.php`, `IndexBookingAuditRequest.php` (new)
- `app/Http/Resources/Rms/MovePreviewResource.php`, `BookingAuditResource.php` (new)
- `app/Enums/BookingStatus.php` (`allowedTransitions` delegates)
- `app/Models/Departure.php` (`DEFAULT_NIGHTS`, `returnDate`)
- `app/Policies/BookingPolicy.php`, `app/Http/Controllers/Rms/BookingController.php`, `app/Http/Resources/Rms/BookingResource.php`, `routes/api/rms.php`
- `tests/Unit/Support/Bookings/TransitionsTest.php`
- `tests/Feature/Bookings/TransitionBookingTest.php`, `MoveBookingTest.php`, `DeleteBookingTest.php`, `UpdateBookingTest.php`, `BookingAuditTest.php`
- `tests/Feature/Bookings/BookingReadTest.php` (`allowed_transitions` no longer `[]`)
- `tests/Concurrency/BookingMutationConcurrencyTest.php`
- `tests/Feature/OpenApi/PanelResponseSchemasTest.php`
- `tests/Support/Bookings/ReservationFixtures.php` (find-or-create by yacht + date)
- `docs/sprints/sprint-04/REPORT.md`

### Deviations
- Scramble does not expand `list<array{to: string, reason_required: bool}>` into item properties (`items` serialises as `[]`). PHPDoc on `BookingResource` still documents the shape; the OpenAPI test asserts the field exists. Same class of issue as task 01 `ItineraryPair`.
- `ReservationFixtures::anamaraDeparture` finds the existing yacht+date row (and can set `festive`) instead of always inserting — the unique index rejected a second 7 Nov / 19 Dec ANAMARA departure.

### Open questions
With `FIN-006` (`modification_fee_usd`) **> 0**, each move re-quotes `price_lines` from current rates and then appends **one** fee line. A second move therefore drops the first move’s fee line and writes a new quote + a new fee. Whether a charged fee should stick across later moves is a client question for when the fee is non-zero (seed is 0).

### Notes for later
- **Sprint 5 / payments:** a move that raises the price of a `FULLY_PAID` booking leaves an amount owed; payments must handle it. `Booking::balance()` is still `total` until then.
- **Sprint 5 / G6:** penalty, refund request, and client notification after cancel/release — the TODO is in `TransitionBooking::applyClaims` immediately after `release`.
- **Sprint 5 / G7:** `OVERDUE`, `ON_HOLD_AGENCY`, `WAITLISTED` stay on the enum with empty targets.
- **Task 05 (done):** `holdExpired()` reads `booking_requests.hold_expired_at`. Moving an expired request still 422s (“confirm or release it first”).
- Task 06: regenerate types; `MovePreviewResource`, `BookingAuditResource`, and `allowed_transitions` (`to` + `reason_required`) — overlay the item shape if Scramble still emits `[]`.

### Quality
anakata-api: `composer check` inside Docker — 477 tests (3172 assertions; held transition vs delete observed 1205), Pint, Larastan OK.

### Git commands for the user

Do **not** run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Support/Bookings/Transitions.php \
  app/Support/Bookings/BookingMutationLock.php \
  app/Actions/Bookings/TransitionBooking.php \
  app/Actions/Bookings/MoveBooking.php \
  app/Actions/Bookings/DeleteBooking.php \
  app/Actions/Bookings/UpdateBooking.php \
  app/Http/Requests/Rms/TransitionBookingRequest.php \
  app/Http/Requests/Rms/PreviewMoveBookingRequest.php \
  app/Http/Requests/Rms/MoveBookingRequest.php \
  app/Http/Requests/Rms/DeleteBookingRequest.php \
  app/Http/Requests/Rms/UpdateBookingRequest.php \
  app/Http/Requests/Rms/IndexBookingAuditRequest.php \
  app/Http/Resources/Rms/MovePreviewResource.php \
  app/Http/Resources/Rms/BookingAuditResource.php \
  app/Enums/BookingStatus.php \
  app/Models/Departure.php \
  app/Policies/BookingPolicy.php \
  app/Http/Controllers/Rms/BookingController.php \
  app/Http/Resources/Rms/BookingResource.php \
  routes/api/rms.php \
  tests/Unit/Support/Bookings/TransitionsTest.php \
  tests/Feature/Bookings/TransitionBookingTest.php \
  tests/Feature/Bookings/MoveBookingTest.php \
  tests/Feature/Bookings/DeleteBookingTest.php \
  tests/Feature/Bookings/UpdateBookingTest.php \
  tests/Feature/Bookings/BookingAuditTest.php \
  tests/Feature/Bookings/BookingReadTest.php \
  tests/Concurrency/BookingMutationConcurrencyTest.php \
  tests/Feature/OpenApi/PanelResponseSchemasTest.php \
  tests/Support/Bookings/ReservationFixtures.php \
  docs/sprints/sprint-04/REPORT.md
git commit -m "$(cat <<'EOF'
Add booking status transitions, date/cabin moves, delete and audit.

Mutations lock departure then booking and reject a stale departure
with 409; moves re-quote at current rates after the locks.
EOF
)"
```

## Task 05 · Requests and their holds, the waitlist

### What was built
A request is a booking in `REQUESTED` with a `booking_requests` row (preferred channel, advisor flag, notes, SLA, hold rule). `CreateBookingRequest` (no RMS create endpoint) re-quotes, draws `ANK-R-YYYY-NNNN`, claims a `HOLD` (`HoldType::Request`) with `BusinessHours::holdExpiry`, and writes `booking.requested`.

An expired HOLD frees the cabin and never cancels the request (G9). `ClaimService::releaseExpiredByIds` dispatches `HoldExpired` **in the same transaction**. `MarkRequestHoldExpired` sets `hold_expired_at` and writes `request.hold_expired` as System. Status stays `REQUESTED`. `TODO(Sprint 7): notify the owner`.

**A4 exception:** this event is synchronous and in-transaction (not queued, not `ShouldDispatchAfterCommit`) so the request row cannot disagree with the claim. Documented in `laravel.mdc` next to History.

**Expiry vs confirm race:** `releaseExpired` takes no departure lock. `convert()` now returns the number of claims converted. If that is fewer than the booking must hold (1 cabin, or 9 for a charter), `TransitionBooking` falls through to `claim(..., BOOKING)` — 409 if the cabin was taken. Never a claimless `PENDING_PAYMENT`. Test seam: `ClaimService::$beforeConvert` (always null in production; reset in Pest `afterEach`).

Confirm / release are `POST /api/rms/requests/{booking}/confirm|release` through `TransitionBooking`, with `requests.confirm` / `requests.release` and own-records. Confirm history: `Status REQUESTED → PENDING PAYMENT · deposit link to be sent via WHATSAPP` + `TODO(Sprint 5): send the deposit link`.

Waitlist is its own table (G7). Position is **computed on read** (FIFO by `created_at` then `id` among active rows of that departure + category). No stored column. Add lock order is **departure → contact** (`DepartureLocks::lock`, then `waitlist_enabled`, then `ResolveContact`, then insert) so it cannot 1213 against `CreateReservation`. Notify is “Mark notified” — no email (deviation from the prototype’s “Notify now”).

### Demo
`DemoRequestsSeeder` (local/testing): skip if that `request_reference` exists; otherwise `ensureAtLeast(Request, 40, 2026)` then 0041 then 0042. References **pinned to 2026** via `$referenceAt`. Each request’s `Carbon::setTestNow` is `try/finally` and restored after that request. 0041 submitted 5 h ago (SLA ~19 h left); 0042 submitted 50 h ago (breached ~26 h). Hold expiries from `BusinessHours`. Waitlist: Anna Whitfield (Suite) and K. Osei (Owner) on festive ANAMARA 19 Dec 2027.

Valid until **Nov 2027** (near-term cutoff + `ClaimService` refusing past departures). Noted on the seeder, Sprint 3 report, and `tests/e2e/fixtures/reference-values.md`.

### Deviations
- Confirm wording says “to be sent” (payments are Sprint 5), not the prototype’s “sent”.
- Waitlist button is “Mark notified”; no email.
- Request references are pinned to 2026 rather than rolling on 1 Jan 2027.

### Open questions
None.

### Notes for later
- Task 06: regenerate types for `BookingRequestResource`, `HoldResource`, `WaitlistEntryResource`, `meta.rules`.
- Task 09: hold remaining formatter (`remaining_business_minutes` / `business_day_minutes`); SLA from signed minutes; expire via `inventory:expire-hold ANK-R-2026-0041`.
- Task 11: `BKG-*` request / hold / waitlist scenarios.
- Engine sprint: public create-request endpoint calling `CreateBookingRequest`.

### Quality
anakata-api: `composer check` inside Docker — 497 tests (3311 assertions), Pint, Larastan OK.

### Git commands for the user

Do **not** run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Enums/HoldRule.php \
  app/Models/BookingRequest.php \
  app/Models/WaitlistEntry.php \
  app/Models/Booking.php \
  app/Providers/AppServiceProvider.php \
  app/Events/HoldExpired.php \
  app/Listeners/MarkRequestHoldExpired.php \
  app/Services/Inventory/ClaimService.php \
  app/Services/Inventory/Availability.php \
  app/Support/BusinessHours.php \
  app/Support/Bookings/RequestParty.php \
  app/Support/Bookings/RequestQueueRules.php \
  app/Support/Bookings/HoldRuleText.php \
  app/Actions/Bookings/CreateBookingRequest.php \
  app/Actions/Bookings/TransitionBooking.php \
  app/Actions/Waitlist/AddWaitlistEntry.php \
  app/Actions/Waitlist/NotifyWaitlistEntry.php \
  app/Actions/Waitlist/RemoveWaitlistEntry.php \
  app/Http/Controllers/Rms/RequestController.php \
  app/Http/Controllers/Rms/HoldController.php \
  app/Http/Controllers/Rms/WaitlistController.php \
  app/Http/Requests/Rms/IndexRequestsRequest.php \
  app/Http/Requests/Rms/ReleaseRequestRequest.php \
  app/Http/Requests/Rms/IndexWaitlistRequest.php \
  app/Http/Requests/Rms/StoreWaitlistEntryRequest.php \
  app/Http/Requests/Rms/NotifyWaitlistEntryRequest.php \
  app/Http/Requests/Rms/RemoveWaitlistEntryRequest.php \
  app/Http/Resources/Rms/BookingRequestResource.php \
  app/Http/Resources/Rms/HoldResource.php \
  app/Http/Resources/Rms/WaitlistEntryResource.php \
  app/Policies/BookingPolicy.php \
  app/Policies/WaitlistEntryPolicy.php \
  app/Console/Commands/ExpireHoldCommand.php \
  database/migrations/2026_09_20_200023_create_booking_requests_table.php \
  database/migrations/2026_09_20_200024_create_waitlist_entries_table.php \
  database/factories/BookingRequestFactory.php \
  database/factories/WaitlistEntryFactory.php \
  database/seeders/DemoRequestsSeeder.php \
  database/seeders/DatabaseSeeder.php \
  routes/api/rms.php \
  .cursor/rules/laravel.mdc \
  tests/Pest.php \
  tests/Unit/Support/BusinessHoursTest.php \
  tests/Feature/Bookings/CreateBookingRequestTest.php \
  tests/Feature/Bookings/RequestHoldExpiryTest.php \
  tests/Feature/Bookings/RequestQueueTest.php \
  tests/Feature/Bookings/WaitlistTest.php \
  tests/Feature/Bookings/ExpireHoldCommandTest.php \
  tests/Feature/Bookings/DemoRequestsSeederTest.php \
  tests/Feature/Bookings/RequestCalendarQueryCountTest.php \
  tests/Feature/Inventory/CabinClaimsTest.php \
  tests/Feature/OpenApi/PanelResponseSchemasTest.php \
  tests/Support/Bookings/ReservationFixtures.php \
  tests/e2e/fixtures/reference-values.md \
  docs/sprints/sprint-03/REPORT.md \
  docs/sprints/sprint-04/REPORT.md
git commit -m "$(cat <<'EOF'
Add booking requests, expiring holds, and the waitlist.

Expired holds free the cabin without cancelling the request;
confirm falls through to a fresh claim if convert races expiry.
EOF
)"
```

## Task 06 · anakata-ui · Regenerate types, retire hand-written ones, release v0.5.0

### What was built
PHPDoc-only prelude on the API so Scramble emits the Sprint 4 shapes, then `pnpm types:api` against `http://localhost:8000/docs/api.json`. Layer `0.4.2` → `0.5.0`. Types only: no composables, components, or behaviour.

Calendar dates (`departure.date`, `return_date`, `balance_due_date`, calendar keys, generate-season `from` / `to`) stay `string` (`YYYY-MM-DD`). Instants stay ISO strings.

### API prelude (PHPDoc only)
Tried first, then regenerated.

| Target | What worked |
|---|---|
| `POST /rms/bookings` 201 `bookings` | `#[DocumentedResponse]` on `BookingController::store` (`list<BookingResource>`). Named `ReservationCreatedResource` PHPDoc is the booking-show shape; Scramble still emits that schema’s `bookings` as `string[]` (`list<BookingResource>` collapsed). The layer alias uses `operations['booking.store']` 201. |
| `GET /rms/requests` `meta.rules` | `#[DocumentedResponse]` on `RequestController::index` (six keys, same pattern as departures `meta.kpis`). |
| `DepartureMutationResource` holder `detail` | PHPDoc union: block `{ reason, reason_label }` **or** booking `{ status, type, segment, display_reference, owner_id, owner_name, party_label, hold_expired }`. |
| Booking enum schemas | Already named from FormRequests (`BookingStatus`, `BookingType`, `BookingSegment`, `MainChannel`, `ChannelOfOrigin`). Resource **fields** stay `string` — FQCN / short enum `@return` failed Larastan (`return.type` vs `->value` strings) and Scramble still emitted `string`. Reverted. |
| `allowed_transitions` items | PHPDoc stays `list<array{to: string, reason_required: bool}>`. Scramble still serialises `items` as `[]` (task 04 leftover). Overlay in the layer; the OpenAPI test only asserts the field exists unless items grow properties. |

`PanelResponseSchemasTest` now also checks created-bookings `$ref` / show keys, request `meta.rules`, and the five enum schemas.

### Line counts

| File | Before | After |
|---|---|---|
| `app/types/inventory.ts` | 339 | 247 |
| `app/types/config.ts` | 363 | 368 |
| `app/types/bookings.ts` | — | 171 |

`config.ts` grew: G5 `holds.*` fields added; published documents stay hand-written (`{[key: string]: unknown}` / unusable `oneOf`).

### Retired (now generated or a thin alias)

| Alias | Now |
|---|---|
| `CabinCategory` | `components['schemas']['CabinCategory']` |
| `GenerateSeasonResult` | `GenerateSeasonResource` |
| `DepartureKpis` | `operations['departure.index']` `meta.kpis` |
| `AvailabilityCounts` | `DepartureResource.availability.counts` |
| `DepartureLocks` | `DepartureResource.locks` |
| `ItineraryCompleteness` | `ItineraryResource.completeness` |
| `CalendarGrid` / `CalendarDeparture` / `CalendarRow` | `CalendarGridResource` + overlays for `status` / `Cabin` / `CabinState` |
| `CabinUnavailableError` / `CabinUnavailableItem` | generated `CabinUnavailableException` 409 + `held_by.kind` overlay |

`Departure`, `InternalBlock`, `Availability`, `ClaimSummary` stay overlays because they nest leftover unions.

### Kept (task 01 list + leftover overlays)

Each leftover has a `Mirrors App\…` comment.

- **`ItineraryPair`** — PHPDoc tuple; Scramble still types `day_plan` / `facts` / `faqs` as `string[]`.
- **Closed unions still `string`:** `CabinState`, `ClaimKind`, `HoldType`, `EngineLabelCode`, `EngineLabelTone`.
- **`ItineraryDefaults`** — generated schema freezes seed literals and types `day_plan` as `string[]`.
- **`Cabin`** — generated `id` / `sort` are `string`.
- **`Itinerary`** — `hero_image_url: string \| null`; pairs via `ItineraryPair`.
- **`ClaimHolderDetail`** — generated `oneOf` is usable; enums on each arm stay `string`.
- Config documents (`RatesDocument`, `BusinessRulesDocument`, `EngineSettingsDocument` and nested `*Rules`) — still untyped objects.

### Booking aliases (`app/types/bookings.ts`)

| Alias | Source |
|---|---|
| `BookingStatus` / `BookingType` / `BookingSegment` | named enum schemas |
| `MainChannel` / `ChannelOfOrigin` / `PreferredChannel` | named enum schemas |
| `Booking` / `BookingListItem` | `BookingResource` + overlays (`can_act: boolean`, enum fields, `allowed_transitions`, `price_lines`, `contact`, `group`) |
| `AllowedTransition` | hand-written (`to` + `reason_required`) — items serialise as `[]` |
| `PriceLine` | hand-written `{ code, label, amount }` |
| `BookingQuoteRequest` | `QuoteReservationRequest` |
| `CreateReservationRequest` | `StoreReservationRequest` |
| `BookingQuote` | hand-written — generated cabins is `unknown[]`; totals freeze as `0 \| null` |
| `CreateReservationResponse` | `operations['booking.store']` 201 + `bookings: Array<Booking>` |
| `MovePreview` | `MovePreviewResource` |
| `BookingAuditRow` | `BookingAuditResource` — generated `client` / `what` are `unknown` |
| `Group` | `GroupResource` + `statuses: Array<BookingStatus>` |
| `GroupSummary` | `{ id, reference, name, coordinator }` |
| `Contact` / `ContactSearchResult` | `ContactResource` |
| `RequestQueueItem` | `BookingRequestResource` — generated `can_act` is `string`; `party` freezes a seed literal |
| `RequestQueueRules` | `operations['request.index']` `meta.rules` |
| `HoldListItem` | hand-written — generated `departure` and `remaining_business_minutes` are `string` |
| `WaitlistEntry` | `WaitlistEntryResource` + `cabin_category: CabinCategory` |
| Claim holder `detail` | `ClaimHolderDetail` in `inventory.ts` (block \| booking \| `null`) |

**No `ChannelOfOriginGroup` const map** in the layer. The API should send the grouped channel list; that is task 08 input.

### Panel
One type-only commit: README layer row `` `extends: ['../anakata-ui']` (`v0.5.0`) ``, and `calendarHelpers` narrows blocked-cell `detail`.

`holder.type` is an untyped morph alias (`string`) in the spec, so TypeScript will not discriminate the generated `oneOf` on `internal_block` / `BLOCK`. Narrow by shape: `detail !== null && 'reason' in detail`.

Engine README is `extends: ['../anakata-ui']` with **no** version pin — no engine commit.

No panel import broke on a retired name; the aliases kept the Sprint 3 names.

### Files touched
**anakata-api (prelude)**
- `app/Http/Controllers/Rms/BookingController.php`
- `app/Http/Controllers/Rms/RequestController.php`
- `app/Http/Resources/Rms/ReservationCreatedResource.php`
- `app/Http/Resources/Rms/DepartureMutationResource.php`
- `tests/Feature/OpenApi/PanelResponseSchemasTest.php`

**anakata-ui**
- `app/types/api.d.ts`
- `app/types/inventory.ts`
- `app/types/config.ts`
- `app/types/bookings.ts` (new)
- `app/types/index.ts`
- `package.json` (`0.5.0`)
- `CHANGELOG.md`
- `README.md`

**anakata-panel**
- `README.md`
- `app/components/calendar/calendarHelpers.ts`

**anakata-api (this report)**
- `docs/sprints/sprint-04/REPORT.md`

### Deviations
- `config.ts` did not shrink (363 → 368): G5 fields added; documents still untyped.
- `list<BookingResource>` on the named created schema becomes `string[]`; 201 path uses `#[DocumentedResponse]`.
- Enum classes on resource `@return` rejected by Larastan and ignored by Scramble.
- `allowed_transitions` items still `[]`.
- Several booking resources (`HoldResource`, `ReservationQuoteResource`, `BookingRequestResource.can_act`) infer badly; overlays rather than more PHPDoc that Scramble would ignore.

### Open questions
None.

### Notes for later
- Task 08: grouped `ChannelOfOrigin` list from the API (no const map in the layer).
- `allowed_transitions` item properties if Scramble ever expands `list<array{…}>`.
- Engine sprint / later pin: add a README version line only if the engine starts naming a layer tag.

### Quality
- anakata-api: `composer check` inside Docker — 497 tests (3311 assertions), Pint, Larastan OK.
- anakata-ui: `pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm build` — pass.
- anakata-panel: `pnpm typecheck`, `pnpm test` (137), `pnpm build` — pass.
- anakata-engine: `pnpm typecheck`, `pnpm build` — pass.
- Fresh clone into `/tmp/anakata-fresh/{anakata-ui,anakata-panel,anakata-engine}` (sibling layout so `extends: ['../anakata-ui']` resolves). Overlayed the working trees (tag `v0.5.0` is not on origin yet). Confirmed the ui clone has **no** `app/types/nuxt.d.ts`.
  - ui / panel / engine: `pnpm typecheck` pass
  - panel / engine: `pnpm build` pass
  - **Repeat this clone after the pushes below**, checking out `anakata-ui` at `v0.5.0` with **no** overlay.

### Git commands for the user

Do **not** run these in the agent. Explicit paths only (never `-A`). Run in this order.

`v0.3.0` exists locally on anakata-ui and is **not** on origin. Tag `v0.5.0` after the ui commit so it points at the regenerated types.

```bash
# 1. anakata-api prelude (PHPDoc only — not this report)
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Http/Controllers/Rms/BookingController.php \
  app/Http/Controllers/Rms/RequestController.php \
  app/Http/Resources/Rms/ReservationCreatedResource.php \
  app/Http/Resources/Rms/DepartureMutationResource.php \
  tests/Feature/OpenApi/PanelResponseSchemasTest.php
git commit -m "$(cat <<'EOF'
Type reservation create and request-queue OpenAPI responses.

Scramble now emits created bookings as BookingResource and
request index meta.rules so the layer can regenerate against them.
EOF
)"
```

```bash
# 2. anakata-ui — commit, then tag the regenerated types, then push
cd /home/mohammad/Code/iconic/anakata/anakata-ui
git add \
  package.json \
  CHANGELOG.md \
  README.md \
  app/types/api.d.ts \
  app/types/inventory.ts \
  app/types/config.ts \
  app/types/index.ts \
  app/types/bookings.ts
git commit -m "$(cat <<'EOF'
Regenerate API types for bookings, requests and holds.

Sprint 4 aliases replace the superseded inventory hand-writes;
calendar dates stay YYYY-MM-DD strings.
EOF
)"
git tag v0.5.0
git push origin HEAD
git push origin v0.3.0
git push origin v0.5.0
```

```bash
# 3. anakata-panel
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  README.md \
  app/components/calendar/calendarHelpers.ts
git commit -m "$(cat <<'EOF'
Pin the layer to v0.5.0 and narrow blocked-cell detail.

holder.type does not discriminate the generated oneOf, so the
calendar helper narrows on reason in detail.
EOF
)"
git push origin HEAD
```

```bash
# 4. anakata-engine — skip
# README is `extends: ['../anakata-ui']` with no version. No files changed.
```

```bash
# 5. anakata-api report (this file only; prelude commit is already on the branch)
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-04/REPORT.md
git commit -m "$(cat <<'EOF'
Record sprint 4 task 06: regenerated UI API types.
EOF
)"
git push origin HEAD
```

```bash
# 6. Fresh-clone repeat — after the pushes, no working-tree overlay
rm -rf /tmp/anakata-fresh
mkdir -p /tmp/anakata-fresh
git clone https://github.com/anakata-project/anakata-ui.git /tmp/anakata-fresh/anakata-ui
git -C /tmp/anakata-fresh/anakata-ui checkout v0.5.0
git clone https://github.com/anakata-project/anakata-panel.git /tmp/anakata-fresh/anakata-panel
git clone https://github.com/anakata-project/anakata-engine.git /tmp/anakata-fresh/anakata-engine
# then in each: pnpm install
# ui / panel / engine: pnpm typecheck
# panel / engine: pnpm build
```

## Task 07 · anakata-panel · Bookings list, booking panel (Overview + History), Groups, Deleted & released

### What was built
`/rms/reservations/bookings` is the prototype `v-book`: date-range on departure date, All / D2C / B2B / Charter chips, 300 ms search, Mine, the bookings table (no GUESTS line), Groups (OPS-008) from `GET /groups?from&to`, and Deleted & released from `GET /bookings/audit` (hidden without `bookings.view_all`).

The booking panel (shared 600 px drawer) shows Overview and History. Guests / Extras / Payments / Documents are listed and disabled with “Arrives in Sprint 6/5/7”. Money, `allowed_transitions`, `can_act`, `balance` and `price_lines` come from `BookingResource` (list = show). Request hold/SLA/notes come from `RequestSummary` on the list (eager-loaded `bookingRequest` + `activeClaims`).

Status changes, delete and request release use a square `UModal` (not `prompt`). Move pages every future departure from Galápagos tomorrow (`per_page=100` until `last_page`); 19 Dec 2027 is in that list. 422 / 409 stay in `.warnbox`. `?open=` matches `display_reference` exactly.

Header and toolbar “New reservation” stay disabled (task 08). Paid is USD 0 with “Payments arrive in Sprint 5”; balance is the API’s `balance` (equals total until Sprint 5).

### API prelude (this task)
`GET /bookings` eager-loads `bookingRequest` and `activeClaims`. `BookingListQueryCountTest` asserts the list query count does not grow with more REQUESTED bookings. `GET /bookings/owners` (`records.act_on_any`, active `panel.rms`). Groups accept `from` / `to`. `RequestSummary` is shared by list, show and the request queue.

### Dates
`useDates().format(Date, 'iso')` is the configured zone (panel: `Pacific/Galapagos`), not UTC. Unit tests: at `2026-09-21T05:30:00.000Z` (23:30 GALT) iso is `2026-09-20`; `galapagosTomorrowIso` is `2026-09-21`. Move uses that helper, not a UTC date.

### Disabled tabs
Overview and History are live. Guests and Extras → Sprint 6. Payments → Sprint 5. Documents → Sprint 7.

### Omitted GUESTS line
The list client cell is name + optional group/coordinator only. A note on the panel: “The GUESTS line arrives in Sprint 6.”

### Reason modal
`ReasonModal` titles `Status CONFIRMED → CANCELLED`, `Delete reservation {ref}`, or the release hold title. Hint is `(required)` / `(optional)` from `reason_required`. Record stays disabled while a required reason is empty. Errors stay in the modal.

### Move dialog
Departure select (all future, festive labelled) + cabin select (FREE, plus the booking’s current code). Preview shows current → new, difference (`--warn` / `--ok` / muted), new lines, sailing-year / festive notes, “No modification fee (FIN-006).” Confirm posts `confirm_total`. A 409 refreshes the preview and keeps the dialog open.

### Browser
As Carolina: 11 seeded bookings, chips, date range, GRP-007. Opened ANK-2026-0003: Overview kv rows, Rates v1 lines, → FULLY PAID / → CANCELLED, reason modal “Status CONFIRMED → CANCELLED” (required), move list includes `19 Dec 2027 · ANAMARA · Festive Expeditions · FESTIVE`. History: `Reservation created in RMS — Suite 01 · 2 AD · seeded`. New reservation disabled. Did **not** confirm cancel / move / delete against seed. Lucía 🔒 and Mateo delete-disabled were not re-run as those users this session (they follow `can_act` / `bookings.delete`). Light theme uses the same tokens; the theme toggle was blocked by the open modal overlay.

Local API was missing `booking_requests` until `php artisan migrate` — list 500’d before that.

### Quality
- anakata-panel: `pnpm lint`, `pnpm typecheck`, `pnpm test` (148), `pnpm build` — pass
- anakata-ui: existing `useDates` GALT iso test
- anakata-api: prelude already covered by `composer check` / `BookingListQueryCountTest`

### Files touched
**anakata-api**
- `app/Http/Controllers/Rms/BookingController.php` (eager-load, owners)
- `app/Http/Controllers/Rms/GroupController.php` (`from` / `to`)
- `app/Http/Requests/Rms/IndexGroupsRequest.php`
- `app/Http/Resources/Rms/BookingResource.php`, `BookingRequestResource.php`, `ReservationCreatedResource.php`
- `app/Http/Resources/Rms/BookingOwnerResource.php` (new)
- `app/Support/Bookings/RequestSummary.php` (new)
- `app/Models/Booking.php` (`activeClaims`)
- `app/Policies/BookingPolicy.php` (`viewOwners`)
- `routes/api/rms.php`
- `tests/Feature/Bookings/BookingReadTest.php`, `BookingListQueryCountTest.php`
- `tests/Feature/OpenApi/PanelResponseSchemasTest.php`

**anakata-ui (v0.5.1)**
- `app/types/api.d.ts`, `bookings.ts`, `index.ts`
- `tests/unit/useDates.test.ts`
- `package.json`, `CHANGELOG.md`, `README.md`

**anakata-panel**
- `app/pages/rms/reservations/bookings.vue`
- `app/components/bookings/*` (`bookingHelpers`, `requestActions`, `BookingPanel`, `GroupDrawer`, `MoveBookingModal`, `ReasonModal`)
- `app/components/history/describe.ts` + `tests/unit/describe.test.ts`
- `tests/unit/bookingHelpers.test.ts`
- `app/assets/css/bookings.css`, `nuxt.config.ts`, `eslint.config.mjs`
- `i18n/locales/en.json`, `app/types/api.ts`, `app/layouts/default.vue`, `README.md`

### Deviations
- Paid/balance copy is “Payments arrive in Sprint 5” (G6), not the prototype’s live paid figure.
- New reservation is disabled (task 08), header and toolbar.
- Group drawer lists bookings only — no coordinator editor (not in this task).
- Browser mutation checks (cancel, festive move, delete, Lucía/Mateo) were not executed against seed.

### Open questions
None.

### Notes for later
- Task 08: enable New reservation and wire the modal.
- Task 09: request queue reuses `requestActions` and `BookingPanel`.
- Task 10: calendar opens `BookingPanel` with `?open=`.
- Sprint 5: Paid / balance from payments; drop the Sprint 5 note.

### Git commands for the user

Do **not** run these in the agent.

```bash
# 1. anakata-api
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Http/Controllers/Rms/BookingController.php \
  app/Http/Controllers/Rms/GroupController.php \
  app/Http/Requests/Rms/IndexGroupsRequest.php \
  app/Http/Resources/Rms/BookingResource.php \
  app/Http/Resources/Rms/BookingRequestResource.php \
  app/Http/Resources/Rms/ReservationCreatedResource.php \
  app/Http/Resources/Rms/BookingOwnerResource.php \
  app/Support/Bookings/RequestSummary.php \
  app/Models/Booking.php \
  app/Policies/BookingPolicy.php \
  routes/api/rms.php \
  tests/Feature/Bookings/BookingReadTest.php \
  tests/Feature/Bookings/BookingListQueryCountTest.php \
  tests/Feature/OpenApi/PanelResponseSchemasTest.php \
  docs/sprints/sprint-04/REPORT.md
git commit -m "$(cat <<'EOF'
Eager-load request holds on the bookings list and expose owners.

RequestSummary reads hold.expires_at from the active HOLD claim,
so the index must load bookingRequest and activeClaims without
an N+1. Groups accept a departure from/to filter.
EOF
)"

# 2. anakata-ui
cd /home/mohammad/Code/iconic/anakata/anakata-ui
git add \
  app/types/api.d.ts \
  app/types/bookings.ts \
  app/types/index.ts \
  tests/unit/useDates.test.ts \
  package.json \
  CHANGELOG.md \
  README.md
git commit -m "$(cat <<'EOF'
Regenerate booking types and pin Galápagos iso dates.

format(Date, 'iso') is the configured zone; at 23:30 GALT
the calendar day stays the Galápagos date, not UTC tomorrow.
EOF
)"
git tag v0.5.1

# 3. anakata-panel
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  README.md \
  app/pages/rms/reservations/bookings.vue \
  app/components/bookings \
  app/components/history/describe.ts \
  app/assets/css/bookings.css \
  app/layouts/default.vue \
  app/types/api.ts \
  i18n/locales/en.json \
  nuxt.config.ts \
  eslint.config.mjs \
  tests/unit/bookingHelpers.test.ts \
  tests/unit/describe.test.ts
git commit -m "$(cat <<'EOF'
Add the bookings list and Overview/History panel.

Money, transitions and own-records come from the API. The
reason modal replaces prompt; move pages all future departures.
EOF
)"
```

## Task 08 · New Reservation modal

### What was built
The prototype `#newmodal` on `/rms/reservations/bookings`, wired to Task 03 `POST /bookings/quote` and `POST /bookings`. Header and toolbar **＋ New reservation** are live (`bookings.create`). Off the bookings route the header goes to `/rms/reservations/bookings?new=1` (optional `departure_id` / `cabin` for task 10). After 201: toast, close, `refreshAll()`, open `BookingPanel` on `bookings[0]`.

No panel GET to `/rates`, `/engine-settings`, or `/business-rules`. Channels, preferred, children ages and `max_per_cabin` come from `GET /bookings/form-options`. Frozen-at-sale deposit / balance days / CHARTER notice numbers come from `quote.terms` plus the party totals.

### API prelude
`GET /api/rms/bookings/form-options` (`bookings.create`, registered next to `bookings/audit` and `bookings/owners`). `BookingFormOptionsResource` (`$wrap = null`): `main` (`trade` = `MainChannel::isTrade()`), `origin` grouped by `ChannelOfOrigin::group()`, `preferred` (enum case names), `guests` from `CurrentConfig::engineSettings()->guests`.

`ReservationQuote.terms`: `balance_days` (cabin or charter); `charter` is non-null only when `type === CHARTER` (`deposit_pct`, `deposit_business_days`, `balance_days`, `dpng_manifest_days`). Present even when a party is `NoRate` so the charter notice can render. PHPDoc so Scramble keeps `terms` as an object. `QuoteReservationRequest` still 422s a CABIN row without `cabin_code` (“Pick a cabin.”) — unchanged.

Pest: `BookingFormOptionsTest` (Sales Exec 200, no `bookings.create` 403, origin groups, B2B `trade: true` / D2C and Partners `false`, seed guests 6 / 17 / 3). `QuoteReservationTest` terms: cabin 120 + `charter` null; festive charter 20 / 5 / 120 / 30. Both resources in `PanelResponseSchemasTest`.

### Types · anakata-ui v0.5.2
PHPDoc + `pnpm types:api`. Alias `BookingFormOptions` only — no hand-written `BookingChannels`. `BookingQuote` leftover overlay adds `terms` (Scramble still types quote `cabins` / totals loosely). Panel README pin: `` `extends: ['../anakata-ui']` (`v0.5.2`) ``.

### Pure helpers
`newReservationHelpers.ts` + `tests/unit/newReservationHelpers.test.ts`:
- Quote payload: CHARTER one party, no `cabin_code`. CABIN `null` until every row has `cabin_code` (price box **Pick a cabin**; no POST).
- Group row: `!charter && (cabinCount >= 2 || existingGroupId != null)`. Name field hidden when an existing group is chosen.
- Festive suffix exactly ` · FESTIVE (+supplement, discounts blocked)`.
- Toasts: `Reservation {ref} created.` · `{n} cabins created under {GRP} ({ANK-…}). The coordinator receives all communications.`
- Back-to-back hidden for CHARTER or festive, and **forced `false` whenever hidden**.
- Trade from API `trade`, never a panel regex.

### Modal
Square/hairline `UModal`, title **New reservation — manual entry**, 640 px. `createValidationQueue` 400 ms. Create disabled while the queue is pending, the quote is missing/has errors, or the create POST is in flight. 409 → `.warnbox` + reload cabins + requote. 422 → `applyApiFormError`. Unsaved: overlay / Cancel / Escape → `confirmUnsaved`.

`watch(open, …, { immediate: true })` so `?new=1` (modal mounts already open) still loads form-options and departures. Parent also sets `newOpen = false` on `created` so the modal actually dismisses after 201.

### Contact pick (G1) — notice; fields stay editable
`ResolveContact` fills empty fields only. After a suggestion is chosen, if the email still matches: `.notice` “Existing contact — the name and phone on file are kept.” Name and phone stay editable (empty-on-file phone can still be sent). Clearing or changing the email dismisses the notice. **Not** read-only.

### Omissions and additions vs the prototype
Omitted (Sprint 5): agency / commission / payment method / `ON_HOLD_AGENCY`. Trade shows the Sprint 5 notice.
Added (create API requires them): email, phone, preferred channel.

### Browser
As **Carolina** (light then dark):
- One cabin, Suite 03, 2 adults, 7 Nov 2027 ANAMARA → **USD 26,600** / deposit **USD 2,660** / T−120 → ANK-2026-0020 Elena Voss, panel opens, owner Carolina.
- Three cabins (21 Nov 2027 ANATIVA S3–S5) → GRP-008 Mira family, three PENDING_PAYMENT rows, lead-guest label + OPS-008 notice.
- Festive CHARTER **19 Dec 2027 ANAMARA** → **USD 211,500** / **USD 42,300**; notice “Deposit 20% within 5 business days … balance 80% at 120 days; DPNG manifest 30 days”. Cabins and back-to-back hidden. Calendar `SOLD` not checked (task 10).
- Two-tab race (modal quoted S1 on 28 Nov 2027 ANATIVA; parallel POST took it) → `.warnbox` “Suite 01 on 28 Nov 2027 · ANATIVA is sold.”; cabin then **Not available**.
- 4 adults → capacity error, Create disabled.
- Create + Cancel disabled while the POST is in flight.
- Harrison contact suggestion → notice; name/phone remain editable; changing the email dismisses the notice.
- Header ＋ New reservation on the bookings route opens the modal; from Calendar it navigates to `?new=1`.

As **Lucía** (Sales Exec): ANK-2026-0025 Lucia Guest created; she is owner. Dark theme on the list + modal.

### Quality
- anakata-api: `docker compose exec app sh -c "composer check"` — 504 passed, Pint, Larastan OK
- anakata-ui: `pnpm types:api`, `pnpm lint`, `typecheck`, `test` (35), `build`
- anakata-panel: `pnpm lint`, `typecheck`, `test` (157), `build`

### Files touched
**anakata-api**
- `app/Http/Controllers/Rms/BookingController.php` (`formOptions`)
- `app/Http/Resources/Rms/BookingFormOptionsResource.php` (new)
- `app/Support/Bookings/BookingFormOptions.php` (new)
- `app/Enums/PreferredChannel.php` (`label()`)
- `app/Services/Pricing/QuoteTerms.php` (new)
- `app/Services/Pricing/ReservationQuote.php`, `ReservationQuoter.php`
- `app/Http/Resources/Rms/ReservationQuoteResource.php`
- `routes/api/rms.php`
- `tests/Feature/Bookings/BookingFormOptionsTest.php` (new)
- `tests/Feature/Bookings/QuoteReservationTest.php`
- `tests/Feature/OpenApi/PanelResponseSchemasTest.php`
- `docs/sprints/sprint-04/REPORT.md`

**anakata-ui (v0.5.2)**
- `app/types/api.d.ts`, `bookings.ts`, `index.ts`
- `package.json`, `CHANGELOG.md`, `README.md`

**anakata-panel**
- `app/components/bookings/NewReservationModal.vue` (new)
- `app/components/bookings/newReservationHelpers.ts` (new)
- `app/composables/useNewReservation.ts` (new)
- `tests/unit/newReservationHelpers.test.ts` (new)
- `app/pages/rms/reservations/bookings.vue`, `app/layouts/default.vue`
- `app/assets/css/bookings.css`, `app/types/api.ts`, `i18n/locales/en.json`
- `eslint.config.mjs`, `README.md`

### Deviations
- Contact fields stay editable with a notice (G1 fill-empty-only), not read-only.
- CABIN quote is gated in the panel until every `cabin_code` is set (**Pick a cabin**). The API still 422s if a CABIN quote is sent without one.
- Calendar occupancy / `SOLD` cells deferred to task 10 — only the `?new=` / prefill hook.
- Immediate `watch(open)` and parent `newOpen = false` on `created` (mount-already-open + UModal staying visible after 201).

### Open questions
None.

### Notes for later
- Task 09: request queue.
- Task 10: calendar / Yacht Layout occupancy and `SOLD`; prefill from a cell.
- Task 11: E2E `BKG-*`.
- Sprint 5: agency, commission, payment method, `ON_HOLD_AGENCY`.

### Git commands for the user

Do **not** run these in the agent. Commit Task 07 first if those files are still uncommitted, then:

```bash
# 1. anakata-api
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Http/Controllers/Rms/BookingController.php \
  app/Http/Resources/Rms/BookingFormOptionsResource.php \
  app/Support/Bookings/BookingFormOptions.php \
  app/Enums/PreferredChannel.php \
  app/Services/Pricing/QuoteTerms.php \
  app/Services/Pricing/ReservationQuote.php \
  app/Services/Pricing/ReservationQuoter.php \
  app/Http/Resources/Rms/ReservationQuoteResource.php \
  routes/api/rms.php \
  tests/Feature/Bookings/BookingFormOptionsTest.php \
  tests/Feature/Bookings/QuoteReservationTest.php \
  tests/Feature/OpenApi/PanelResponseSchemasTest.php \
  docs/sprints/sprint-04/REPORT.md
git commit -m "$(cat <<'EOF'
Expose booking form-options and quote.terms for the panel.

Staff with only bookings.create can load channels and the
frozen-at-sale terms without config-page permissions.
EOF
)"

# 2. anakata-ui — commit, then tag, then push
cd /home/mohammad/Code/iconic/anakata/anakata-ui
git add \
  app/types/api.d.ts \
  app/types/bookings.ts \
  app/types/index.ts \
  package.json \
  CHANGELOG.md \
  README.md
git commit -m "$(cat <<'EOF'
Regenerate types for form-options and quote.terms.

v0.5.2 aliases BookingFormOptions; the quote leftover keeps
terms so the panel never reads rates or business-rules.
EOF
)"
git tag v0.5.2
git push origin HEAD
git push origin v0.5.2

# 3. anakata-panel
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  README.md \
  app/components/bookings/NewReservationModal.vue \
  app/components/bookings/newReservationHelpers.ts \
  app/composables/useNewReservation.ts \
  tests/unit/newReservationHelpers.test.ts \
  app/pages/rms/reservations/bookings.vue \
  app/layouts/default.vue \
  app/assets/css/bookings.css \
  app/types/api.ts \
  i18n/locales/en.json \
  eslint.config.mjs
git commit -m "$(cat <<'EOF'
Add the New reservation modal on live quote and create.

Form-options and quote.terms are the only sources; Create
stays disabled while the POST is in flight.
EOF
)"
```

## Task 09 · Booking Requests and Holds & Waitlist

### What was built
Two live queues in anakata-panel, plus an API prelude so the holds page can format Expires without reading `/requests`.

**Booking Requests** (`/rms/reservations/booking-requests`): date-range filter, verbatim notice, Incoming requests table (`renderReq`), Confirm / Release, row → BookingPanel. Nav badge is the unfiltered open-request count.

**Holds & Waitlist** (`/rms/operations/holds`): Active holds + Waitlist, shared date-range, row → BookingPanel, Add / Mark notified / Remove.

### API prelude
`RequestSummary` now computes `hold.remaining_business_minutes` (same `BusinessHours` as expiry). `RequestQueueRules::businessDayMinutes()` is the single source for day length (09:00–18:00 → 540).

`HoldResource` wraps `Booking` (not an untyped array) with PHPDoc so OpenAPI types `departure` as `{ date, yacht }` and `remaining_business_minutes` as integer. Adds `booking_id`.

`GET /api/rms/holds` accepts `from` / `to` on the departure date (`IndexHoldsRequest`) and returns `meta.rules.business_day_minutes` from `RequestQueueRules::businessDayMinutes`. Sales Execs / CFO never need `/business-rules`.

Pest: `RequestQueueTest` (queue remaining minutes, holds `booking_id` + date filter + `meta.rules` 540, **cfo@ / external finance can read remaining minutes and `meta.rules`**). `BookingReadTest` remaining minutes on `request.hold`. `PanelResponseSchemasTest`: HoldResource `departure` object + integer remaining + `booking_id`; holds `from`/`to`; `meta.rules.business_day_minutes`.

### Types · anakata-ui v0.5.3
`pnpm types:api` first. Generated `HoldResource.departure` is an object and `remaining_business_minutes` is `number`. Overlays only where Scramble still mistypes:

| Alias | Overlay | Why |
|---|---|---|
| `HoldListItem` | `type: string`, `booking_id: number \| null` | Generated `type` is `string \| null`; `booking_id` is `number` while PHPDoc is `int\|null` (WEB/AGENCY later). |
| `RequestQueueItem` | `hold`, `sla` (plus existing `can_act` / `party` / `contact`) | Generated `hold` / `sla` are `string \| object` because of the runtime `??` fallback; `hold.expires_at` freezes as `null`. |
| `BookingRequestSummary.hold` | `remaining_business_minutes: number` | Already present on the generated booking `request.hold`; kept so the panel type is one object. |

`HoldListRules` is the generated `hold.index` `meta.rules`. Panel README pin: `` `extends: ['../anakata-ui']` (`v0.5.3`) ``.

### Notice numbers
The Booking Requests notice is one `i18n-t` string matching the prototype, including “No request is ever auto-cancelled without team review…”. Numbers come from **that list’s** `meta.rules` (`near_term_business_hours`, `long_lead_business_days`, `response_hours`, `cabin_deposit_pct`). Seed: 48 / 5 / 24 / 10.

### Countdown refresh
- **Contact SLA:** `formatSla(slaRemainingMinutes(due_at, now))`. `useSlaNow` ticks `now` every 60s; no refetch.
- **Hold remaining:** `formatHoldRemaining(remaining_business_minutes, business_day_minutes, expired)`. Hours when remaining hours (`minutes / 60`) &lt; 72, else days (`minutes / business_day_minutes`). Expired → `HOLD EXPIRED — CABIN NOT HELD`. If `business_day_minutes` is 0, hours only.
- **Lists:** refetch every 5 minutes. No client-side business-minute tick.

Holds Expires uses **`GET /holds` `meta.rules.business_day_minutes`**, not `useOpenRequests`. CFO has `panel.rms` + `bookings.view_all` but not `requests.confirm` / `requests.release`, so they never poll `/requests`.

### Navigation badge
`useOpenRequests`: unfiltered `GET /api/rms/requests`, count = `data.length`. Starts from the layout. Gated the same way as the nav item: `requests.confirm` **or** `requests.release`. Poll every 5 minutes and after confirm/release. Prototype `reqbadge` (`.nav-badge.pill.p-req`). CFO does not see Booking Requests and is redirected if they type the URL.

### Booking panel
Same `ConfirmRequestModal` and `formatHoldRemaining`. Confirm modal uses the booking’s frozen `deposit_pct` (queue fetches `GET /bookings/{id}` first). Release toast: “Hold released, cabin returned to inventory.” SLA notice uses `useOpenRequests.rules.response_hours` when present (hidden for CFO).

### Browser (after `reset.sh`)
As **Carolina** (dark, then light on Holds):
- Two requests: 0042 SLA BREACH — 26h (coral), 0041 19h (green). Badge **2**. Notice 48 / 5 / 24 / 10%. Both holds **45 business hours** (5 long-lead days × 540 min = 45h, under the 72h threshold — not “business days”).
- `inventory:expire-hold ANK-R-2026-0041` → row `HOLD EXPIRED — CABIN NOT HELD`. Confirm modal: “10% … via WHATSAPP” + Sprint 5 sentence. Confirm → PENDING_PAYMENT, gone from queue, badge **1**, toast “Request confirmed”.
- Release modal opened (reason required). Record click was blocked by the browser tool; release completed via `TransitionBooking` with reason “Client withdrew”. Queue then empty, badge **0**.
- Waitlist: two festive 19 Dec rows. Mark-notified modal (channel select). After notify: “Notified 20 Sep 2026 via EMAIL by Carolina M.”; Mark notified hidden, Remove remains.

As **cfo@**: no Booking Requests item, no ＋ New Reservation, no RMS/CRM switch. Holds **Expires** column shows **45 business hours** for both REQUEST rows (from holds `meta.rules`, not `/requests`). Waitlist visible, no Add / Mark notified / Remove.

Lucía own-records: API test still blocks confirm/release on Mateo’s request; panel `can_act` disables the buttons. A second Lucía browser pass after the CFO session was not repeated (sign-out click blocked).

### Quality
- anakata-api: `docker compose exec app sh -c "composer check"` — 506 passed, 510 files, Pint, Larastan OK
- anakata-ui: `pnpm lint`, `typecheck`, `test` (35), `build`
- anakata-panel: `pnpm lint`, `typecheck`, `test` (166), `build`

### Files touched
**anakata-api**
- `app/Support/Bookings/RequestSummary.php`, `RequestQueueRules.php`
- `app/Http/Resources/Rms/HoldResource.php`, `BookingRequestResource.php`, `BookingResource.php`
- `app/Http/Controllers/Rms/HoldController.php`
- `app/Http/Requests/Rms/IndexHoldsRequest.php` (new)
- `tests/Feature/Bookings/RequestQueueTest.php`, `BookingReadTest.php`
- `tests/Feature/OpenApi/PanelResponseSchemasTest.php`
- `docs/sprints/sprint-04/REPORT.md`

**anakata-ui (v0.5.3)**
- `app/types/api.d.ts`, `bookings.ts`, `index.ts`
- `package.json`, `CHANGELOG.md`, `README.md`

**anakata-panel**
- `app/pages/rms/reservations/booking-requests.vue` (new)
- `app/pages/rms/operations/holds.vue` (new)
- `app/components/requests/requestHelpers.ts`, `ConfirmRequestModal.vue` (new)
- `app/components/holds/AddWaitlistModal.vue`, `NotifyWaitlistModal.vue` (new)
- `app/composables/useOpenRequests.ts`, `useSlaNow.ts` (new)
- `app/components/bookings/BookingPanel.vue`
- `app/layouts/default.vue`, `app/navigation/rms.ts`
- `app/assets/css/bookings.css`, `app/assets/css/shell.css`
- `app/types/api.ts`, `i18n/locales/en.json`, `eslint.config.mjs`, `README.md`
- `tests/unit/requestHelpers.test.ts` (new), `tests/unit/guards.test.ts`

### Deviations
- Prototype “Notify now” → **Mark notified** (task 05 / G7; no email this sprint).
- Seed TEC-004 holds display as **business hours** (45h &lt; 72h), not “business days”. The 72-hour rule is `remaining_minutes / 60`; 4 business days at 540 min/day is only 36 hours.
- Confirm modal adds the Sprint 5 deposit-link sentence.
- Release Record was not clicked in the agent browser; the modal and the API release were checked separately.

### Open questions
None.

### Notes for later
- Task 10: Calendar / Yacht Layout occupancy (`SOLD` / hold cells).
- Task 11: E2E `BKG-*` (queue, expire-hold, confirm, release, waitlist, Lucía `can_act`, CFO Expires without Booking Requests).
- Sprint 5: deposit link, WEB / AGENCY hold pills with minutes from data.

### Git commands for the user

Do **not** run these in the agent. Commit Task 08 first if those files are still uncommitted, then:

```bash
# 1. anakata-api
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Support/Bookings/RequestSummary.php \
  app/Support/Bookings/RequestQueueRules.php \
  app/Http/Resources/Rms/HoldResource.php \
  app/Http/Resources/Rms/BookingRequestResource.php \
  app/Http/Resources/Rms/BookingResource.php \
  app/Http/Controllers/Rms/HoldController.php \
  app/Http/Requests/Rms/IndexHoldsRequest.php \
  tests/Feature/Bookings/RequestQueueTest.php \
  tests/Feature/Bookings/BookingReadTest.php \
  tests/Feature/OpenApi/PanelResponseSchemasTest.php \
  docs/sprints/sprint-04/REPORT.md
git commit -m "$(cat <<'EOF'
Type holds remaining minutes and expose business-day rules.

CFO can format Expires from GET /holds without reading the
requests queue or the business-rules document.
EOF
)"

# 2. anakata-ui — commit, then tag, then push
cd /home/mohammad/Code/iconic/anakata/anakata-ui
git add \
  app/types/api.d.ts \
  app/types/bookings.ts \
  app/types/index.ts \
  package.json \
  CHANGELOG.md \
  README.md
git commit -m "$(cat <<'EOF'
Regenerate types for typed holds and request remaining minutes.

v0.5.3 overlays only the Scramble leftovers on HoldListItem
and RequestQueueItem hold/sla.
EOF
)"
git tag v0.5.3
git push origin HEAD
git push origin v0.5.3

# 3. anakata-panel
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  README.md \
  app/pages/rms/reservations/booking-requests.vue \
  app/pages/rms/operations/holds.vue \
  app/components/requests/requestHelpers.ts \
  app/components/requests/ConfirmRequestModal.vue \
  app/components/holds/AddWaitlistModal.vue \
  app/components/holds/NotifyWaitlistModal.vue \
  app/composables/useOpenRequests.ts \
  app/composables/useSlaNow.ts \
  app/components/bookings/BookingPanel.vue \
  app/layouts/default.vue \
  app/navigation/rms.ts \
  app/assets/css/bookings.css \
  app/assets/css/shell.css \
  app/types/api.ts \
  i18n/locales/en.json \
  eslint.config.mjs \
  tests/unit/requestHelpers.test.ts \
  tests/unit/guards.test.ts
git commit -m "$(cat <<'EOF'
Add Booking Requests and Holds & Waitlist queues.

Holds format Expires from their own meta.rules so CFO can
see remaining time without the requests permission.
EOF
)"
```

## Task 10 · Booking states in the Calendar and Yacht Layout

### What was built
Sprint 3 Calendar and Yacht Layout now render every booking / request / hold state from `claim.holder.detail`, open BookingPanel or New Reservation from a cell, and refetch occupancy once after those mutations.

`mapCabinCell` is the single presentation function (calendar class + deck text). Both pages call `canActOnBooking(ownerId, currentUserId, hasActOnAny)` — they do not inline the lock rule.

### State table as built

| Claim | Calendar | Deck |
|---|---|---|
| HOLD, `hold_type` REQUEST | `c-req` / `REQ` | `s-hold` / "Requested · {party}" |
| BOOKING, type CHARTER | `c-charter` / `CHARTER` | `s-conf` / "Charter · {party}" |
| BOOKING, PENDING_PAYMENT | `c-dep` / `PEND` | `s-dep` / "Pending payment · {party}" |
| BOOKING, CONFIRMED | `c-conf` / last 4 | `s-conf` / "Confirmed · {party} · {segment}" |
| BOOKING, FULLY_PAID or ON_BOARD | `c-full` / last 4 | `s-conf` / "Fully paid · {party} · {segment}" |
| BOOKING, COMPLETED | `c-conf` / last 4 | "Completed · {party}" (no segment) |
| HOLD WEB / AGENCY (no booking detail) | `c-hold` / `HOLD` or `AGCY` | `s-hold` / "On hold · …" |
| BLOCKED | `c-block` / FAM MAINT NEG COURT | `s-block` / never 🔒 |
| Expired request hold (`hold_expired` or Availability `FREE`) | `c-av` / `·` | Available. Cabin is sellable (G9). Request stays REQUESTED. |

Segment is on Fully paid / Confirmed (and ON_BOARD as Fully paid), matching the prototype `cabHtml` sold branch. Charter / request / pending / completed do not append segment.

### Lock · `canActOnBooking`
`holder.detail` has no `can_act`. The helper matches `App\Policies\Concerns\ChecksOwnRecords::ownsOrMayActOnAny`: `records.act_on_any` **or** `owner_id === currentUserId`. Unit-tested. Blocks never take `lock`. Deck prefixes "🔒 " when locked.

**Follow-up:** overlay `can_act` on the calendar resource so the panel can stop recomputing this.

### Clicks
- Booking / live request → `GET /bookings/{id}` → BookingPanel (no `window.confirm`).
- Free cell + `bookings.create` → New Reservation with `{departureId, cabinCode}`. Tooltip is the prototype prompt (`freeCellPrompt`).
- Block → Sprint 3 `?open=` link.
- No sailing → no action.

### Refresh
`useCalendarGrid` owns the `GET /api/rms/calendar` fetch. `CalendarOccupancyHost` takes that `refresh` and calls it **once** after create / update / delete (create then opens `response.bookings[0]`). No second calendar fetch after create.

### `cellAt` and JSON keys
Calendar JSON reindexes `rows[].cells` to `"0".."n"` (yacht date order). Looking up `cells[String(departure.id)]` first collides when departure id is `1` (`cells["1"]` is the second sailing). `cellAt` prefers a complete 0..n list when every index key exists; otherwise departure-id keys. Unit-tested against the id-1 collision.

### Browser
**Year 2027** (eight ANAMARA Sundays, both themes): 0003 Suite 01 7 Nov; 0005 Suite 02 7 Nov `c-full`; 0007 Suite 03 14 Nov; PEND 0014 Suite 05 14 Nov; REQ 0041 Suite 04 21 Nov and 0042 Suite 05 28 Nov; CHARTER all nine ANAMARA cabins 19 Dec; FAM Suite 07–08 14 Nov (no lock). 0009 Owner 21 Nov.

**CFO:** every booking/request locked (no `records.act_on_any`, not the owner). Click 0003 → panel Harrison & Whitfield, 7 Nov, Suite 01.

**Lucía:** hers (0003, 0007, 0011, 0018, 0014, 0041) unlocked; Mateo’s (0005, 0016, 0017, 0019, 0009, 0042) and Carolina’s charter locked. Free Suite 04 7 Nov → modal prefilled `7 Nov 2027 · ANAMARA` / Suite 04 → create **ANK-2026-0020** PENDING_PAYMENT. One calendar fetch; cell became `PEND` without a reload; panel opened.

**Yacht Layout** 19 Dec 2027: ANAMARA every cabin `🔒 Charter · 0 AD` (API `party_label`; seed charter `adults: 0`, not prototype “16 PAX”). ANATIVA available.

**`inventory:expire-hold ANK-R-2026-0041`:** Suite 04 21 Nov became `c-av` (Availability drops the claim). Request is still open (badge still 2).

### Quality
- anakata-panel: `pnpm lint`, `typecheck`, `test` (174), `build`

### Files touched
**anakata-panel**
- `app/components/calendar/calendarHelpers.ts` (`canActOnBooking`, `mapCabinCell` states, `cellAt` list-index)
- `app/composables/useCalendarGrid.ts` (new)
- `app/components/calendar/CalendarOccupancyHost.vue` (new)
- `app/components/calendar/CalendarCell.vue`
- `app/pages/rms/reservations/calendar.vue`, `yacht-layout.vue`
- `app/assets/css/inventory.css` (`button.cell`, `.cab.is-click`)
- `tests/unit/calendarHelpers.test.ts`

**anakata-api**
- `tests/e2e/scenarios/inventory/INV-12-lucia-read-only.md` (E4: Lucía may open New reservation from a free cell)
- `docs/sprints/sprint-04/REPORT.md`

### Deviations
- Prototype “WIRE” / “Pending wire” → **PEND** / **Pending payment** (payment method is Sprint 5).
- Charter deck text is **Charter · 0 AD**, not “16 PAX” — `party_label` is computed from `adults` / `children` and the seed charter is `adults: 0`.
- No `window.confirm` on a free cell; the prompt is the tooltip / title and the modal opens directly.
- Lock is a local `canActOnBooking` helper, not an API `can_act` field.

### Open questions
None.

### Notes for later
- Overlay `can_act` on the calendar resource (`holder.detail` or the cell) so the panel can drop the local lock copy.
- Emit calendar `cells` with non-numeric keys (or stringified departure ids that JSON will not reindex to 0..n).
- Sprint 5: restore WIRE for wire pending payments.
- Task 11: E2E `BKG-*` — calendar Year 2027 states, Lucía 🔒, free-cell create + one refresh, expire-hold as free, Yacht Layout charter. Local DB now has ANK-2026-0020 and an expired 0041 hold; `reset.sh` before the P1 run.
- Charter `party_label` / 16 PAX if Anakata wants a pax count that is not `adults`+`children`.

### Git commands for the user

Do **not** run these in the agent.

```bash
# 1. anakata-panel
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  app/components/calendar/calendarHelpers.ts \
  app/composables/useCalendarGrid.ts \
  app/components/calendar/CalendarOccupancyHost.vue \
  app/components/calendar/CalendarCell.vue \
  app/pages/rms/reservations/calendar.vue \
  app/pages/rms/reservations/yacht-layout.vue \
  app/assets/css/inventory.css \
  tests/unit/calendarHelpers.test.ts
git commit -m "$(cat <<'EOF'
Show booking states on Calendar and Yacht Layout.

Own-records lock uses canActOnBooking (ChecksOwnRecords).
Clicks open the booking panel or a prefilled new reservation.
EOF
)"

# 2. anakata-api
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  tests/e2e/scenarios/inventory/INV-12-lucia-read-only.md \
  docs/sprints/sprint-04/REPORT.md
git commit -m "$(cat <<'EOF'
Record Task 10 calendar occupancy and INV-12 free-cell note.

Lucía may open New reservation from a free calendar cell.
EOF
)"
```

## Task 11 · E2E scenarios for Sprint 4; P1 run

### What was built
Twelve `BKG-*` browser scenarios under `tests/e2e/scenarios/bookings/`, tags `sprint-4`, `bookings`. Fixtures name the seeded bookings, requests, waitlist and next references. INV-10, BR-01, BR-02 and INV-01 were rewritten for the Sprint 4 seed. `INDEX.md` lists the twelve; P1 grows by BKG-01, 02, 03, 05, 06 and 09.

**No local e2e run.** Every value not read off a screen in this task and not taken from a passing Pest assertion is marked with the greppable string `⚠ UNVERIFIED`. Count: **63** (`rg '⚠ UNVERIFIED' tests/e2e --glob '*.md'`). The verification report must state how many were cleared and how many remain.

BKG-02, 03, 04 and 05 each allocate ANATIVA 2027 cabins. Step 1 is `reset.sh` plus an Available (`·`) check on the target cabin(s), so a missed reset fails there instead of as a later false conflict.

### The scenarios

| ID | Priority | User | File |
|---|---|---|---|
| BKG-01 | P1 | Carolina | `bookings/BKG-01-seeded-bookings-segments.md` |
| BKG-02 | P1 | Carolina | `bookings/BKG-02-create-one-cabin.md` |
| BKG-03 | P1 | Carolina | `bookings/BKG-03-create-three-cabin-group.md` |
| BKG-04 | P2 | Carolina | `bookings/BKG-04-festive-charter.md` |
| BKG-05 | P1 | Carolina ×2 | `bookings/BKG-05-no-double-booking.md` |
| BKG-06 | P1 | Carolina | `bookings/BKG-06-transitions-cancel.md` |
| BKG-07 | P2 | Carolina | `bookings/BKG-07-date-change-reprice.md` |
| BKG-08 | P2 | Mateo, Carolina | `bookings/BKG-08-delete-admin-audit.md` |
| BKG-09 | P1 | Carolina | `bookings/BKG-09-request-queue.md` |
| BKG-10 | P2 | Carolina | `bookings/BKG-10-expired-request-hold.md` |
| BKG-11 | P2 | Carolina | `bookings/BKG-11-waitlist.md` |
| BKG-12 | P2 | Lucía | `bookings/BKG-12-own-records.md` |

INV-10 **keeps DEP-003**. Date/yacht are locked by the two seeded bookings; Delete names `2 blocked, 2 sold`, then `2 sold` after releasing BLK-001. The history-only Delete sentence is unreachable on that row.

BKG-07 ends by moving GRP-007 member ANK-2026-0016 and expecting `This booking belongs to GRP-007 — moving a group to another departure isn't supported yet.`

### Old-scenario changes

| File | Change |
|---|---|
| INV-10 | DEP-003 now locked; Delete counts include sold bookings |
| BR-01 | Registry 50 / 25 / 15 / 11; seven PENDING CLIENT |
| BR-02 | Differ KPI 12 then 11; publish still 12% → 15% |
| INV-01 | ANAMARA 14 Nov `0007` / `PEND`; 19 Dec `CHARTER`; layout `Charter · 0 AD` |

INV-07 ANATIVA bookable 63, INV-08/09 “9 free”, INV-06 `DEP-042` left as written (ANATIVA still empty; generate season is 2028). Desk-check only.

### Wording differences / ⚠ UNVERIFIED list

63 markers. Fixtures **9**, scenarios **54**.

Named values the user called out:

- Departures KPI recount **121** bookable / **full 0** — `Availability::kpis` arithmetic
- Hold remaining **45 business hours** — task 09 browser
- Bookings list **13** rows — index has no status filter; task 07 said 11
- Registry KPIs and chips **50 / 25 / 15 / 10 / 11** — task 02 / Pest JSON, not a reset screen

Other markers are i18n / REPORT browser strings (toasts, chip labels, `PEND`, lock copy, Delete wrap, History `Moved · …`).

Calculator totals match the seed (`DemoBookingsSeeder::$priceDifferences` empty). Conflict `… is sold.` and the group-move 409 are Pest-backed and unmarked.

### Verification pass

Not run in this task. After merge, a cloud agent:

1. `tests/e2e/bin/up.sh` — wait for `ALL UP`. If it fails, write **ENV** and **stop**. Do not guess values. **Sprint 3’s cloud P1 never ran**, so the cloud path itself is unproven.
2. `reset.sh` before **each** of: BKG-01, BKG-02, BKG-03, BKG-05, BKG-06, BKG-09, INV-10, BR-01, BR-02.
3. Report at `tests/e2e/runs/YYYY-MM-DD-HHMM-<slug>.md` that separates:
   - **(i) Scenario wording/value fixes** — update the file; drop `⚠ UNVERIFIED` when the screen agrees. State cleared vs remaining of the **63**.
   - **(ii) Real application bugs** — `BUG` with screenshot, console, request, step. **Only (ii) goes back to code.**

The full P1 set (Sprints 1–4) runs **only after** this pass has cleared the markers.

### Cloud run (full P1 — later)

Placeholder — not started here.

- **Prompt:** `Run all P1 e2e scenarios on dev and write the report`
- **P1 set (Sprints 1–4), after verification:** SMK-01, SMK-02, AUTH-01, AUTH-03, AUTH-04, AUTH-08, ROLE-01, RATE-03, RATE-05, RATE-06, ENG-01, ENG-02, BR-01, BR-02, INV-01, INV-02, INV-05, INV-06, INV-08, INV-09, BKG-01, BKG-02, BKG-03, BKG-05, BKG-06, BKG-09
- **Run report path:** `tests/e2e/runs/YYYY-MM-DD-HHMM-<slug>.md`
- **Summary:** _not run yet_

### Files touched

- `tests/e2e/fixtures/reference-values.md`
- `tests/e2e/scenarios/INDEX.md`
- `tests/e2e/scenarios/config/BR-01-fresh-seed-registry.md`
- `tests/e2e/scenarios/config/BR-02-differ-reset-publish.md`
- `tests/e2e/scenarios/inventory/INV-01-seeded-calendar.md`
- `tests/e2e/scenarios/inventory/INV-10-departure-locks.md`
- `tests/e2e/scenarios/bookings/BKG-01-seeded-bookings-segments.md`
- `tests/e2e/scenarios/bookings/BKG-02-create-one-cabin.md`
- `tests/e2e/scenarios/bookings/BKG-03-create-three-cabin-group.md`
- `tests/e2e/scenarios/bookings/BKG-04-festive-charter.md`
- `tests/e2e/scenarios/bookings/BKG-05-no-double-booking.md`
- `tests/e2e/scenarios/bookings/BKG-06-transitions-cancel.md`
- `tests/e2e/scenarios/bookings/BKG-07-date-change-reprice.md`
- `tests/e2e/scenarios/bookings/BKG-08-delete-admin-audit.md`
- `tests/e2e/scenarios/bookings/BKG-09-request-queue.md`
- `tests/e2e/scenarios/bookings/BKG-10-expired-request-hold.md`
- `tests/e2e/scenarios/bookings/BKG-11-waitlist.md`
- `tests/e2e/scenarios/bookings/BKG-12-own-records.md`
- `docs/sprints/sprint-04/REPORT.md`

### Deviations
- No local BKG-02 / BKG-05 / INV-10 browser pass (task file asked for one; the approved plan replaced it with UNVERIFIED markers + a cloud verification pass).
- INV-10 kept DEP-003 rather than inventing a block-only departure.

### Open questions
The 63 `⚠ UNVERIFIED` markers. Sprint 3 cloud P1 never ran — `up.sh` on a cloud machine is unproven.

### Notes for later
- Full P1 only after the verification pass clears markers.
- VIS-01 still does not include Calendar / Bookings / Requests / Holds (Sprint 3 leftover).

### Git commands for the user

Do **not** run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  tests/e2e/fixtures/reference-values.md \
  tests/e2e/scenarios/INDEX.md \
  tests/e2e/scenarios/config/BR-01-fresh-seed-registry.md \
  tests/e2e/scenarios/config/BR-02-differ-reset-publish.md \
  tests/e2e/scenarios/inventory/INV-01-seeded-calendar.md \
  tests/e2e/scenarios/inventory/INV-10-departure-locks.md \
  tests/e2e/scenarios/bookings \
  docs/sprints/sprint-04/REPORT.md
git status
git commit -m "$(cat <<'EOF'
Add Sprint 4 booking e2e scenarios with unverified markers.

Twelve BKG scripts and the seed-affected INV/BR files; screen
values not read in this task are marked for a cloud pass.
EOF
)"
```

---

## Sprint 4 · summary

### What’s done
The RMS takes reservations. API: departure-row lock, business-hours holds (G5, rules v2 shape change), contacts / groups / create, transitions / move / delete / audit, requests (holds expire without cancelling), waitlist. `anakata-ui` shipped booking types (`v0.5.0` then `v0.5.1`–`v0.5.3`). Panel: Bookings (Overview + History), New reservation, Booking Requests, Holds & Waitlist, calendar / layout occupancy. Browser scenarios `BKG-01`–`BKG-12` are written with **63** `⚠ UNVERIFIED` markers. The cloud verification pass and the full P1 run are still to attach.

### Open questions from tasks 01–11

Compiled from each task’s REPORT (Open questions **and** leftover Notes that are still open). Do not read a heading that said “None” as closed.

| Task | Still open |
|---|---|
| 01 | Open-questions heading was empty. Leftover note: optional replace of on-disk G10 wording. |
| 02 | TEC-004: which days and hours; which public holidays; from how many days before departure is a request near-term (defaults Mon–Fri 09:00–18:00 GALT, holidays `[]`, 120 days). **Shape migrations** publish through `ConfigPublisher` against the current `rules()`: a DB behind this and a later shape migration fails because the merged document lacks the later required path. Policy before go-live: fold shape migrations into `initial()` while no production exists, or keep replay-in-order only. |
| 03 | Open-questions heading was empty. **`GET /api/rms/contacts?q=` is not own-records scoped** (`ContactController` returns any matching name/email, top 10). Still undecided whether a Sales Exec should see another owner’s clients. |
| 04 | If `modification_fee_usd` > 0, a later move drops the earlier FIN-006 line and writes a new quote + a new fee — should a charged fee stick? A move that **raises the price of a FULLY_PAID booking** leaves an amount owed; `Booking::balance()` is still `total` until Sprint 5. Group move to another departure is unsupported (now an expected 409 in BKG-07). |
| 05 | Open-questions heading was empty. Demo request refs are pinned to 2026; combined with fixed 2027 departures the demo is valid until Nov 2027. |
| 06 | Open-questions heading was empty. Scramble still mistypes several booking shapes (`allowed_transitions` items, `can_act`, quote cabins); the layer keeps overlays. |
| 07 | Open-questions heading was empty. Paid stays `USD 0` until Sprint 5. |
| 08 | Open-questions heading was empty. Agency / commission / `ON_HOLD_AGENCY` wait for Sprint 5. |
| 09 | Open-questions heading was empty. Confirm copy says the deposit link is sent in Sprint 5. |
| 10 | Open-questions heading was empty. **Overlay `can_act` on the calendar claim shape** (`holder.detail` or the cell) so the panel can drop the local `canActOnBooking` copy. Charter `party_label` is `0 AD` on the seed (adults 0), not “16 PAX”. |
| 11 | **63** `⚠ UNVERIFIED` markers. Sprint 3’s cloud P1 never ran; if `up.sh` fails on the cloud machine, say so rather than guessing values. |

### Still open outside the sprint
- Go-live date
- Production domains
- LEG-001 (cancellation policy customer-facing text)
- LEG-002 (LOPDP / GDPR architecture)
- B2 pricing items (PENDING CLIENT: engine SPEC §10 / online-deposit advantage and max total discount)
- The two TEC-004 client questions (business hours; the near-term boundary)
- Group-move note: moving a group to another departure is not supported (BKG-07 expects the 409)

### Git commands per repo, in order

Do **not** run these here. Each task already listed its exact `git add` set. Run them in this order if they are not already on `dev`:

1. **anakata-api** — tasks 01, 02, 03, 04, 05, then 07–10 API preludes, **11 (this commit, above)**.
2. **anakata-ui** — task 06 (`v0.5.0` + `git tag v0.5.0` + push tag), task 07 (`v0.5.1` + tag + push), task 08 (`v0.5.2` + tag + push), task 09 (`v0.5.3` + tag + push).
3. **anakata-panel** — tasks 07 (list + panel), 08 (New reservation), 09 (requests + holds), 10 (calendar occupancy).

Full command blocks live under each Task section above. `anakata-engine` has no Sprint 4 commits.

