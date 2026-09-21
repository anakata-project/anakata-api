# Sprint 6 · Guests, consents, extras and Galápagos fees

**Goal:** a booking knows who is travelling and everything it is charged for.
- Every passenger has a record: name as on the passport, date of birth, nationality, passport, insurance declaration, and a guardian's consent when a minor travels.
- Passport numbers and medical notes are encrypted with their own key and shown in full only to the operations team. They are purged on the retention schedule.
- The four required consents (and optional marketing) are logged with version, time, IP and source, and kept for seven years.
- Each guest's Galápagos park entry fee (PNG) is derived from age at departure and nationality. The guest chooses whether Anakata collects it, and the TCT card.
- Contracted extras (flights, hotels, spa, bar, boutique) come from a priced catalogue and are frozen at sale.
- The balance now covers everything the booking is charged: cruise, extras and the fees Anakata collects.

Documents (the invoice that shows all this) and their emails are Sprint 7. The manifest and DPNG export are Sprint 11. Guest preferences and NPS are Sprint 11.

- **anakata-api:**
  - the sensitive-data foundation: dedicated-key encryption, masking, redaction
  - guests, guardian consent, validation, PNG categories
  - the consent log and the retention jobs
  - the extras catalogue, booking extras, fee collection, and the charges model
  - Contacts In
- **anakata-ui:** regenerated types, release `v0.7.0`.
- **anakata-panel:**
  - the booking panel's Guests tab
  - the booking panel's Extras tab, the extras catalogue editor, the charges rows on Overview
  - Contacts In
- **E2E:** guest and extras scenarios and a P1 run.

## Before task 01
1. **Close Sprint 5.** The Task 11 run did not go to the cloud: the spawn failed because the workspace has four git remotes and the cloud environment requires exactly one. It ran locally against the working stack, and walked 4 of the 11 planned scenarios.
   - Fix the remote configuration so a cloud agent can start (the fix belongs in `.cursor/environment.json` / the repo setup, not in the e2e scripts).
   - On the cloud machine: walk PAY-05, PAY-08, PAY-10 (P1), PAY-03 with the cfo@ context, and BKG-02 / 06 / 09; then the full P1 set (Sprints 1–5). Attach both reports; fix any `BUG` first.
   - Record how many of the 55 leftover Sprint 4 `⚠ UNVERIFIED` markers the run clears.
   - Review and merge `e2e/sprint-05` into `dev`.
2. **Small leftovers from Sprint 5:**
   - `requests.confirmSprint` still says the deposit link arrives in Sprint 5. It exists now; fix the copy.
   - Record the fresh-clone check against the pushed `v0.6.3` and `v0.6.4` tags.
3. **Copy `08-dev-decisions.md`** from this folder to `docs/requirements/` (adds section I).
4. **Ask the client** the questions below. The sprint builds with the defaults and flags them.

