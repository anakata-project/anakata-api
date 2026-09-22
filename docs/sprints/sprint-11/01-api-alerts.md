# Task 01 · anakata-api · Alerts
**Repo:** anakata-api · **Sprint:** 11 (read `README.md` in this folder first)
**Needs:** the README's "Before task 01" done.

## Goal
One inbox for everything that needs a person's attention, raised by the facts that cause it and resolved by the facts that clear it (N1), so no alert depends on someone visiting the right module.

## Read first
- `docs/requirements/08-dev-decisions.md`: **N1**, and D4, D5, G5, H5, H6, J5, M6
- doc 06 backlog item 2 (alerts inbox)
- The Sprint 10 task code (`RaiseTask`, `CloseTask`, `anakata:crm-tasks`, the task kinds registry) — alerts follow the same shape; the Graph mailer and `deliveries` (J5)

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Schema.** `alerts`: `kind` (enum `AlertKind`), `severity` (INFO, WARN, CRITICAL), `title`, `sentence` (built in the API), subject columns (`booking_id`, `departure_id`, `agency_id`, `payment_id`, `delivery_id`, `guest_response_id`, all nullable), `crm_task_id` (nullable), `idempotency_key` (unique), `raised_at`, `acknowledged_at`, `acknowledged_by`, `resolved_at`, `resolution` (text), `emailed_at` (nullable). No deletes; audit columns.
2. **The registry.** A PHP registry of kinds, one entry each: severity, audience (permissions — a user sees the alert if they hold any), the condition in words, the resolving fact, and whether it emails. This task ships the kinds whose facts exist today:

   | Kind | Severity | Audience | Raised when | Resolves when |
   |---|---|---|---|---|
   | OVERDUE_BALANCE | WARN | `bookings.overdue_decision` | the OVERDUE flag becomes true (day 1) | the flag clears |
   | COMMISSION_CAP | WARN | `commissions.override_cap` | a booking is held at ON_HOLD_AGENCY | it leaves ON_HOLD_AGENCY |
   | WIRE_NOT_RECEIVED | WARN | `payments.mark_wire_received` | an AWAITING_WIRE payment passes its window | it is received or released |
   | SLA_BREACH | WARN | `records.act_on_any` | an open system CRM task passes its due time | the task closes |
   | DELIVERY_FAILED | WARN | `sync.retry` | a delivery is FAILED or BLOCKED | a later delivery of the same document is SENT |
   | LOW_OCCUPANCY | INFO | `departures.manage` | added in task 02 | — |
   | CONFIRMED_AT_DEPARTURE, LEDGER_DRIFT, COMMISSION_LEAKAGE | CRITICAL / WARN | — | added in task 02 | — |
   | MANIFEST_DATA_OVERDUE | — | — | added in task 03 | — |
   | NPS_LOW | — | — | added in task 05 | — |

3. **Actions.** `RaiseAlert` (idempotent on the key; raising an already-resolved key opens a new row with a suffixed key only when the condition returns after resolution) and `ResolveAlert`. Raised by queued listeners where an event exists and by one sweep, `anakata:alerts` every five minutes (registered in `AnakataSchedule`, with the run hooks), which raises missed alerts from current state and resolves those whose condition cleared, with the fact in `resolution`. Link `crm_task_id` when a task with the matching key exists.
4. **Email for CRITICAL.** Once per alert per recipient, to the active users of its audience, English, through the existing Graph mailer. Staff emails are not customer deliveries (`deliveries` requires a booking, J5), so record them in `alert_notifications` (`alert_id`, `user_id`, `status` SENT / FAILED, `error`, `sent_at`; unique on alert and user — the idempotency guard). A failed send is retried by the sweep once, then left FAILED and shown on the alert. No email for INFO or WARN. The email carries the title, the sentence and a panel link — nothing else.
5. **Endpoints** (Sanctum; every user sees only alerts whose audience they are in):
   - `GET /api/alerts?state=open|acknowledged|resolved&severity=&kind=&section=rms|crm` — newest first, paginated; each row with the registry's label, the subject reference and its panel deep link, the linked task, and whether the user may acknowledge. `meta.counts` by severity for open alerts (the topbar badge).
   - `POST /api/alerts/{alert}/acknowledge` — hides it from the open list for everyone; does not resolve it; history.
   - `GET /api/alerts/kinds` — the registry, served so the panel copies nothing.
6. **Separation.** Alert code never changes a booking, payment, task or delivery (assert row counts in a test). The CRM section's view of alerts (`section=crm`) contains no sensitive field (walk the resource).

## Don't
- Don't send email for anything but CRITICAL.
- Don't resolve an alert by acknowledging it.
- Don't duplicate the task logic; link to the task.

## Checks
- `composer check`.
- Each shipped kind: raised by its event and by the sweep when the listener did not run; resolved when the fact clears; audience filtering per demo user.
- Replaying the sweep raises nothing new; a condition that returns after resolution raises a new alert.
- A CRITICAL fixture emails each audience user once (the mail fake), with `alert_notifications` rows; a re-run sends nothing.
- The no-side-effect test; the sensitive-field walk.

## Report
Create `docs/sprints/sprint-11/REPORT.md` with the heading `# Sprint 11 · Report`, then append **Task 01**: the schema, the registry as built, the sweep, email, endpoints, and the no-side-effect test. Git commands listed, not run.
