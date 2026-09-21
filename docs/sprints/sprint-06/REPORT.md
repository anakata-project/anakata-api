# Sprint 6 · Report
Each task appends its section below.

## Task 01 · Sensitive data: dedicated-key encryption, masking, redaction

### The key
`SENSITIVE_DATA_KEY` is its own secret (`config/sensitive.php`), same format as `APP_KEY` (`base64:` + 32 bytes). It never falls back to `APP_KEY`. Rotating sessions/cookies must not make passports unreadable, and a leaked `APP_KEY` must not decrypt them.

- `.env.example`: empty.
- `phpunit.xml` and `.env.testing.example`: dedicated test key (`force="true"` in phpunit so an empty local `.env.testing` cannot override it).
- e2e `api.env` / `api.testing.env`: a different dedicated key.
- README first-time setup: `php artisan key:generate --show`, paste into `SENSITIVE_DATA_KEY` — never copy `APP_KEY`.

A missing key throws `RuntimeException` naming `SENSITIVE_DATA_KEY` on first use of the cast.

`anakata:config-verify` and `/api/health` have no required-secrets list. **Production checklist:** set `SENSITIVE_DATA_KEY` before guests are stored.

### The cast
`App\Casts\SensitiveEncrypted` builds its own memoised `Encrypter` from `config('sensitive.key')` and `config('app.cipher')`. `null` and `''` store as `null`. Columns will be `text` (task 02). Ciphertext cannot be searched or indexed — nothing looks a guest up by passport number.

Proof model `sensitive_proof_hosts` (suite-wide test migration): raw column is ciphertext; the model reads plaintext; a different `SENSITIVE_DATA_KEY` cannot decrypt; rotating `APP_KEY` leaves the row readable.

**Rotation (later sprint, not built):** `SENSITIVE_DATA_PREVIOUS_KEYS`, decrypt-with-fallback, and a re-encrypt command.

### Masking (the API masks)
`App\Support\Guests\Masking` is the only place a resource will expose a sensitive value (task 02). The panel never receives a value the current user may not see.

- `passport`: full value if `guests.view_sensitive`; otherwise `'•••• '` + last three when the value has 6+ characters. Fewer than 6 characters (including empty string) → `'••••'` with no trailing characters, so a short number is never shown in full. `null` → `null`.
- `note`: `{ value, on_file }`. Denied viewers get `value: null` and still learn whether a note is on file.

### Who sees sensitive data
Functional spec: “masked for Sales Exec”; prototype `canOps()` is everyone but the sales agent.

- **Admin** — always (D3).
- **Manager** — yes. `SystemRole::Manager->defaultPermissions()` includes `guests.view_sensitive` (fresh databases).
- **Sales Exec** and **External finance** — no.

`RolesSeeder` is unchanged (`firstOrCreate`, never updates existing roles). An admin who removes this personal-data permission (LEG-002 still unresolved) must not have it put back on the next seed.

One-time DML migration `2026_09_21_200033_grant_guests_view_sensitive_to_manager` calls `GrantGuestsViewSensitive` (same pattern as `engine_copy.manage` / `agencies.manage`): lock the manager row, add the permission if missing, write `role.updated` as System. It runs once. Later removals stick. `down()` does not strip it.

### Redaction
`History::redact()` already walked nested objects. Tests now cover a list of guests and a full stored row: field names (`passport_no`) stay, values never do. `extraContext` is redacted the same way, so a passport cannot leak through `context`.

`dob` and `nationality` stay in `SensitiveFields` (CRM guard + history) and are **not** encrypted. PNG categories and the nationality KPI need them in clear text (I3).

### Logs
`dontFlash` includes every `SensitiveFields` key (merged with Laravel’s password defaults). A failing request that carries a passport number in the body does not write that number to the log or to the exception handler’s context. Request input is not added to exception context.

### CRM guard
`crm.sensitive` was already on the `/api/crm` group. An architecture test asserts every `api/crm` route gathers that middleware, so Sprint 9 cannot register CRM routes outside the guard.

### Checks
`composer check` inside Docker — 660 Pest tests (4429 assertions), Pint, Larastan level 6.

### Git (do not run)

```bash
git add config/sensitive.php
git add app/Casts/SensitiveEncrypted.php
git add app/Support/Guests/Masking.php
git add app/Support/Roles/GrantGuestsViewSensitive.php
git add app/Support/History/History.php
git add app/Enums/SystemRole.php
git add bootstrap/app.php
git add database/migrations/2026_09_21_200033_grant_guests_view_sensitive_to_manager.php
git add tests/Support/SensitiveEncrypted/SensitiveProofHost.php
git add tests/database/migrations/2026_09_21_000001_create_sensitive_proof_hosts_table.php
git add tests/Unit/Support/Guests/MaskingTest.php
git add tests/Unit/Enums/SystemRoleTest.php
git add tests/Feature/SensitiveData
git add tests/Feature/Auth/GrantGuestsViewSensitiveTest.php
git add tests/Feature/Crm/CrmSensitiveMiddlewareArchTest.php
git add tests/Feature/History/HistoryWriterTest.php
git add tests/Feature/Database/DatabaseSetupTest.php
git add tests/TestCase.php
git add .env.example .env.testing.example phpunit.xml
git add tests/e2e/environment/api.env tests/e2e/environment/api.testing.env
git add README.md
git add docs/sprints/sprint-06/REPORT.md
git commit -m "$(cat <<'EOF'
Add dedicated-key encryption, API masking, and leak-proofing for guest data.

EOF
)"
```

## Task 02 · Guests, guardian consent, validation, PNG categories

### Table
`guests`: `booking_id` (restrict, never cascade), `position` (1-based, unique per booking), `is_lead`, generated `lead_key` = `if(is_lead, booking_id, null)` + unique (one lead), passport-as-on-document names, `dob` / `passport_expiry` (`CalendarDate`), `nationality` char 2, `ecuador_resident`, encrypted `passport_no` / `medical_note` / `dietary_note` / `accessibility_note` (`SensitiveEncrypted`, text), insurance, guardian name / relationship / `guardian_consented_at` / `guardian_recorded_by`, stored `png_category` + `png_fee`, audit columns.

Model `Guest`, morph alias `guest`. `historyLabel()` / `displayName()` = trimmed name or `Guest {position}`. Completeness is derived (prototype `gComplete`): first + last + dob + nationality + passport + expiry + insurance. `passport_no IS NOT NULL` is enough in SQL because the cast stores empty as null.

The first guest created on a booking is the lead. Slots are added via POST; `CreateReservation` does not auto-create them.

### Permission and masking
View: `BookingPolicy::view`. Write: own-records (`can_act` / `ownsOrMayActOnAny`), same as the booking panel’s other edits. Medical, dietary and accessibility notes additionally need `guests.view_sensitive` — 403 otherwise.

`GuestResource` always goes through `Masking`. Sales Exec: `•••• ` + last three, notes `{ value: null, on_file }`. Operations (Admin / Manager): full values. `dob` and `nationality` are returned (the panel needs them) and stay in `SensitiveFields`. Empty `passport_no` from a user without the permission means unchanged; a typed value replaces; they cannot read back what they typed. Empty from operations clears it.

### PNG decision table (I4, B6, FIN-004, decision rows 4–5)
`App\Support\Guests\PngCategory::for` — age is whole years on the **Galápagos departure date** (`Age::at`, prototype `ageAt`), never an instant difference. Amounts from the published engine-settings document.

| Condition | Category | Fee source |
|---|---|---|
| missing DOB or nationality | `PENDING` | `null` |
| age `< fees.png.exempt_under_age` | `EXEMPT` | `0` |
| `EC` or Ecuador resident | `NATIONAL_OR_RESIDENT` | `fees.png.national_or_resident` at any age |
| CO / PE / BO (Res. 002-CGREG-24-02-2024), age `> 12` | `CAN_ADULT` | `fees.png.can_adult` |
| CO / PE / BO, age `≤ 12` | `CAN_MINOR` | `fees.png.can_minor` |
| else age `> 12` | `FOREIGN_OVER_12` | `fees.png.foreign_over_12` |
| else | `FOREIGN_12_AND_UNDER` | `fees.png.foreign_12_and_under` |

Cutoff 12 is B6, not the child-rate age of 17. Stored on the guest; recomputed on every guest write and on `MoveBooking`. A later publish of the amounts does **not** rewrite stored fees (G4; task 04’s SQL balance will sum stored values).

### Issues — none blocks a save
Computed by the API, returned with the list: `{ severity, code, guest_id, message }`. Guest data arrives piece by piece; saving is refused only for invalid input (DOB in the future, malformed nationality, expiry before the DOB, guardian name missing when consent is set, slot limit, illegal delete).

| Code | Severity | Source |
|---|---|---|
| `under_min_age` | error | OPS-004, `guests.child_min_age` |
| `guardian_consent_missing` | error | §6.4, `is_minor_now` (Galápagos today) without `guardian_consented_at` — including a guest who turns 18 before departure |
| `passport_expired` | error | expiry before `Departure::returnDate()` |
| `insurance_undeclared` | warning | OPS-005, named guest, booking CONFIRMED / FULLY_PAID / ON_BOARD / COMPLETED |
| `children_mismatch` | warning | cabin, every guest has a DOB, ages `child_min_age`–`child_max_age` ≠ priced `children` |
| consents | — | empty until task 03 |

`adults` / `children` stay the priced party (G4). The comparison is an issue, not an automation.

### Guardian consent
Visible only for `is_minor_now`. Ticking records the timestamp once and the user; unticking clears it. A guardian name is required when consent is set.

Consent is its own logical change: `guest.guardian_consented` only. `guest.updated` lists the other changed fields and is written only if at least one of those changed — never both entries for the consent alone.

### History
On the **booking**: `guest.added` (`Guest slot added ({n} guests)`), `guest.updated` (`Passenger updated — {name}: {labels}`), `guest.removed` (`Empty guest slot removed ({n} guests)`), `guest.guardian_consented`. Values go through `History::redact`. Field names are allowed.

