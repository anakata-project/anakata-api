# Task 01 · anakata-api · Yachts, cabins, the calendar-date cast, itineraries
**Repo:** anakata-api · **Sprint:** 3 (read `README.md` in this folder first)

## Goal
The fixed inventory (two yachts, nine cabins each) exists everywhere. Calendar dates have their own cast. Itineraries are full content records that the RMS edits and publishes, with the prototype's completeness score and publish rules.

## Read first
- `docs/requirements/08-dev-decisions.md`: D5, D6, **F4, F5, F6**
- `docs/requirements/02-data-model.md`: Itinerary; `01-functional-spec.md` §3 and §14
- `prototype/rms_index.html`: `mkItin`, `itinChecks`, `drawItinEditor`, `readItin`, `saveItin`, `delItin`, `itinImg`
- `docs/requirements/examples/seed-data.json` → `itineraries` (prototype keys: `k`, `desc`, `long`, `hi`, `inc`, `exc`, `plan`, `metaT`, `metaD`, `nDays`…)
- `.cursor/rules/laravel.mdc` (Actions, History, References, audit columns)

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Roadmap.** In `docs/sprints/ROADMAP.md`, move "holds, waitlist" and the "Holds & Waitlist" page from Sprint 3 to Sprint 4 (F8). Nothing else.
2. **Calendar-date cast (F5).**
   - Add `App\Casts\CalendarDate`: stores `Y-m-d` and reads as `CarbonImmutable` at midnight, **with no time zone conversion**.
   - A model's `toArray()` / JSON must emit `"2027-11-07"`, never the `Iso::utc()` timestamp. Check how `SerializesDatesAsUtc::serializeDate()` interacts with casts and make the date win (for example, the cast returns a value object that serialises itself, or the model's `$dates`/casts path skips `serializeDate`).
   - Test: a model with a `CalendarDate` attribute serialises as `YYYY-MM-DD` in `toArray()`, in a resource, and through `json_encode`, whatever the app and database time zones.
   - Add one line to the "Dates" guidance in `laravel.mdc`: date-only fields use `CalendarDate`, timestamps use `Iso::utc()`.
3. **Yachts and cabins (F4).**
   - Tables:
     - `yachts`: `id`, `code` (unique: `ANAMARA`, `ANATIVA`), `name`, audit columns, timestamps
     - `cabins`: `id`, `yacht_id` FK, `code` (`S1`…`S8`, `OWNER`), `label` (`Suite 01`…`Suite 08`, `Owner's Suite`), `category` (enum `SUITE` / `OWNER`), `sort` (1–9), audit columns, timestamps; unique `(yacht_id, code)`
   - Models `Yacht` (`cabins()`, `departures()` later) and `Cabin` (`yacht()`), with `HasAuditColumns` + `SerializesDatesAsUtc` as usual. No morph aliases; they're never history subjects.
   - `CabinCategory` already exists as `App\Services\Pricing\CabinCategory` (Sprint 2). Move it to `App\Enums\CabinCategory`, update the pricer's imports, and use the same enum for cabins. There must be one enum, not two.
   - `InventorySeeder` runs in **every environment**, is idempotent (`firstOrCreate` by code), and is called from `DatabaseSeeder`.
   - Read-only endpoint `GET /api/rms/yachts`: yachts with their cabins in `sort` order, for any `panel.rms` user. There are no write endpoints.
4. **Itineraries.** Table `itineraries`, columns from doc 02, snake_case:
   - `id`, `code` (unique, immutable after creation; `WEST`, `NORTH`, `FEST`), `name`, `status` (enum `PUBLISHED` / `DRAFT` / `HIDDEN`, default `DRAFT`), `sort_order`, `festive` (bool)
   - `days`, `nights`, `embark`, `disembark`, `tagline`
   - `hero_image_path` (nullable), `hero_alt`, `fallback_gradient`
   - `card_description`, `overview`, `long_description`
   - JSON lists: `highlights`, `chips`, `facts` (list of `[label, value]`, max 6), `day_plan` (list of `[title, text]`), `included`, `excluded`, `faqs` (list of `[question, answer]`)
   - `slug` (unique, nullable), `meta_title`, `meta_description`
   - audit columns, timestamps

   Model `Itinerary`, with morph alias `itinerary` for history.
