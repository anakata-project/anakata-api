# Task 02 · anakata-api · Guests, guardian consent, validation, PNG categories
**Repo:** anakata-api · **Sprint:** 6 · **Needs:** task 01.

## Goal
Each booking holds one record per passenger. The API derives each guest's age at departure, PNG category and fee, completeness, and the issues staff must resolve. Guardian consent is recorded for minors. Sensitive fields go through task 01's cast and masking.

## Read first
- `docs/requirements/08-dev-decisions.md`: **I2, I3, I4, I5, I10**, and B6, G4, G9
- `02-data-model.md` → Guest / passenger (fields and derived values); `03-business-rules.md` FIN-004, OPS-004, OPS-005, §6.4; `05-decisions-and-open-questions.md` rows 4 and 5
- `prototype/rms_index.html`: `COUNTRIES`, `CAN_NAT`, `PNG_FIXED`, `ageAt`, `pngCat`, `G`, `gName`, `gComplete`, `gdOf`, `guestIssues`, `isMinorNow`, `conf`, `guestForm`, `saveGuest` (its change-list wording), `addGuest`, `rmGuest`, and `seedOps` (the demo passengers)
- Engine settings `guests.*` and `fees.png.*`; `Departure::returnDate()`; `BookingMutationLock`

## Do
1. **`guests` table and model.** `booking_id`, `position` (1-based order), `is_lead`, `first_name`, `last_name` (as on the passport), `dob` (date), `nationality` (char 2), `ecuador_resident` (bool), `passport_no` (text, `SensitiveEncrypted`), `passport_expiry` (date), `email`, `insurance_declared` (bool), `medical_note`, `dietary_note`, `accessibility_note` (text, `SensitiveEncrypted`), `guardian_name`, `guardian_relationship`, `guardian_consented_at`, `guardian_recorded_by`, plus the derived and stored `png_category` and `png_fee` (nullable int), audit columns.
   - Model `Guest`, morph alias `guest`, `historyLabel()` = the display name or "Guest {position}".
   - Exactly one lead per booking (generated column + unique index, the `active_key` pattern). The first guest created is the lead.
   - `ON DELETE` restrict; a guest is removed through the action below, never by cascade.
