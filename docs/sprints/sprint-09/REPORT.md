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

## Task 03 · Attribution and behavioural events

The engine can tell the API what a consenting visitor does (fixed vocabulary) and where they came from. Events sit anonymous until an identifying submit stitches them onto a contact. A booking made from the engine stores UTM first/last touch once; a trigger refuses any later change.

Decisions: L6, L7, L8 (and K3, K8, I6, A4). Prototype `v-activity` “purge anonymous at 13 months” loses to L6 (30 days). Prototype `abandon_checkout` is `abandon_cart` (SPEC §8).

### Vocabulary and whitelist (L6)
`App\Enums\BehaviouralEventName`: SPEC §8 names plus `view_departure`, `page_view`, and server-only `identity.stitched`. `acceptedFromClient()` excludes `identity.stitched` — ingest 422s it.

`App\Support\Engine\BehaviouralEventParams` keeps only the keys allowed for that name. Unknown **names** → 422; unknown **keys** → dropped; typed values that fail (including free text) → 422. Coupon codes only on `apply_promotion` / `remove_promotion` / `promo_invalid`. No IP, UA, email, name, or access tokens.

`App\Support\Engine\PagePath` is the single redactor for every stored path (`page_path` and attribution `landing_path`):
1. Strip query string and hash.
2. Prefix whitelist of credential-bearing routes. Today: any path matching `^/complete/` is stored as the literal `/complete/[token]`. Further segments after the token are dropped.
3. Other paths stay query-stripped, no host, max 200 after redaction.

### Schema
Migration `200080`:
- `contacts.engine_identified_at` — nullable timestamp, set once on first stitch. Merge empty-field copy with `first_touch` / `last_touch`.
- `behavioural_events` — `event_id` unique, `session_id`, nullable `contact_id` (nullOnDelete), `name`, `params`, `occurred_at`, `received_at`, standard audit columns (engine writes `created_by` null).
- `behavioural_event_daily` — unique `(date, name, itinerary_code)`; empty string when itinerary is absent.
- `bookings.utm_first` / `utm_last` JSON nullable. Trigger (H1 pattern B): any `UPDATE` that changes either column (`NOT (NEW.x <=> OLD.x)`) signals `45000`. Writable only on INSERT. Staff `CreateBooking` leaves them null.

`ContactReferences` now includes `behavioural_events.contact_id`. Merge moves stitched events; unmerge restores them.

MQL SQL is `contacts.engine_identified_at IS NOT NULL OR (marketing consent)`. A waitlist/charter stitch with no booking is MQL.

### Ingest — `POST /api/engine/events`
On top of `throttle:engine`, `throttle:engine-events`: **30/min per IP** and **20/min per `session_id`** (working values; the task gave none). Dual-`Limit` style as `engine-complete`.

Payload `{ session_id, events: [{ event_id, name, occurred_at, params }] }`. `session_id` required, 16–64 `^[A-Za-z0-9_-]+$`, never derived from IP (IPv4/IPv6 rejected). Events min 1, max 25. `occurred_at` clamped to `[now-24h, now]`. Idempotency = unique `event_id` (`insertOrIgnore`). After a session has been stitched, new inserts set `contact_id` from the latest `identity.stitched` row for that session.

Response `EngineEventsAcceptedResource`: `{ accepted, duplicate }`. No `change_history` on ingest.

### Stitching (L7)
`StitchEngineIdentity` runs inside the identifying transactions after `ResolveContact` (or the booking’s contact on complete): checkout submit, waitlist, charter enquiry, complete billing / guest / declarations.

When `session_id` is present: back-fill only `contact_id IS NULL`; insert one `identity.stitched` (`params.count` = rows affected); set `engine_identified_at` if null; history `identity.stitched` on the contact (count only). Same contact + same session again is a no-op unless there were new nulls. A second **different** contact on the same session keeps existing rows on the first; later ingest follows the latest stitch.

Attribution is only accepted on checkout submit. Waitlist / charter / complete stitch only.

### Attribution (L8) and trade wins
`App\Support\Crm\AttributionTouch`: `source`, `medium`, `campaign`, `content`, `term`, `landing_path`, `captured_at`. Unknown keys dropped. UTM strings capped at 100; `landing_path` 200 after `PagePath`. Empty object → null.

Engine checkout writes `utm_first` / `utm_last` at INSERT and includes them on `booking.requested` history. Contact: `first_touch` set only when null; `last_touch` replaced every time.

**Trade wins:** engine checkout stays `WEB_DIRECT` / `HotelBookingEngine` and does not set `agency_id` / `commission_*`. Staff `CreateBooking` sets agency + FIN-005 commission and leaves UTM null. Neither path writes the other’s columns. Commission follows the agency (Sprint 5 / FIN-005); marketing UTM is stored beside it; neither overwrites the other.

