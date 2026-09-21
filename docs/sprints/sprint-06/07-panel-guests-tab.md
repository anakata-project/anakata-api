# Task 07 · anakata-panel · Booking panel: Guests tab
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 6 · **Needs:** task 06 (`v0.7.0`).

## Goal
The Guests tab, disabled since Sprint 4, becomes the passenger list: completeness and PNG totals, the issues the API finds, a card per guest, an edit form, guardian consent for minors, and the consent records.

## Read first
- Sprint 5 task 07 in its REPORT (how the Payments tab was extracted, lazy-loaded and refreshed)
- `prototype/rms_index.html`: `drGuests` (the privacy note, the two KPIs, the warnbox, the add button, the consent table and its footnote), `guestCard`, `guestForm`, `saveGuest`, `gfAge`, `addGuest`, `rmGuest`, `recConsent`
- This sprint's REPORT tasks 01–03 for the masking rule, the issue codes and the consent shape

## Do
1. **Enable the tab** in `BOOKING_TABS` (remove the Sprint 6 tooltip). Extract `BookingGuestsTab.vue`, lazy-loaded on first visit like History and Payments.
2. **Header.** The prototype's privacy note, then two KPIs from the list summary: "Complete {n}/{total}" ("needed for DPNG list & manifest") and "PNG fees (by nationality)" with the known total and "{n} guests pending data" when there are pending ones — or, when fees are not collected, the prototype's "paid at SCY airport". The collected/not-collected state comes from the booking (task 04). No arithmetic in the panel.
3. **Issues.** The API's `issues` in a `.warnbox`, errors with ✕ and warnings with ⚠, in the order the API sends them. Never recomputed in the panel.
4. **Guest cards** (prototype `guestCard`): name or "Guest {n} — name pending", LEAD and MINOR pills, age at departure, country name, EC resident, the passport **as the API sent it** (masked or full), expiry, PNG category label and fee, insurance declared or not, guardian status for minors, and "medical note on file" when `medical_note_on_file` is true and the value is absent. Incomplete cards take the prototype's `inc` style.
5. **Edit form** (prototype `guestForm`), one guest at a time:
   - names, DOB, nationality (a select filled from the API's country list — task 05 added it; no copy of `COUNTRIES` in the panel), EC resident, passport number, expiry, email, insurance declared.
   - **Passport for users without `guests.view_sensitive`:** the field is empty with the placeholder "Restricted — enter to replace". Sending it empty leaves the stored number unchanged (task 02's contract); typing replaces it. Say so under the field.
   - **Medical, dietary and accessibility notes** only for `guests.view_sensitive`.
   - **Guardian block**, shown when the DOB makes the guest a minor **today** — the API's `is_minor_now` after save, and a client-side preview while typing only to show or hide the block (prototype `gfAge`), with the save result as the truth. Name, relationship, "Guardian consent received (timestamp logged)".
   - Save → PATCH; field errors through `applyApiFormError`; a DOB in the future is the API's 422, not a panel check. A failed save keeps the form open with the typed values (the textarea/list rules from earlier sprints). Cancel with unsaved changes → `confirmUnsaved`.
   - Remove: offered only for a non-lead guest whose name is empty (the API enforces it; the panel just hides the button otherwise).
6. **＋ Add guest** while the API reports room (below the limit) and the user `can_act`. The limit comes from the API summary, not from engine settings in the panel.
7. **Consent records** (prototype table): document, version (with an "outdated" marker when the API says so), accepted, source (and IP when present). Required documents without a row show "Missing" in coral; marketing shows "Not given" in the muted tone. **Record** per missing document for `can_act`: a `ReasonModal` variant asking "How was it obtained?", posting to the consents endpoint. The prototype footnote under the table.
8. **After every write:** refresh the tab, the booking (the summary on Overview, and the balance when fees are collected), and the list.
9. **Helpers, tested:** `guestDisplayName(guest, index)`, `issueIcon(severity)`, `consentRowState(row)` (`present | missing | not_given`), `showGuardianBlock(dobIso, todayIso)` (the preview only — note in the helper's docblock that the API's `is_minor_now` is authoritative).
10. **Overview.** The completeness summary `{complete}/{total}` beside the party, from `guests_summary` (the prototype's GUESTS line, which Sprint 4 omitted).

## Don't
- Don't mask anything in the panel — render what the API sent.
- Don't compute PNG categories, fees or issues.
- Don't copy the country list.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.7.0`.
- Browser, both themes, after `reset.sh` (on the cloud machine for task 10; locally only if you choose to):
  - Carolina (operations permissions) on `ANK-2026-0005`: three guests, the Brandt child a MINOR with guardian consent, full passports, PNG categories and fees.
  - Lucía on her own booking: passports masked, no medical fields, "Restricted — enter to replace" works both ways.
  - An incomplete booking lists the issues; completing a guest removes them without a reload.
  - Recording a consent adds it with its source; nothing lets you edit or remove it.

## Report
Append **Task 07**: the tab's structure, what each role sees, the passport-replace behaviour, the guardian preview versus the API's answer, the consent recording, and the Overview line. Git commands listed, not run.
