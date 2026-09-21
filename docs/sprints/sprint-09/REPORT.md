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