### Retention (LEG-002)
Shape change: `retention.behavioural_raw_months` = 24, `retention.behavioural_unstitched_days` = 30. PENDING CLIENT, source L6 / doc 07 §8. DML publish as System. Registry: two `here()` rows. Counts: all 69→71, here 44→46, differs_or_flagged 23→25. `anakata:config-verify` fails on a latest document missing the keys, then passes after `up()`.

`anakata:events-retention {--dry-run}`: Galápagos today via `BusinessTime::now()`. Stitched raw older than 24 months, and unstitched older than 30 days, upsert `behavioural_event_daily` by `(date(occurred_at), name, itinerary_code)` then delete. No extra dimensions. `--dry-run` prints counts and writes nothing. Scheduled daily, `timezone(BusinessTime::zone())`, `withoutOverlapping()`, next to `anakata:retention`. Console counts only.

### Deviations
None from the plan. Limiter values (30/min IP, 20/min session) chosen because the task gave none.

### Open questions
Retention windows remain PENDING CLIENT (LEG-002).

### Notes for later
- Task 04: CRM timeline / activity reads these tables.
- Task 08: engine `track()`, consent banner, UTM capture.
- Task 09: E2E CRM-07.

### Checks
`composer check` passed (1050 tests). Pint and Larastan clean. `anakata:config-verify` covered by the shape-change migration test (fails before `up()`, passes after).

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add app/Enums/BehaviouralEventName.php
git add app/Support/Engine/PagePath.php
git add app/Support/Engine/EngineSessionId.php
git add app/Support/Engine/BehaviouralEventParams.php
git add app/Support/Crm/AttributionTouch.php
git add app/Support/Crm/ContactDerived.php
git add app/Support/Crm/ContactReferences.php
git add app/Support/Config/Documents/BusinessRulesDocument.php
git add app/Support/Config/Documents/RetentionRules.php
git add app/Support/BusinessRules/Registry.php
git add app/Models/BehaviouralEvent.php
git add app/Models/BehaviouralEventDaily.php
git add app/Models/Booking.php
git add app/Models/Contact.php
git add app/Actions/Engine/IngestBehaviouralEvents.php
git add app/Actions/Contacts/StitchEngineIdentity.php
git add app/Actions/Contacts/MergeContacts.php
git add app/Actions/Checkout/SubmitEngineCheckout.php
git add app/Actions/Waitlist/AddWaitlistEntry.php
git add app/Actions/Charter/CreateCharterEnquiry.php
git add app/Console/Commands/EventsRetentionCommand.php
git add app/Http/Controllers/Engine/EngineEventsController.php
git add app/Http/Controllers/Engine/CompleteReservationController.php
git add app/Http/Controllers/Engine/WaitlistController.php
git add app/Http/Requests/Engine/StoreEngineEventsRequest.php
git add app/Http/Requests/Engine/SubmitCheckoutRequest.php
git add app/Http/Requests/Engine/StoreEngineWaitlistRequest.php
git add app/Http/Requests/Engine/StoreEngineCharterEnquiryRequest.php
git add app/Http/Requests/Engine/UpdateCompleteBillingRequest.php
git add app/Http/Requests/Engine/UpdateCompleteGuestRequest.php
git add app/Http/Requests/Engine/RecordCompleteDeclarationsRequest.php
git add app/Http/Resources/Engine/EngineEventsAcceptedResource.php
git add app/Http/Resources/Rms/BookingResource.php
git add app/Providers/AppServiceProvider.php
git add database/factories/BehaviouralEventFactory.php
git add database/factories/ContactFactory.php
git add database/migrations/2026_09_21_200080_add_attribution_and_behavioural_events.php
git add database/migrations/2026_09_21_200081_add_behavioural_event_retention_to_business_rules.php
git add routes/api/engine.php
git add routes/console.php
git add tests/Unit/Engine/PagePathTest.php
git add tests/Feature/Engine/BehaviouralEventParamsTest.php
git add tests/Feature/Engine/IngestEventsTest.php
git add tests/Feature/Engine/EventStitchingTest.php
git add tests/Feature/Engine/BookingAttributionTest.php
git add tests/Feature/Retention/EventsRetentionCommandTest.php
git add tests/Feature/Config/AddBehaviouralRetentionToBusinessRulesMigrationTest.php
git add tests/Feature/Config/BusinessRulesDocumentTest.php
git add tests/Feature/Config/BusinessRulesEndpointsTest.php
git add tests/Feature/Config/BusinessRulesSeederTest.php
git add tests/Feature/Crm/ContactDerivedTest.php
git add tests/Feature/Crm/ContactMergeTest.php
git add tests/Feature/OpenApi/EngineResponseSchemasTest.php
git add tests/Feature/OpenApi/PanelResponseSchemasTest.php
git add docs/sprints/sprint-09/REPORT.md
git commit -m "$(cat <<'EOF'
Add engine behavioural-event ingest, identity stitching and frozen UTM.

