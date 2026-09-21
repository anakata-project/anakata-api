# Sprint 9 · Report
Each task appends its section below.

## Task 01 · Contacts as CRM records; the derived fields

The `contacts` table is now the CRM people record. Staff can edit a small set of owned fields. Lifecycle, lifetime value, segment and the marketing-consent summary are computed in SQL from the booking tables and the consent log, and are never stored.

### Owned fields
New columns on `contacts`: `type` (`DIRECT_PASSENGER · TRAVEL_AGENT · CORPORATE_CHARTER`, default DIRECT_PASSENGER), `language` (ISO 639-1, default `en`), `phone_e164` (nullable, task 02), `first_touch` / `last_touch` (nullable JSON, task 03). Name, email, phone, country and preferred channel stay.

`ResolveContact` accepts an optional type hint. It never downgrades: a hint only applies on create, or when the existing type is DIRECT_PASSENGER. TRAVEL_AGENT and CORPORATE_CHARTER are not swapped.

Defaults at creation:
- `RegisterAgency` and the demo agency seeder resolve a people row as TRAVEL_AGENT (agency `contact` is still a string on `agencies`; this is the agency contact of an agency).
- `CreateCharterEnquiry` sets CORPORATE_CHARTER.
- Everything else stays DIRECT_PASSENGER.

`language` is stored for information; every customer message stays in English (L10).

### Derived fields (L2)
`Contact::scopeWithDerived()` adds SQL aggregates so a list never loads bookings row by row. Fragments live in `App\Support\Crm\ContactDerived`.

- **Lifetime value:** `SUM(Booking::chargesTotalSql())` of non-deleted bookings in CONFIRMED, FULLY_PAID, ON_BOARD, COMPLETED, OVERDUE. A cancelled booking drops out by itself.
- **Segment:** HIGH above `crm.segment_high_ltv`, MID from `crm.segment_mid_ltv`, else NEW. Thresholds come from `CurrentConfig`, never literals in the SQL.
- **Lifecycle** — one `CASE`, this order:
  1. **AGENT** — TRAVEL_AGENT whose agency email matches `contacts.email` and `agencies.status = APPROVED` (RMS “ACTIVE”).
  2. **GUEST** — ON_BOARD, or a sold booking (CONFIRMED, FULLY_PAID, OVERDUE) whose cruise is under way: `departures.date <= today` and return date `>= today`.
  3. **BOOKED** — a sold booking whose departure is still ahead.
  4. **SQL** — REQUESTED, PENDING_PAYMENT or ON_HOLD_AGENCY.
  5. **PAST_GUEST** — a COMPLETED booking, or a sold booking whose cruise has ended (return date before today), and nothing ahead. Return date is the same SQL as `Departure::returnDate()`: `DATE_ADD(date, INTERVAL IF(nights > 0, nights, 7) DAY)`.
  6. **MQL** — `(FALSE /* TODO(task 03) identified behaviour */ OR latest MARKETING consent accepted)`.
  7. **PROSPECT**.

Dates are Galápagos calendar days (`BusinessTime::now()`). Soft-deleted bookings are excluded.

Until Sprint 11 automates ON_BOARD / COMPLETED, a departed booking often stays CONFIRMED / FULLY_PAID. The mid-cruise GUEST window and the return-date PAST_GUEST rule keep those contacts off PROSPECT. Tests: FULLY_PAID departed yesterday / returns in six days → GUEST; FULLY_PAID returned yesterday → PAST_GUEST; that plus a new REQUESTED → SQL.

**Consent summary:** `ContactConsentSummary` — `marketing` is the latest MARKETING row across the contact’s bookings (accepted, not withdrawn); `transactional` is always true. Sprint 10 replaces this with the consent register. **NPS** is always `null`.

### Shape change
`crm.segment_high_ltv` (20000) and `crm.segment_mid_ltv` (8000), PENDING CLIENT, source L2 / prototype `segOf`. DML migration publishes as System. Registry: two `here()` rows, new `RuleGroup::Crm`. Counts: all 67→69, here 42→44, differs_or_flagged 21→23. `anakata:config-verify` fails on a latest document missing `crm.*`, then passes after `up()`.

