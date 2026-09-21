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