Consenting visitors send a fixed event vocabulary; identifying submits stitch the session onto a contact, and booking UTM is written once and then refused by a trigger.
EOF
)"
```

## Task 04 · The CRM read API: timeline, activity, sync and field ownership

The three CRM screens this sprint builds now have a read API. There is no bus and no replay (L5, B9): the CRM reads the booking tables and recorded job health.

### Contact timeline
`GET /api/crm/contacts/{contact}/timeline` — newest first, page/`per_page`, `{ at, kind, title, detail, link }`. One SQL `UNION ALL` in `App\Support\Crm\ContactTimeline`.

Sources and wording:
- **Booking milestones** from `change_history` on the contact’s bookings: created, requested, confirmed, fully paid, cancelled, moved. Detail is `after.what` when it exists; requested and moved use a fixed sentence. No other before/after keys.
- **Payments** — kind, amount (`Money::format`), status. No method, gateway id, or note.
- **Deliveries** — kind, status, `to`.
- **Consents** — document, version, source, accepted or withdrawn. No IP.
- **Behavioural events** stitched to the contact, with itinerary and departure names resolved.
- **Merges** from `contact_merges` (who, reason, undone). `contact.merged` history is not also emitted.

Excluded: `payment.*` / `payment_link.*` history, `consent.recorded`, guest and document history, `booking.released`, raw before/after, anything in `SensitiveFields`. A merged contact’s timeline includes the loser’s bookings and events because merge already repoints those rows.

### Activity KPIs
`GET /api/crm/activity?from&to&name&identified`. Stream: time, event, contact name or `anonymous`, detail, side. `begin_checkout` and `submit_booking_request` are `RMS + CRM`; everything else is `CRM`.

`meta.kpis` is SQL, not page counts:
- `events_today` — Galápagos today (`BusinessTime`), still respects `name` and `identified`
- `identified` / `anonymous` / `inventory_touching` — same filters as the list
- `web_hold_minutes` / `web_hold_extension_minutes` — published business rules (`holds.web_minutes` 20, extension 10)

### Sync & Field Ownership instead of a bus
Five endpoints. The prototype’s live bus and replay have no equivalent in one application; failed jobs and FAILED deliveries are the replacement.

- `GET /api/crm/sync/ownership` — doc 07 §3 as implemented. “Mirrored to” is `read_by`. Booking-derived CRM rows say they are read directly from the booking tables and name the class. Deals, tasks, conversations: not built (Sprint 10).
- `GET /api/crm/sync/jobs` — every event on `Schedule` (not the doc 07 job table). Ledger reconcile and the other unscheduled prototype jobs do not appear.
- `GET /api/crm/sync/failures` — Horizon `failed_jobs` and `deliveries.status = FAILED`.
- `POST /api/crm/sync/failures/{id}/retry` — `sync.retry` only (Admin). `job:{uuid}` → `queue:retry`. `delivery:{id}` → Sprint 7 resend via `App\Actions\Crm\RetryFailedDelivery` so the CRM controller never imports `App\Actions\Documents`.
- `GET /api/crm/sync/identity` — merge log (who, when, reason, undone).
- `GET /api/crm/sync/events` — the six domain events in `app/Events` plus every `BehaviouralEventName`, with producer and listeners. `meta.note` states there is no bus.

Reads use `panel.crm`. `sync.retry` is not on Manager or Sales Exec.

### `scheduled_runs` hooks
Table `scheduled_runs` (`command`, start, finish, `running|succeeded|failed`, exit code, output ≤ 500). `RecordScheduledRuns::attach()` registers `before` / `after` / `onFailure` on every `Schedule::command()` in `routes/console.php`. A test fails if a scheduled command is missing the hook.

### Deviations
None from the plan.

### Open questions
None.

### Notes for later
- Task 05: regenerate types from these resources.
- Task 07: panel Activity and Sync screens consume `meta.kpis`; do not count in the panel.
- Task 09: E2E CRM scenarios for timeline, activity, and a failed job retry.

### Checks
`composer check` passed (1066 tests). Pint and Larastan clean.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add app/Enums/Permission.php
git add app/Enums/BehaviouralEventName.php
git add app/Enums/ScheduledRunOutcome.php
git add app/Models/ScheduledRun.php
git add app/Support/Schedule/RecordScheduledRuns.php
git add app/Support/Crm/BehaviouralEventDetail.php
git add app/Support/Crm/ContactTimeline.php
git add app/Support/Crm/EngineActivity.php
git add app/Support/Crm/FieldOwnership.php
git add app/Support/Crm/EventCatalogue.php
git add app/Support/Crm/CrmSync.php
git add app/Support/Crm/SyncJobs.php
git add app/Support/Crm/SyncFailures.php
git add app/Actions/Crm/RetryFailedDelivery.php
git add app/Policies/SyncPolicy.php
git add app/Http/Controllers/Crm/ContactTimelineController.php
git add app/Http/Controllers/Crm/EngineActivityController.php
git add app/Http/Controllers/Crm/SyncController.php
git add app/Http/Requests/Crm/IndexContactTimelineRequest.php
git add app/Http/Requests/Crm/IndexEngineActivityRequest.php
git add app/Http/Resources/Crm/ContactTimelineItemResource.php
git add app/Http/Resources/Crm/EngineActivityItemResource.php
git add app/Http/Resources/Crm/FieldOwnershipResource.php
git add app/Http/Resources/Crm/ScheduledJobResource.php
git add app/Http/Resources/Crm/SyncFailureResource.php
git add app/Http/Resources/Crm/SyncIdentityResource.php
git add app/Http/Resources/Crm/EventCatalogueResource.php
git add app/Http/Resources/Crm/RetrySyncFailureResource.php
git add app/Providers/AppServiceProvider.php
git add database/migrations/2026_09_21_200082_create_scheduled_runs_table.php
git add routes/api/crm.php
git add routes/console.php
git add tests/Feature/Crm/ContactTimelineTest.php
git add tests/Feature/Crm/EngineActivityTest.php
git add tests/Feature/Crm/SyncOwnershipAndEventsTest.php
git add tests/Feature/Crm/SyncJobsTest.php
git add tests/Feature/Crm/SyncFailuresTest.php
git add tests/Feature/OpenApi/CrmResponseSchemasTest.php
git add docs/sprints/sprint-09/REPORT.md
git commit -m "$(cat <<'EOF'
Add the CRM read API for timeline, activity and sync health.

The panel can now read a contact timeline, the engine event stream, and the ownership contract as this app actually implements it — recorded job runs and failed side effects, with no event bus.
EOF
)"
```

