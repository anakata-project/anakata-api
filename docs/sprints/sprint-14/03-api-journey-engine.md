# Task 03 · anakata-api · The journey engine
**Repo:** anakata-api · **Sprint:** 14 · **Needs:** task 02.

## Goal
Enrol, wait, send, exit — with consent and suppression checked at every step, and nothing written outside the CRM (Q3, Q4, Q9).

## Read first
- `docs/requirements/08-dev-decisions.md`: **Q3, Q4, Q9**, Q1, Q2, Q5, and A4, J5, M1, M2, M6, N1
- The prototype `JOURNEYS`: eight journeys with their goal, trigger, steps, conversion note, exit and the suppression line
- The task sweep and alert sweep (Sprints 10 and 11) as the shape to copy; `ConsentGate`; the delivery path and its keys

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Schema.**
   - `journeys`: `key`, `name`, `goal`, `kind` (MARKETING or TRANSACTIONAL, Q4), `trigger` (a structured definition: an event, a booking state, a segment entry, or a time offset from one of those), `exit_conditions` (JSON), `active`, `system`, audit columns.
   - `journey_steps`: `journey_id`, `position`, `delay` (from enrolment or from the previous step, in days or hours, business or calendar — state which), `template_key` (task 04), `condition` (optional, a segment or a fact that must still hold), audit columns.
   - `journey_enrolments`: `journey_id`, `contact_id`, `booking_id` (nullable, for booking-scoped journeys), `position`, `next_due_at`, `status` (ACTIVE, EXITED, SUPPRESSED, COMPLETED), `exit_reason`, `enrolled_at`, `exited_at`, unique on journey, contact and booking so one contact cannot be enrolled twice for the same subject.
   - `journey_sends`: enrolment, step, template version, delivery id, sent_at — the record of what was sent from what.
2. **Enrolment.** Queued listeners on the events the triggers name, plus a sweep for the time-based ones. Enrolling checks the journey is active and enabled in the catalogue, the contact is not suppressed, and, for a marketing journey, that `ConsentGate` allows MARKETING. Idempotent per journey, contact and subject.
3. **The runner.** `anakata:journeys`, every fifteen minutes, with the run hooks: take enrolments whose `next_due_at` has passed, and for each, in one transaction — re-check the exit conditions, the catalogue switch, suppression and consent (Q3); send the step's template through the normal delivery path with key `journey:{enrolment}:{step}`; record the send; move to the next step or complete. Exit conditions are also checked by the listeners that can make them true, so a booking created at 10:00 ends the nurture journey before its 10:15 step.
4. **The eight journeys**, seeded from the prototype with their triggers, steps and exits, each marked marketing or transactional with the contract it rests on written in the definition (Q4). Where a step duplicates something the system already sends (the balance reminders, the pre-trip package, the NPS survey), the journey points at that automation rather than sending a second copy — the catalogue is the single list, and the REPORT says which steps are pointers.
5. **Nothing else is written (Q9).** A journey writes enrolments, sends, deliveries and contact timeline rows. It raises a task (M6) where the prototype hands to a person ("sales exec personal note", "hands to sales at 24 h silence"). A test asserts booking, payment, guest and document tables are untouched by a full run.
6. **Endpoints** (`panel.crm`): `GET /api/crm/journeys` (definitions with steps, live enrolment counts per step, the exit sentence and the suppression sentence), `GET /api/crm/journeys/{key}/enrolments`, `PATCH /api/crm/journeys/{key}` (active, `rules.manage`), and on the contact drawer, that contact's enrolments and sends.

## Don't
- Don't check consent only at enrolment.
- Don't send the same step twice, or two journeys' copies of the same message.
- Don't let a journey change a booking.

## Checks
- `composer check`.
- Enrolment on each trigger, once; a step sends at its due time and not before; the next step follows; completion.
- A withdrawal between steps stops the next step (the enrolment becomes SUPPRESSED with its reason).
- An exit condition met between steps exits before the send, including the listener path.
- A disabled catalogue switch stops the send but leaves the enrolment.
- The no-side-effect table test; the handover task is raised once.

## Report
Append **Task 03**: the schema, enrolment and the runner, the eight journeys with their kinds and which steps are pointers, the re-check rule, and the no-side-effect test. Git commands listed, not run.
