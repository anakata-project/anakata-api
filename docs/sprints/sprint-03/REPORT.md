# Sprint 3 · Report
Each task appends its section below.

## Task 01 · Yachts, cabins, the calendar-date cast, itineraries

### What was built
Fixed inventory (ANAMARA / ANATIVA × nine cabins) is seeded in every environment. Itineraries are full content records with the prototype completeness score and publish rules. Calendar dates have their own cast so they stay `YYYY-MM-DD`.

**Roadmap (F8):** “holds, waitlist” and the panel page “Holds & Waitlist” moved from Sprint 3 to Sprint 4 in `docs/sprints/ROADMAP.md`.

**Yachts and cabins (F4):** `yachts` and `cabins` tables, `Yacht` / `Cabin` models (`HasAuditColumns` + `SerializesDatesAsUtc`, no morph aliases). `CabinCategory` moved from `App\Services\Pricing` to `App\Enums\CabinCategory` (one enum). `InventorySeeder` is idempotent (`firstOrCreate` by code) and runs everywhere. `GET /api/rms/yachts` is read-only for any `panel.rms` user.

**Itineraries:** table + model (morph alias `itinerary`), `ItineraryStatus`, completeness, `mkItin` defaults, RMS CRUD under `/api/rms/itineraries`. Viewing needs `panel.rms`; writes need `itineraries.manage`. Lucía views; Mateo writes. `DemoInventorySeeder` (local/testing) maps the three seed-data itineraries and is idempotent by `code`. Seeded `img` values are empty, so `hero_image_path` stays null.

`storage:link` is in the README first-time setup and in `tests/e2e/bin/up.sh`.

### How the cast bypasses the UTC serializer
`App\Casts\CalendarDate` stores `Y-m-d` and `get()` returns `CarbonImmutable` at midnight with no `->utc()`.

Eloquent’s `addCastAttributesToArray()` runs `serializeDate()` (`Iso::utc()`, and `Date::serializeUsing` does the same for raw Carbon) **before** a custom cast’s `serialize()` when `get()` returns a `DateTimeInterface`. The cast’s `serialize()` therefore reads the **raw** attribute (`$attributes[$key]`) and overwrites the timestamp with `Y-m-d`. Resources that expose a calendar date must still call `toDateString()` so dumping the Carbon attribute does not hit `Date::serializeUsing`.

Proved with a test-only `CalendarDateHost` (UTC, `Pacific/Galapagos`, `Asia/Tokyo`) on `toArray()`, a resource, and `json_encode($model)`.

### Seed-data key map
| Prototype / JSON | Column |
|---|---|
| `k` | `code` |
| `order` | `sort_order` |
| `nDays` | `days` |
| `tag` | `tagline` |
| `grad` (CSS) | `fallback_gradient` (key, via reverse `GRADS`) |
| `img` | `hero_image_path` (empty → null) |
| `alt` | `hero_alt` |
| `desc` | `card_description` |
| `long` | `long_description` |
| `hi` | `highlights` |
| `plan` | `day_plan` |
| `inc` | `included` |
| `exc` | `excluded` |
| `metaT` | `meta_title` |
| `metaD` | `meta_description` |
| `chips`, `overview`, `facts`, `faqs`, `slug`, `name`, `status`, `festive`, `embark`, `disembark`, `nights` | same names |

### Delete-permission deviation
The prototype’s `delItin` is Admin-only (`ROLE !== 'admin'`). Here `itineraries.manage` + no departures is enough (Manager can delete). Noted as required by the task.

### Where the prototype’s `STD_*` defaults were copied from
`docs/requirements/prototype/rms_index.html` lines 1071–1077: `STD_FACTS`, `STD_INC`, `STD_EXC`, `STD_FAQ`, `STD_OVERVIEW`, `STD_CHIPS`, plus `mkItin` defaults (embark/disembark San Cristóbal (SCY), 8 days / 7 nights, `order` 9, `GRADS['Western (slate)']`). Stored in `App\Support\Itineraries\Defaults`. Gradient keys live in `App\Support\Itineraries\Gradients` (`GRADS` at line 1066). Completeness mirrors `itinChecks` (lines 1096–1100).

### PATCH history
One entry per logical change, same transaction:

- content only → `itinerary.updated` (no `status` in the diff)
- status only → `itinerary.published` / `itinerary.hidden` (or `itinerary.updated` when returning to `DRAFT`)
- content **and** status → `itinerary.updated` first (content diff, status omitted), then the status event

### Image replacement
Store the new file, update the row and write `itinerary.image_replaced` in the transaction, delete the previous file in `DB::afterCommit`. On failure after store, delete the **new** file and leave the old path and file intact. `afterFileStored()` is a test hook for the forced-failure case.