### Endpoints
Sanctum, `panel.crm`, `crm.sensitive` on every CRM route.

- `GET /api/crm/contacts` — filters `type`, `lifecycle`, `main_channel`, `channel_of_origin` (first non-deleted booking), `consent` (`marketing` / `transactional_only`), `q` (name, email, phone). Paginated, `per_page` default 50. Derived fields on every row. `meta.filters.type` and `meta.filters.lifecycle` are `{ value, label }` from the enums.
- `GET /api/crm/contacts/{contact}` — owned + derived + first-booking attribution + bookings (`id`, `display_reference`, departure date, status, `charges_total`, `balance`, `can_act`). No guests, payments, notes or sensitive keys.
- `PATCH /api/crm/contacts/{contact}` — owned fields only: name, email, phone, country, language, preferred channel, type. New email that belongs to another contact → 409 naming it and suggesting a merge (task 02). History `contact.updated` records field names, not values. Permission `contacts.manage` (Admin, Manager, Sales Exec).

### Visibility (L1) and the Sprint 4 question
Every user with `panel.crm` sees every contact. Bookings on the profile keep own-records for `can_act` only; reading is allowed. `GET /api/rms/contacts?q=` is unchanged (already global). That closes the Sprint 4 question: people are shared; bookings and money stay own-records. PENDING CLIENT confirmation as in the README.

The PATCH 403 case uses a custom role with `panel.crm` and without `contacts.manage`. The seeded Sales Exec has `contacts.manage`.

### AGENT link (smallest version)
No `contacts.agency_id`. AGENT is TRAVEL_AGENT + `LOWER(agencies.email) = contacts.email` + APPROVED. A dedicated FK is a later option if email match is not enough.

### Separation (L9)
CRM resources live in `App\Http\Resources\Crm`. Arch test now also forbids `App\Actions\Guests` and `App\Actions\Extras`. Feature tests walk CRM contact responses with `assertNoSensitiveFields()`.

### Deviations
Two pre-existing Larastan nits in engine files blocked `composer check` (`SubmitCheckoutRequest` departure null check; `CheckoutStatusResource` nullsafe on `contact`). Tightened only those expressions. No behaviour change.

### Open questions
Segment thresholds remain PENDING CLIENT. Who sees which contacts remains PENDING CLIENT (default: everyone with CRM access).

### Notes for later
- Task 02: `phone_e164`, merge/unmerge, email-409 merge UX.
- Task 03: MQL identified-behaviour column; `first_touch` / `last_touch`.
- Task 06: panel Contacts; optional partner `pid` on the profile.
- `contacts.agency_id` if email match is too weak.

### Checks
`composer check` passed (996 tests). Pint and Larastan clean.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add app/Enums/ContactType.php
git add app/Enums/ContactLifecycle.php
git add app/Enums/ContactSegment.php
git add app/Enums/ContactConsentFilter.php
git add app/Enums/Permission.php
git add app/Enums/SystemRole.php
git add app/Enums/RuleGroup.php
git add app/Models/Contact.php
git add app/Actions/Contacts/ResolveContact.php
git add app/Actions/Contacts/UpdateContact.php
git add app/Actions/Agencies/RegisterAgency.php
git add app/Actions/Charter/CreateCharterEnquiry.php
git add app/Support/Crm/ContactDerived.php
git add app/Support/Crm/ContactConsentSummary.php
git add app/Support/Config/Documents/CrmRules.php
git add app/Support/Config/Documents/BusinessRulesDocument.php
git add app/Support/BusinessRules/Registry.php
git add app/Support/Roles/GrantContactsManage.php
git add app/Policies/ContactPolicy.php
git add app/Http/Controllers/Crm/ContactController.php
git add app/Http/Requests/Crm/IndexContactsRequest.php
git add app/Http/Requests/Crm/UpdateContactRequest.php
git add app/Http/Resources/Crm/ContactResource.php
git add app/Http/Resources/Crm/ContactBookingResource.php
git add app/Http/Requests/Engine/SubmitCheckoutRequest.php
git add app/Http/Resources/Engine/CheckoutStatusResource.php
git add database/migrations/2026_09_21_200075_add_crm_fields_to_contacts.php
git add database/migrations/2026_09_21_200076_add_crm_segment_thresholds_to_business_rules.php
git add database/migrations/2026_09_21_200077_grant_contacts_manage_to_sales_roles.php
git add database/factories/ContactFactory.php
git add database/seeders/DemoAgenciesSeeder.php
git add routes/api/crm.php
git add tests/Arch/ArchTest.php
git add tests/Unit/Enums/SystemRoleTest.php
git add tests/Feature/Auth/GrantContactsManageTest.php
git add tests/Feature/Bookings/ContactResolutionTest.php
git add tests/Feature/Agencies/AgencyEndpointsTest.php
git add tests/Feature/Engine/EngineWaitlistCharterTest.php
git add tests/Feature/Config/BusinessRulesEndpointsTest.php
git add tests/Feature/Config/BusinessRulesSeederTest.php
git add tests/Feature/Config/AddCrmSegmentThresholdsToBusinessRulesMigrationTest.php
git add tests/Feature/Crm/ContactDerivedTest.php
git add tests/Feature/Crm/ContactEndpointsTest.php
git add tests/Feature/Crm/ContactQueryCountTest.php
git add docs/sprints/sprint-09/REPORT.md
git commit -m "$(cat <<'EOF'
Add CRM contacts with derived lifecycle, LTV and segment.