**Questions for the client:**
- **LEG-002, retention periods** (decision B4, still PENDING CLIENT): passport data anonymised 24 months after the cruise, and medical, dietary and accessibility notes purged 90 days after disembarkation. Confirm, since this sprint's jobs will delete data on that schedule.
- **Consent documents:** the version labels and texts for Terms & Conditions, the cancellation policy (LEG-001), the privacy policy (LEG-002) and the travel-insurance declaration (OPS-005). The sprint stores the version labels as PENDING CLIENT values.
- **The extras catalogue:** confirm the six services and their prices (the prototype takes them from invoice mockup v6: flights USD 420 per person, pre- and post-cruise hotel USD 320 per room-night; spa, premium bar and boutique priced on request).
- **Galápagos fees, default:** when a booking is created, is the PNG fee and the TCT card "paid by the guest directly" (the prototype's unchecked default) or "collected by Anakata"?

## Decisions this sprint implements
Recorded as **I1–I10** in `docs/requirements/08-dev-decisions.md`:
- I1: sensitive data has its own key.
- I2: the API masks; the panel never sees what it may not show.
- I3: one guest record per passenger.
- I4: the PNG category and fee are derived and stored per guest.
- I5: minors, and validation that warns rather than blocks.
- I6: the consent log is append-only.
- I7: retention jobs.
- I8: the extras catalogue is a versioned document; booking extras are frozen.
- I9: charges, balance and what OVERDUE means now.
- I10: fee collection is a per-booking choice, and the lock order.

## How this sprint is run
As before: one task at a time; plan → review → agent; each task appends to `REPORT.md`.

| # | Repo | Task |
|---|---|---|
| 01 | anakata-api | Sensitive data: dedicated-key encryption, masking, redaction |
| 02 | anakata-api | Guests, guardian consent, validation, PNG categories |
| 03 | anakata-api | The consent log and the retention jobs |
| 04 | anakata-api | The extras catalogue, booking extras, fee collection, the charges model |
| 05 | anakata-api | Contacts In |
| 06 | anakata-ui | Regenerate types, release `v0.7.0` |
| 07 | anakata-panel | Booking panel: Guests tab |
| 08 | anakata-panel | Booking panel: Extras tab and charges; the extras catalogue editor |
| 09 | anakata-panel | Contacts In |
| 10 | anakata-api | E2E scenarios for Sprint 6; P1 run |

Dependencies:
- 01 → 02 → 03; 04 needs 02 (collected PNG fees are summed from guests); 05 needs 02.
- 06 needs 01–05.
- 07–09 need 06; 08 needs 07 (the charges rows sit beside the guest count).
- 10 needs everything.

## Context every task needs
- Rules in each repo's `.cursor/rules/`. Decisions: `08-dev-decisions.md`, sections A–I. In particular **B4** (retention), **B6** (PNG cutoff 12), **B8** (the dedicated-key cast and the CRM guard, instead of database roles).
- The sources:
  - `01-functional-spec.md` §4 (booking panel: Guests and Extras tabs), §8 (Contacts In)
  - `02-data-model.md`: Guest / passenger, Consent record, Extra service, the deposit-scope note
  - `03-business-rules.md`: FIN-004 (PNG and TCT), OPS-004 (minimum age 6), OPS-005 (insurance declaration), OPS-013 (DPNG manifest timing), §6.4 (consent and data)
  - `05-decisions-and-open-questions.md` rows 4, 5 and 7
- The prototype `prototype/rms_index.html`:
  - `COUNTRIES`, `CAN_NAT`, `PNG_FIXED`, `ageAt`, `pngCat`
  - `G`, `gName`, `gComplete`, `gdOf`, `mask`, `guestIssues`, `isMinorNow`, `conf`
  - `drGuests`, `guestCard`, `guestForm`, `saveGuest`, `addGuest`, `rmGuest`, `gfAge`
  - `CONSENT_DOCS`, `CONSENT_VER`, `recConsent`, the consent table in `drGuests`
  - `ANC`, `ancBy`, `ancTotal`, `drExtras`, `anPick`, `addAnc`, `rmAnc`, `setFee`, `feeRows`
  - `renderAncCat` (the catalogue editor in Rates & Promotions)
  - `v-contacts`, `renderContactsIn`, `renderNat`
  - `seedOps` (the demo passengers and consents)
- What already exists:
  - `Permission::GuestsViewSensitive` and `Permission::ExtrasManage`
  - `App\Support\SensitiveFields` (`passport_no`, `medical_note`, `dietary_note`, `accessibility_note`, `dob`, `nationality`), used by `History::redact()` and by the `GuardCrmSensitiveData` middleware
  - Engine settings `guests.child_min_age` / `child_max_age` / `max_per_cabin` / `max_per_yacht` and `fees.tct_pp` / `fees.png.*` (six categories)
  - Business rules `payments.extras_due_hours`, `retention.passport_months_after_cruise`, `retention.medical_days_after_cruise`, `manifests.dpng_*`
  - `Departure::returnDate()`, `Booking::balanceSql()`, `Ledger`, `PaymentsKpis`
  - **Never hard-code any of these values.**
- API in Docker only; git read-only for Cursor; compatibility check before any package; frontends verified on a fresh clone; **tags pushed**; no hand-written type overlays for fields the API can type.
- **E2E rule:** screen facts are gathered after `tests/e2e/bin/reset.sh`, on the cloud machine; amounts come from `fixtures/reference-values.md`.

## E2E scenarios this sprint adds (task 10)
`GST-01` … `GST-08` and `EXT-01` … `EXT-05`, listed in task 10.

## Definition of done for the sprint
- **Sensitive data:** passport numbers and medical, dietary and accessibility notes are encrypted at rest with `SENSITIVE_DATA_KEY` (not `APP_KEY`). A database dump shows ciphertext. A Sales Exec sees `•••• 123`; the operations team sees the number. No history entry, log line or CRM-section response ever contains one of these values.
- **Guests:** a booking's passengers can be added up to the cabin or yacht maximum, edited, and removed when empty. The lead guest is marked. The Guests tab shows completeness, each guest's PNG category and fee, and the issues the API finds: under the minimum age at departure, a minor without guardian consent, a passport expiring before the return date, a missing insurance declaration, children priced versus children present, missing consents.
- **Consents:** the four required documents and marketing are listed per booking with version, time and source. Staff can record a consent obtained off-line, with how it was obtained. Nothing edits or deletes a consent row.
- **Retention:** the retention command anonymises passports and purges notes on the configured schedule, records it as System, and has a dry run.
- **Extras and fees:** the catalogue is edited and published like the other configuration documents. Adding an extra to a booking freezes its rate, and removing one is recorded. Switching "Anakata collects the PNG fee" or "Anakata manages the TCT card" adds or removes those charges.
- **Money stays true:** Overview shows cruise, extras, collected fees, the charges total, Paid and Balance, and every one of them comes from the API. The deposit is still a share of the cruise charges only. A fully paid booking that gains an extra shows a balance again but stays FULLY_PAID. OVERDUE still means the cruise balance past T−120.
- **Contacts In** lists the contacts behind bookings and requests, and the top ten guest nationalities.
- All checks pass on fresh clones. `anakata-ui` `v0.7.0` is tagged and pushed. The cloud P1 run (Sprints 1–6) is attached with no open `BUG`.