### Files touched
- `.cursor/rules/laravel.mdc`
- `README.md`
- `tests/e2e/bin/up.sh`
- `docs/sprints/ROADMAP.md`
- `docs/requirements/08-dev-decisions.md` (F1–F9 now on disk)
- `docs/sprints/sprint-03/REPORT.md`
- `app/Casts/CalendarDate.php`
- `app/Enums/CabinCategory.php` (moved from `app/Services/Pricing/CabinCategory.php`)
- `app/Enums/ItineraryStatus.php`
- `app/Models/Yacht.php`, `Cabin.php`, `Itinerary.php`
- `app/Actions/Itineraries/CreateItinerary.php`, `UpdateItinerary.php`, `ReplaceItineraryImage.php`, `DeleteItinerary.php`
- `app/Support/Itineraries/Defaults.php`, `Completeness.php`, `Gradients.php`, `SeedMapper.php`
- `app/Policies/YachtPolicy.php`, `ItineraryPolicy.php`
- `app/Http/Controllers/Rms/YachtController.php`, `ItineraryController.php`, `RatesController.php`
- `app/Http/Requests/Rms/StoreItineraryRequest.php`, `UpdateItineraryRequest.php`, `StoreItineraryImageRequest.php`, `Concerns/ValidatesItineraryContent.php`
- `app/Http/Resources/Rms/YachtResource.php`, `CabinResource.php`, `ItineraryResource.php`, `ItineraryDefaultsResource.php`
- `app/Providers/AppServiceProvider.php`
- `app/Services/Pricing/CabinPricer.php`, `QuoteInput.php`
- `database/migrations/2026_09_20_200013_create_yachts_table.php`
- `database/migrations/2026_09_20_200014_create_cabins_table.php`
- `database/migrations/2026_09_20_200015_create_itineraries_table.php`
- `database/factories/YachtFactory.php`, `CabinFactory.php`, `ItineraryFactory.php`
- `database/seeders/InventorySeeder.php`, `DemoInventorySeeder.php`, `DatabaseSeeder.php`
- `routes/api/rms.php`
- `tests/database/migrations/2026_09_20_000001_create_calendar_date_hosts_table.php`
- `tests/Support/CalendarDate/CalendarDateHost.php`, `CalendarDateHostResource.php`
- `tests/Feature/Inventory/*`
- `tests/Feature/Database/DatabaseSetupTest.php`
- `tests/Unit/Services/Pricing/CabinPricerTest.php`

### Deviations
- Prototype `delItin` is Admin-only; API uses `itineraries.manage` (task).
- Prototype code regex allows a hyphen (`A-Z0-9_-`); the task drops it (`A-Z0-9_`), 2–10 characters (validation list + editor, not the POST line’s `{2,16}`).
- `fallback_gradient` is stored as the GRADS **key**; the resource returns the CSS.
- `ReplaceItineraryImage` is not `final` so the forced-failure test can subclass `afterFileStored()`.
- `08-dev-decisions.md` already contains F1–F9 on disk (sprint README asked to replace it before task 01). This task did not author that prose.

### Open questions
None.

### Notes for later
- `Yacht::departures()` and `Itinerary` departures relation + `departures_count` (task 02).
- Delete 409 while a departure uses the itinerary (`TODO` test in `ItineraryDeleteGuardTest`).
- Resource returns gradient CSS only; the panel editor (task 06) will need the key or a reverse map for the select.

### Quality
- anakata-api: `composer check` inside Docker — 290 tests (1782 assertions) + 1 todo (task 02 delete guard), Pint, Larastan OK.
- `/docs/api` lists `/rms/yachts`, `/rms/itineraries`, `/defaults`, `/{itinerary}`, `/image`, `/history`.

### Git commands for the user

Do **not** run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  .cursor/rules/laravel.mdc \
  README.md \
  tests/e2e/bin/up.sh \
  docs/sprints/ROADMAP.md \
  docs/requirements/08-dev-decisions.md \
  docs/sprints/sprint-03 \
  app/Casts/CalendarDate.php \
  app/Enums/CabinCategory.php \
  app/Enums/ItineraryStatus.php \
  app/Models/Yacht.php \
  app/Models/Cabin.php \
  app/Models/Itinerary.php \
  app/Actions/Itineraries \
  app/Support/Itineraries \
  app/Policies/YachtPolicy.php \
  app/Policies/ItineraryPolicy.php \
  app/Http/Controllers/Rms/YachtController.php \
  app/Http/Controllers/Rms/ItineraryController.php \
  app/Http/Controllers/Rms/RatesController.php \
  app/Http/Requests/Rms/StoreItineraryRequest.php \
  app/Http/Requests/Rms/UpdateItineraryRequest.php \
  app/Http/Requests/Rms/StoreItineraryImageRequest.php \
  app/Http/Requests/Rms/Concerns/ValidatesItineraryContent.php \
  app/Http/Resources/Rms/YachtResource.php \
  app/Http/Resources/Rms/CabinResource.php \
  app/Http/Resources/Rms/ItineraryResource.php \
  app/Http/Resources/Rms/ItineraryDefaultsResource.php \
  app/Providers/AppServiceProvider.php \
  app/Services/Pricing/CabinPricer.php \
  app/Services/Pricing/QuoteInput.php \
  app/Services/Pricing/CabinCategory.php \
  database/migrations/2026_09_20_200013_create_yachts_table.php \
  database/migrations/2026_09_20_200014_create_cabins_table.php \
  database/migrations/2026_09_20_200015_create_itineraries_table.php \
  database/factories/YachtFactory.php \
  database/factories/CabinFactory.php \
  database/factories/ItineraryFactory.php \
  database/seeders/InventorySeeder.php \
  database/seeders/DemoInventorySeeder.php \
  database/seeders/DatabaseSeeder.php \
  routes/api/rms.php \
  tests/database/migrations/2026_09_20_000001_create_calendar_date_hosts_table.php \
  tests/Support/CalendarDate \
  tests/Feature/Inventory \
  tests/Feature/Database/DatabaseSetupTest.php \
  tests/Unit/Services/Pricing/CabinPricerTest.php