The existing people table becomes the CRM record: staff edit owned fields, and list/profile read booking-derived values in one SQL scope.
EOF
)"
```

## Task 02 · Identity resolution, aliases, merge and unmerge

One person, one contact. Email and phone are normalised so `ResolveContact` finds the same person; remaining duplicates are suggested, never auto-merged; staff merge into the oldest id; the loser stays as an alias so old URLs still open the survivor; a merge can be undone for 30 days.

### Normalisation (L3)
`Contact::normalizeEmail` already case-folds and trims and does **not** strip plus-addressing. Confirmed: `Ana@X.test` = `ana@x.test`; `ana+trip@x.test` ≠ `ana@x.test`. No email backfill.

Phone: `giggsey/libphonenumber-for-php` `^9.0` (locked **9.0.39**, plus `giggsey/locale` 2.9.0). Packagist `php: ^8.1` covers 8.1–8.5; the project is PHP ^8.4 / Laravel 13. Wrapper `App\Support\Contacts\PhoneNumber::toE164` parses with the contact's ISO-2 `country` as default region; if country is missing, only a number that already starts with `+` is tried (`ZZ`). Unparseable or invalid → `phone_e164 = null`, raw `phone` kept. Called from `ResolveContact` (raw upsert sets `phone_e164` — the builder bypasses mutators), `UpdateContact` when phone or country changes, and a migration data step that backfills existing rows. Index on `contacts.phone_e164` is non-unique.

`ResolveContact`:
1. Normalised email → upsert as before, then walk `merged_into_id` / the alias chain to the current survivor and fill that row, never the loser.
2. No email → oldest live (`notMerged()`, `id` ASC) with that `phone_e164`; else create.
3. No email and no parseable phone → create. **Name never matches** (tested: two name-only creates stay two contacts).

### Schema
No Eloquent SoftDeletes on `contacts`. `merged_into_id` is the list-exclusion flag (`Contact::scopeNotMerged()`). SoftDeletes would 404 implicit `{contact}` binding and force `withTrashed()` through RMS search, `ResolveContact`, and factories.

`contact_merges` is append-only except:
- undo columns (`undone_at`, `undone_by`, `undo_reason`, plus audit timestamps)
- one erase pattern for Sprint 10 subject requests (doc 07 §8): `erased_at` set (NULL → non-NULL) **together with** `merged_identifiers = NULL` and `loser_fields = NULL`. No erase Action in this task.

Triggers refuse `DELETE`, refuse a second erase, refuse `erased_at` without those two nulls, refuse nulling the identifier snapshots without `erased_at`, and refuse every other column.

`contact_aliases`: `alias_id` unique (the loser), `contact_id` (survivor), `merge_id`. **Never hard-deleted.** Unmerge stamps `contact_merges.undone_*`; route binding only follows an alias whose merge is not undone.

Permission `contacts.merge` ("Merge contacts", group `crm`) granted to Manager only. Sales Exec stays on `contacts.manage`. Admin already holds every permission (D3).

### Contact-bearing tables
`App\Support\Crm\ContactReferences` is the single list merge and unmerge iterate:

| Table | Column |
|---|---|
| `bookings` | `contact_id` |
| `groups` | `coordinator_contact_id` |
| `waitlist_entries` | `contact_id` |
| `charter_enquiries` | `contact_id` |

Not in the list: `consents` and `booking_requests` (booking-scoped — they move when the booking is repointed), `change_history` (morph on each contact; loser history stays). `TODO(task 03)` for `behavioural_events.contact_id`.

### Aliases and binding (L4)
`Contact::resolveRouteBinding` loads the row, and if it is an alias of a live merge, returns the current survivor with `resolvedFromAliasId` + `resolvedMergeId` stashed on the model. `ContactResource` adds `resolved_from_alias`, `alias_id`, `merge_id` so task 06 can show "Merged into …" and offer Undo. Index/list still return the survivor `id` only.

`GET /api/crm/contacts` and RMS `GET /api/rms/contacts?q=` use `notMerged()`.

`GET contacts/duplicates` is registered **before** `contacts/{contact}`.

### Duplicates
`GET /api/crm/contacts/duplicates` (`panel.crm`): candidate pairs only — same `phone_e164`, or same normalised name (`mb_strtolower` + trim + collapse whitespace) **and** same country. Both sides `notMerged()`. Dedup as `(min_id, max_id)`. Unique email is already one row, so it is not listed. Nothing merges.

### Merge
`POST /api/crm/contacts/{contact}/merge` `{ contact_id, reason }` — `contacts.merge`.

- Both ids resolve through aliases. Same person after resolve → 422. Already merged with no alias path → 422.
- **Survivor = lower id**, whatever order staff picked. Response `swapped: true` when the URL contact is not the survivor.
- One transaction: `lockForUpdate` **lower id first**, then the other (never 1213). Child `UPDATE`s take row locks as they go; they never take a departure lock (H10-safe). Concurrent merge of an overlapping contact waits **1205**.
- Repoint every `ContactReferences` row; snapshot loser owned fields; copy empty survivor owned fields (`email`, `phone`, `country`, `first_touch`, `last_touch` only — `type` / `language` / `preferred_channel` have defaults so they are not overwritten). Recompute `phone_e164` after filling phone/country.
- **Unique email:** if the survivor takes the loser's email, null the loser's `email` **first** in the same transaction, then copy from the snapshot. Identifiers always go on `merged_identifiers` so unmerge can restore them.
- History `contact.merged` on **both** subjects (D5 — field names, not values).

### Unmerge
`POST /api/crm/contact-merges/{merge}/undo` `{ reason }` — `contacts.merge`.

- Already undone / `erased_at` set / `now() >= merged_at + 30 days` (UTC, D6) → 422.
- A later non-undone merge where this survivor is the **loser** → 422 naming that merge; undo in order (L4).
- Restore a logged row **only if its FK still equals the survivor**. A booking staff moved to a third contact stays there. Rows created after the merge were never in the log and stay with the survivor. `skipped_rows` is on the response; the history reason appends `Skipped: bookings#123.`
- **Unique email on undo:** if `survivor_filled` includes `email` and the survivor still holds that copied address, null the survivor's `email` first, then write `loser_fields.email` onto the loser. Other `survivor_filled` keys revert only if they still equal the copied value.
- Alias row stays; binding ignores it. `loser.merged_into_id` cleared. History `contact.unmerged` on both.
- Undo writes the undo columns through the query builder (Eloquent `save()` recasts JSON and trips the freeze trigger).