2. **Slots and limits.** A cabin booking holds at most `guests.max_per_cabin`; a charter at most `guests.max_per_yacht` (engine settings, never literals). The party counts (`adults`, `children`) stay the priced party (G4); guests are the people. They are compared, not synced (issue below).
3. **Endpoints.**
   - `GET /api/rms/bookings/{booking}/guests` — the list, with the summary: `complete_count`, `total`, `png_known_total`, `png_pending_count`, and the `issues` list.
   - `POST /api/rms/bookings/{booking}/guests` — add a slot (any subset of fields; a slot starts empty, as in the prototype).
   - `PATCH /api/rms/guests/{guest}` — edit. `passport_no` sent as an empty string by a user without `guests.view_sensitive` means "unchanged", because they never received the value; sent non-empty, it replaces (the prototype's "Restricted — enter to replace"). A user without the permission cannot read back what they typed.
   - `DELETE /api/rms/guests/{guest}` — only a non-lead guest whose name is still empty (the prototype's `rmGuest` rule). Otherwise 422.
   - Permission: view with `BookingPolicy::view`; write with the booking's own-records rule (`can_act`), the same as the booking panel's other edits. Medical, dietary and accessibility notes can be written only with `guests.view_sensitive` (operations); others get 403 on those fields.
4. **Writes take the booking lock.** Every guest write runs in a transaction that takes `BookingMutationLock::acquire` first (departure → booking), because a guest write can change the booking's collected fees and therefore its balance (I9, I10). Record this in the REPORT's lock-order line.
5. **`GuestResource`.** Every sensitive field through task 01's `Masking`. Plus the derived fields: `age_at_departure`, `is_minor_now`, `png_category`, `png_category_label`, `png_fee`, `complete`, and `guardian: { name, relationship, consented_at } | null`. `dob` and `nationality` are returned (the panel needs them), but they stay in `SensitiveFields` so a CRM response can never carry them.
6. **PNG categories (I4).** `App\Support\Guests\PngCategory::for(?dob, ?nationality, bool $resident, departureDate, EngineSettingsDocument)`:
   - missing DOB or nationality → `PENDING`, fee `null`;
   - under `fees.png.exempt_under_age` → `EXEMPT`, fee 0;
   - `EC` or resident → `NATIONAL_OR_RESIDENT`, `fees.png.national_or_resident` at any age (decision row 5);
   - Andean Community (`CO`, `PE`, `BO` — a code constant citing Res. 002-CGREG-24-02-2024, like the prototype's `CAN_NAT`) → `CAN_ADULT` over 12, `CAN_MINOR` at 12 and under;
   - otherwise `FOREIGN_OVER_12` / `FOREIGN_12_AND_UNDER`.
   - The cutoff is 12 (B6), not the child-rate age of 17. Age is whole years at the **Galápagos departure date**, computed on calendar dates — the prototype's `ageAt`, never an instant difference.
   - The category and fee are stored on the guest (I4) and recomputed on every guest write, on a booking move (hook into `MoveBooking`), and when the collection choice changes (task 04). A later publish of the fee amounts does **not** rewrite stored fees; record that, and why (G4, and the SQL balance in task 04 sums stored values).
7. **Issues (I5)**, computed by the API, returned with the list, each `{ severity: error|warning, code, guest_id|null, message }`, prototype wording:
   - under `guests.child_min_age` at departure (error, OPS-004);
   - a minor today (`is_minor_now`, the prototype's `isMinorNow`) without `guardian_consented_at` (error, §6.4);
   - passport expiring before `Departure::returnDate()` (error);
   - no insurance declaration on a booking that is CONFIRMED or later (warning, OPS-005 — the prototype's `conf`);
   - on a cabin booking where every guest has a DOB: guests aged `child_min_age`–`child_max_age` ≠ the priced `children` (warning: "check the quote");
   - missing required consents (warning) — task 03 fills this in; return an empty contribution until then.
   - Issues never block a save. Guest data arrives piece by piece; the list is how staff see what is still wrong. Record that saving is never refused for an issue, only for invalid input (a DOB in the future, a malformed nationality, an expiry before the DOB).
8. **Guardian consent.** Visible and required only for `is_minor_now`. Setting `guardian_consented_at` records the timestamp once and the user; unticking clears it, with history. A guardian name is required when consent is set.
9. **History.** `guest.added`, `guest.updated`, `guest.removed` on the **booking**. `guest.updated`'s `what` lists the changed fields by name, prototype wording ("Passenger updated — Julia Brandt: passport number, medical note"); values go through `History::redact`. A guardian consent gets its own `guest.guardian_consented` entry.
10. **Booking exposure.** `BookingResource` gains `guests_summary: { complete, total }` (the prototype's `gdOf`, "2/3") for the list and Overview, without loading every guest on the index. Add a query-count test for the bookings index.
11. **Seed (local/testing).** The passengers from the prototype's `seedOps`, per booking, with their passports and expiries, the Brandt child's guardian consent, and the incomplete guests exactly as seeded (they are the fixtures for the issues list). Values from the prototype seed, amounts derived. Idempotent. Mark the lead guest.

## Don't
- Don't sync `adults`/`children` from guests or reprice a booking from guest ages. The comparison is an issue, not an automation.
- Don't block a transition on guest issues this sprint. Record it as an open question (should CONFIRMED require complete guests? The prototype doesn't).
- Don't expose any sensitive value unmasked to a user without `guests.view_sensitive`, in any resource.

## Checks
- `composer check`.
- PNG: every category, the boundary at 12 and at the exempt age, EC resident, the three CAN countries, and a 23:30 GALT departure-date edge; amounts from a published engine-settings version, and a republish that does not change stored fees.
- Issues: each one, including the minor turning 18 before departure (still a minor today → guardian needed).
- Masking: Sales Exec sees `•••• ` + last three and `on_file` for a medical note; operations sees both in full; a Sales Exec PATCH with an empty passport leaves it unchanged; a non-empty one replaces it; writing a medical note without the permission is 403.
- Limits: the slot limit per cabin and per charter; removal only of an empty non-lead guest.
- History never contains a passport number or a note.
- Concurrency: two guest writes on one booking serialise (the Sprint 4 harness, 1205 never 1213).

## Report
Append **Task 02**: the table, the permission and masking rules, the PNG decision table with its sources, the issues and why none blocks a save, the lock order, the seed, and the open question about CONFIRMED with incomplete guests. Git commands listed, not run.