### Lock order
Guest writes: **departure → booking → guest row** (`BookingMutationLock::acquire` first). A guest write can later change collected PNG and therefore the balance (I9, I10). `MoveBooking` still locks old + new departures, then the booking, then recomputes stored PNG.

### Booking exposure
`BookingResource.guests_summary: { complete, total }` (prototype `gdOf`). Index uses `withCount` (`scopeWithGuestSummary`); query count does not grow when extra bookings have guests.

### Nationality
Validated as `^[A-Z]{2}$` for now. Tightened to the ISO list in task 05 (Contacts In), which adds that list.

### Seed
`DemoGuestsSeeder` (local/testing), idempotent, after demo bookings and requests. Passengers from prototype `seedOps`. Incomplete guests stay incomplete (issues fixtures). Brandt child: guardian Markus Brandt / Father / 02 Jul 2026, 14:05 Galápagos. First slot is lead.

Charter padding (ANK-2026-0012, priced party **0 adults**): slot count from the published engine settings `guests.max_per_yacht`, not a literal 16.

### Open question
Should CONFIRMED require complete guests? The prototype does not; this sprint does not block the transition.

### Checks
`composer check` inside Docker — 693 Pest tests (4625 assertions), Pint (692 files), Larastan level 6 (0 errors).

### Git (do not run)

```bash
git add database/migrations/2026_09_21_200034_create_guests_table.php
git add app/Enums/PngCategory.php
git add app/Models/Guest.php app/Models/Booking.php
git add app/Support/Guests
git add app/Actions/Guests app/Actions/Bookings/MoveBooking.php
git add app/Policies/GuestPolicy.php app/Policies/BookingPolicy.php
git add app/Http/Controllers/Rms/GuestController.php
git add app/Http/Requests/Rms/SaveGuestRequest.php
git add app/Http/Resources/Rms/GuestResource.php app/Http/Resources/Rms/BookingResource.php
git add app/Http/Controllers/Rms/BookingController.php
git add app/Providers/AppServiceProvider.php
git add routes/api/rms.php
git add database/factories/GuestFactory.php
git add database/seeders/DemoGuestsSeeder.php database/seeders/DatabaseSeeder.php
git add tests/Unit/Support/Guests
git add tests/Feature/Guests
git add tests/Concurrency/GuestWriteConcurrencyTest.php
git add tests/Feature/Bookings/BookingListQueryCountTest.php
git add tests/Feature/Database/DatabaseSetupTest.php
git add docs/sprints/sprint-06/REPORT.md
git commit -m "$(cat <<'EOF'
Add booking guests with PNG categories, issues, and guardian consent.

EOF
)"
```

## Task 03 · The consent log and the retention jobs

### Shape change and registry counts
`legal.consent_versions` is on the business-rules document: `terms`, `cancellation`, `privacy`, `insurance`, `marketing`. Defaults are the prototype `CONSENT_VER` labels (text still PENDING CLIENT — LEG-001 / LEG-002 / OPS-005). `fromArray()` fills a missing key as `""`.

DML-only migration `2026_09_21_200035_add_consent_versions_to_business_rules` publishes v2 as System when any of the five keys is missing, merging only those keys. Approval reference: `Sprint 6: legal.consent_versions added (defaults from prototype CONSENT_VER, sources LEG-001 / LEG-002 / OPS-005)`. Fresh installs stay on v1 via `initial()`. `anakata:config-verify` fails on a pre-change document and passes after.

Registry: five new `here` rows in group **Legal documents**, status `PENDING CLIENT`. Fresh-seed totals:

- all **55** · here **30** · other_pages **15** · locked **10** · differs_or_flagged **16**
- Flagged: 15 pending-status rows + OPS-006

BR-01 and `tests/e2e/fixtures/reference-values.md` moved with those numbers.

### Consent table — append-only
`consents`: `booking_id` (restrict), `document` (`TERMS · CANCELLATION · PRIVACY · INSURANCE · MARKETING`), `version`, `accepted_at`, `ip` (nullable), `source` (`ENGINE · PAYMENT_LINK · STAFF`), `recorded_by`, `how_obtained`, `withdrawn` (default false, for Sprint 9), timestamps + audit.

Triggers refuse every `UPDATE` and `DELETE` (`consents is append-only`), same discipline as `change_history`. There is **no unique key**. A unique accepted `(booking, document, version)` would permanently block accept → withdraw → re-accept of v1, and the table cannot be patched later.

“Already consented” is the latest row: `RecordConsent` `lockForUpdate`s **only the booking** (no departure, no H10 conflict), reads the latest consent for `(booking, document)`, and no-ops if that row is a non-withdrawn accept of the same version. Otherwise it inserts. Accept → same version again is a no-op. Accept → withdrawal (inserted in the test) → re-accept of v1 inserts a new row. Two concurrent staff posts produce one row (1205, never 1213).

Consents are outside the retention job (seven years, I6). Morph alias `consent`.

### `outdated` and LEG-002
`GET /api/rms/bookings/{booking}/consents` returns one row per document: current version from the published rules, latest **accepted** consent (withdrawn rows ignored) or `null`, `required`, `outdated`.

`outdated: true` when an accepted row’s version is not the current label. It still **counts** as present — missing-consents does not fire. Whether staff must re-capture an outdated version is a legal question for **LEG-002**, not decided here.

### `RecordConsent` is the only write
Staff `POST /api/rms/bookings/{booking}/consents` `{ document, how_obtained }` — own-records, current version, `source = STAFF`, `ip` null, history `consent.recorded` (`Consent recorded — {label}`, reason = how obtained). Sprints 7 and 8 call the same action with `PAYMENT_LINK` / `ENGINE` and an IP. Nothing is pre-checked.

### Missing-consents issue
On CONFIRMED or later, one warning: `Missing consent records: Privacy policy, ….` Marketing is never required. Pending-payment / requested stay silent. Task 02’s placeholder is filled (`consents_missing`).

### Retention
`anakata:retention`, `daily()` in `Pacific/Galapagos`, `withoutOverlapping()`. Chunks `Booking::withTrashed()` whose guests still have a non-null `passport_no`, `passport_expiry`, or note — not every booking.

Values from the published rules only. Calendar math on Galápagos dates vs `Departure::returnDate()`:

- Passports when today is **after** `returnDate->addMonthsNoOverflow(months)`. 29 February 2028 + 24 months → purge boundary **28 February 2030** (`addMonths` would give 1 March 2030). Day before / on the boundary: no; day after: yes.
- Notes when today is after `returnDate + days`.

One System history entry per booking that actually changed: counts only, no values. `--dry-run` prints the counts and writes nothing. A second run the same day writes nothing. Soft-deleted bookings are included.

**B4 must be confirmed before this runs in production** (sprint README client question 1). The API README now says so.

### Seed
`DemoConsentsSeeder` (local/testing) calls `RecordConsent`. Confirmed demo bookings get the four required docs from the payment-link seed (02 Jul 2026, 14:05 GALT, a stable IP). Marketing too, except ANK-2026-0007. PENDING_PAYMENT / REQUESTED get none. Versions come from the published document.

### Checks
`composer check` inside Docker — 718 Pest tests (4753 assertions), Pint (715 files), Larastan level 6 (0 errors).

### Git (do not run)

```bash
git add README.md
git add app/Enums/BookingStatus.php app/Enums/RuleGroup.php
git add app/Enums/ConsentDocument.php app/Enums/ConsentSource.php
git add app/Models/Booking.php app/Models/Consent.php
git add app/Policies/BookingPolicy.php
git add app/Providers/AppServiceProvider.php
git add app/Support/BusinessRules/Registry.php
git add app/Support/Config/Documents/BusinessRulesDocument.php
git add app/Support/Config/Documents/ConsentVersions.php
git add app/Support/Guests/GuestIssues.php
git add app/Support/Consents app/Support/Retention
git add app/Actions/Consents
git add app/Console/Commands/RetentionCommand.php
git add app/Http/Controllers/Rms/ConsentController.php
git add app/Http/Requests/Rms/RecordConsentRequest.php
git add app/Http/Resources/Rms/BookingConsentResource.php
git add app/Http/Resources/Rms/ConsentResource.php
git add database/factories/ConsentFactory.php
git add database/migrations/2026_09_21_200035_add_consent_versions_to_business_rules.php
git add database/migrations/2026_09_21_200036_create_consents_table.php
git add database/seeders/DemoConsentsSeeder.php database/seeders/DatabaseSeeder.php
git add routes/api/rms.php routes/console.php
git add tests/Feature/Config/AddConsentVersionsMigrationTest.php
git add tests/Feature/Config/BusinessRulesDocumentTest.php
git add tests/Feature/Config/BusinessRulesEndpointsTest.php
git add tests/Feature/Database/DatabaseSetupTest.php
git add tests/Feature/Guests/GuestIssuesTest.php
git add tests/Feature/Consents tests/Feature/Retention
git add tests/Concurrency/ConsentWriteConcurrencyTest.php
git add tests/e2e/fixtures/reference-values.md
git add tests/e2e/scenarios/config/BR-01-fresh-seed-registry.md
git add docs/sprints/sprint-06/REPORT.md
git commit -m "$(cat <<'EOF'
Add the append-only consent log and guest-data retention jobs.

EOF
)"
```

## Task 04 · Extras catalogue, booking extras, fees, charges

### Catalogue = `ConfigKind::Extras`
Fourth config document, same Sprint 2 path. `extra_versions` is append-only. `CurrentConfig::extras()`. `ConfigSeeder` publishes `initial()` when the table is empty — no DML migration.

`initial()` is the prototype ANC six items (all `active: true`): FLT 420 / HPRE 320 / HPOST 320 / SPA · BAR · BTQ on request.

**Immutable once published: codes only.** Names, units, prices and flags may change. Retire with `active: false`. `ConfigDocument::publishErrors()` (default `[]`) is applied in `ConfigPublisher` the same way as rate-year errors — 422 keyed `document.items.{i}.code`. Endpoints `/api/rms/extras` (GET / validate / versions). View `panel.rms`, publish `extras.manage`. Approval reference required.