## Task 05 · anakata-ui · Regenerate types, release `v0.10.0`

### What was built
PHPDoc / OpenAPI prelude on the API so Scramble names the CRM contact schema and `$ref`s nested CRM resources, then types regenerated against `http://localhost:8000/docs/api.json`. Layer `0.9.0` → `0.10.0`. Types only: no composables, components, or frontend behaviour.

Calendar dates stay `string` (`YYYY-MM-DD`). Instants stay ISO strings.

RMS `Contact` stays the search row. The CRM people row is `CrmContact` on the barrel.

### API prelude

| Target | What landed |
|---|---|
| `Crm\ContactResource` | `#[SchemaName('CrmContactResource')]`. `bookings` is `ContactBookingResource::collection(...)` (was `->resolve()` → `{}`). `first_touch` / `last_touch` go through `AttributionTouch::from()` so the optional UTM keys `$ref` a typed object. `@phpstan-return` matches the resolved array. |
| `AttributionTouch::from()` | `@return` lists the optional keys (`source`, `medium`, `campaign`, `content`, `term`, `landing_path`, `captured_at`). |
| `ContactDuplicateResource` | `a` / `b` return `new ContactResource` (was `toArray()`). |
| `ContactMergeResultResource` | `merge` / `contact` return the resource classes. |
| `ContactUnmergeResultResource` | `merge` returns `ContactMergeResource`. |
| `ContactController::duplicates` | Return type `AnonymousResourceCollection`. |
| `SyncController::ownership` / `failures` | Return type `AnonymousResourceCollection`. |
| `CrmResponseSchemasTest` | Asserts `CrmContactResource` derived keys and no sensitive fields; `$ref`s for bookings / duplicate / merge; named `ContactType` / `ContactLifecycle`; contacts `meta.filters`; activity `meta.kpis`; jobs `meta.kpis`; events `meta.note`. |
| `PanelResponseSchemasTest` | `ContactResource` stays the RMS search row and has no `lifetime_value`. |
| `EngineResponseSchemasTest` | `StoreEngineEventsRequest` exists; client event-name enum does not include `identity.stitched`. |

`meta.filters`, activity `meta.kpis`, jobs `meta.kpis` and events `meta.note` were already on the `#[DocumentedResponse]` attributes. No re-type.

Engine event `params` and checkout `attribution` stay loose in the generated request bodies (Laravel `array` rules). Leftovers in `engine.ts` type them for the frontends. No new rejecting validation.

### Line counts