### Merge log
`GET /api/crm/contact-merges` — `panel.crm`, paginated, newest first. Typed for task 04's identity-resolution log (`GET /api/crm/sync/identity` will wrap this later).

### CRM schema test
Task 01 never added one. `tests/Feature/OpenApi/CrmResponseSchemasTest.php` asserts `ContactDuplicateResource`, `ContactMergeResource`, `ContactMergeResultResource`, `ContactUnmergeResultResource`, and every `*ContactResource*` schema has properties. Scramble may emit both RMS and CRM `ContactResource`; both are asserted.

### Deviations
- "Soft-deleted from lists" is `merged_into_id` + `notMerged()`, not Eloquent SoftDeletes — SoftDeletes would 404 `{contact}` and leak through RMS search / `ResolveContact`.
- Aliases are never deleted; an undone merge makes the alias inert.
- 30-day tests use `$this->travel(30)->days()` because the trigger forbids updating `merged_at`.
- No distinct Scramble schema name on CRM `ContactResource` — the test accepts every `*ContactResource*` component.

### Open questions
None. The skip-if-moved rule, unique-email restore order, and erasable merge log were specified in the plan.

### Notes for later
- Task 03: add `behavioural_events` to `ContactReferences`.
- Task 04: timeline merge entries; `GET /api/crm/sync/identity` reads `contact_merges`.
- Task 06: duplicates panel, side-by-side merge, undo from the survivor timeline.
- Sprint 10 subject requests (doc 07 §8): an erase Action that sets `erased_at` and nulls `merged_identifiers` + `loser_fields` in one update. The trigger is ready.