### Frozen booking extras
`booking_extras`: snapshotted `name` / `unit` / `rate_usd`. Add only active catalogue items; on-request `rate_usd` required; optional override allowed. Remove deletes the row; history keeps before-state. Terminal statuses 422. Lock order departure → booking → extra row. History uses prototype wording + `Money::format`. A later catalogue publish never rewrites rows (G4).

`GET /api/rms/bookings/{booking}/extras` returns rows + `extras_total` and the fee-display facts the panel checkboxes need (`png_known_total`, `png_pending_count`, `tct_pp`, `tct_count`, `extras_due_hours`).

### Fee collection — default false; TCT = guest records
`png_collected` / `tct_collected` default **false** (PENDING CLIENT). `tct_rate_usd` snapshotted from `fees.tct_pp` on switch-on. TCT basis is **guest records**, not the priced party (prototype `paxCount`). PNG = Σ stored `guests.png_fee` when collected; pending category contributes 0. PNG switch-on calls `ApplyPng::toBooking()`. Switch-off leaves stored amounts in place but they drop out of the balance.

`PATCH /api/rms/bookings/{booking}/fees` — own-records, same lock, same terminal 422. History `booking.fees_changed` with prototype `setFee` wording.

### Charges model — one formula
`charges_total` = `total` + extras + collected fees. `balance()` / `balanceSql()` = charges − `Ledger::paid`. `cruise_outstanding` = `GREATEST(0, total − paid)`. `depositAmount()` unchanged (share of `total`). OVERDUE is CONFIRMED / ON_HOLD_AGENCY + cruise outstanding > 0 + Galápagos date after due date. `extras_due_at` is shown only (Sprint 11 alerts).

Writers use `balanceFresh()` / `chargesTotalFresh()` so a loaded list aggregate cannot hide a just-inserted extra or fee:

- `ApplyPaymentEffects` FULLY_PAID — `balanceFresh() <= 0` from CONFIRMED only
- `RecordPayment` overpayment — `paidFresh + amount` vs `chargesTotalFresh()`
- `CreatePaymentLink` default / cap / empty-balance — `balanceFresh()`

FULLY_PAID never regresses: an extra on a fully paid booking re-opens `balance` and does not move status back. Paying that extra to zero writes no second FULLY_PAID transition. Penalties stay on cruise `total`. Extras and collected fees are treated as refunded in full minus nothing until the client says otherwise.

### Grep of `->total` / `bookings.total` in `app/` — three buckets

Pricing-engine `Quote::$total` and paginator `meta.total` omitted.

**Stays cruise** (deposit, penalties, commission, repricing, the `total` field itself):

- `Booking::depositAmount()` — `$this->total * deposit_pct`
- `Booking::commissionAmount()` — `$this->total * commission_pct`
- `PaymentsKpis` `commission_accrued` — `ROUND(bookings.total * commission_pct / 100)`
- `CreateRefundRequest` — `CancellationPenalty::penalty($booking->total, …)`
- `MoveBooking` — writes / confirms `$booking->total` as the cruise reprice (`current_total`, `new_total`, `difference`)
- `CreateReservation` / `CreateBookingRequest` — persist the quoted cruise total
- `BookingResource` `total`, `AgencyResource` booking `total`, `AgencyBookingWindow` `sum('total')` (agency revenue)
- `GroupResource` `total` (sum of cruise totals)
- `BookingRequestResource` `estimated_value`

**Becomes charges** (anything meaning “what is owed”):

- `Booking::balance()` / `balanceSql()` — `charges_total − paid`
- `RecordPayment` overpayment — compare `paidFresh + amount` to `chargesTotalFresh()`; warning wording stays “above its total by …”
- `ApplyPaymentEffects` FULLY_PAID — `balanceFresh() <= 0` from CONFIRMED only
- `CreatePaymentLink` default / cap / empty-balance guard — `balanceFresh()`
- `TransitionBooking` manual FULLY_PAID note — already `balance()` (charges after this change)
- `scopePendingPayment`, `PaymentsKpis` `pending` / `pending_count` — keep `balanceSql()` (now charges)
- `GroupResource` `balance` — already sums `balance()`
- `FlagOverdueCommand` history key `balance` — **not** this bucket; see next

**Becomes cruise outstanding** (OVERDUE):

- `Booking::isOverdue()` — `cruiseOutstanding() > 0`
- `Booking::scopeOverdue()` — `cruiseOutstandingSql() > 0`
- `PaymentsKpis` `overdue_count` / `overdue_amount` — `cruiseOutstandingSql()`
- `BookingController::overdueKpis()` — `SUM(cruiseOutstandingSql())`
- `FlagOverdueCommand` history `balance` value — store `cruiseOutstanding()` so extras never inflate the overdue flag

### Seed
`DemoExtrasSeeder` after guests (PNG already stored). Amounts from the published catalogue / engine settings. Not on `ANK-2026-0003` or `ANK-2026-0005`.

- `ANK-2026-0011` — FLT × 2 → extras 840
- `ANK-2026-0007` — HPRE × 1 → extras 320
- `ANK-2026-0009` — `png_collected` (two DE/SE adults → 200 × 2 = 400)

Deposit / commission / AG-001 revenue stay cruise. `reference-values.md` updated in this task.

### e2e lines this seed invalidates (for task 10)

Expected new balances: 0007 **21,267**; 0009 **45,400**; 0011 **24,780**. Pending KPI **433,547**. Deposit, settled, pledged, commission **2,328**, AG-001 revenue **23,275** stay.

| File | Line / check | Today | After seed |
|---|---|---|---|
| `tests/e2e/fixtures/reference-values.md` § Seeded money intro | “Balance on the list is `total − settled` (H2)” | cruise-only formula | charges − settled |
| same, money table 0007 | Balance `USD 20,947` | 20,947 | 21,267 |
| same, money table 0009 | Balance `USD 45,000` | 45,000 | 45,400 |
| same, money table 0011 | Balance `USD 23,940` | 23,940 | 24,780 |
| same, KPI paragraph | Pending `USD 431,987` | 431,987 | 433,547 |
| `tests/e2e/scenarios/bookings/BKG-01-seeded-bookings-segments.md` E5 | `0007 USD 20,947` · `0009 USD 45,000` | those two figures | 21,267 / 45,400 |
| `tests/e2e/scenarios/payments/PAY-06-payments-revenue.md` E1 pending | `USD 431,987` | 431,987 | 433,547 |

**Not invalidated** (cruise / not money): BKG-01 E3 (segment filter), BKG-12 E1 (locks), INV-01 E5 (calendar `0007`), PAY-06 E3 commission `USD 2,328` on 0007, AG-001 revenue `USD 23,275`.

`reference-values.md` was updated here. Do **not** rewrite the BKG-01 / PAY-06 scenario files in this task.

### Checks
`composer check` inside Docker — 748 Pest tests (4934 assertions), Pint (742 files), Larastan level 6 (0 errors). `anakata:config-verify` — extras v1 valid.

### Git (do not run)

```bash
git add app/Enums/ConfigKind.php
git add app/Support/Config/ConfigDocument.php
git add app/Support/Config/Documents/ExtraItem.php
git add app/Support/Config/Documents/ExtrasDocument.php
git add app/Services/Config/ConfigPublisher.php
git add app/Services/Config/ConfigRegistry.php
git add app/Services/Config/CurrentConfig.php
git add app/Providers/AppServiceProvider.php
git add app/Models/ExtraVersion.php app/Models/BookingExtra.php app/Models/Booking.php
git add app/Policies/ExtraVersionPolicy.php app/Policies/BookingExtraPolicy.php app/Policies/BookingPolicy.php
git add app/Actions/Extras
git add app/Actions/Payments/RecordPayment.php app/Actions/Payments/CreatePaymentLink.php
git add app/Support/Bookings/BookingCharges.php
git add app/Support/Payments/ApplyPaymentEffects.php
git add app/Support/Payments/PaymentsKpis.php
git add app/Console/Commands/FlagOverdueCommand.php
git add app/Http/Controllers/Rms/ExtrasController.php
git add app/Http/Controllers/Rms/BookingExtraController.php
git add app/Http/Controllers/Rms/BookingFeesController.php
git add app/Http/Controllers/Rms/BookingController.php
git add app/Http/Requests/Rms/AddBookingExtraRequest.php
git add app/Http/Requests/Rms/UpdateBookingFeesRequest.php
git add app/Http/Resources/Rms/BookingExtraResource.php
git add app/Http/Resources/Rms/BookingResource.php
git add database/migrations/2026_09_21_200037_create_extra_versions_table.php
git add database/migrations/2026_09_21_200038_create_booking_extras_and_fee_columns.php
git add database/factories/BookingExtraFactory.php database/factories/BookingFactory.php
git add database/seeders/DemoExtrasSeeder.php database/seeders/DatabaseSeeder.php
git add routes/api/rms.php
git add tests/Pest.php
git add tests/Feature/Config/ExtrasDocumentTest.php
git add tests/Feature/Config/ExtrasEndpointsTest.php
git add tests/Feature/Config/ConfigVerifyCommandTest.php
git add tests/Feature/Database/DatabaseSetupTest.php
git add tests/Feature/Bookings/BookingListQueryCountTest.php
git add tests/Feature/Payments/RecordPaymentTest.php
git add tests/Feature/Extras
git add tests/Concurrency/ExtraVsPaymentConcurrencyTest.php
git add tests/e2e/fixtures/reference-values.md
git add docs/sprints/sprint-06/REPORT.md
git commit -m "$(cat <<'EOF'
Add the extras catalogue, frozen booking extras, and charges balance.

EOF
)"
```

### Notes for later (do not build)
Invoice lines (Sprint 7); extras overdue alerts (Sprint 11); on-board extras status (08 C / B6); Contacts In (task 05); panel Extras tab (task 08); rewrite the BKG-01 / PAY-06 lines listed above (task 10). Open refund question: extras and collected fees refunded in full minus nothing until the client says otherwise.

