# Task 02 · anakata-api · The consent register and the send-time gate
**Repo:** anakata-api · **Sprint:** 10 · **Needs:** task 01.

## Goal
Consent becomes a per-contact record the CRM owns (doc 07 §3): every purpose with its latest state and full history, fed by the engine, the RMS and staff, and one gate that every future non-transactional send must pass.

## Read first
- `docs/requirements/08-dev-decisions.md`: **M1, M2, M3**, and I6, L6, L7, L9, D5
- `07-three-system-integration-contract.md` §3 (the Consent row), §4.5 (`contact.consent_changed`), §8, §10 rule 4
- `prototype/crm_index.html`: `v-privacy` (the consent register table: purpose, basis, opt-in needed, captured at, contacts), `openContact` (the consent rows)
- `RecordConsent`, `ConsentController` (RMS), `SubmitEngineCheckout`, the complete-page declarations, `ContactConsentSummary`, `ContactDerived::marketingConsentSql()`, the stitching code from Sprint 9 task 03, `ConsentVersions`

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Schema.** `contact_consents`: `contact_id`, `purpose` (enum `ConsentPurpose`: MARKETING, PROFILING, REMARKETING, WHATSAPP, ANALYTICS), `granted` (bool), `version` (string, the text version), `captured_at`, `ip` (nullable), `capture_point` (enum: ENGINE_FORM, ENGINE_BANNER, STAFF, BOOKING_LOG_BACKFILL, SUBJECT_REQUEST), `recorded_by` (nullable user), `how_obtained` (nullable text, required for STAFF), `source_consent_id` (nullable, the I6 row it came from), audit columns. Triggers refuse update and delete (the I6 pattern). Index `(contact_id, purpose, captured_at)`.
2. **One write path.** `RecordContactConsent` is the only writer. It appends a row, writes `contact.consent_changed` history on the contact (purpose and granted, never the IP), and does nothing else — no listener, no queue (M2: the gate reads the latest row).
3. **Feeds.**
   - `RecordConsent` (I6): when it writes a MARKETING row, it also calls `RecordContactConsent` for the booking's contact with the same version, time, IP and source mapped to a capture point (ENGINE → ENGINE_FORM, PAYMENT_LINK → ENGINE_FORM, STAFF → STAFF with its `how_obtained`), in the same transaction. A withdrawal writes `granted = false`.
   - **Stitching (M3):** when a session is stitched to a contact, record ANALYTICS granted with capture point ENGINE_BANNER, `captured_at` = the session's earliest event `occurred_at`, and `version` = `legal.consent_versions.analytics`. Idempotent per (contact, session): stitching the same session twice records once.
   - **Staff:** `POST /api/crm/contacts/{contact}/consents` with `purpose`, `granted`, `how_obtained` (required), `version` (defaults to the current version for that purpose where one exists). Permission `consents.record` (new; Admin and Manager by default). MARKETING recorded here is not copied into the I6 booking log — I6 stays booking-level.
4. **Backfill (once, in a migration).** Every existing I6 MARKETING row becomes a register row for its booking's contact, same time, version, IP, `granted = !withdrawn`, capture point BOOKING_LOG_BACKFILL, `source_consent_id` set. Report the count.
5. **Shape change.** Add `legal.consent_versions.analytics` (placeholder text version, PENDING LEG-002) with the usual procedure: DML migration, `anakata:config-verify` before and after, registry row and counts, BR fixtures.
6. **The gate (M2).** `ConsentGate::allows(Contact $contact, ConsentPurpose $purpose): bool` — true only when the latest row for that purpose is granted. There is no transactional purpose: transactional sends do not call the gate. Unit-tested for every purpose, a withdrawal after a grant, and a merged contact (the survivor's rows plus the loser's, latest wins).
7. **Merge and unmerge.** Add `contact_consents` to the contact-bearing tables of Sprint 9 task 02, so a merge repoints the rows and an unmerge restores them, with the existing skip rules.
8. **Derived summary switch.** `ContactConsentSummary` and `ContactDerived::marketingConsentSql()` now read the register's latest MARKETING row instead of the I6 log. Lifecycle MQL (marketing consent, no booking) follows automatically. The filter `consent=marketing|transactional_only` keeps its meaning. Test that a contact's summary is unchanged by the switch after the backfill.
9. **Read endpoints** (`panel.crm`, the sensitive-data guard):
   - `GET /api/crm/consents/register` — one row per purpose: purpose, label, basis (Contract for transactional, Consent for the rest), opt-in needed, where it is captured today (from a PHP registry; "not captured yet" for PROFILING, REMARKETING, WHATSAPP), and the number of contacts whose latest row is granted. Transactional is the first row, basis Contract, count = all contacts. This is the prototype's register table, served so the panel copies nothing.
   - `GET /api/crm/contacts/{contact}/consents` — the contact's current state per purpose and the full history (newest first): purpose, granted, version, captured_at, capture point, recorded by, how obtained. The IP is shown as present or absent, never the value.
   - `GET /api/crm/consents/data-map` — the personal-data map (doc 07 §8) as a PHP registry, written as this system implements it: where each item is stored, whether the CRM can read it, and the retention with the business-rule key that sets it.
10. **Timeline.** Register rows appear on the contact timeline (purpose, granted or withdrawn, capture point), replacing the I6 consent lines for MARKETING so the same event is not shown twice.

## Don't
- Don't store transactional consent.
- Don't return an IP address from any CRM endpoint.
- Don't pre-check or infer consent from preferred channel or from a booking.
- Don't change the engine.

## Checks
- `composer check`; `anakata:config-verify` before and after the shape change.
- Triggers refuse update and delete.
- Engine checkout with the marketing opt-in → one I6 row and one register row; without → neither; complete-page withdrawal → `granted = false` in both.
- Stitching records ANALYTICS once per session with the first event's time.
- Backfill count equals the I6 MARKETING count.
- The gate for every purpose, withdrawal, merge, unmerge.
- Register counts agree with the list filter `consent=marketing`.
- The sensitive-field walk and the CRM schema test.

## Report
Append **Task 02** to `docs/sprints/sprint-10/REPORT.md`: the schema and triggers, every feed and its capture point, the backfill count, the shape change and registry counts, the gate and where it will be used, the summary switch, the endpoints. Git commands listed, not run.
