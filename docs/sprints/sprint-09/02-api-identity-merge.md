# Task 02 · anakata-api · Identity resolution, aliases, merge and unmerge
**Repo:** anakata-api · **Sprint:** 9 · **Needs:** task 01.

## Goal
One person, one contact. Emails and phone numbers are normalised so the same person is found however they typed it; staff merge the duplicates that remain into the oldest contact; the losing id lives on as an alias so nothing ever points at a dead record; and a merge can be undone for 30 days, exactly.

## Read first
- `docs/requirements/08-dev-decisions.md`: **L3, L4**, and D5, H10
- `07-three-system-integration-contract.md` §4.5 (`contact.identity_resolved`), §6 (the four identity keys and the merge rule)
- `Contact::normalizeEmail`, `ResolveContact`, every table with a `contact_id` (bookings, groups' coordinator, booking requests if they carry one, waitlist entries, charter enquiries, consents if keyed by contact, and task 03's events)

## Do
1. **Normalisation (L3).**
   - Email: case-folded and trimmed; **plus-addressing is preserved** (`ana+trip@…` and `ana@…` are different addresses — doc 07 §6). Confirm `normalizeEmail` does exactly this; fix it and backfill if not.
   - Phone: E.164 into `phone_e164` with a maintained library (`giggsey/libphonenumber-for-php` after a compatibility check), parsed with the contact's country as the default region. Unparseable numbers stay in `phone` with `phone_e164` null. Backfill existing contacts in the migration's data step.
   - `ResolveContact` matches on normalised email first; with no email, on `phone_e164`; otherwise creates. Record that name alone never matches.
2. **Duplicates.** `GET /api/crm/contacts/duplicates` lists candidate pairs: same `phone_e164`, or same normalised name plus same country. Suggestions only — **nothing merges automatically** (the unique email index already prevents the one case that would be certain).
3. **Aliases (L4).** `contact_aliases`: `alias_id` (the losing contact's id, unique), `contact_id` (the survivor), `merge_id`. Route-model binding for `{contact}` resolves an alias to its survivor (and the response says it did), so any old link — in a panel URL, a history entry, an email — still opens the right person. Aliases are never deleted.
4. **Merge** — `POST /api/crm/contacts/{survivor}/merge` `{ contact_id, reason }`, new permission `contacts.merge` (Admin and Manager by default):
   - The survivor is **the older of the two** (lower id) whatever order staff pick them in — doc 07's rule; the API swaps if needed and says so.
   - One transaction: lock both contacts (lower id first — a fixed order so two merges never deadlock); for every table with a `contact_id`, repoint the loser's rows to the survivor and **record each repointed row** (table, row id) in the merge log; fill the survivor's empty owned fields from the loser (never overwrite a value the survivor has); keep the loser's email and phone in `merged_identifiers` on the log; write the alias; mark the loser merged (`merged_into_id`, soft-deleted from lists).
   - History on both contacts: `contact.merged` with the reason. The survivor's timeline shows the merge (task 04).
5. **Unmerge** — `POST /api/crm/contact-merges/{merge}/undo` `{ reason }` within 30 days (`contacts.merge`): restore exactly the rows the log recorded to the loser, restore the loser's own fields, remove the alias, un-soft-delete the loser. Rows created *after* the merge stay with the survivor — they belong to the merged person. After 30 days → 422; the merge is permanent. A merge that has itself been merged onward (the survivor later merged into a third contact) cannot be undone until the later merge is undone — refuse with the reason.
6. **The log** — `contact_merges`: survivor, loser, reason, merged_by, merged_at, the repointed rows, the merged identifiers, undone_at, undone_by, undo reason. Append-only except the undo fields. `GET /api/crm/contact-merges` for task 04's identity-resolution log.
7. **Resources** typed, into the CRM schema test.

## Don't
- Don't merge automatically on anything but an exact normalised email (which is already one contact).
- Don't delete a contact or an alias.
- Don't restore rows the merge did not move.

## Checks
- `composer check`.
- Normalisation: case and whitespace in email; plus-addressing kept distinct; phone formats in several countries to the same E.164; unparseable stays null.
- Merge: every contact-bearing table repointed; survivor is the older one whichever is chosen; fields filled not overwritten; the old id resolves to the survivor.
- Unmerge: exactly the recorded rows return; a booking created after the merge stays; after 30 days refused; chained merges refused until undone in order.
- Concurrency (Sprint 4 harness): two merges touching the same contact serialise; never 1213.

## Report
Append **Task 02**: normalisation and the phone library, what counts as a duplicate, aliases and binding, the merge and unmerge rules, the log, and the chained-merge rule. Git commands listed, not run.