## Task 05 · Contacts In

### The two endpoints
`GET /api/rms/contacts-in?from&to` — one row per booking or request (requests are `REQUESTED` bookings). Window is Galápagos calendar days on `departures.date`, same as the bookings list. Soft-deleted rows excluded (G8). Cancelled and released stay on the list. Paginated (`per_page` default 50).

`GET /api/rms/contacts-in/nationalities?from&to` — `{ nationalities: [{ nationality, country_name, guests, bookings }], unknown, total_guests }`. One aggregate over `guests.nationality`. Top ten by guests desc, then country name asc. Cancelled, cancelled-postpaid **and released** are excluded (prototype only drops the two cancelled statuses; RELEASED is out too). Soft-deleted bookings excluded.

### Value on the list is `charges_total`
Not the cruise `total`. A booking with extras or collected fees shows cruise + extras + collected PNG/TCT (task 04 / I9). Prototype `renderContactsIn` still renders `b.total`; the panel column label stays “Value”.

### Visibility
`Booking::scopeVisibleTo` — own-records unless `bookings.view_all`. Extracted from the bookings index; `BookingController::index` now calls it. `scopeDepartingBetween` is the same `departures.date` window. Contacts In reuses both. Seeded Sales Exec still has `bookings.view_all`; the own-records fixture is a custom role with only `panel.rms`.

### Named guests only
Empty padded slots (no first name and no last name) are **excluded**, not reported as `slots_not_filled`. They do not count in `unknown` or `total_guests`. `Guest::scopeNamed`. Seeded charter `ANK-2026-0012` (Rutger + yacht-max empty slots) contributes one NL guest; its empty slots do not appear in `unknown`.

### Country names
`App\Support\Countries` over `app/Support/Countries/iso3166.php` — official ISO-3166-1 alpha-2 English short names. No Composer package. `SaveGuestRequest` now validates against that list (empty still allowed). Prototype `XX` / Other is not a country code. The panel does not get a countries endpoint (guest form select is task 07).

### CRM-guard test
`nationality` stays in `SensitiveFields` (correct for CRM). The RMS aggregate is allowed. A probe that returns the same payload through `crm.sensitive` throws in testing (`CRM response contained sensitive fields: nationality`), so Sprint 9 cannot move this route into the CRM section unnoticed. The list and aggregate never return `dob`, `passport_no` or notes.

### Still open
Sprint 4 task 03: `GET /api/rms/contacts?q=` is not own-records scoped. Still undecided whether a Sales Exec should see another owner’s clients. Not decided here.

### Checks
`composer check` inside Docker — 775 Pest tests (5303 assertions), Pint (752 files), Larastan level 6 (0 errors).

### Git (do not run)

```bash
git add app/Support/Countries.php app/Support/Countries/iso3166.php
git add app/Models/Booking.php app/Models/Guest.php
git add app/Http/Controllers/Rms/BookingController.php
git add app/Http/Controllers/Rms/ContactsInController.php
git add app/Http/Requests/Rms/IndexContactsInRequest.php
git add app/Http/Requests/Rms/SaveGuestRequest.php
git add app/Http/Resources/Rms/ContactInResource.php
git add app/Http/Resources/Rms/ContactsInNationalitiesResource.php
git add routes/api/rms.php
git add tests/Unit/Support/CountriesTest.php
git add tests/Feature/ContactsIn
git add tests/Feature/Guests/GuestEndpointsTest.php
git add tests/Feature/OpenApi/PanelResponseSchemasTest.php
git add docs/sprints/sprint-06/REPORT.md
git commit -m "$(cat <<'EOF'
Add the RMS Contacts In list and nationality aggregate.

EOF
)"
```

### Notes for later (do not build)
Panel Contacts In (task 09); regenerate types (task 06); guest-form country select reads `GET /api/rms/countries` (task 06 prelude, task 07). Sprint 4 contact-search visibility still open.

## Task 06 · anakata-ui · Regenerate types, release `v0.7.0`

### What was built
PHPDoc / OpenAPI prelude on the API so Scramble emits the Sprint 6 shapes, including `GET /api/rms/countries`, then types regenerated against `http://localhost:8000/docs/api.json`. Layer `0.6.4` → `0.7.0`. Types only: no composables, components, or panel behaviour.

Calendar dates stay `string` (`YYYY-MM-DD`). Instants stay ISO strings.

### API prelude

| Target | What landed |
|---|---|
| `GET /api/rms/countries` | `{ code, name }[]` from `Countries::all()` (the same ISO list guest validation uses). Raw JSON array, `$wrap = null`. Authorised by the existing `permission:panel.rms` group — not `bookings.create` / form-options. |
| `MaskedNoteResource` | Named schema for `{ value: string \| null, on_file: boolean }`. `GuestResource` notes `$ref` it. Inline `@var` so Scramble does not emit `on_file: string`. |
| `ContactsInNationalitiesResource` | `@property` + `#[DocumentedResponse]` so `nationalities` / `unknown` / `total_guests` are typed (were `string`). |
| `BookingConsentResource.outdated` | Inline `@var bool`. Nested `consent` is a `ConsentResource`. |
| `ContactInResource.can_act` | Inline `@var bool`. |
| `PanelResponseSchemasTest` | `GuestResource`, `MaskedNoteResource`, `BookingConsentResource`, `ConsentResource`, `BookingExtraResource`, `CountryResource`; BookingResource charge keys + `guests_summary`; guest-list and extras-list additional properties; extras current/versions paths; contacts-in `from`/`to`; `ConsentDocument` enum. |

`PngCategory` and `ConsentSource` have no FormRequest schema. Resource fields for those stay `string`; the layer leftovers close the unions.

### Note shape

The API sends `medical_note` / `dietary_note` / `accessibility_note` as `{ value: string | null, on_file: boolean }` (`Masking::note`). `passport_no` is `string | null` (full, masked, or empty). The type does not claim the panel always has the full note: `value` is nullable.

### Line counts

| File | Before | After |
|---|---|---|
| `app/types/api.d.ts` | 6535 | 7490 |
| `app/types/inventory.ts` | 247 | 247 |
| `app/types/config.ts` | 368 | 401 |
| `app/types/bookings.ts` | 208 | 208 |
| `app/types/payments.ts` | 161 | 161 |
| `app/types/index.ts` | 176 | 204 |
| `app/types/anakata-augment.d.ts` | 17 | 17 |
| `app/types/guests.ts` | — | 101 |
| `app/types/extras.ts` | — | 24 |

### Schema → alias (`app/types/guests.ts`)

| Alias | Source |
|---|---|
| `MaskedNote` | `MaskedNoteResource` (`value: string \| null`, `on_file: boolean`) |
| `Guest` | `GuestResource` + `png_category: PngCategory \| null` |
| `PngCategory` | leftover (`PENDING` … `FOREIGN_12_AND_UNDER`) — mirrors `App\Enums\PngCategory` |
| `GuestIssueSeverity` | leftover `'error' \| 'warning'` |
| `GuestIssue` | generated `guest.index` issue item + `severity` overlay |
| `GuestListSummary` | generated `guest.index` additional (`complete_count`, `total`, `png_known_total`, `png_pending_count`, `issues`) |
| `ConsentDocument` | named enum schema |
| `ConsentSource` | leftover `'ENGINE' \| 'PAYMENT_LINK' \| 'STAFF'` |
| `Consent` | `ConsentResource` + `document` / `source` overlays |
| `BookingConsent` | extra alias — `BookingConsentResource` (the list row the panel table iterates) |
| `Country` | `CountryResource` (`GET /rms/countries`) |
| `ContactInRow` | `ContactInResource` + booking enum overlays |
| `NationalityRow` / `NationalitiesSummary` | `ContactsInNationalitiesResource` |

### Schema → alias (`app/types/extras.ts` + `config.ts`)

New `extras.ts` (not `payments.ts`): booking extras are frozen catalogue lines, not ledger payments. Guests already have their own file.

| Alias | Source |
|---|---|
| `BookingExtra` | `BookingExtraResource` (fully generated) |
| `ExtrasListSummary` | generated `bookingExtra.index` additional (fee-display facts) |
| `ExtrasCatalogueItem` | leftover — mirrors `App\Support\Config\Documents\ExtraItem` |
| `ExtrasCatalogue` / `ExtrasDocument` | leftover — `ConfigCurrentResource.document` is untyped |
| `ExtrasVersion` | `ConfigVersion<ExtrasCatalogue>` (same envelope as rates / engine / rules) |
| `ConsentVersions` | leftover on `BusinessRulesDocument.legal.consent_versions` |
| `RuleGroup` | leftover gained `'legal'` |

### Booking

Same `Booking` alias. Scalars `guests_summary`, `extras_total`, `fees_collected_total`, `png_collected`, `tct_collected`, `png_pending_count`, `charges_total`, `cruise_outstanding`, `extras_due_at` come through from `BookingResource`. No new overlays.

### Kept leftovers

No inventory leftovers retired. New leftovers listed above, each with a `Mirrors App\…` comment.

No country / PNG / consent / extras runtime list in the layer. Labels and names come from the API.

### Pins

Panel and engine README rows now say `` `extends: ['../anakata-ui']` (`v0.7.0`) ``. **Neither pin is enforced** — the apps resolve the sibling folder, so the version line is documentation only.

### Files touched
**anakata-api (prelude)**
- `app/Http/Controllers/Rms/CountryController.php` (new)
- `app/Http/Resources/Rms/CountryResource.php` (new)
- `app/Http/Resources/Rms/MaskedNoteResource.php` (new)
- `app/Http/Resources/Rms/GuestResource.php`
- `app/Http/Resources/Rms/BookingConsentResource.php`
- `app/Http/Resources/Rms/ContactInResource.php`
- `app/Http/Resources/Rms/ContactsInNationalitiesResource.php`
- `app/Http/Controllers/Rms/ContactsInController.php`
- `routes/api/rms.php`
- `tests/Feature/Countries/CountriesEndpointTest.php` (new)
- `tests/Feature/OpenApi/PanelResponseSchemasTest.php`