git commit -m "$(cat <<'EOF'
Add yachts, cabins, CalendarDate, and RMS itineraries.

Fixed inventory is seeded everywhere; demo itineraries stay local.
Calendar dates serialise as Y-m-d and never pass through Iso::utc().
EOF
)"
```

## Task 02 · Departures and generate season

### What was built
A departure is one yacht sailing one Sunday. RMS users create them one at a time or generate a season. Availability is not stored (task 03). Date/yacht locks and the claims delete guard are also task 03.

**Table `departures`:** `reference` (`DEP-NNN` from `ReferenceService`), `date` (`CalendarDate`), `yacht_id` / `itinerary_id` (`restrictOnDelete`), `status` (`ON_SALE` / `CLOSED` / `HIDDEN` / `CHARTER`), `urgency_threshold` (default 3), `waitlist_enabled` (default true), `public_note` (max 40), `festive`, unique `(yacht_id, date)`. Morph alias `departure`. `return_date` is `date + 7` days (OPS-001), never stored.

**Endpoints** under `/api/rms/departures`. View / history: `panel.rms`. Writes: `departures.manage`. Lucía reads; Mateo writes.

**Actions:** `CreateDeparture` (draws the reference in its transaction), `UpdateDeparture` (content → `departure.updated`; status → `departure.status_changed` separately), `DeleteDeparture` (no claims guard yet), `GenerateSeason`.

**Unique `(yacht_id, date)`:** FormRequest returns 422 on `date`. Create / Update / GenerateSeason also catch MySQL 1062 on `departures_yacht_id_date_unique`, then read the winning row after rollback and return the same 422 (`YachtDateConflict`). If the winner is gone, the parenthetical reference is omitted.

### ALT table used in the test
`runSeason` `(w + yi) % 2`, `yacht_ids` = ANAMARA then ANATIVA, festive window off, from `2028-01-02`:

- w0 `2028-01-02` — ANAMARA WEST / ANATIVA NORTH
- w1 `2028-01-09` — ANAMARA NORTH / ANATIVA WEST
- w2 `2028-01-16` — ANAMARA WEST / ANATIVA NORTH
- w3 `2028-01-23` — ANAMARA NORTH / ANATIVA WEST
- w4 `2028-01-30` — ANAMARA WEST / ANATIVA NORTH
- w5 `2028-02-06` — ANAMARA NORTH / ANATIVA WEST

Acceptance range `2028-01-02`–`2028-03-26` = 13 Sundays × 2 = 26, opposite routes each week. Festive window on → the two `2028-01-02` rows use `FEST`.

### Warning texts
Do not block; returned as a top-level `warnings` array on create/update.

- Twin not festive, this one is: `ANATIVA's departure on 19 Dec 2027 is not festive.`
- Twin is festive, this one is not: `ANATIVA's departure on 19 Dec 2027 is festive.`
- Festive departure + non-festive itinerary: `This departure is festive but itinerary WEST is not.`
- Non-festive departure + festive itinerary: `This departure is not festive but itinerary FEST is festive.`

Date copy matches prototype `fmtD` (`j M Y`, e.g. `7 Nov 2027`) via `App\Support\Dates\Format::calendar()`.

Sunday 422: `Anakata sails Sunday → Sunday. {date} is not a Sunday.`
Duplicate 422: `{YACHT} already has a departure on {date} ({DEP-NNN}).`

### `runSeason` behaviour that was unclear
- `yi` is the index in the **request’s** `yacht_ids`, not a fixed ANAMARA=0.
- The festive window is month/day only: 15–31 Dec or 1–2 Jan, any year — not a continuous season spanning a year boundary as a single interval.
- Festive overrides the pattern **after** ALT/WEST/NORTH is chosen (`if (isF) k = 'FEST'`).
- Existing `(yacht, date)` pairs are skipped, not errors. A concurrent insert that wins between the skip-check and the write 422s the whole generate and rolls it back.
- Generated rows use prototype defaults: threshold 3, waitlist on, empty note.
- First Sunday is on or after `from` (if `from` is already Sunday, it is used). Carbon has no `nextOrSame`; we branch on `dayOfWeek`.

### Demo seed
`DemoInventorySeeder` (local/testing) now also upserts the 16 seed-data departures by `(yacht_id, date)`, keeping `DEP-001`…`DEP-016`, then `ensureAtLeast(Departure, 16)`. Next create is `DEP-017`. `di` is ignored; the calendar date is `date`.

### Itinerary delete guard
`DELETE /api/rms/itineraries/{id}` is 409 `Used by 1 departure` / `Used by 3 departures`. Task 01 TODO removed.

