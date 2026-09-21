# Task 03 · anakata-api · The consent log and the retention jobs
**Repo:** anakata-api · **Sprint:** 6 · **Needs:** task 02.

## Goal
Every booking carries a log of the four required consents and the optional marketing consent, each with its document version, time, IP and source — append-only and kept seven years. Staff can record a consent obtained off-line. Separately, the retention jobs anonymise passports and purge notes on the configured schedule (B4), so the data this sprint starts storing is also deleted on time.

## Read first
- `docs/requirements/08-dev-decisions.md`: **I6, I7**, and B4, H1 (the append-only pattern)
- `02-data-model.md` → Consent record; `03-business-rules.md` §6.4; `05-decisions-and-open-questions.md` row 7
- `prototype/rms_index.html`: `CONSENT_DOCS`, `CONSENT_VER`, `recConsent` (the "how was it obtained?" prompt), the consent table in `drGuests` ("Missing" in coral for required, "Not given" for marketing, the footnote), and the consent rows `seedOps` creates for confirmed bookings
- Business rules `retention.passport_months_after_cruise` (24) and `retention.medical_days_after_cruise` (90); the Sprint 2 shape-change procedure and Sprint 4 task 02's migration

## Do
1. **Consent versions — a business-rules shape change.** Add `legal.consent_versions`: `terms`, `cancellation`, `privacy`, `insurance`, `marketing`, each a version label string. Defaults from the prototype's `CONSENT_VER` ("v2026.1 (text pending LEG-001)" and so on). Registry rows PENDING CLIENT with sources LEG-001 / LEG-002 / OPS-005.
   - Follow the Sprint 4 task 02 procedure exactly: `initial()` includes the values; a DML-only migration publishes a new version as System with hard-coded defaults, merging only missing keys; `anakata:config-verify` fails before it and passes after. Registry counts and the e2e BR fixtures move — record the new totals.
2. **`consents` table and model.** `booking_id`, `document` (`TERMS · CANCELLATION · PRIVACY · INSURANCE · MARKETING`, enum with the prototype labels), `version`, `accepted_at` (instant), `ip` (nullable — null for staff-recorded consents), `source` (`ENGINE · PAYMENT_LINK · STAFF`), `recorded_by` (nullable user), `how_obtained` (nullable, required when `source = STAFF`), timestamps.
   - **Append-only (I6):** a trigger refuses `UPDATE` and `DELETE`, the `change_history` pattern. A withdrawn marketing consent is a new row with a `withdrawn` flag, not an edit — add the `withdrawn` column now so Sprint 9's consent register has somewhere to write.
   - Unique `(booking_id, document, version)` for accepted rows: a new version is a new row; the same version twice is a no-op, not an error.
   - Model `Consent`, morph alias `consent`. Consents are **not** subject to the retention job (seven years, I6).
3. **Endpoints.**
   - `GET /api/rms/bookings/{booking}/consents` — one row per document, the latest accepted consent or `null`, plus `required: bool` and the current version from the business rules. A consent whose version is older than the current one is returned with `outdated: true`; record that it still counts, since re-acceptance is a legal question for LEG-002.
   - `POST /api/rms/bookings/{booking}/consents` `{ document, how_obtained }` — staff record (the prototype's `recConsent`). Permission: the booking's own-records rule. Version is the current one; `accepted_at` now; `source = STAFF`; `ip` null. History `consent.recorded` with the document and how it was obtained.
   - The engine (Sprint 8) and the payment link page (Sprint 7) will write `ENGINE` / `PAYMENT_LINK` rows with the IP. Put the write in one action, `RecordConsent`, that both will call; this task only calls it from the staff endpoint and the seed.
4. **The missing-consents issue.** Fill task 02's placeholder: on a booking that is CONFIRMED or later, each required document without an accepted row is listed in one warning ("Missing consent records: Privacy policy, …" — prototype wording). Marketing is never required.
5. **Retention (I7).** `anakata:retention` (daily, scheduled in the Galápagos timezone, the Sprint 5 rule):
   - For guests whose departure's `returnDate()` is more than `retention.passport_months_after_cruise` months ago: set `passport_no` and `passport_expiry` to null.
   - For guests whose return date is more than `retention.medical_days_after_cruise` days ago: set `medical_note`, `dietary_note` and `accessibility_note` to null.
   - Calendar arithmetic on Galápagos dates. Values from the published business rules, never literals.
   - One history entry per booking per run that changed something, as System: "Retention — passport data anonymised for N guests (24 months after the cruise, B4)". No values, obviously.
   - Idempotent: a second run the same day writes nothing.
   - `--dry-run` prints the counts it would change and writes nothing. Record in the README that the client must confirm B4 before this runs in production (README client question 1).
   - Soft-deleted bookings are included — their guests' data must still be purged.
6. **Seed.** Consent rows for the confirmed demo bookings as the prototype seeds them (source `PAYMENT_LINK`, an IP, the versions from the published document), and none for the rest, so the missing-consents warning has fixtures.

## Don't
- Don't write customer-facing consent text (LEG-001 / LEG-002). Version labels only.
- Don't purge anything outside the two retention rules, and don't touch consents or financial rows.
- Don't pre-check or infer a consent. A consent exists only when a row says so.

## Checks
- `composer check`, including `anakata:config-verify` before and after the migration.
- The trigger refuses update and delete; a new version creates a new row; the same version twice is a no-op.
- Staff recording requires `how_obtained`; own-records applies.
- Missing-consents warning appears only from CONFIRMED on, and never for marketing.
- Retention with a travelled clock: the day before and the day after each boundary; idempotent second run; dry run writes nothing; soft-deleted booking included; history has counts and no values.

## Report
Append **Task 03**: the shape change and new registry counts, the consent table and why it is append-only, `outdated` and the open LEG-002 question, `RecordConsent` as the single write path for Sprints 7–8, the retention rules and their dry run, and that B4 must be confirmed before production. Git commands listed, not run.