**anakata-ui**
- `app/types/api.d.ts`
- `app/types/guests.ts` (new)
- `app/types/extras.ts` (new)
- `app/types/config.ts`
- `app/types/index.ts`
- `package.json` (`0.7.0`)
- `CHANGELOG.md`
- `README.md`

**anakata-panel / anakata-engine**
- `README.md` (documentation pin only)

**anakata-api (this report)**
- `docs/sprints/sprint-06/REPORT.md`

### Deviations
- `BookingConsent` is an extra alias (not in the task list) because the consent table iterates `BookingConsentResource`, not `ConsentResource`.
- `NationalitiesSummary` is the envelope around `NationalityRow`.
- Extras catalogue leftovers live in `config.ts` and are re-exported from `extras.ts`.

### Open questions
None.

### Notes for later
- Task 07: nationality select from `GET /api/rms/countries` (`Country`). Note cards read `guest.medical_note.on_file` / `.value`, not `medical_note_on_file`.
- Task 08: extras tab from `BookingExtra` + `ExtrasListSummary`; catalogue editor from `ExtrasCatalogue`.
- Task 09: Contacts In from `ContactInRow` / `NationalityRow`.

### Quality
- anakata-api: `composer check` inside Docker — 778 tests (5455 assertions), Pint (756 files), Larastan level 6 (0 errors).
- anakata-ui: lint, typecheck, test (35), build — pass.
- anakata-panel / anakata-engine: typecheck — pass.
- Fresh clone into `/tmp/anakata-fresh/{anakata-ui,anakata-panel,anakata-engine}` (sibling layout). Overlayed the working trees. Confirmed the ui clone has **no** `app/types/nuxt.d.ts`.
  - ui / panel / engine: typecheck pass
  - panel / engine: build pass
  - **OVERLAY CLONE OK**
  - **Repeat this clone after the pushes below**, checking out `anakata-ui` at `v0.7.0` with **no** overlay.

### Git commands for the user

Do **not** run these in the agent. Explicit paths only (never `-A`). Run in this order.

```bash
# 1. anakata-api prelude (countries + OpenAPI typing — not this report)
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Http/Controllers/Rms/CountryController.php \
  app/Http/Controllers/Rms/ContactsInController.php \
  app/Http/Resources/Rms/CountryResource.php \
  app/Http/Resources/Rms/MaskedNoteResource.php \
  app/Http/Resources/Rms/GuestResource.php \
  app/Http/Resources/Rms/BookingConsentResource.php \
  app/Http/Resources/Rms/ContactInResource.php \
  app/Http/Resources/Rms/ContactsInNationalitiesResource.php \
  routes/api/rms.php \
  tests/Feature/Countries/CountriesEndpointTest.php \
  tests/Feature/OpenApi/PanelResponseSchemasTest.php
git commit -m "$(cat <<'EOF'
Type Sprint 6 OpenAPI responses and add GET /rms/countries.

Guest notes emit MaskedNoteResource; Contacts In nationalities and
the ISO country list are named schemas so the layer can regenerate
them instead of hand-writing the shapes.
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
  app/types/config.ts \
  app/types/index.ts \
  app/types/guests.ts \
  app/types/extras.ts
git commit -m "$(cat <<'EOF'
Regenerate API types for guests, extras, countries and charges.

Sprint 6 aliases live in guests.ts and extras.ts; Booking keeps
the same name. Catalogue leftovers stay in config.ts.
EOF
)"
git tag v0.7.0
git push origin HEAD
git push origin v0.7.0
```

```bash
# 3. anakata-panel
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add README.md
git commit -m "$(cat <<'EOF'
Document the layer pin as v0.7.0.

extends still resolves the sibling folder; the version is documentation only.
EOF
)"
git push origin HEAD
```

```bash
# 4. anakata-engine
cd /home/mohammad/Code/iconic/anakata/anakata-engine
git add README.md
git commit -m "$(cat <<'EOF'
Document the layer pin as v0.7.0.

extends still resolves the sibling folder; the version is documentation only.
EOF
)"
git push origin HEAD
```

```bash
# 5. anakata-api report
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-06/REPORT.md
git commit -m "$(cat <<'EOF'
Record sprint 6 task 06: regenerated UI API types.
EOF
)"
git push origin HEAD
```

```bash
# 6. Fresh-clone repeat — after the pushes, no working-tree overlay
rm -rf /tmp/anakata-fresh
mkdir -p /tmp/anakata-fresh
git clone https://github.com/anakata-project/anakata-ui.git /tmp/anakata-fresh/anakata-ui
git -C /tmp/anakata-fresh/anakata-ui checkout v0.7.0
git clone https://github.com/anakata-project/anakata-panel.git /tmp/anakata-fresh/anakata-panel
git clone https://github.com/anakata-project/anakata-engine.git /tmp/anakata-fresh/anakata-engine
# then in each: pnpm install
# ui / panel / engine: pnpm typecheck
# panel / engine: pnpm build
```

## Task 07 · Booking panel Guests tab

### What was built
The booking panel Guests tab is live. `BOOKING_TABS.guests` is enabled. `BookingGuestsTab` mounts on first visit (`v-else-if="tab === 'guests'"`, same as Payments), fetches guests / consents / countries, and emits `updated` through `onPaymentsUpdated` so Overview, the list GUESTS line, and History refresh after every write. The panel renders API fields only — no PNG, issue, or mask arithmetic. Country names come from `GET /api/rms/countries`.

### API prelude — slot limit on the guest list

`GuestCapacity::max(BookingType, GuestsSettings)` is the single charter (`max_per_yacht`) vs cabin (`max_per_cabin`) rule. `AddGuest` and `GuestIssues::summary` both call it.

`GuestIssues::summary` now returns `complete_count`, `total`, `png_known_total`, `png_pending_count`, `max`, `can_add` (`total < max`). `GuestController::index` additional + `#[DocumentedResponse]` PHPDoc include both keys. `PanelResponseSchemasTest` guest-index keys: `max`, `can_add`.

### Types — `v0.7.1`

`pnpm types:api` against `http://localhost:8000/docs/api.json`. `guest.index` gained `max: number` and `can_add: boolean`. `GuestListSummary` is still `Omit<GuestIndexBody, 'data' | 'issues'>` — no local intersection, no leftover overlay for those keys.

| File | Before | After |
|---|---|---|
| `app/types/api.d.ts` | 7490 | 7492 |
| `app/types/inventory.ts` | 247 | 247 |
| `app/types/config.ts` | 401 | 401 |
| `app/types/bookings.ts` | 208 | 208 |
| `app/types/payments.ts` | 161 | 161 |
| `app/types/index.ts` | 204 | 204 |
| `app/types/anakata-augment.d.ts` | 17 | 17 |
| `app/types/guests.ts` | 101 | 101 |
| `app/types/extras.ts` | 24 | 24 |

Layer `0.7.0` → `0.7.1`. Panel and engine README pins to `v0.7.1` (documentation only; `extends` still resolves the sibling folder).

### Tab structure

1. Privacy note (§6.4).
2. Two `AnkKpi`s: Complete `{complete_count}/{total}` (“needed for DPNG list & manifest”); PNG fees `money(png_known_total)` — subtitle `{n} guests pending data` when `png_pending_count > 0`, else “paid at SCY airport” when `booking.png_collected === false`.
3. `summary.issues` in `.warnbox`, API order, `issueIcon` ✕ / ⚠.
4. Cards (`guestDisplayName` uses `guest.position`, not the list index; LEAD / MINOR from `is_lead` / `is_minor_now`; country **name** from the loaded list; passport as sent; “medical note on file” only when `on_file && value === null`).
5. One-at-a-time edit form (`SaveGuestRequest` fields). Notes only when `can('guests.view_sensitive')`. Guardian preview via `showGuardianBlock(dob, galapagosTodayIso)` — API `is_minor_now` after save.
6. Add when `can_act && summary.can_add`; Remove when `!is_lead` and name empty.
7. Consent table (`BookingConsent`); Record via `ReasonModal` (`how_obtained` required); no edit/delete.

### What each role sees (browser)

Seeded guests were missing from the running local DB (`Guest::count()` was 0). Seeded `DemoGuestsSeeder` + `DemoConsentsSeeder` (not a full `reset.sh`). Both themes on the panel.

**Carolina** (Admin, `guests.view_sensitive`) on `ANK-2026-0005`: Complete 3/3; PNG USD 500, “paid at SCY airport”; Markus LEAD, Julia, Leon MINOR + guardian Markus Brandt / Father; full passports (`C4F7K2L9M`, `C4F7K8Q1R`, `C4F9T3W6Z`); PNG Foreign adult USD 200 / Foreign minor USD 100; medical / dietary / accessibility fields on the form; nationality select from `GET /countries` (250 options). Add hidden (`can_add` false at cabin max). Overview party `2 AD + 1 CH · 3/3`. List `GUESTS 3/3`.

**Lucía** (Sales Exec) on `ANK-2026-0003`: issues listed (insurance undeclared); form passport empty with placeholder “Restricted — enter to replace” and help “Leave empty to keep the stored number”; no note fields. Seed 0003 has no stored passport numbers, so the card shows `Passport —`. Masking on a number she owns: `ANK-2026-0007` Mariana `Passport •••• 567` (not `AX1234567`).

### Passport-replace

Without `guests.view_sensitive` the input is empty. Sending `passport_no: ''` leaves the stored number unchanged (task 02). Typing replaces it. Operations see the full value and empty clears it.

### Guardian preview vs `is_minor_now`

`showGuardianBlock` is calendar age &lt; 18 against `todayIso` (empty DOB → false). Docblock: the API's `is_minor_now` is authoritative after save. Leon DOB 2015-03-02 showed the guardian block; after save the card uses `is_minor_now` + guardian line.

### Consent recording