### Files touched
- `app/Enums/DepartureStatus.php`, `SeasonPattern.php`
- `app/Models/Departure.php`; `Yacht.php` / `Itinerary.php` (relations); `Itinerary::departuresCount()` now real (`withCount` on the itinerary index)
- `app/Actions/Departures/CreateDeparture.php`, `UpdateDeparture.php`, `DeleteDeparture.php`, `GenerateSeason.php`
- `app/Actions/Itineraries/DeleteItinerary.php`
- `app/Support/Dates/Format.php`
- `app/Support/Departures/YachtDateConflict.php`, `Warnings.php`, `SeedMapper.php`
- `app/Support/History/History.php` (optional `$extraContext`; generate season sets `action: generate season`)
- `app/Policies/DeparturePolicy.php`
- `app/Http/Controllers/Rms/DepartureController.php`, `ItineraryController.php`
- `app/Http/Requests/Rms/StoreDepartureRequest.php`, `UpdateDepartureRequest.php`, `IndexDeparturesRequest.php`, `GenerateSeasonRequest.php`, `Concerns/ValidatesDepartureDate.php`
- `app/Http/Resources/Rms/DepartureResource.php` (task 03 seam: no `availability` / `engine_label` / `locks`)
- `app/Providers/AppServiceProvider.php` (morph alias)
- `database/migrations/2026_09_20_200016_create_departures_table.php`
- `database/factories/DepartureFactory.php`
- `database/seeders/DemoInventorySeeder.php`
- `routes/api/rms.php`
- `tests/Feature/Inventory/DepartureEndpointsTest.php`, `GenerateSeasonTest.php`, `DepartureHistoryTest.php`, `DemoInventorySeederTest.php`, `ItineraryDeleteGuardTest.php`
- `tests/Feature/Database/DatabaseSetupTest.php`

### Deviations
- Create/update JSON is assembled with `response()->json([…resource, 'warnings' => …])` so `warnings` sits next to the fields. Laravel `additional()` on a `$wrap = null` resource still nested the departure under `data`.
- Itinerary delete copy is pluralised (`1 departure` / `3 departures`); the task file said `{n} departures` even for n=1 (plan correction).

### Open questions
None.

### Notes for later
- Task 03 extends `DepartureResource` with availability, engine_label and locks, and adds the date/yacht lock plus the claims delete guard.
- ALT alternation restarts at each generated range (`w` counts from that range’s first Sunday), so a season generated in two parts can give a yacht the same route two weeks running at the join. That is the prototype’s behaviour; the departures can be edited afterwards.

### Quality
- anakata-api: `composer check` inside Docker — 320 tests (2222 assertions), Pint, Larastan OK.

### Git commands for the user

Do **not** run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Enums/DepartureStatus.php \
  app/Enums/SeasonPattern.php \
  app/Models/Departure.php \
  app/Models/Yacht.php \
  app/Models/Itinerary.php \
  app/Actions/Departures \
  app/Actions/Itineraries/DeleteItinerary.php \
  app/Support/Dates \
  app/Support/Departures \
  app/Support/History/History.php \
  app/Policies/DeparturePolicy.php \
  app/Http/Controllers/Rms/DepartureController.php \
  app/Http/Controllers/Rms/ItineraryController.php \
  app/Http/Requests/Rms/StoreDepartureRequest.php \
  app/Http/Requests/Rms/UpdateDepartureRequest.php \
  app/Http/Requests/Rms/IndexDeparturesRequest.php \
  app/Http/Requests/Rms/GenerateSeasonRequest.php \
  app/Http/Requests/Rms/Concerns/ValidatesDepartureDate.php \
  app/Http/Resources/Rms/DepartureResource.php \
  app/Providers/AppServiceProvider.php \
  database/migrations/2026_09_20_200016_create_departures_table.php \
  database/factories/DepartureFactory.php \
  database/seeders/DemoInventorySeeder.php \
  routes/api/rms.php \
  tests/Feature/Inventory \
  tests/Feature/Database/DatabaseSetupTest.php \
  docs/sprints/sprint-03/REPORT.md
git commit -m "$(cat <<'EOF'
Add RMS departures and generate-season.

