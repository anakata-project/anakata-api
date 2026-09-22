# CRM-08 · Edit email to another contact → conflict offers merge
- **Tags:** sprint-9, crm
- **Priority:** P2
- **Users:** Carolina
- **Start:** reset

## Why
A colliding email must not silently overwrite the other person. The 409 names the contact and, with `contacts.merge`, offers Review merge.

## Steps
1. Sign in as `carolina@anakata.test` / `password`. Open `http://localhost:3001/crm/sales/contacts`.
2. Search `Whitfield`. Open **Anna Whitfield**.
3. Under **Edit**, change Email to `k.osei@anakata.test`. `Save`.
4. Read the warning. Confirm **Review merge** is shown. Do **not** confirm the merge.

## Expected
- [ ] E1 · Save stays on the drawer. Warning: `That email belongs to contact #{id} (K. Osei). Merge the contacts to keep a single record.` The `{id}` is K. Osei’s id. ⚠ UNVERIFIED — `UpdateContact` message; task 06 saw `contact #20`.
- [ ] E2 · **Review merge** is offered (`contacts.merge` + parsed `contact #(\d+)`).
- [ ] E3 · Anna Whitfield’s email is unchanged (still `whitfield.anna@anakata.test`). K. Osei is still a separate row.
- [ ] E4 · Clicking **Review merge** opens the merge modal with both contacts. Stop there — CRM-04 owns merge and undo.

## Notes
Do not complete the merge. If **Review merge** is missing but the 409 message is correct, classify **SCENARIO** only when the id cannot be parsed; otherwise **BUG** (Admin has `contacts.merge`).
