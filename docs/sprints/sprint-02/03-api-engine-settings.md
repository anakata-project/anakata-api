# Task 03 · anakata-api · Engine settings
**Repo:** anakata-api · **Sprint:** 2 (read `README.md` in this folder first)
**Needs:** tasks 01 and 02.

## Goal
Every guest rule, sales-calendar setting, Galápagos fee amount and piece of copy the booking engine shows lives in one versioned document. Admins edit the rule fields; Managers edit the copy (E6).

## Read first
- `docs/requirements/02-data-model.md`: "Engine settings", "PNG entry fee"
- `docs/requirements/03-business-rules.md`: OPS-002, OPS-004, FIN-004, the language decision, and guests per cabin
- `docs/requirements/04-booking-engine-contract.md`: `settings` in the feed, sync rule 8, "Changes the engine must make"
- `docs/requirements/08-dev-decisions.md`: B1 and B6 (the engine prototype predates the 12 Sep decisions; the PNG fee rules), E1–E4, **E6**
- `prototype/rms_index.html`: `ES`, `ES_RULES` (the rule fields), `ES_COPY` (the copy fields), `buildESet()`, `esIssues()`
- `docs/requirements/examples/seed-data.json` → `engine_settings`

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **`EngineSettingsDocument`** (task 01 contract). Snake_case keys; the initial values come from `seed-data.json`:
   ```json
   {
     "guests": {
       "max_per_cabin": 3, "max_per_yacht": 16,
       "child_min_age": 6, "child_max_age": 17,
       "adult_required_with_children": true,
       "under_age_message": "Under 6 not accommodated"
     },
     "calendar": { "default_search_from": "2027-11", "default_search_to": "2028-01", "default_adults": 2, "horizon_months": 24 },
     "locale": { "default": "en", "live": ["en"], "currency": "USD" },
     "fees": {
       "tct_pp": 20,
       "png": {
         "foreign_over_12": 200, "foreign_12_and_under": 100,
         "can_adult": 100, "can_minor": 30,
         "national_or_resident": 30, "exempt_under_age": 2
       },
       "show_in_price_panel": true,
       "footnote": "…"
     },
     "copy": {
       "book_now_pay_later": "…", "traveling_with_children": "…", "solo_and_triple": "…",
       "pay_today": "…", "details_note": "…",
       "confirmation_steps": ["…", "…", "…"]
     },
     "charter": {
       "headline": "…", "intro": "…", "itinerary_label": "Customizable",
       "response_sla_hours": 24,
       "group_contexts": ["Family", "Friends", "Corporate / Incentive", "Celebration"],
       "thank_you": "…"
     }
   }
   ```
   - **PNG fees: the full FIN-004 table, not the prototype's two amounts.** The prototype stores only the foreign adult and child fees. Doc 02 and doc 03 define six categories:
     - foreign visitor over 12: 200
     - foreign visitor 12 and under: 100
     - CAN (Andean Community: Colombia, Peru, Bolivia) adult: 100
     - CAN minor: 30
     - Ecuadorian nationals and residents, any age: 30
     - under 2: exempt

     The guests sprint computes the fee per guest from this table. The price panel shows the two foreign amounts, as in the prototype.
   - **`locale` is read-only.** English only is an Anakata decision. The document carries it for the engine feed, but `rules()` pins `default` to `en`, `live` to `["en"]` and `currency` to `USD`, and the page shows it as a static table as the prototype does.
   - **Errors**, mirroring `esIssues()`:
     - `max_per_cabin` 1–4, `max_per_yacht` 1–36
     - `child_min_age` ≤ `child_max_age`, both 0–17
     - `default_adults` 1–16 and ≤ `max_per_cabin` × 9
     - `horizon_months` 6–36
     - search months are `YYYY-MM` with from ≤ to
     - fee amounts are integers ≥ 0, `exempt_under_age` 0–12
     - `response_sla_hours` 1–72
     - copy fields are non-empty, max 320 characters (headline 60, itinerary label 30, under-age message 60)
     - `confirmation_steps` has exactly 3 items
     - `group_contexts` has 1–8 non-empty unique items
     - carry over any other checks in `esIssues()` and list them in the report
   - **Warnings:** `max_per_yacht` ≠ 16 (OPS-002 says 16), and any `esIssues()` warnings.
   - **Labels** as in the prototype's `ES_LABEL`.