`ANK-2026-0007` marketing was Not given. Record → `ReasonModal` “How was it obtained?” → `POST { document, how_obtained }`. Row became Staff — how obtained. No edit or delete control. Toast “Consent recorded”. Source labels are i18n (`ENGINE` / `PAYMENT_LINK` / `STAFF`).

### Overview + list GUESTS line

Overview: `{complete}/{total}` on the same kv row as `party_label` (`bookings.partyWithGuests`). List: mono `GUESTS {n}/{total}` under the client (`--ok` when complete, `--iv38` otherwise). `bookings.guestsOmitted` removed. No Overview DPNG `gdwarn`.

### `guestDisplayName` position / gap

`guestDisplayName` uses `guest.position`. Unit test: positions 1 and 3 (removed 2) read `Guest 3 — name pending`, never `Guest 2`. On `ANK-2026-0014` the empty slot rendered `Guest 2 — name pending` (position, not a zero-based index).

### History

`describe.ts` maps `guest.added` / `guest.updated` / `guest.removed` / `guest.guardian_consented` / `consent.recorded` via `after.what`.

### Files touched

**anakata-api (prelude)**
- `app/Support/Guests/GuestCapacity.php` (new)
- `app/Support/Guests/GuestIssues.php`
- `app/Actions/Guests/AddGuest.php`
- `app/Http/Controllers/Rms/GuestController.php`
- `tests/Unit/Support/Guests/GuestCapacityTest.php` (new)
- `tests/Feature/Guests/GuestEndpointsTest.php`
- `tests/Feature/Guests/GuestLimitsTest.php`
- `tests/Feature/OpenApi/PanelResponseSchemasTest.php`

**anakata-ui**
- `app/types/api.d.ts`
- `package.json` (`0.7.1`)
- `CHANGELOG.md`

**anakata-panel**
- `app/components/guests/BookingGuestsTab.vue` (new)
- `app/components/guests/guestHelpers.ts` (new)
- `tests/unit/guestHelpers.test.ts` (new)
- `app/components/bookings/BookingPanel.vue`
- `app/components/bookings/bookingHelpers.ts`
- `app/components/bookings/ReasonModal.vue` (optional `label`)
- `app/components/history/describe.ts`
- `app/pages/rms/reservations/bookings.vue`
- `app/assets/css/bookings.css` (`.gcard`, `.gcard-inc`, `.gcard-edit`, `.gtop`, form)
- `app/types/api.ts` (re-export `Guest`, `GuestListSummary`, `GuestIssue`, `GuestIssueSeverity`, `BookingConsent`, `Country`, `ConsentDocument`, `ConsentSource`)
- `i18n/locales/en.json`
- `eslint.config.mjs`
- `tests/unit/bookingHelpers.test.ts` (disabled tabs: extras, documents)
- `tests/unit/describe.test.ts`
- `README.md` (pin `v0.7.1`)

**anakata-engine**
- `README.md` (pin `v0.7.1`)

**anakata-api (this report)**
- `docs/sprints/sprint-06/REPORT.md`

### Deviations
- Running API had no guest rows. Seeded guests + consents instead of `reset.sh`.
- `ANK-2026-0003` seed has no passport numbers; Lucía masking checked on her `ANK-2026-0007`.
- Completing Guest 2 on `ANK-2026-0014` (Alex Ellison) and recording marketing on `0007` mutated local seed data for the browser pass.
- `ReasonModal` `hint="required"` for how-obtained (the field is the reason).
- After a write, `onPaymentsUpdated` reloads the booking and the panel lands on Overview (same as Payments). Re-opening Guests shows the new data without a full page reload.

### Open questions
None.

### Notes for later
- Preferences / NPS (Sprint 11).
- Overview DPNG completeness warnbox (Sprint 11).
- Extras tab / catalogue (task 08).

### Quality
- anakata-api: `composer check` inside Docker — 779 tests (5465 assertions), Pint (758 files), Larastan level 6 (0 errors).
- anakata-ui: `pnpm lint`, `typecheck`, `test` (35), `build` — pass.
- anakata-panel: `pnpm lint`, `typecheck`, `test` (204), `build` — pass.

### Fresh clone (two-step, git read-only)

**Step 1 — overlay (agent, before push).** Sibling clone into `/tmp/anakata-fresh/{anakata-ui,anakata-panel,anakata-engine}`, working trees overlaid. Confirmed the ui clone has **no** `app/types/nuxt.d.ts`.

- ui: `pnpm typecheck` pass
- panel / engine: `pnpm typecheck` + `pnpm build` pass
- **OVERLAY CLONE OK**

**Step 2 — real tag (user, or agent in a follow-up).** After `v0.7.1` is on origin, repeat the clone checking out `anakata-ui` at `v0.7.1` with **no** overlay. Result not recorded yet.

### Git commands for the user

Do **not** run these in the agent. Explicit paths only (never `-A`). Run in this order.

```bash
# 1. anakata-api prelude
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Support/Guests/GuestCapacity.php \
  app/Support/Guests/GuestIssues.php \
  app/Actions/Guests/AddGuest.php \
  app/Http/Controllers/Rms/GuestController.php \
  tests/Unit/Support/Guests/GuestCapacityTest.php \
  tests/Feature/Guests/GuestEndpointsTest.php \
  tests/Feature/Guests/GuestLimitsTest.php \
  tests/Feature/OpenApi/PanelResponseSchemasTest.php
git commit -m "$(cat <<'EOF'
Expose guest-list slot max and can_add from the same capacity rule.

AddGuest and the list summary now share GuestCapacity so the panel
Add button cannot drift from the API limit.
EOF
)"
```

```bash
# 2. anakata-ui — commit, then tag, then push HEAD and the tag
cd /home/mohammad/Code/iconic/anakata/anakata-ui
git add \
  package.json \
  CHANGELOG.md \
  app/types/api.d.ts
git commit -m "$(cat <<'EOF'
Regenerate guest-list types for max and can_add.

GuestListSummary picks both up from guest.index. No leftover overlay.
EOF
)"
git tag v0.7.1
git push origin HEAD
git push origin v0.7.1
```

```bash
# 3. anakata-panel
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  README.md \
  app/assets/css/bookings.css \
  app/components/bookings/BookingPanel.vue \
  app/components/bookings/ReasonModal.vue \
  app/components/bookings/bookingHelpers.ts \
  app/components/guests/BookingGuestsTab.vue \
  app/components/guests/guestHelpers.ts \
  app/components/history/describe.ts \
  app/pages/rms/reservations/bookings.vue \
  app/types/api.ts \
  eslint.config.mjs \
  i18n/locales/en.json \
  tests/unit/bookingHelpers.test.ts \
  tests/unit/describe.test.ts \
  tests/unit/guestHelpers.test.ts
git commit -m "$(cat <<'EOF'
Enable the booking panel Guests tab.

Passenger list, edit form, consents and the Overview/list
completeness line render only what the API sends.
EOF
)"
git push origin HEAD
```

```bash
# 4. anakata-engine
cd /home/mohammad/Code/iconic/anakata/anakata-engine
git add README.md
git commit -m "$(cat <<'EOF'
Document the layer pin as v0.7.1.

extends still resolves the sibling folder; the version is documentation only.
EOF
)"
git push origin HEAD
```

```bash
# 5. anakata-api report
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-06/REPORT.md
git commit -m "$(cat <<'EOF'
Record sprint 6 task 07: booking panel Guests tab.
EOF
)"
git push origin HEAD
```

```bash
# 6. Fresh-clone repeat — after the pushes, no working-tree overlay
rm -rf /tmp/anakata-fresh
mkdir -p /tmp/anakata-fresh
git clone https://github.com/anakata-project/anakata-ui.git /tmp/anakata-fresh/anakata-ui
git -C /tmp/anakata-fresh/anakata-ui checkout v0.7.1
git clone https://github.com/anakata-project/anakata-panel.git /tmp/anakata-fresh/anakata-panel
git clone https://github.com/anakata-project/anakata-engine.git /tmp/anakata-fresh/anakata-engine
# then in each: pnpm install
# ui / panel / engine: pnpm typecheck
# panel / engine: pnpm build
```

## Task 08 · Extras tab, charges rows, catalogue editor

### What was built
The booking panel Extras tab is live. `BOOKING_TABS.extras` is enabled (`disabled: false`, `arrivesSprint: null`). Only Documents stays disabled. `BookingExtrasTab` mounts next to Guests, loads `GET /api/rms/bookings/{id}/extras` and `GET /api/rms/extras`, and emits `updated` through `onPaymentsUpdated` so Overview, the list, and History refresh after every write. Overview money rows come from `chargesRows(booking)` — labels and API amounts only. Rates & Promotions now hosts a second `useConfigEditor('extras')` (own versions, own publish bar) instead of the ADMIN / DIRECTOR placeholder.

Types already existed on layer `v0.7.1` (`BookingExtra`, `ExtrasListSummary`, `ExtrasCatalogue`, `ExtrasDocument`, `ExtrasVersion`). Re-exported from `app/types/api.ts`. No API changes.

### Tab structure

1. Intro note (`bookings.extrasIntro` — prototype wording about §4.6 g / on-request spa, bar, boutique).
2. Table (`list mini-t`): service + optional `.gmeta` note, qty, `rate_usd`, API `amount` (never `qty × rate` in the panel), remove. Empty: “No additional services contracted.” Footer: “Ancillary subtotal” = API `extras_total`.
3. **Add a service** when `can_act` and status is not `CANCELLED` / `CANCELLED_POSTPAID` / `RELEASED`. Select is filtered to `active === true`. On pick / mount, `extraAddDefaults(item, booking.guests_summary.total)` prefills qty (`max(guestCount, 1)`) and rate (`price_usd`, empty when on request). `tct_count` is the TCT fee basis, not a headcount. POST `{ code, qty, rate_usd, note }`. On-request with no rate → 422 `rate_usd` via `applyApiFormError`.
4. Remove: confirmation-only `UModal` titled **“Remove {name} × {qty}?”** — Cancel / Remove, no reason field. Not `ReasonModal` (DELETE takes no body). History already records the removal. Never `window.confirm`.
5. After every write: reload extras + `emit('updated')`.

