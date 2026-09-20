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