2. **Rule fields vs copy fields (E6).** The document class declares `copyPaths()`:
   - `fees.footnote`
   - everything under `copy.*`
   - `charter.headline`, `charter.intro`, `charter.itinerary_label`, `charter.group_contexts`, `charter.thank_you`

   Every other path is a rule field. This matches the prototype's `ES_RULES` / `ES_COPY` split; note any difference in the report.
3. **New permission `engine_copy.manage`**, label "Edit engine copy", group `commercial`, not a flag.
   - Add the enum case. The enum docblock requires a default decision for Manager and Sales Exec: **Manager yes, Sales Exec no.**
   - `SystemRole::defaultPermissions()` gains it for Manager.
   - `RolesSeeder` never overwrites existing roles, so add a **data migration** that grants it to the existing `manager` role, if present, without touching anything else. Write a history row `role.updated` (actor System, reason "Sprint 2: new permission engine_copy.manage"). Down removes it.
   - Update the Sprint 1 enum tests that pin the case list and the Manager defaults.
4. **Table and model:** `engine_settings_versions`, model `EngineSettingsVersion`, morph alias `engine_settings_version`.
5. **Publishing rights and the approval reference.** Compute the changed paths between the submitted document and the current version.
   - A rule path changed → the actor needs `engine_settings.manage`, and the approval reference is **required**.
   - Only copy paths changed → the actor needs `engine_copy.manage` **or** `engine_settings.manage`, and the approval reference is optional.
   - Otherwise → 403 with a message naming the problem: "This change includes rule fields (Max guests per cabin). Only users who can edit engine rules can publish it."
   - Implement it in the policy with the changed-path set as the argument. Actions don't check it.
6. **Endpoints**, mounted under `/api/rms/engine-settings` with task 01's routes.
   - Viewing: any user with `panel.rms`.
   - `POST /versions`: the rule in step 5.
   - `GET /` also returns `copy_paths`, so the panel can lock fields the user can't edit.
   - `POST /validate` also returns `rule_fields_changed: bool`, so the panel knows whether the approval reference is required and whether this user may publish.
7. **Seeding.** Map the prototype's seed values to the new keys; assert the mapping against `seed-data.json` in a test, as in task 02. The PNG categories the prototype lacks use the doc 02 values above.
   - **The seeded fee footnote is out of date.** It says the PNG fee "is paid at SCY airport", but since 12 Sep 2026 the guest chooses whether Anakata collects it. Keep the seed text unchanged, and list it under Open questions as copy to review with the client.
8. **Tests:**
   - validation errors and warnings
   - `copyPaths()` classification
   - Manager publishes a copy-only change with no approval reference → 201
   - Manager publishes a change including `guests.max_per_cabin` → 403 with the message
   - Admin publishes a rule change without an approval reference → 422
   - Sales Exec publishes anything → 403
   - the data migration grants `engine_copy.manage` to an existing Manager role, and is idempotent
   - the locale pins reject `"es"`

## Out of scope
The engine feed and push (engine API sprint). The per-guest PNG calculation (guests sprint). The engine previews on the settings page (panel task 08 leaves placeholders).

## Acceptance criteria
- [ ] `GET /api/rms/engine-settings` returns version 1 with the full FIN-004 fee table.
- [ ] Mateo can publish a copy change without a reference. Mateo cannot publish a guest-rule change.
- [ ] `composer check` passes.
- [ ] A "Task 03" section in `REPORT.md` covering:
  - the rule/copy path list
  - every validation check carried over from `esIssues()`
  - the permission migration
  - the footnote open question