Sunday sailings, one per yacht per date, with festive warnings
and the prototype ALT pattern. Demo seed keeps DEP-001–016.
EOF
)"
```

## Task 03 · Cabin claims, holds, availability

### What was built
One `cabin_claims` table is the only occupancy source (F1). The database refuses two active claims on the same cabin and departure via a generated `active_key`. Availability, calendar, layout, engine labels and KPIs are computed from it. Holds are claims with an expiry (F3). No real holders yet — tests use `claim_holder`; task 04 adds `internal_block`.

**Table `cabin_claims`:** FKs `departure_id` / `cabin_id` (`restrictOnDelete`), morph holder, `kind` (`BLOCK` / `HOLD` / `BOOKING`), `hold_type` (`WEB` / `REQUEST` / `AGENCY` / `CHARTER_QUOTE`, only for HOLD), `expires_at`, `released_at`, `release_reason` (`RELEASED` / `EXPIRED` / `CONVERTED` / `CANCELLED` / `MOVED`), generated `active_key`, unique on `active_key`, indexes `(departure_id, released_at)`, `(holder_type, holder_id)`, `(kind, expires_at)`, audit columns. Claims are never deleted: model `delete()` throws; MySQL `BEFORE DELETE` trigger `cabin_claims_prevent_delete`.

### Generated-column DDL
As created by MySQL 8:

```sql
`active_key` varchar(64) GENERATED ALWAYS AS (
  if((`released_at` is null), concat(`departure_id`, '-', `cabin_id`), NULL)
) STORED
UNIQUE KEY `cabin_claims_active_key_unique` (`active_key`)
```

Released rows store `active_key` NULL, so many released claims can share a (departure, cabin).

### ClaimService
The only writer. Must run inside the caller's transaction (same guard as `History` / `ReferenceService`).

`claim()` inside that transaction:

1. Plain `SELECT` (no locking clause) of ids of active `HOLD` rows with `expires_at` in the past for exactly `(departure_id, cabin_ids being claimed)`. If none, skip to insert.
2. For each id: `UPDATE … WHERE id = ? AND released_at IS NULL AND expires_at < now`. Write `hold.expired` on the holder only when the update affected 1 row.
3. Insert in cabin `sort` order. A 1062 on `cabin_claims_active_key_unique` is a real conflict → `CabinUnavailableException` (409, `{ message, unavailable }`). No retry.

`releaseExpired()` (the job) uses the same pattern: plain `SELECT` of up to 500 expired active HOLD ids, then PK `UPDATE` per id. No range `UPDATE` and no `FOR UPDATE`.

`convert()` is append-only: release the from-holder `CONVERTED`, then insert for the to-holder.

Every successful claim / release / convert / releaseExpired dispatches `AvailabilityChanged` (`ShouldDispatchAfterCommit`). No listener.

### Why not 1062-then-retry, and why not FOR UPDATE
Deviation from the task file's "on unique violation, release the expired hold and retry once":

- A failed insert takes a **shared** lock on the conflicting row. Releasing it afterwards needs an **exclusive** lock. Two concurrent claimers upgrading shared → exclusive deadlock (**1213**).
- `SELECT … FOR UPDATE` of the expired range also deadlocks in the usual case (no expired rows): InnoDB still takes **gap locks** on the scanned range. Two concurrent claimers of the same free cabin both hold the gap; both inserts wait on each other → **1213**.
- Updating by primary key takes record locks only.

The job uses the same unlocked SELECT + PK UPDATE so it cannot take gap locks that block or deadlock concurrent claims.

### Concurrency test outcome
`TruncatingTestCase` + `mysql_lock` + `innodb_lock_wait_timeout = 1`. Both races observed **1205** (lock wait timeout). Never 1213. Never two active rows.

| Case | Observed |
|---|---|
| Two claimers, same FREE cabin, no expired holds | 1205 |
| Two claimers, same expired hold | 1205 |

### Engine label precedence

| # | Condition | code | text | tone |
|---|---|---|---|---|
| 1 | status `HIDDEN` or itinerary not `PUBLISHED` | `NOT_SHOWN` | NOT SHOWN | wait |
| 2 | all 9 cabins `SOLD` under one holder | `CHARTERED` | CHARTERED — NOT SHOWN | wait |
| 3 | status `CLOSED` | `CLOSED` | CLOSED — ENQUIRE | comp |
| 4 | status `CHARTER` | `CHARTER` | PRIVATE CHARTER ONLY | pend |
| 5 | `free == 0` and `held > 0` (F9) | `LIMITED` | LIMITED AVAILABILITY | hold |
| 6 | `free == 0` | `FULL` | FULL · WAITLIST or FULL | canc |
| 7 | `free ≤ urgency_threshold` | `ONLY_N_LEFT` | ONLY N CABIN(S) LEFT | hold |
| 8 | otherwise | `AVAILABLE` | AVAILABLE | conf |

`LIMITED` tone is `hold` (warning). The prototype `engLabel` has no F9 rule. Expired but unreleased holds count as `FREE`.

KPIs (`meta.kpis`) follow prototype `renderDeps` over the **live** subset of the filtered list (`ON_SALE` and label not `NOT_SHOWN` / `CHARTERED`), not the current page: `on_sale_on_engine`, `cabins_bookable`, `showing_only_n_left`, `full`.

### Departure locks
- Date/yacht: locked while any **active** unexpired `HOLD` or `BOOKING` exists. Blocks do not lock. 409: `Date and yacht are locked — {n} cabin(s) sold or held on this departure. Move guests with "Move to another departure" on each booking first.`
- Delete: any claim row blocks, because claims are never deleted and `departure_id` is `restrictOnDelete`.
  - Active claims → 409 with counts (`2 blocked`, `1 held`, `1 sold` as present).
  - Only released claims → 409 `This departure has inventory history (released blocks or holds). Close or hide it instead.`
- Detail `locks: { date_and_yacht, delete, reason }`. `delete` is true in both claim cases.

### Endpoints
All `panel.rms`. `GET /api/rms/calendar?from&to&yacht_id` (default Galápagos today → +6 months, max 18 months). `GET /api/rms/departures/{id}/layout`. List `with_cabins=1`. Itinerary rows gain `live_departures_count`.

Job `inventory:release-expired-holds` every minute, `withoutOverlapping()`.

### Files touched
- `app/Enums/ClaimKind.php`, `HoldType.php`, `ReleaseReason.php`, `CabinState.php`, `EngineLabelCode.php`, `EngineLabelTone.php`
- `app/Models/CabinClaim.php`; `Departure.php` / `Itinerary.php` (snapshot, claims, live count)
- `app/Services/Inventory/ClaimService.php`, `Availability.php`
- `app/Support/Inventory/EngineLabel.php`, `DepartureSnapshot.php`, `DepartureLocks.php`, `Snapshots.php`
- `app/Exceptions/CabinUnavailableException.php`
- `app/Events/AvailabilityChanged.php`
- `app/Console/Commands/ReleaseExpiredHoldsCommand.php`
- `app/Actions/Departures/UpdateDeparture.php`, `DeleteDeparture.php`
- `app/Http/Controllers/Rms/DepartureController.php`, `ItineraryController.php`, `CalendarController.php`
- `app/Http/Requests/Rms/IndexDeparturesRequest.php`, `IndexCalendarRequest.php`
- `app/Http/Resources/Rms/DepartureResource.php`, `ItineraryResource.php`
- `database/migrations/2026_09_20_200017_create_cabin_claims_table.php`
- `routes/api/rms.php`, `routes/console.php`
- `tests/TestCase.php`, `tests/TruncatingTestCase.php`
- `tests/Support/Inventory/ClaimHolder.php`
- `tests/database/migrations/2026_09_20_000002_create_claim_holders_table.php`
- `tests/Feature/Inventory/CabinClaimsTest.php`, `AvailabilityEndpointsTest.php`, `DepartureLocksTest.php`, `ReleaseExpiredHoldsTest.php`
- `tests/Feature/Database/DatabaseSetupTest.php`
- `tests/Unit/Support/Inventory/EngineLabelTest.php`
- `tests/Concurrency/ClaimServiceConcurrencyTest.php`

### Deviations
- Expired-hold cleanup is a plain SELECT of ids then PK UPDATE, then insert — not "1062 then retry once", and not `SELECT … FOR UPDATE`. Same pattern on the job. See above.
- Delete checks **all** claim rows, not only active ones, so a released block 409s instead of 500 on the FK.
- `LIMITED` tone `hold` is assigned here; the prototype has no pill for F9.
- Engine label codes for CLOSED / CHARTER / FULL / ONLY_N_LEFT / AVAILABLE are derived from the task texts (the task only named `NOT_SHOWN`, `CHARTERED`, `LIMITED`).

### Open questions
For Sprint 4 (TEC-004 / hold durations):
- (a) What "business hours" means for TEC-004 holds (days, hours, Galápagos public holidays?).
- (b) Where "near-term" ends and "long-lead" begins (48 business hours vs 5 business days).

### Notes for later
- Task 04 is the first real holder (`internal_block`) and the demo S7–S8 block on 14 Nov 2027 ANAMARA. Calendar is all `FREE` until then.
- Task 07 maps `engine_label.tone` → pill class and reads `meta.kpis` / `locks`.
- Task 08 maps `FREE` / `HELD` / `SOLD` / `BLOCKED` → calendar cell classes; booking sub-states stay Sprint 4.

### Quality
- anakata-api: `composer check` inside Docker — 352 tests (2600 assertions), Pint, Larastan OK.

### Git commands for the user

Do **not** run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Enums/ClaimKind.php \
  app/Enums/HoldType.php \
  app/Enums/ReleaseReason.php \
  app/Enums/CabinState.php \
  app/Enums/EngineLabelCode.php \
  app/Enums/EngineLabelTone.php \
  app/Models/CabinClaim.php \
  app/Models/Departure.php \
  app/Models/Itinerary.php \
  app/Services/Inventory \
  app/Support/Inventory \
  app/Exceptions/CabinUnavailableException.php \
  app/Events/AvailabilityChanged.php \
  app/Console/Commands/ReleaseExpiredHoldsCommand.php \
  app/Actions/Departures/UpdateDeparture.php \
  app/Actions/Departures/DeleteDeparture.php \
  app/Http/Controllers/Rms/DepartureController.php \
  app/Http/Controllers/Rms/ItineraryController.php \
  app/Http/Controllers/Rms/CalendarController.php \
  app/Http/Requests/Rms/IndexDeparturesRequest.php \
  app/Http/Requests/Rms/IndexCalendarRequest.php \
  app/Http/Resources/Rms/DepartureResource.php \
  app/Http/Resources/Rms/ItineraryResource.php \
  database/migrations/2026_09_20_200017_create_cabin_claims_table.php \
  routes/api/rms.php \
  routes/console.php \
  tests/TestCase.php \
  tests/TruncatingTestCase.php \
  tests/Support/Inventory \
  tests/database/migrations/2026_09_20_000002_create_claim_holders_table.php \
  tests/Feature/Inventory/CabinClaimsTest.php \
  tests/Feature/Inventory/AvailabilityEndpointsTest.php \
  tests/Feature/Inventory/DepartureLocksTest.php \
  tests/Feature/Inventory/ReleaseExpiredHoldsTest.php \
  tests/Feature/Database/DatabaseSetupTest.php \
  tests/Unit/Support/Inventory \
  tests/Concurrency/ClaimServiceConcurrencyTest.php \
  docs/sprints/sprint-03/REPORT.md
git commit -m "$(cat <<'EOF'
Add cabin claims, computed availability, and departure locks.

The database unique active_key is the double-booking guard.
Expired holds are released by PK update before insert, never after a 1062.
EOF
)"
```