### Checks
Pint and Larastan clean. Full suite 1016 passed; Task 02 subset 27 passed after the style/stan fixes.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add composer.json
git add composer.lock
git add app/Support/Contacts/PhoneNumber.php
git add app/Support/Crm/ContactReferences.php
git add app/Support/Crm/DuplicateContacts.php
git add app/Support/Roles/GrantContactsMerge.php
git add app/Actions/Contacts/ResolveContact.php
git add app/Actions/Contacts/UpdateContact.php
git add app/Actions/Contacts/MergeContacts.php
git add app/Actions/Contacts/UndoContactMerge.php
git add app/Models/Contact.php
git add app/Models/ContactMerge.php
git add app/Models/ContactAlias.php
git add app/Enums/Permission.php
git add app/Enums/SystemRole.php
git add app/Policies/ContactPolicy.php
git add app/Policies/ContactMergePolicy.php
git add app/Providers/AppServiceProvider.php
git add app/Http/Controllers/Crm/ContactController.php
git add app/Http/Controllers/Crm/ContactMergeController.php
git add app/Http/Controllers/Rms/ContactController.php
git add app/Http/Requests/Crm/MergeContactRequest.php
git add app/Http/Requests/Crm/UndoContactMergeRequest.php
git add app/Http/Resources/Crm/ContactResource.php
git add app/Http/Resources/Crm/ContactDuplicateResource.php
git add app/Http/Resources/Crm/ContactMergeResource.php
git add app/Http/Resources/Crm/ContactMergeResultResource.php
git add app/Http/Resources/Crm/ContactUnmergeResultResource.php
git add database/migrations/2026_09_21_200078_add_contact_identity_resolution.php
git add database/migrations/2026_09_21_200079_grant_contacts_merge_to_manager.php
git add routes/api/crm.php
git add tests/Unit/Contacts/ContactNormalisationTest.php
git add tests/Unit/Enums/SystemRoleTest.php
git add tests/Feature/Auth/GrantContactsMergeTest.php
git add tests/Feature/Bookings/ContactResolutionTest.php
git add tests/Feature/Crm/ContactDuplicatesTest.php
git add tests/Feature/Crm/ContactMergeTest.php
git add tests/Feature/Crm/ContactMergeTriggerTest.php
git add tests/Feature/OpenApi/CrmResponseSchemasTest.php
git add tests/Concurrency/ContactMergeConcurrencyTest.php
git add docs/sprints/sprint-09/REPORT.md
git commit -m "$(cat <<'EOF'
Add CRM identity resolution with merge, aliases and unmerge.

Normalise email and phone, merge duplicates into the oldest contact, keep the losing id as an alias, and allow a 30-day undo that skips rows staff moved after the merge.
EOF
)"
```