### Galápagos fees

Heading: “Galápagos fees — the guest chooses who collects them”.

`feeLabel(kind, amounts)` builds the two `.chkline` sentences from extras-summary facts:

| Kind | Sources | Sentence |
|---|---|---|
| PNG | `png_known_total`, `png_pending_count` | “Guest pays the PNG park entry fee to Anakata (USD {png_known_total} — by nationality, see Guests). Unchecked = paid directly at SCY airport on arrival.” + “{n} guests pending data” when pending > 0 |
| TCT | `tct_pp`, `tct_count` | “Anakata manages the TCT transit card (USD {tct_pp} × {tct_count}). Unchecked = guest pre-registers or pays at the origin airport.” |

Checked state = `png_collected` / `tct_collected`. Toggle → `PATCH /api/rms/bookings/{id}/fees` with that one boolean. Disabled when `!can_act` or terminal.

Footnote uses API `extras_due_hours` (seed 72). **The prototype “re-issue an updated invoice” notice is not shown** — invoices are Sprint 7.

### Overview charges rows

`chargesRows` returns visibility + API values. `money()` stays in the template.

| Row | Source |
|---|---|
| Cruise | `total` (was `kvCabinTotal`) |
| Extras | `extras_total` |
| Galápagos fees collected | `fees_collected_total`; suffix “pending data” when `png_pending_count > 0` |
| rule | visual separator |
| **Charges total** | `charges_total` |
| Paid / pledged / Balance | unchanged; balance is already charges − paid from task 04 |
| Deposit | `deposit_pct` + `deposit_amount`; label **“Deposit {pct}% of cruise charges”** |
| Extras and fees due by {date} | only when `balance > 0` **and** (`extras_total > 0` or `fees_collected_total > 0`); date = `extras_due_at` |

### What OVERDUE still means on screen

The OVERDUE pill and OPS-007 block still read `booking.overdue`. Task 04 / I9 made that flag **cruise-outstanding only**. Adding extras or collected fees does not flip the pill. A FULLY_PAID booking that gains an extra shows `balance > 0` and keeps the FULLY PAID pill.

### Catalogue editor

`ConfigKindSlug` includes `'extras'`. Base path `/api/rms/${kind}` already hits `/api/rms/extras`. A second editor — separate document, own versions:

- `extrasEditor = useConfigEditor('extras')`
- `canPublishExtras = can('extras.manage')` (read is `panel.rms`)
- `useUnsavedGuard(() => editor.dirty || extrasEditor.dirty)`
- Own `ConfigPublishBar` (`approval-required`, 409 → “Load the latest version”) + `ConfigHistoryPanel`
- Confirm note: “Existing booking extras keep the rate they were sold at.”
- ADMIN / DIRECTOR pill kept

`ExtrasCataloguePanel` + `extrasCatalogueHelpers` (`EXTRAS_DRAFT_KEY`, `extrasFieldLabels(draft)` for `items.{i}.*`). Table: code, name, unit, price (empty = “on request” → `null`), transfer voucher, active.

- Code **read-only** when it exists on the published document; new draft rows can type a code.
- **No client-side max-length or distinct checks** — publish validation reports those through `editor.errorsFor`.
- No delete of published codes — deactivate. Unpublished draft-only rows may be dropped.
- Add item appends `{ code: '', name: '', unit: '', price_usd: null, triggers_transfer_voucher: false, active: true }`.

### Helpers (tested) and History

- `feeLabel(kind, amounts)` — the two checkbox sentences, including pending-data.
- `chargesRows(booking)` — row list / visibility only; no sums.
- `extraAddDefaults(item, guestCount)` — qty + rate; caller passes `booking.guests_summary.total`.
- `extrasWritable(status, canAct)` — hides the add form and fee toggles on terminal statuses.

`describe.ts` maps `extra.added`, `extra.removed`, `booking.fees_changed` through `after.what` (same as guests).

### Browser (after `tests/e2e/bin/reset.sh`, both themes)

Carolina (Admin). Dark default and light.

- **ANK-2026-0003** (CONFIRMED) + flights × 2: extras USD 840, charges USD 27,440, balance moved, deposit still USD 2,660. Qty defaulted to 2 (`guests_summary.total`, list was 0/2 complete).
- On-request SPA with empty rate → 422 `rate_usd`. Inactive items are filtered out of the select (seed catalogue items are all active).
- **ANK-2026-0005** (FULLY_PAID): PNG on → fees USD 500, charges USD 38,405, balance USD 500, pill still FULLY PAID. Then FLT × 3 → extras USD 1,260, charges USD 39,665, balance USD 1,760, deposit still USD 3,791, list still FULLY PAID.
- **ANK-2026-0014** (PENDING PAYMENT): PNG sentence “USD 200 … 1 guests pending data”. Toggle on → Overview “Galápagos fees collected pending data USD 200”, charges USD 26,800, “Extras and fees due by 11 Nov 2027, 00:00”, deposit still USD 2,660.
- Catalogue publish FLT 420 → 500 (V2, Carolina, confirm note shown). **0005 extra kept USD 420 / 1,260**. New add form on 0014 prefills 500.

After a write the panel lands on Overview (same as Guests / Payments). Re-opening Extras shows the new rows.

### Files touched

**anakata-panel**
- `app/components/extras/BookingExtrasTab.vue` (new)
- `app/components/extras/ExtrasCataloguePanel.vue` (new)
- `app/components/extras/extraHelpers.ts` (new)
- `app/components/extras/extrasCatalogueHelpers.ts` (new)
- `tests/unit/extraHelpers.test.ts` (new)
- `tests/unit/extrasCatalogueHelpers.test.ts` (new)
- `app/components/bookings/BookingPanel.vue`
- `app/components/bookings/bookingHelpers.ts`
- `app/composables/useConfigEditor.ts`
- `app/pages/rms/commercial/rates.vue`
- `app/components/history/describe.ts`
- `app/types/api.ts`
- `app/assets/css/bookings.css`
- `app/assets/css/config.css`
- `i18n/locales/en.json`
- `eslint.config.mjs`
- `tests/unit/bookingHelpers.test.ts` (disabled tabs: documents only)
- `tests/unit/describe.test.ts`

**anakata-api (this report)**
- `docs/sprints/sprint-06/REPORT.md`

### Deviations
- Task file still says fresh-clone against `v0.7.0`; this task uses **`v0.7.1`** (task 07 bump).
- Extra remove is confirmation-only (approved plan). The task file still says “ReasonModal-style”; a typed reason would be discarded.
- `rates.extrasNote` (placeholder copy) removed; the catalogue panel uses `rates.extrasHelp`.
- After a write, `onPaymentsUpdated` reloads the booking and the panel lands on Overview (same as Guests).
- Catalogue publish confirm listed the whole `items` array as one change (API validate `changes` path), not `items.0.price_usd`. Field labels still exist for the per-item paths.

### Open questions
None.

### Notes for later
- Invoice / re-issue notice (Sprint 7).
- E2E scenarios `EXT-01` … `EXT-05` (task 10).

### Quality
- anakata-panel: `pnpm lint`, `typecheck`, `test` (212), `build` — pass.

### Fresh clone (two-step, git read-only)

**Step 1 — overlay (agent, before push).** Sibling trees into `/tmp/anakata-fresh/{anakata-ui,anakata-panel,anakata-engine}` from the working copies (no GitHub clone — `v0.7.1` is not on origin yet). Confirmed the ui tree is **0.7.1** and has **no** `app/types/nuxt.d.ts`.

- ui: `pnpm typecheck` pass
- panel / engine: `pnpm typecheck` + `pnpm build` pass
- **OVERLAY CLONE OK**

**Step 2 — real tag (user, or agent in a follow-up).** After `v0.7.1` is on origin and this panel work is pushed, repeat the clone checking out `anakata-ui` at `v0.7.1` with **no** overlay. Result not recorded yet.

### Git commands for the user

Do **not** run these in the agent. Explicit paths only (never `-A`). Run in this order.

```bash
# 1. anakata-panel
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  app/components/extras/BookingExtrasTab.vue \
  app/components/extras/ExtrasCataloguePanel.vue \
  app/components/extras/extraHelpers.ts \
  app/components/extras/extrasCatalogueHelpers.ts \
  tests/unit/extraHelpers.test.ts \
  tests/unit/extrasCatalogueHelpers.test.ts \
  app/components/bookings/BookingPanel.vue \
  app/components/bookings/bookingHelpers.ts \
  app/composables/useConfigEditor.ts \
  app/pages/rms/commercial/rates.vue \
  app/components/history/describe.ts \
  app/types/api.ts \
  app/assets/css/bookings.css \
  app/assets/css/config.css \
  i18n/locales/en.json \
  eslint.config.mjs \
  tests/unit/bookingHelpers.test.ts \
  tests/unit/describe.test.ts
git commit -m "$(cat <<'EOF'
Enable the booking panel Extras tab and catalogue editor.

Overview charges rows and fee sentences render API fields only;
the extras catalogue reuses the existing config publish flow.
EOF
)"
git push origin HEAD
```

```bash
# 2. anakata-api report
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-06/REPORT.md
git commit -m "$(cat <<'EOF'
Record sprint 6 task 08: extras tab, charges rows, catalogue editor.
EOF
)"
git push origin HEAD
```

```bash
# 3. Fresh-clone repeat — after the pushes, no working-tree overlay
rm -rf /tmp/anakata-fresh
mkdir -p /tmp/anakata-fresh
git clone https://github.com/anakata-project/anakata-ui.git /tmp/anakata-fresh/anakata-ui
git -C /tmp/anakata-fresh/anakata-ui checkout v0.7.1
git clone https://github.com/anakata-project/anakata-panel.git /tmp/anakata-fresh/anakata-panel
git clone https://github.com/anakata-project/anakata-engine.git /tmp/anakata-fresh/anakata-engine
# then in each: pnpm install
# ui / panel / engine: pnpm typecheck
# panel / engine: pnpm build
```