## Task 04 · Internal blocks; Sprint 2 follow-ups

### What was built
`InternalBlock` is the first real claim holder. Staff take cabins off sale (`FAM_TRIP` / `MAINTENANCE` / `NEGOTIATION_HOLD` / `COURTESY`) through `ClaimService::claim(..., ClaimKind::Block)` in one transaction. Availability, calendar and layout already map `BLOCK` → `BLOCKED`; this task adds the write path, `/api/rms/blocks`, the demo fam-trip row, and the three Sprint 2 departure-aware config checks.

**Table `internal_blocks`:** `reference` (`BLK-NNN`, `ReferenceType::Block`, pad 3, global counter), `reason`, `notes` (max 500), `released_at` / `released_by` / `release_note`, audit columns. Morph alias `internal_block`. Blocks are never deleted (`delete()` throws; no DELETE route). Scope never changes after create — release and make a new block.

**Actions** (one history row each):
- `CreateInternalBlock` — 1–20 departures, cabins `S1`–`S8` / `OWNER` or `ALL`. All-or-nothing. Past departure → 422. Conflict → 409 with the full list (`"Suite 02 on 14 Nov 2027 · ANATIVA is held."`) plus `unavailable[]`. After the first unique-index miss, remaining requested pairs are read with a **plain SELECT** (no `FOR UPDATE` / lock in share mode) so the message lists every collision without bringing back gap locks. History `block.created` with the scope.
- `ReleaseInternalBlock` — `ClaimService::release(..., RELEASED)`, sets released fields. Second release → 409 `This block is already released.` History `block.released`.
- `UpdateInternalBlockNotes` — reason and notes only. No-op writes no history. Event `block.updated`. Allowed on released blocks (task 04 does not restrict; the panel can hide the form).

