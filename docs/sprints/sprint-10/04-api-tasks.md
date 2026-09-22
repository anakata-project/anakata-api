# Task 04 · anakata-api · Tasks and SLAs
**Repo:** anakata-api · **Sprint:** 10 · **Needs:** task 03.

## Goal
Every SLA in the commercial process becomes a task with an owner and a due time, raised by the system when the RMS state calls for it and closed by the system when the state clears — so nothing depends on someone remembering, and no task ever changes a booking.

## Read first
- `docs/requirements/08-dev-decisions.md`: **M6**, and A4, D4, D5, G5, H5, H6, H8, H9, J5 (idempotency keys), L1
- `07-three-system-integration-contract.md` §3 (Tasks row), §4.4 (`payment.overdue`, `commission.cap_breach`, `refund.issued`), §10 rule 6
- `prototype/crm_index.html`: `v-tasks` (notice), `TASKS` (due, priority, title, context, owner, source, action), `renderTasks` (who sees what), `doneTask`
- Business rules `sla.response_hours`, `sla.refund_business_days`, `payments.wire_window_hours`; `BusinessTime`; the OVERDUE flag and `anakata:flag-overdue`; `RefundRequest`; `CharterEnquiry`; AWAITING_WIRE payments; ON_HOLD_AGENCY bookings; `scheduled_runs`

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Schema.** `crm_tasks`: `title`, `context` (short sentence built in the API), `contact_id` (nullable), `deal_id`, `booking_id`, `charter_enquiry_id`, `refund_request_id`, `payment_id`, `subject_request_id` (all nullable; task 05 fills the last), `owner_id` (nullable — unassigned), `needs_permission` (nullable `Permission`), `due_at`, `source` (SYSTEM or USER), `kind` (enum `TaskKind`: the kinds below, MANUAL, and SUBJECT_REQUEST, which task 05 raises), `idempotency_key` (unique, nullable for manual), `status` (OPEN, DONE, AUTO_CLOSED, CANCELLED), `closed_at`, `closed_by`, `outcome` (text; required for DONE and CANCELLED), audit columns. Add to the contact-bearing tables for merge and unmerge.
2. **Contact activities.** `contact_activities`: `contact_id`, `kind` (CALL, EMAIL, MEETING, NOTE, TASK_COMPLETED), `body` (text, max 2,000), `occurred_at`, `deal_id` / `crm_task_id` (nullable), audit columns. `POST /api/crm/contacts/{contact}/activities` (`contacts.manage`). A validation rule rejects bodies that look like a passport number or a date of birth, with a sentence saying these belong in the RMS; the response and the guard never return anything sensitive. Shown on the timeline; included in merge and unmerge.
3. **System tasks (M6).** One action `RaiseTask` (idempotent on the key; a second raise with the same key is a no-op) and one `CloseTask`. Raised by queued listeners where an event exists, and by the sweep in step 4 for everything time-based. Add small after-commit events where creation does not dispatch one today (`CharterEnquiryReceived`, `RefundRequested`) — no other behaviour changes.

   | Kind | Raised when | Key | Due | Owner · needs | Auto-closes when |
   |---|---|---|---|---|---|
   | REQUEST_RESPONSE | a booking enters REQUESTED | `request:{booking}` | created + `sla.response_hours` business hours | booking owner | the booking leaves REQUESTED |
   | CHARTER_QUOTE | a charter enquiry is received | `charter:{enquiry}` | received + `sla.response_hours` business hours | the enquiry's deal owner, or unassigned | the enquiry leaves NEW |
   | OVERDUE_DECISION | the OVERDUE flag becomes true (sweep) | `overdue:{booking}:{due date}` | the Galápagos day it was flagged | booking owner · `bookings.overdue_decision` | the flag clears (paid, or the decision recorded) |
   | COMMISSION_CAP | a booking is held at ON_HOLD_AGENCY | `cap:{booking}` | + 1 business day | booking owner · `commissions.override_cap` | the booking leaves ON_HOLD_AGENCY |
   | WIRE_WINDOW | a payment is AWAITING_WIRE | `wire:{payment}` | the end of its window | booking owner · `payments.mark_wire_received` | the payment is received or released |
   | REFUND_DECISION | a refund request is created | `refund:{request}` | + `sla.refund_business_days` business days | booking owner · `refunds.approve` | the request is approved, declined or executed |
   | DEAL_QUOTE | a deal enters QUOTED | `deal-quote:{deal}:{entered_at}` | + `sla.response_hours` | deal owner | the deal leaves QUOTED |

   The context line names the reference, amount where relevant (read from the ledger or charges SQL) and the rule code (OPS-009, OPS-007, FIN-005…), the prototype's second line. Every task links to where the action happens (the RMS booking, the refund approval, the enquiry) — the task itself has no action on the booking.
4. **The sweep.** `anakata:crm-tasks` every five minutes, with the Sprint 9 `scheduled_runs` hooks (registered through task 01's schedule class). It raises the time-based kinds (OVERDUE_DECISION; any event-based kind whose event was missed, by scanning current state — so a lost listener cannot lose a task) and auto-closes every open system task whose condition has cleared, with outcome "Resolved in the RMS" and the fact that cleared it. It never writes anything but tasks.
5. **Manual tasks.** `POST /api/crm/tasks` (title, due, contact, optional deal, owner defaults to self; `records.act_on_any` may assign others). `PATCH /api/crm/tasks/{task}` for title, due and owner under the same rule.
6. **Completing.** `POST /api/crm/tasks/{task}/complete` with `outcome` (required), and `…/cancel` with `outcome` (manual tasks only; a system task that no longer applies closes itself). Allowed for the owner, holders of `needs_permission`, and `records.act_on_any`. Completing writes a TASK_COMPLETED contact activity with the outcome, and nothing else (M6, prototype hint). History `task.completed` / `task.cancelled`.
7. **Visibility.** `GET /api/crm/tasks?scope=mine|all|unassigned&status=open|closed&kind=&due=overdue|today|week` — `mine` includes tasks the user may act on through `needs_permission`; `all` needs `records.act_on_any`. Each row: due (UTC) and a priority computed in the API (`bad` overdue, `warn` due today in Galápagos, `ok` later), title, context, source label ("RMS · request.submitted + 24 h", "CRM · OPS-009 SLA timer", "Manual"), owner, needs permission, links (booking reference, contact, deal), and whether the user may complete it. `meta.kpis`: open tasks, SLA breached, near SLA, raised by the system, the quote SLA hours — the prototype's five, in SQL.
8. **Timeline.** Task raised (system), completed, auto-closed appear on the contact timeline.

## Don't
- Don't change a booking, a payment, a refund request or an enquiry from any task code path. OPS-007: nothing here cancels a booking.
- Don't raise a task twice for the same key, and don't reopen an auto-closed one.
- Don't send email from tasks; alerts are Sprint 11.

## Checks
- `composer check`.
- Each kind: raised once by its event, raised by the sweep when the listener did not run, due time across weekends and holidays, auto-closed when the RMS clears it.
- Replaying an event and re-running the sweep create nothing new.
- Visibility: Sales Exec sees their own plus tasks their permissions allow; Admin sees all; unassigned.
- Completing writes one activity and changes no other table (assert row counts on bookings, payments, refund requests, enquiries).
- The activity body rule rejects a passport-like string; the sensitive-field walk.

## Report
Append **Task 04**: the schema, the kinds table as built, the events added, the sweep and its schedule, visibility, completion and the no-side-effect test. Git commands listed, not run.