## Task 09 · Contacts In

### What was built
The Contacts In placeholder at `/rms/commercial/contacts-in` is a real RMS page. One `DateRangeFilter` on the booking departure date (noun: contacts) drives both fetches. The prototype CRM notice sits above two stacked `.panel`s. Row click loads `GET /api/rms/bookings/{id}` and opens the existing `BookingPanel`. `@updated` refreshes both sources.

Types already existed on layer `v0.7.1` (`ContactInRow`, `NationalityRow`, `NationalitiesSummary`). Re-exported from `app/types/api.ts`. No API changes.

### The two sources
`GET /api/rms/contacts-in?from&to&page&per_page=50` — paginated `ContactInRow`. Columns: contact + TRAVEL ADVISOR pill when `travel_advisor`, segment pill, source (`channel_of_origin` only), booking `display_reference`, status pill, **Value = `charges_total`** (not the cruise `total`), owner + 🔒 when `can_act` is false.

`GET /api/rms/contacts-in/nationalities?from&to` — `NationalitiesSummary`. Country name from the API, guests, bookings, and a proportional bar. `nationalityBarWidth(count, max)` is presentation only (`Math.round(count / max * 100)`, `0` when `max <= 0`).

### `slots_not_filled`
`NationalitiesSummary` is `{ nationalities, unknown, total_guests }` only. There is **no** `slots_not_filled`. Task 05 dropped unnamed padded slots (`Guest::scopeNamed`); they are not in `unknown` or `total_guests`. The footnote is the unknown-nationality line only.

### Navigation
The nav item already existed (`sprint: 6`). Gated on `panel.rms` (explicit RMS view, not `bookings.view_all`). Guards test: visible with `panel.rms`; without it, `pageDecision` sends the user to the RMS home.

### Browser (both themes, after `reset.sh` on `anakata-api`)
Carolina on `/rms/commercial/contacts-in`, All dates: **14 contacts**. Seeded mix includes Harrison & Whitfield (`ANK-2026-0003`), Brandt (`ANK-2026-0005`), L. Moreau with the TRAVEL ADVISOR pill (`ANK-R-2026-0042`), Vandermeer Charter (`ANK-2026-0012`). Nationalities: AR 4 / US 4 / FR 3 / DE 3 / EC 2 / SE 2 / GB 2 / CO 1 / NL 1; bars 100 · 100 · 75 · 75 · 50 · 50 · 50 · 25 · 25; “0 guests without nationality yet.” Year 2026 empties **both** panels; Year 2027 restores the 14. A row opens the booking panel (`ANK-2026-0003`). Light theme matches dark. Seeded Lucía still has `bookings.view_all`, so 🔒 was not visible on her rows as Admin.

### Deviations
- Task file still says fresh-clone against `v0.7.0`; this task uses **`v0.7.1`** (task 07 bump).
- Source cell is `channel_of_origin` only. Prototype ` · VIA {preferred_channel}` is omitted — `ContactInResource` does not send it.
- Prototype `CAN` / `NATIONAL` pills omitted (would hard-code PNG nationality sets).

### Open questions
None.

### Notes for later
- E2E Contacts In coverage (task 10, if a scenario is added).
- Sprint 9 CRM profiles.

### Quality
- anakata-panel: `pnpm lint`, `typecheck`, `test` (215), `build` — pass.

### Fresh clone (two-step, git read-only)

**Step 1 — overlay (agent, before push).** Sibling trees into `/tmp/anakata-fresh/{anakata-ui,anakata-panel,anakata-engine}` from the working copies. Confirmed the ui tree is **0.7.1** and has **no** `app/types/nuxt.d.ts`.

- ui: `pnpm typecheck` pass
- panel / engine: `pnpm typecheck` + `pnpm build` pass
- **OVERLAY CLONE OK**

**Step 2 — real tag (user, or agent in a follow-up).** After this panel work is pushed, repeat the clone checking out `anakata-ui` at `v0.7.1` with **no** overlay. Result not recorded yet.

### Git commands for the user

Do **not** run these in the agent. Explicit paths only (never `-A`). Run in this order.

```bash
# 1. anakata-panel
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  app/pages/rms/commercial/contacts-in.vue \
  app/components/contacts/contactHelpers.ts \
  tests/unit/contactHelpers.test.ts \
  tests/unit/guards.test.ts \
  app/navigation/rms.ts \
  app/types/api.ts \
  app/assets/css/lists.css \
  i18n/locales/en.json \
  eslint.config.mjs
git commit -m "$(cat <<'EOF'
Add the RMS Contacts In list and nationality bars.

The page reads the two Contacts In endpoints and draws bar
widths in the panel only; empty guest slots stay dropped.
EOF
)"
git push origin HEAD
```

```bash
# 2. anakata-api report
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-06/REPORT.md
git commit -m "$(cat <<'EOF'
Record sprint 6 task 09: panel Contacts In.
EOF
)"
git push origin HEAD
```

```bash
# 3. Fresh-clone repeat — after the pushes, no working-tree overlay
rm -rf /tmp/anakata-fresh
mkdir -p /tmp/anakata-fresh
git clone https://github.com/anakata-project/anakata-ui.git /tmp/anakata-fresh/anakata-ui
git -C /tmp/anakata-fresh/anakata-ui checkout v0.7.1
git clone https://github.com/anakata-project/anakata-panel.git /tmp/anakata-fresh/anakata-panel
git clone https://github.com/anakata-project/anakata-engine.git /tmp/anakata-fresh/anakata-engine
# then in each: pnpm install
# ui / panel / engine: pnpm typecheck
# panel / engine: pnpm build
```

## Task 10 · E2E scenarios for Sprint 6; P1 run

### ENV — stopped

Cloud spawn failed before `up.sh`. The parent workspace has **four git remotes** (api, ui, panel, engine). Cursor Cloud requires exactly one:

```
environment: "cloud" requires exactly one known git remote for the parent workspace; found 4.
```

Same class as Sprint 5 Task 11. The README “Before task 01” remotes fix (`.cursor/environment.json` `context: ".."`) is still outstanding. This task does not guess screen values and does not fall back to a local run.

Run file: [`tests/e2e/runs/2026-09-21-1050-sprint6-env.md`](../../../tests/e2e/runs/2026-09-21-1050-sprint6-env.md).

### Scenarios defined (not walked)

Thirteen scripts were written from seeders, i18n, and tasks 07–09 browser notes. They were **not** read off a cloud `reset.sh` screen. Lines that still need that screen are marked `⚠ UNVERIFIED`.

| ID | File |
|---|---|
| GST-01 | `tests/e2e/scenarios/guests/GST-01-brandt-guests-tab.md` |
| GST-02 | `tests/e2e/scenarios/guests/GST-02-lucia-passport-mask.md` (db-check ciphertext + history) |
| GST-03 | `tests/e2e/scenarios/guests/GST-03-fill-incomplete-guest.md` |
| GST-04 | `tests/e2e/scenarios/guests/GST-04-minor-guardian-block.md` |
| GST-05 | `tests/e2e/scenarios/guests/GST-05-passport-expiry-before-return.md` |
| GST-06 | `tests/e2e/scenarios/guests/GST-06-record-missing-consent.md` |
| GST-07 | `tests/e2e/scenarios/guests/GST-07-cabin-limit-remove-empty.md` |
| GST-08 | `tests/e2e/scenarios/guests/GST-08-contacts-in.md` |
| EXT-01 | `tests/e2e/scenarios/extras/EXT-01-add-flights-confirmed.md` |
| EXT-02 | `tests/e2e/scenarios/extras/EXT-02-png-collection-pending.md` |
| EXT-03 | `tests/e2e/scenarios/extras/EXT-03-fully-paid-gains-extra.md` |
| EXT-04 | `tests/e2e/scenarios/extras/EXT-04-catalogue-publish-frozen-rate.md` |
| EXT-05 | `tests/e2e/scenarios/extras/EXT-05-extra-unpaid-not-overdue.md` |

INDEX P1 grew by GST-01, 02, 03, 06 and EXT-01, 02.

### What was not built

- No fixture confirmations; `reference-values.md` money/registry lines still wait on a reset screen.
- BKG-01 E5, PAY-01/02/06, BKG-06, BR-01/02 not rewritten.
- No `sprint6-p1` or `sprints-1-6-full` walk.

### Marker counts

| | Count |
|---|---|
| Sprint 4 leftovers at start of Sprint 5 Task 11 | 63 |
| After Sprint 5 Task 11 | **55** |
| Cleared this task | **0** (no screen) |
| New `⚠ UNVERIFIED` this task | **0** |
| After this task | **55** leftovers, plus any new guest/extras lines still unwritten |

### Open questions

- Cursor cloud + four remotes: launch the agent from **anakata-api alone**, or fix `.cursor/environment.json` so `context` is this repo (not `..`). Until one of those happens, Task 10 cannot run.
- Everything listed under tasks 01–09 that is still PENDING CLIENT (LEG-001 / LEG-002 / B4 retention, PNG/TCT default, extras catalogue prices, consent texts) stays open.

### Notes for later

Re-run Task 10 on a Cloud Agent attached only to `anakata-api`: `up.sh` → `ALL UP`, then the approved plan (fixtures, revisit, thirteen scenarios, both P1 runs, sprint summary, push `e2e/sprint-06`).

### Merge steps for the user

Do **not** merge this ENV note as if the sprint P1 ran. After a successful cloud run, merge `e2e/sprint-06` into `dev`.

If you want this ENV report on a branch now:

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git checkout -B e2e/sprint-06
git add tests/e2e/runs/2026-09-21-1050-sprint6-env.md
git add docs/sprints/sprint-06/REPORT.md
git commit -m "$(cat <<'EOF'
Record Sprint 6 Task 10 ENV stop: cloud spawn saw four remotes.

EOF
)"
git push -u origin e2e/sprint-06
```