**Endpoints** under `/api/rms/blocks`. Viewing needs `panel.rms`; writes need `blocks.manage`. `GET /` filters `status=active|released|all` (default active), `from` / `to` (departure dates of claims), `yacht_id`. Each row carries `scope_summary`, reason, notes, created/released actors, and claims. `POST /`, `PATCH /{block}`, `POST /{block}/release`, `GET /{block}/history`.

**Demo seed (F6, local/testing):** `BLK-001`, FAM_TRIP, ANAMARA S7–S8 on 14 Nov 2027 (`DEP-003`), notes `Virtuoso agents fam — 4 pax`, created by System. Next live block is `BLK-002`.

**Sprint 2 follow-ups** live in injected `DepartureConfigChecks` (not static `rules()` / not `app()` inside documents). Wired into `ConfigValidator` and `ConfigPublisher`. Documents stay unit-testable; the three `TODO(Sprint 3)` comments are gone.

### Scope-summary rules
Pure `App\Support\Blocks\ScopeSummary`. Groups claims by date then yacht code. One group: `{YACHT} · {cabins} · {j M Y}`. Several groups joined with `"; "`.

| Cabins | Label |
|---|---|
| All 9 (`S1`–`S8` + `OWNER`) | `Full yacht` |
| Consecutive suites | `Suite 07–08` (en-dash, zero-padded) |
| Gapped suites | `Suite 01, Suite 03` |
| Owner | `Owner's Suite` (after suites) |
| Mixed | `Suite 07–08, Owner's Suite` |

Examples: `ANATIVA · Suite 07–08 · 14 Nov 2027`, `ANAMARA · Full yacht · 31 Oct 2027`.

### Seed-data inconsistencies
- Prototype static `v-block` table shows **ANATIVA** · Suite 07–08 · 14 Nov 2027. Prototype `occ()` and this seed use **ANAMARA** S7–S8 on that date (matches the calendar).
- Prototype maintenance row (ANAMARA · Full yacht · 31 Oct 2027) has **no** matching departure in `seed-data.json`. Not seeded.

### Exact texts of the new rates and engine checks

| Check | When | Path | Text |
|---|---|---|---|
| Rates error | A published year is removed and that year still has departures | `years` (publish: `document.years`) | `Can't remove {year} — {n} departures sail that year.` |
| Rates warning | Departures exist in a year that is not in the draft rates | `years` | `Departures in {year} have no rates.` |
| Engine warning | `calendar.default_search_from` is before the first bookable month (`ON_SALE` and engine label not `NOT_SHOWN` / `CHARTERED`) | `calendar.default_search_from` | `Default search starts before the first bookable month (Nov 2027) — guests would open on empty months.` |

Removing a year with zero departures is fine. With the demo seed, publishing rates without 2027 is refused (16 departures). Fresh seed first bookable month is `2027-11`.

