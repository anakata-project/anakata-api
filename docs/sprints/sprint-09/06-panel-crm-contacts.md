# Task 06 · anakata-panel · CRM Contacts: list, profile, timeline, merge
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 9 · **Needs:** task 05 (`v0.10.0`).

## Goal
The CRM's Contacts screen: every person, with the fields the CRM derives from the bookings, a profile drawer with their bookings and timeline, editing of the fields the CRM owns, and merging duplicates.

## Read first
- `prototype/crm_index.html`: `v-contacts` (notice, filters, columns, the derived-lifecycle hint), `renderContacts`, `openContact` (every row of the drawer, the read-only badges such as "FROM RMS LEDGER" and "ALL SENDS IN ENGLISH")
- This sprint's REPORT tasks 01, 02 and 04; the panel's CRM shell and navigation (`/crm/sales/contacts`)

## Do
1. **The list** (replaces the placeholder): the prototype notice (the CRM is the system of record for people; passports and medical data are never here); filters for type, lifecycle, main channel, channel of origin and consent from `meta.filters` and the existing channel enums; a search box; columns name, type, country, lifecycle, main channel, channel of origin, lifetime value, segment, consent (MKT ✓ / TX ONLY), NPS ("—" until Sprint 11). Every value from the API. The prototype's hint line under the table.
2. **The profile drawer** (`openContact`): name, type · lifecycle · contact id; country · language with "ALL SENDS IN ENGLISH"; preferred channel; main channel and channel of origin; lifetime value "FROM RMS BOOKINGS" and segment; first and last touch; consent (transactional always on; marketing from the summary; "Consent register arrives in Sprint 10"); the partner record for a travel agent (link to the RMS agency).
   - **Bookings** — the contact's bookings from the profile, each opening the RMS booking panel (a deep link into the RMS section; the RMS decides what the user may do there).
   - **Timeline** — `GET …/timeline`, paginated, each item's wording from the API, links where the API gives one.
   - **Edit** (`contacts.manage`): the owned fields, `applyApiFormError`; a 409 email conflict offers "Review merge" with the other contact.
3. **Duplicates and merge** (`contacts.merge`): a "Possible duplicates" panel above the list from `GET …/duplicates`; "Merge" opens a side-by-side comparison of the two contacts (from their profiles), states which one survives (the older, as the API decides) and what moves, asks for a reason, and confirms. After a merge, the drawer shows "Merged into …" for the old id (the API resolves it) and an **Undo** within 30 days on the survivor's timeline merge entry. Say plainly what an undo restores and what stays.
4. **Helpers, tested:** `segmentPillClass`, `lifecyclePillClass`, `consentPillLabel` (presentation only).

## Don't
- Don't compute lifecycle, lifetime value, segment or consent in the panel.
- Don't show guest data, payments detail or notes in the CRM.
- Don't offer any action that changes a booking from the CRM screens; link to the RMS instead.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.10.0`.
- Browser, both themes, after `reset.sh`: the seeded contacts with their lifecycles and segments; cancel a booking in the RMS → that contact's value and segment change; edit a contact; the email conflict leads to a merge; merge two duplicates → their bookings appear on the survivor and the old link opens the survivor; undo restores them.

## Report
Append **Task 06**: the list and filters, the drawer, editing, duplicates, merge and undo, and the links into the RMS. Git commands listed, not run.