| File | Before | After |
|---|---|---|
| `app/types/api.d.ts` | 9886 | 11120 |
| `app/types/inventory.ts` | 247 | 247 |
| `app/types/config.ts` | 430 | 430 |
| `app/types/bookings.ts` | 208 | 208 |
| `app/types/payments.ts` | 161 | 161 |
| `app/types/index.ts` | 256 | 286 |
| `app/types/anakata-augment.d.ts` | 17 | 17 |
| `app/types/guests.ts` | 101 | 101 |
| `app/types/extras.ts` | 24 | 24 |
| `app/types/documents.ts` | 61 | 61 |
| `app/types/offers.ts` | 37 | 37 |
| `app/types/engine.ts` | 296 | 354 |
| `app/types/crm.ts` | — | 105 |

`api.d.ts` was regenerated with `pnpm types:api` and never hand-edited.

### Schema → alias (`app/types/crm.ts` — `/api/crm` only)

No imports from `./bookings`, `./guests`, `./payments`, or any RMS `*Resource`.

| Alias | Source |
|---|---|
| `ContactType` | named `ContactType` |
| `Lifecycle` | named `ContactLifecycle` |
| `Segment` | leftover `HIGH \| MID \| NEW` — mirrors `App\Enums\ContactSegment` |
| `Contact` | `CrmContactResource` + overlays on `type` / `lifecycle` / `segment` / `consent`; `bookings` omitted (list row) |
| `ContactBooking` | leftover `can_act: boolean` — generated freezes as `string` |
| `ContactProfile` | `Contact` + `bookings: Array<ContactBooking>` |
| `ContactFilters` | pick `meta.filters` from `crm.contact.index` |
| `ContactDuplicate` | leftover `a` / `b: Contact` and `reasons: Array<string>` — generated `reasons` is `string` |
| `ContactMerge` | `ContactMergeResource` |
| `ContactMergeResult` | leftover `swapped: boolean` and `contact: ContactProfile` — generated `swapped` is `string` |
| `ContactUnmergeResult` | leftover `skipped_rows` — generated is `string` |
| `TimelineItem` | `ContactTimelineItemResource` |
| `ActivityEvent` | `EngineActivityItemResource` + `name` overlay (`BehaviouralEventName`) |
| `ActivityKpis` | activity operation `meta.kpis` |
| `OwnershipRow` | `FieldOwnershipResource` |
| `ScheduledJobRun` | `ScheduledJobResource` |
| `SyncFailure` | `SyncFailureResource` |
| `EventCatalogueRow` | `EventCatalogueResource` |
| `SyncIdentityRow` | `SyncIdentityResource` |
| `RetrySyncFailure` | `RetrySyncFailureResource` |
| `SyncKpis` | jobs operation `meta.kpis` |

`preferred_channel`, `main_channel` and `channel_of_origin` stay the CRM schema's `string`.

### Schema → alias (`app/types/engine.ts` — `/api/engine` only)

| Alias | Source |
|---|---|
| `EngineEventsAccepted` | `EngineEventsAcceptedResource` |
| `EngineEventName` | `StoreEngineEventsRequest.events[].name` (client names; no `identity.stitched`) |
| `EngineEventParams` | leftover optional whitelist keys — mirrors `App\Support\Engine\BehaviouralEventParams`. Generated `params` is `string[] \| null` |
| `EngineEventsInput` | leftover over `StoreEngineEventsRequest` (`session_id: string`, typed `events`) |
| `AttributionTouch` | leftover — mirrors `App\Support\Crm\AttributionTouch`. Generated checkout touches are `string[]` |
| `AttributionInput` | leftover `{ first_touch?, last_touch? }` |

### Barrel

`Contact` from `bookings.ts` is unchanged (RMS search). CRM `Contact` is re-exported as `CrmContact`.

### Kept leftovers

Inventory leftovers stay as they are. New leftovers listed above, each with a `Mirrors App\…` comment.

No lifecycle / type / segment / event-name runtime list in the layer.

### Pins

Panel and engine README rows now say `` `extends: ['../anakata-ui']` (`v0.10.0`) ``. The Netlify fallback in each `nuxt.config.ts` is `github:anakata-project/anakata-ui#v0.10.0`. **Neither pin is enforced** — the apps resolve the sibling folder, so the version line is documentation only.

### Files touched
**anakata-api (prelude)**
- `app/Http/Resources/Crm/ContactResource.php`
- `app/Http/Resources/Crm/ContactDuplicateResource.php`
- `app/Http/Resources/Crm/ContactMergeResultResource.php`
- `app/Http/Resources/Crm/ContactUnmergeResultResource.php`
- `app/Support/Crm/AttributionTouch.php`
- `app/Http/Controllers/Crm/ContactController.php`
- `app/Http/Controllers/Crm/SyncController.php`
- `tests/Feature/OpenApi/CrmResponseSchemasTest.php`
- `tests/Feature/OpenApi/PanelResponseSchemasTest.php`
- `tests/Feature/OpenApi/EngineResponseSchemasTest.php`