5. **Completeness and publish rules**, mirroring `itinChecks`. Put them in `App\Support\Itineraries\Completeness`, used by the resource and by the publish Action:
   - Checks, in the prototype's order: name, card description, day-by-day plan, days/nights, hero photo, highlights, long description, includes, excludes, FAQs, URL slug, SEO title, SEO description.
   - The first four are **blocking**: an itinerary can't be published while any is missing.
   - It returns `{ pct, missing: [...], blocking: [...] }`, with `pct` rounded as the prototype does.
6. **Actions and endpoints** under `/api/rms/itineraries`. Viewing needs `panel.rms`; changes need `itineraries.manage`.

   | Method · path | Does |
   |---|---|
   | `GET /` | list, `sort_order` then name, with `completeness` and `departures_count` |
   | `GET /defaults` | the `mkItin` defaults for a new itinerary (so the panel doesn't copy them) |
   | `GET /{itinerary}` | full record + completeness |
   | `POST /` | create (status `DRAFT`); `code` required, `^[A-Z0-9_]{2,16}$`, unique |
   | `PATCH /{itinerary}` | update any field except `code`; changing `status` to `PUBLISHED` runs the blocking checks → 422 listing the missing items |
   | `POST /{itinerary}/image` | upload the hero image (jpg/png/webp, max 4 MB as in the prototype; ~2400 px wide recommended, not enforced), stored on the `public` disk under `itineraries/`, replacing the previous file |
   | `DELETE /{itinerary}` | refused (409) while any departure uses it (after task 02); otherwise deleted |
   | `GET /{itinerary}/history` | its `change_history` |

   - Each mutation is an Action writing history: `itinerary.created`, `itinerary.updated` (diff), `itinerary.published`, `itinerary.hidden`, `itinerary.image_replaced`, `itinerary.deleted`.
   - The prototype allows deletion by Admin only; here `itineraries.manage` + no departures is enough. Note it as a deviation.
   - Validation limits, **as in the prototype editor** (`drawItinEditor`):
     - name 40
     - code 2–10 characters (`^[A-Z0-9_]{2,10}$`)
     - card description 220
     - meta title 60, meta description 155
     - slug `^[a-z0-9-]+$`
     - days and nights each 1–30
     - `facts` exactly 6 pairs (doc 02 `facts[6][2]`)
     - `fallback_gradient` one of the prototype's `GRADS` values (store the key name, e.g. "Western (slate)", and have the resource return the CSS gradient)
     - list items non-empty
   - **New itinerary defaults** from `mkItin`: embark and disembark "San Cristóbal (SCY)", 8 days / 7 nights, the standard chips, overview, facts, includes, excludes and FAQs (`STD_*` constants). Put them in one PHP class so the defaults are data in one place.
   - The resource returns `hero_image_url` (a public URL) instead of the path.
   - `php artisan storage:link` must be part of the setup: add it to the README and to the e2e `up.sh` if missing.
7. **Demo seed (F6).** `DemoInventorySeeder` runs in `local` / `testing` only. It maps the three seed-data itineraries to the new columns (key map in a test, as in Sprint 2) and is idempotent by `code`. Seeded `img` values are empty, so `hero_image_path` stays null.
8. **Tests:**
   - `InventorySeeder` gives 2 yachts × 9 cabins, idempotently
   - the cast serialisation tests (step 2)
   - completeness checks and the blocking rule (publish refused with the missing list)
   - `code` immutable
   - image upload validation (fake storage)
   - the delete guard (after task 02; for now a test marked with the Sprint 3 task 02 TODO, or add it in task 02)
   - permissions: Lucía views (200) and can't write (403); Mateo writes
   - history events
   - the seed-data mapping

## Out of scope
Departures (task 02). The engine feed of itineraries (engine sprint). The itinerary preview.

## Acceptance criteria
- [ ] `migrate:fresh --seed` locally gives 2 yachts, 18 cabins and 3 published itineraries. On a production-like seed, yachts and cabins only.
- [ ] Publishing an itinerary with no day plan returns 422 naming "day-by-day plan".
- [ ] Every calendar date in the API is `YYYY-MM-DD`.
- [ ] `composer check` passes; the endpoints are typed in `/docs/api`.
- [ ] A "Task 01" section in `REPORT.md` covering:
  - how the cast bypasses the UTC serializer
  - the seed-data key map
  - the delete-permission deviation
  - where the prototype's `STD_*` defaults were copied from
