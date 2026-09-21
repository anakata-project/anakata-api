# Task 01 · anakata-api · Sensitive data: dedicated-key encryption, masking, redaction
**Repo:** anakata-api · **Sprint:** 6 (read `README.md` in this folder first)
**Needs:** Sprint 5 closed (README "Before task 01").

## Goal
Before any guest record exists, the machinery that protects it does. Passport numbers and medical, dietary and accessibility notes are encrypted with their own key, masked by the API for users who may not see them, and never written to history, logs or CRM-section responses. This task builds and proves that machinery on its own; task 02 is the first thing that uses it.

## Read first
- `docs/requirements/08-dev-decisions.md`: **I1, I2**, and B4, B8, A3
- `03-business-rules.md` §6.4; `01-functional-spec.md` §4, the Guests bullets ("masked for Sales Exec; medical notes are operations-only")
- `prototype/rms_index.html`: `mask` (the `•••• ` + last three form) and `canOps`, and where `guestForm` hides the passport and medical fields
- `app/Support/SensitiveFields.php`, `app/Support/History/History.php` (`redact`), `app/Http/Middleware/GuardCrmSensitiveData.php` and its tests

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **The key.** `SENSITIVE_DATA_KEY` in `.env.example` (empty) and `config/app.php` (or a small `config/sensitive.php`), same format as `APP_KEY` (`base64:` 32 bytes). Document how to generate it in the README.
   - It is **not** `APP_KEY`, and must not fall back to it. Rotating `APP_KEY` (sessions, cookies) must never make passports unreadable, and a leaked `APP_KEY` must not decrypt them.
   - Missing key: the cast throws a clear exception on first use outside `testing`. `phpunit.xml` / `.env.testing.example` carry a test key. The e2e `api.env` carries its own.
   - Add `SENSITIVE_DATA_KEY` to the `config-verify` / health checks you already have only if they have a natural "required secrets" list; otherwise note it in the REPORT for the production checklist.
2. **The cast.** `App\Casts\SensitiveEncrypted`: builds its own `Illuminate\Encryption\Encrypter` from the dedicated key (once, memoised), encrypts on set, decrypts on get, stores `null` as `null`, and treats an empty string as `null`.
   - Encrypted columns are `text` (ciphertext is long). Record that a ciphertext cannot be searched or indexed, which is fine: nothing needs to look a guest up by passport number.
   - Record the rotation story without building it: a second `SENSITIVE_DATA_PREVIOUS_KEYS` list, decrypt-with-fallback, and a re-encrypt command, left for a later sprint.
3. **Masking (I2).** One helper, `App\Support\Guests\Masking`:
   - `passport(?string $value, bool $canViewSensitive)` → the full number, or `•••• ` plus the last three characters (the prototype's `mask`), or `null`.
   - `note(?string $value, bool $canViewSensitive)` → the text, or `null` plus an `on_file: bool` sibling so the panel can say "medical note on file" without seeing it.
   - The decision lives in the API. A resource that exposes a sensitive field **always** goes through this helper with `guests.view_sensitive` for the current user. The panel never receives a value it is not allowed to show — it is not masked in the browser.
4. **Redaction, extended.** `History::redact()` already replaces `SensitiveFields` keys. Check two gaps and close them:
   - nested payloads (a guest object inside `before`/`after`), and list payloads (several guests) — add tests;
   - the prototype's history lines name the fields that changed ("Passenger updated — Julia Brandt: passport number, medical note"). That wording is fine: the *field names* are allowed, the *values* never are. Add a test that a history entry records "passport number" changed and contains neither the old nor the new number.
5. **Logs.** A test-level guard that the sensitive values cannot leak through exception context: a failing request that carries a passport number in its body must not write it to the log. Laravel's `dontFlash` covers the session; add the sensitive keys to it, and verify the exception handler's context does not dump the request input for these keys.
6. **The CRM guard.** It exists and strips `SensitiveFields` keys from CRM-section responses. CRM screens start in Sprint 9, so there is nothing to route through it yet. Add an architecture test that any future `App\Http\Controllers\Crm\*` route group carries the middleware, so Sprint 9 cannot forget it.
7. **A proof model.** There is no guest table yet. Prove the cast with a test-only model and migration loaded suite-wide (the Sprint 1 rule: never run DDL inside `RefreshDatabase`), asserting: the raw column is ciphertext; the model reads plaintext; a different key cannot decrypt; `APP_KEY` rotation leaves it readable.

## Don't
- Don't encrypt `dob` or `nationality`. They are in `SensitiveFields` for the CRM guard and for history redaction, but PNG categories and the nationality aggregate need them in clear text (I3). Record that distinction.
- Don't create the `guests` table (task 02).
- Don't build key rotation (record it).

## Checks
- `composer check`.
- The proof-model tests above, the redaction tests, the log test, the architecture test.

## Report
Append **Task 01** to `docs/sprints/sprint-06/REPORT.md`: the key and why it is separate, the cast, the masking helper and the rule that the API masks, what redaction covers, the log guard, the CRM architecture test, the rotation plan left for later, and `SENSITIVE_DATA_KEY` for the production checklist. List the git commands; do not run them.
