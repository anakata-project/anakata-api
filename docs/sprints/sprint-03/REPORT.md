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