**anakata-ui**
- `app/types/api.d.ts`
- `app/types/crm.ts` (new)
- `app/types/engine.ts`
- `app/types/index.ts`
- `package.json` (`0.10.0`)
- `CHANGELOG.md`
- `README.md`

**anakata-panel / anakata-engine**
- `README.md` (documentation pin only)
- `nuxt.config.ts` (Netlify fallback tag)

**anakata-api (this report)**
- `docs/sprints/sprint-09/REPORT.md`

### Deviations
- Extra aliases (`ContactBooking`, `ContactFilters`, `ContactMergeResult`, `ContactUnmergeResult`, `SyncIdentityRow`, `RetrySyncFailure`, `SyncKpis`) so `crm.ts` stays readable. Same pattern as Sprint 8's extra engine aliases.
- `consent.marketing` is overlayed as `boolean` — Scramble still emits `string | boolean`.
- `AttributionTouch::from()` PHPDoc change is shared with checkout submit and stitch. Runtime filter is unchanged.

### Open questions
None.

### Notes for later
- Task 06: Contacts list / profile / merge consume `CrmContact`, `ContactProfile`, `ContactDuplicate`, `ContactMerge`, `TimelineItem`. Filters from `ContactFilters`.
- Task 07: Activity from `ActivityEvent` + `ActivityKpis`; Sync from `OwnershipRow`, `ScheduledJobRun`, `SyncFailure`, `EventCatalogueRow`. Do not count KPIs in the panel.
- Task 08: emit `EngineEventsInput`; send `AttributionInput` on checkout submit. `EngineEventName` is the client whitelist.

### Quality
- anakata-api: `composer check` inside Docker — 1066 tests (7681 assertions), Pint (1099 files), Larastan level 6 (0 errors).
- anakata-ui: lint, typecheck, test (35), build — pass.
- anakata-panel / anakata-engine: typecheck and build — pass (real checks).
- Fresh clone into `/tmp/anakata-fresh/{anakata-ui,anakata-panel,anakata-engine}` (sibling layout). Overlayed the working trees. Confirmed the ui clone is **0.10.0** and has **no** `app/types/nuxt.d.ts`.
  - ui / panel / engine: typecheck pass
  - panel / engine: build pass
  - **OVERLAY CLONE OK**
  - **Repeat this clone after the pushes below**, checking out `anakata-ui` at `v0.10.0` with **no** overlay.

### Git commands for the user

Do **not** run these in the agent. Explicit paths only (never `-A`). Run in this order.

```bash
# 1. anakata-api prelude (OpenAPI typing — not this report)
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Http/Resources/Crm/ContactResource.php \
  app/Http/Resources/Crm/ContactDuplicateResource.php \
  app/Http/Resources/Crm/ContactMergeResultResource.php \
  app/Http/Resources/Crm/ContactUnmergeResultResource.php \
  app/Support/Crm/AttributionTouch.php \
  app/Http/Controllers/Crm/ContactController.php \
  app/Http/Controllers/Crm/SyncController.php \
  tests/Feature/OpenApi/CrmResponseSchemasTest.php \
  tests/Feature/OpenApi/PanelResponseSchemasTest.php \
  tests/Feature/OpenApi/EngineResponseSchemasTest.php
git commit -m "$(cat <<'EOF'
Type Sprint 9 CRM and engine event OpenAPI responses.

CrmContactResource is a stable schema name; nested merge and
duplicate contacts $ref named resources so the layer can regenerate.
EOF
)"
```

```bash
# 2. anakata-ui — commit, then tag, then push HEAD and the tag
cd /home/mohammad/Code/iconic/anakata/anakata-ui
git add \
  package.json \
  CHANGELOG.md \
  README.md \
  app/types/api.d.ts \
  app/types/index.ts \
  app/types/crm.ts \
  app/types/engine.ts
git commit -m "$(cat <<'EOF'
Regenerate API types for CRM contacts and engine events.

Sprint 9 aliases live in crm.ts (/api/crm only). The barrel exports
the people row as CrmContact. RMS Contact stays the search row.
EOF
)"
git tag v0.10.0
git push origin HEAD
git push origin v0.10.0
```

```bash
# 3. anakata-panel
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add README.md nuxt.config.ts
git commit -m "$(cat <<'EOF'
Document the layer pin as v0.10.0.

extends still resolves the sibling folder; the version is documentation only.
EOF
)"
git push origin HEAD
```

```bash
# 4. anakata-engine
cd /home/mohammad/Code/iconic/anakata/anakata-engine
git add README.md nuxt.config.ts
git commit -m "$(cat <<'EOF'
Document the layer pin as v0.10.0.

extends still resolves the sibling folder; the version is documentation only.
EOF
)"
git push origin HEAD
```

```bash
# 5. anakata-api report
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-09/REPORT.md
git commit -m "$(cat <<'EOF'
Record sprint 9 task 05: regenerated UI API types.
EOF
)"
git push origin HEAD
```

