# Task 05 · anakata-api · Subject requests
**Repo:** anakata-api · **Sprint:** 10 · **Needs:** task 04.

## Goal
A person's data-protection requests — access, erasure, rectification, objection — are recorded, tracked against their SLA and carried out across the CRM and RMS data in one place, within the limits LEG-002 leaves open.

## Read first
- `docs/requirements/08-dev-decisions.md`: **M1, M7**, and B4, D5, I1, I6, I7, J2, L4, L6, L9
- `07-three-system-integration-contract.md` §8 (personal data map, subject requests paragraph)
- `prototype/crm_index.html`: `v-privacy` (the subject-requests table and its hint)
- The retention command (I7), `GuardCrmSensitiveData`, the merge code (aliases), task 02's register and gate, task 04's `RaiseTask`

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Permission and routes.** New permission `privacy.manage` (Admin only by default). Routes under `/api/privacy` (Sanctum, `privacy.manage`), with their own controllers under `App\Http\Controllers\Privacy` and resources under `App\Http\Resources\Privacy`. They are outside the CRM routes on purpose (M7): the access export reads RMS tables the CRM may not. Extend the arch tests: CRM controllers may not use `App\Actions\Privacy`, and privacy controllers may not be reached from `/api/crm`.
2. **Schema.** `subject_requests`: `contact_id`, `type` (ACCESS, ERASURE, RECTIFICATION, OBJECTION), `received_at`, `due_at` (received + `privacy.request_sla_days`, calendar days), `channel` (how it arrived: EMAIL, PHONE, LETTER, IN_PERSON), `verified_how` (text, required before completion — how the requester's identity was checked), `status` (OPEN, COMPLETED, REJECTED), `completed_at`, `completed_by`, `outcome` (text), `export_path` (nullable, private storage), audit columns. History on every change. Creating one raises a SUBJECT_REQUEST task (task 04, key `subject:{id}`, owner = creator, needs `privacy.manage`, due = `due_at`), auto-closed when the request closes.
3. **Shape change.** `privacy.request_sla_days` (30, PENDING LEG-002), usual procedure, registry counts.
4. **Endpoints.**
   - `GET /api/privacy/requests` (filters type, status, overdue) and `GET …/{id}`; `POST /api/privacy/requests` (contact, type, received_at, channel, notes).
   - **Access** — `POST …/{id}/export` builds a JSON document (and a ZIP containing it) into private storage: the contact's owned and derived fields; the consent register and the I6 rows for their bookings; bookings where they are client of record or group coordinator (reference, dates, status, charges total, payments as kind, amount, date and status — no gateway ids); documents issued (kind, version, number, date) and deliveries (kind, status, date); contact activities; tasks about them (titles and outcomes); behavioural events stitched to them; merge log entries that involve them. **No passenger records** (M7): the export states that passenger data held for travel is provided separately by the controller pending LEG-002. `GET …/{id}/export` downloads it. The file is deleted 30 days after completion by the retention command.
   - **Rectification** — completes with an outcome that names the fields changed; the change itself is made through the normal contact edit (`PATCH /api/crm/contacts/{contact}`) and is linked by id.
   - **Objection** — completing records MARKETING, PROFILING and REMARKETING withdrawn in the register (capture point SUBJECT_REQUEST, `recorded_by` the user) through task 02's `RecordContactConsent`. Effective at the next gate check (M2). Transactional messages continue.
   - **Erasure** — `POST …/{id}/erase` with a typed confirmation (the contact's email). Refused with 409 and a sentence when the contact has a booking whose return date has not passed, or an open refund request. Otherwise, in one transaction: name becomes "Erased contact #{id}", email, phone, `phone_e164`, first and last touch and country are cleared; language and type stay; behavioural events for the contact and its stitched sessions are deleted; contact activities are replaced by one line "Erased on {date}"; a row in `erasure_log` keeps the contact id, the SHA-256 of the normalised email, when and who. Kept, and said so in the outcome: issued documents and their snapshots, payments, the I6 consent log and the register (legal obligation, seven years), bookings (they reference the contact id). Aliases pointing at the contact keep resolving to it. Guests on their bookings are not touched here — the I7 retention job already anonymises them on schedule; the outcome says when.
   - `POST …/{id}/reject` with an outcome.
5. **Guard.** Every privacy response still passes `GuardCrmSensitiveData`; the export file is the only place RMS booking detail appears, and it never contains a passport number, date of birth, nationality or note.
6. **Timeline.** Subject request received and closed (type and outcome sentence, no export content) appear on the contact timeline.

## Don't
- Don't erase or alter an issued document, a payment or a consent row.
- Don't put passenger data in the export.
- Don't let any `/api/crm` route reach these actions.

## Checks
- `composer check`; `config-verify` before and after.
- Due dates and the task; overdue filter.
- Export content for a contact with bookings, payments, documents, consents and events — and the absence of every sensitive key (walk the JSON).
- Erasure refused with a future booking and with an open refund; allowed after; what changed and what did not (row counts and field values); a later booking by the same email creates a new contact and does not inherit consent.
- Objection withdraws the three purposes; the gate returns false.
- Permissions: Manager and Sales Exec get 403 on every privacy route.

## Report
Append **Task 05**: the permission and route split and why, each request type as built, what erasure keeps and removes, the export contents and exclusions, the shape change and counts. Git commands listed, not run.
