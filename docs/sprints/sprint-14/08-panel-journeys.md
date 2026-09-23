# Task 08 · anakata-panel · Journeys
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 14 · **Needs:** task 06 (`v0.15.0`).

## Goal
The team can see what each journey does, who is in it, and stop it — and read the words it sends (Q3, Q8).

## Read first
- This sprint's REPORT tasks 03 and 04; the prototype `v-journeys` and `renderJourneys` (the card, the step strip with enrolled counts, the footer with conversion, exit and suppression)

## Do
1. **Page** `app/pages/crm/marketing/journeys.vue` (replaces the placeholder; the nav entry is already there with its "later" marker — change it to sprint 14 and extend the navigation test).
2. **Cards** from `GET /api/crm/journeys`: name, goal, the trigger line, a MARKETING or TRANSACTIONAL pill, the step strip (each step's timing, name and live enrolment count), and the footer with the exit sentence and the suppression line, all from the API.
3. **Controls** with `rules.manage`: Active toggle per journey, with the API's confirmation sentence. A disabled catalogue switch on one of its steps is shown on that step, linking to Automations (task 09).
4. **Enrolments drawer:** from `GET …/enrolments` — contact, booking where there is one, current step, next due, status, and the exit reason for those that left. A contact opens the CRM contact drawer.
5. **Templates:** each step links to its published template version, shown read-only (subject, body, variables, version, approval reference) with a Preview against a chosen contact, and Send test to me. Editing and publishing is behind `rules.manage`: create a draft, edit the structured fields, publish with an approval reference, and see the version list. No rich-text editor (Q8).
6. **Contact drawer:** a Journeys section showing that contact's enrolments and what was sent, with dates and template versions.

## Don't
- Don't compute a count, a due time or a step state in the panel.
- Don't offer an edit the API's payload did not allow.
- Don't show a message body to a user without `panel.crm`.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.15.0`.
- Browser, both themes, after `reset.sh`: the eight cards with their steps; enrol a contact (an engine action) and see the count move; open the enrolments drawer; preview a template against a real contact; send a test to yourself (Mailpit); publish a new version with an approval reference; turn a journey off and confirm the API refuses new enrolments.

## Report
Append **Task 08**: the cards, the controls, enrolments, the template panel, the contact section, and the browser pass. Git commands listed, not run.