```bash
# 6. Fresh-clone repeat — after the pushes, no working-tree overlay
rm -rf /tmp/anakata-fresh
mkdir -p /tmp/anakata-fresh
git clone https://github.com/anakata-project/anakata-ui.git /tmp/anakata-fresh/anakata-ui
git -C /tmp/anakata-fresh/anakata-ui checkout v0.10.0
git clone https://github.com/anakata-project/anakata-panel.git /tmp/anakata-fresh/anakata-panel
git clone https://github.com/anakata-project/anakata-engine.git /tmp/anakata-fresh/anakata-engine
# then in each: pnpm install
# ui / panel / engine: pnpm typecheck
# panel / engine: pnpm build
```

## Task 06 · anakata-panel · CRM Contacts

Panel only. Types from the sibling layer at `v0.10.0` (`CrmContact`, `ContactProfile`, `ContactBooking`, `ContactFilters`, `ContactDuplicate`, `ContactMerge`, `ContactMergeResult`, `ContactUnmergeResult`, `TimelineItem`). Re-exported from `app/types/api.ts` via `#anakata-ui/app/types`; RMS `Contact` stays the search row. No lifecycle, lifetime value, segment or consent math in the panel. No booking, payment, guest or note mutations from these screens.

The catch-all `app/pages/crm/[group]/[item].vue` stays. `/crm/sales/contacts` is a real page.

### List
`GET /api/crm/contacts` with `page`, `per_page=50`, 300ms-debounced `q`, and filters: type and lifecycle from `meta.filters`; main channel and channel of origin from `GET /api/rms/bookings/form-options` when the user has `bookings.create` (otherwise those two stay on All); consent `marketing` / `transactional_only`.

Notice is the task sentence (CRM is the system of record for people; passport and medical data are never here). The prototype “pushed to RMS” line is omitted (L5 / B9). Hint under the table is the prototype derived-lifecycle line.

NPS is a flat “—” on every row, including agents. The API sends `null`. The prototype’s “n/a” for agents is dropped on purpose (see Deviations).

Pill classes live in `app/assets/css/crm.css` (registered in `nuxt.config.ts`): `.pill.hi`, `.pill.mid`, `.pill.new`, `.pill.ok`, plus `.ro`. Pagination matches Contacts In.

Duplicates: `GET /api/crm/contacts/duplicates` for every CRM user (`ContactPolicy::viewAny` is `panel.crm`). A 403 is swallowed (no toast). Merge stays behind `contacts.merge`. Reasons: `phone_e164` → “Same phone”, `name_country` → “Same name and country”. Seed after `reset.sh` has no pairs.

### Profile drawer
`ContactDrawer` (`USlideover`), opened by a row click and by `?open={id}`.

`?open=` lifecycle: an old id still loads through route binding and shows the alias banner; after a merge, `router.replace` the query with the survivor’s id; closing the drawer clears `?open=`.

Rows in prototype order: name, type · lifecycle · contact id; country · language + **ALL SENDS IN ENGLISH**; preferred / main / origin channels; LTV + **FROM RMS LEDGER** + segment pill; first / last touch; NPS “—”; consent transactional **ALWAYS ON**, marketing **OPTED IN** / **NOT OPTED IN**, plus “Consent register arrives in Sprint 10”. No profiling or WhatsApp rows.

Partner record: `GET /api/rms/agencies?q={email}` only when lifecycle is `AGENT` or type is `TRAVEL_AGENT` and the user has `panel.rms`. Match by email, case-insensitive and trimmed. Exactly one match shows the row and links `/rms/commercial/b2b?open={id}`. Zero or several matches hide the row. There is no `agency_id` on the contact (task 01). `b2b.vue` now opens the agency drawer from `?open=`.

Bookings from `profile.bookings` link to `/rms/reservations/bookings?open={display_reference}`. Timeline `GET …/timeline` renders `title` and `detail`; a `booking` link uses the same bookings URL; a `contact` link opens that contact in this drawer. Alias banner when `resolved_from_alias`: **Merged into {survivor name}**.

“Not held here”: passport, date of birth, nationality, and medical, dietary or mobility notes stay in the RMS.

### Edit
Shown when `can('contacts.manage')`. Owned fields only. `PATCH /api/crm/contacts/{id}`. Errors through `applyApiFormError`.

A 409 is `{ message }` in the shape `That email belongs to contact #{id} ({name}). …`. Helper `parseEmailConflictContactId` reads `/contact #(\d+)/`. A hit plus `contacts.merge` offers **Review merge**. A miss shows the server message only.

This depends on the server’s message text. A structured field (for example `conflicting_contact_id`) should be requested from the backend later.

