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