**OPS-006:** `source_display` unchanged (`Sales open 1 Nov 2026 · first cruise 7 Nov 2027`). PRO-001 note kept. Current display is `First cruise 7 Nov 2027` when the earliest departure is 7 Nov 2027 (`differs: false`); any other first date (`differs: true`); no departures → `No departures yet` / `differs: null`.

### Files touched
- `.cursor/rules/laravel.mdc`
- `app/Enums/BlockReason.php`, `app/Enums/ReferenceType.php`
- `app/Models/InternalBlock.php`
- `app/Actions/Blocks/CreateInternalBlock.php`, `ReleaseInternalBlock.php`, `UpdateInternalBlockNotes.php`
- `app/Support/Blocks/ScopeSummary.php`, `ConflictMessage.php`
- `app/Policies/InternalBlockPolicy.php`
- `app/Http/Controllers/Rms/InternalBlockController.php`
- `app/Http/Requests/Rms/IndexInternalBlocksRequest.php`, `StoreInternalBlockRequest.php`, `UpdateInternalBlockRequest.php`, `ReleaseInternalBlockRequest.php`
- `app/Http/Resources/Rms/InternalBlockResource.php`
- `app/Services/Config/DepartureConfigChecks.php`, `ConfigValidator.php`, `ConfigPublisher.php`
- `app/Support/BusinessRules/Registry.php`
- `app/Support/Config/Documents/RatesDocument.php`, `EngineSettingsDocument.php`
- `app/Providers/AppServiceProvider.php`
- `database/migrations/2026_09_20_200018_create_internal_blocks_table.php`
- `database/seeders/DemoInventorySeeder.php`
- `routes/api/rms.php`
- `tests/Feature/Inventory/InternalBlocksTest.php`, `AvailabilityEndpointsTest.php`, `DemoInventorySeederTest.php`
- `tests/Feature/Config/DepartureConfigChecksTest.php`
- `tests/Feature/References/ReferenceServiceTest.php`
- `tests/Feature/Database/DatabaseSetupTest.php`
- `tests/Unit/Support/Blocks/ScopeSummaryTest.php`
- `docs/sprints/sprint-03/REPORT.md`

### Deviations
- Collision scan after the first unique-index miss is a plain SELECT (approved in the plan). A locking read would bring back the gap locks task 03 removed.
- Notes/reason PATCH is allowed on released blocks. Task 04 does not forbid it; task 09 can hide the form.
- Multi-group `scope_summary` joins groups with `"; "`. Conflict lines join with a space (each already ends with a period).

### Open questions
None.

### Notes for later
- Task 09 panel: `/rms/operations/blocks`, 409 modal, “To change cabins or dates, release this block and create a new one.”
- Task 10 E2E: `INV-*` including calendar BLOCKED cells and rates-without-2027.
- First bookable month ignores the day (month only), matching prototype `ymd.slice(0,7)`.
- Demo inventory is fixed in Nov–Dec 2027 and `ClaimService` refuses past departures, so after **14 Nov 2027** the demo block (and the e2e reset) will fail to seed. Before then, make demo dates relative to “today” or skip past-dated demo claims.

### Quality
- anakata-api: `composer check` inside Docker — 370 tests (2569 assertions), Pint, Larastan OK.

### Git commands for the user

Do **not** run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  .cursor/rules/laravel.mdc \
  app/Enums/BlockReason.php \
  app/Enums/ReferenceType.php \
  app/Models/InternalBlock.php \
  app/Actions/Blocks \
  app/Support/Blocks \
  app/Policies/InternalBlockPolicy.php \
  app/Http/Controllers/Rms/InternalBlockController.php \
  app/Http/Requests/Rms/IndexInternalBlocksRequest.php \
  app/Http/Requests/Rms/StoreInternalBlockRequest.php \
  app/Http/Requests/Rms/UpdateInternalBlockRequest.php \
  app/Http/Requests/Rms/ReleaseInternalBlockRequest.php \
  app/Http/Resources/Rms/InternalBlockResource.php \
  app/Services/Config/DepartureConfigChecks.php \
  app/Services/Config/ConfigValidator.php \
  app/Services/Config/ConfigPublisher.php \
  app/Support/BusinessRules/Registry.php \
  app/Support/Config/Documents/RatesDocument.php \
  app/Support/Config/Documents/EngineSettingsDocument.php \
  app/Providers/AppServiceProvider.php \
  database/migrations/2026_09_20_200018_create_internal_blocks_table.php \
  database/seeders/DemoInventorySeeder.php \
  routes/api/rms.php \
  tests/Feature/Inventory/InternalBlocksTest.php \
  tests/Feature/Inventory/AvailabilityEndpointsTest.php \
  tests/Feature/Inventory/DemoInventorySeederTest.php \
  tests/Feature/Config/DepartureConfigChecksTest.php \
  tests/Feature/References/ReferenceServiceTest.php \
  tests/Feature/Database/DatabaseSetupTest.php \
  tests/Unit/Support/Blocks \
  docs/sprints/sprint-03/REPORT.md
git commit -m "$(cat <<'EOF'
Add internal blocks and departure-aware config checks.

Staff take cabins off sale through the claim table; rates and engine
settings now refuse or warn using the departure calendar.
EOF
)"
```