### Merge and undo
`MergeContactsModal` loads both profiles side by side and states the API rule before submit (older / lower id survives; bookings, group coordinator, waitlist and charter enquiries move; empty email / phone / country / touches on the survivor are filled). Reason required. `POST /api/crm/contacts/{id}/merge`. After success the toast says whether `swapped` was true, the list refreshes, and `?open=` becomes the survivor’s id.

Undo: load `GET /api/crm/contact-merges` only with `contacts.merge`. Match `survivor_id === link.id` AND equal timestamps (`Date.parse` / `getTime`, never strings) AND `undone_at === null`. Zero matches hide Undo; one match offers it (30-day guard); two or more show a disabled Undo (“Cannot identify this merge uniquely”).

The 30-day helper is a UI guard only, computed in UTC. The window is exclusive at 30 days — Undo is offered only while `now < merged_at + 30 days` (`UndoContactMerge` 422s at `now >= merged_at + 30 days`). Invalid or missing date hides Undo. The server 422 is the authority.

Confirm copy uses the API’s words. `skipped_rows` from the response is shown.

### Helpers
`app/components/crm/contactHelpers.ts` + `tests/unit/crmContactHelpers.test.ts`: `segmentPillClass`, `lifecyclePillClass` (`''`), `consentPillLabel`, 409 parser, timestamp match including mixed `Z` / `+00:00`, exclusive 30-day window, partner email match.

Strings under `crmContacts` in `i18n/locales/en.json`.

### Deviations
- NPS is a flat “—” for every row, including agents. The prototype’s “n/a” for agents is dropped; the API sends `null` until Sprint 11.
- 409 Review merge parses the server message text (`/contact #(\d+)/`). Request a structured `conflicting_contact_id` later.
- Partner row is a unique email match against `GET /api/rms/agencies?q=`. There is no `agency_id` on the contact (task 01).
- Seed after `reset.sh` has no duplicate pairs; the duplicates panel stays hidden until a pair exists.

### Open questions
None.

### Notes for later
- Task 07: Activity and Sync screens consume the rest of the CRM types.
- Backend: add `conflicting_contact_id` (and name) on the 409 body so Review merge does not depend on copy.

### Quality
- anakata-panel against the sibling layer (`v0.10.0`): `pnpm lint`, `pnpm typecheck`, `pnpm test` (241), `pnpm build` — pass.
- Fresh clone into `/tmp/anakata-fresh-s9-06` overlayed against a `v0.10.0` ui checkout: panel typecheck and build pass. Layer pin was not bumped.

### Browser
After `COMPOSE_PROJECT_NAME=anakata-api E2E_ALLOW_RESET=1 tests/e2e/bin/reset.sh`, both themes on `/crm/sales/contacts` (Carolina admin):

- Seeded lifecycles and segments (BOOKED/HIGH, PROSPECT/NEW, SQL/NEW, AGENT/NEW). NPS “—” on every row.
- Drawer: A. Fontaine `?open=12` — FROM RMS LEDGER, ALL SENDS IN ENGLISH, booking `ANK-2026-0018` → RMS, timeline, Not held here, edit.
- Partner: S. Ferreira (`AGENT`) shows **AG-001 · Blue Latitude Travel** → `/rms/commercial/b2b?open=`.
- Edit: Anna Whitfield → “Anna Whitfield edited”.
- 409: email `k.osei@anakata.test` → “That email belongs to contact #20 (K. Osei).” + **Review merge**.
- Merge 19 + 20: survivor 19, K. Osei leaves the list, `?open=20` shows **Merged into Anna Whitfield edited**. Undo (reason required) restores K. Osei; timeline says undone; query becomes `?open=19`.
- Cancel `ANK-2026-0018` in the RMS → refresh Contacts: A. Fontaine LTV `USD 26,600` / HIGH / BOOKED becomes — / NEW / MQL. No CRM control changed the booking.

### Git commands for the user

Do **not** run these in the agent. Explicit paths only (never `-A`).

```bash
# 1. anakata-panel
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  app/assets/css/crm.css \
  app/components/crm/ContactDrawer.vue \
  app/components/crm/MergeContactsModal.vue \
  app/components/crm/contactHelpers.ts \
  app/pages/crm/sales/contacts.vue \
  app/pages/rms/commercial/b2b.vue \
  app/types/api.ts \
  eslint.config.mjs \
  i18n/locales/en.json \
  nuxt.config.ts \
  tests/unit/crmContactHelpers.test.ts
git commit -m "$(cat <<'EOF'
Add the CRM Contacts list, profile drawer, merge and undo.

Staff see derived fields from the CRM API only, edit owned identity,
and merge duplicates with a 30-day undo. Bookings open in the RMS.
EOF
)"
```

```bash
# 2. anakata-api report
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-09/REPORT.md
git commit -m "$(cat <<'EOF'
Record sprint 9 task 06: panel CRM Contacts.
EOF
)"
```
