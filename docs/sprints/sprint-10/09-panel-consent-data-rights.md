# Task 09 · anakata-panel · Consent & Data Rights
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 10 · **Needs:** task 08.

## Goal
The consent register, the personal-data map and subject requests on one screen, and each contact's consent state and history in their drawer.

## Read first
- `prototype/crm_index.html`: `v-privacy` (notice, consent register, data map, subject requests, the pending-items hint), `openContact` (consent rows)
- This sprint's REPORT tasks 02 and 05; screenshot `crm-07-privacy.png`

## Do
1. **The page** — `app/pages/crm/system/consent.vue` (replaces the placeholder):
   - Notice (i18n): consent is a CRM record every send checks at send time; the LOPDP / GDPR architecture is pending with the client (LEG-002). Say what is built, not what is mirrored.
   - **Consent register** from `GET /api/crm/consents/register`: purpose, basis, opt-in needed, captured at (showing "not captured yet" as the API sends it), contacts. Transactional first.
   - **Data map** from `GET /api/crm/consents/data-map`: data, stored in (system badge), in the CRM (Never in coral), retention and its rule.
   - **Subject requests** — shown only with `privacy.manage`: the list from `GET /api/privacy/requests` (type, contact, received, due with an overdue highlight, status), filters, and "New request" (contact search, type, received, channel). The request drawer: details, `verified_how` (required before completing), and per type — **Access**: Build export, then Download; **Rectification**: a link to the contact's edit and an outcome naming the fields; **Objection**: Complete, stating which purposes will be withdrawn; **Erasure**: states what is removed and what is kept (the API's sentences), a typed email confirmation, and shows the 409 sentence when refused. Reject with an outcome for any type.
   - Without `privacy.manage` the subject-requests panel is replaced by one line saying who handles requests.
   - The pending-items hint in i18n (LEG-001, LEG-002, LEG-004, TEC-003, TEC-005).
2. **Contact drawer consent block** (replaces Sprint 9's summary rows and the "arrives in Sprint 10" line): transactional ALWAYS ON; each register purpose with OPTED IN / NOT OPTED IN / NO RECORD from `GET …/contacts/{id}/consents`; a History toggle (purpose, granted or withdrawn, version, when, capture point, by, how obtained; the IP as "recorded" / "—"); **Record consent** with `consents.record` (purpose, granted or withdrawn, how obtained — required). The ALL SENDS IN ENGLISH badge stays.
3. **Helpers, tested:** `consentStateLabel(state)`, `subjectRequestDueClass(dueAt, status)` — the due class reads the API's overdue flag if it sends one; otherwise compares in UTC.

## Don't
- Don't compute counts, due dates or consent state in the panel.
- Don't show an IP address or export contents in the panel.
- Don't show the subject-requests panel or call `/api/privacy` without `privacy.manage`.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.11.0`.
- Browser, both themes, after `reset.sh`: the register counts; accept marketing on an engine request → the contact shows MARKETING opted in with capture point engine form; record a WhatsApp consent as staff; a consenting engine session stitched on submission → ANALYTICS in the history; an objection withdraws marketing and the register count drops; an access export downloads and contains no passenger data; erasure refused for a contact with an upcoming departure; erasure of a contact with no upcoming booking (create one if the seed has none, and say so in the REPORT) shows "Erased contact #…" in Contacts; Manager sees no subject-requests panel.

## Report
Append **Task 09**: the page sections, the drawer consent block, the subject-request flows, helpers, and the browser pass. Git commands listed, not run.
