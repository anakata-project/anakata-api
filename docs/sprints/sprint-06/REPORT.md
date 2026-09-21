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
