# Task 04 · anakata-api · Triggers and the schedule; each document's status
**Repo:** anakata-api · **Sprint:** 7 · **Needs:** task 03.

## Goal
Documents are issued and sent when their trigger happens, and every booking can answer "what has been sent, what is scheduled, what is waiting, and why". This task wires events and a daily schedule to tasks 01–03, and computes each document's status from facts.

## Read first
- `docs/requirements/08-dev-decisions.md`: **J6, J7, J9**, and B9 ("at-least-once side effects via queued listeners"), H4 (the payment-driven transitions), I9 (cruise versus charges), I10
- `03-business-rules.md` §4.1.4 (reminders 21 and 7 days before the due date)
- `prototype/rms_index.html`: `docsFor` (every row: name, recipient, trigger wording, date, status) and `DSTC` (the statuses)
- `ApplyPaymentEffects`, `TransitionBooking`, `MarkWireReceived`, the Stripe webhook job, the booking-extras and fee-collection actions, `MoveBooking`

## Do
1. **Event triggers (J7)** — queued listeners, after commit, each calling `IssueDocument` then `SendDocument` with the idempotency keys from task 03:

   | Trigger | Documents |
   |---|---|
   | The booking reaches CONFIRMED (by a deposit, or by a manual transition) | Booking confirmation & invoice (v1) and booking summary |
   | A payment becomes settled (recorded, webhook, wire marked received, reconciliation apply) | Payment confirmation for that payment |
   | The booking reaches FULLY_PAID (by money, or manually) | Final invoice |
   | After the invoice was issued, the charges change: an extra added or removed, fee collection switched, a move that reprices, a guest change that changes a collected PNG fee | A new version of the current invoice, reason from the change ("Extra added — Domestic flights × 2"), sent to the client |

   - Raise small domain events from the existing actions (`BookingStatusChanged`, `PaymentSettled`, `BookingChargesChanged`) rather than listening to history entries. The actions already know what happened; history is a record, not a bus.
   - A confirmed booking whose invoice is re-issued twice in one request produces **one** new version: the listener re-reads the booking, compares the snapshot's totals with the latest version's, and issues only when something on the invoice actually changed.
   - No invoice is issued for REQUESTED, PENDING_PAYMENT or ON_HOLD_AGENCY bookings. A cancelled booking gets no automatic document; its refund is handled by Refund Approvals.
2. **The daily schedule** — `anakata:documents-due`, daily in the Galápagos timezone (the Sprint 5 rule), idempotent through the delivery keys:
   - **Balance reminders:** for each CONFIRMED or ON_HOLD_AGENCY booking with a **cruise balance** (I9) open, on `balance_due_date − N` for each N in `payments.balance_reminder_days`. The reminder states the cruise balance and the due date. Once the cruise balance is paid, no reminder is sent — the prototype's "NOT NEEDED". Reminders follow the effective due date, so an OPS-007 extension moves them.
   - **Pre-trip itinerary:** at T−45 (departure date − 45 days) for confirmed-or-later bookings. Put 45 in the business rules as `documents.pretrip_days_before` via this task's shape change (next point) rather than as a literal.
   - **Transfer voucher:** at T−7, only when a transfer-triggering extra exists; `documents.voucher_days_before` likewise.
   - Missed days: the command sends anything whose date has passed and was never sent, as long as the booking still qualifies — so a day the scheduler did not run is caught up, and a reminder whose due date has already passed is not sent late (the balance is then OPS-007's business, not a reminder's).
   - `--dry-run` lists what it would send.
3. **Shape change.** `documents.pretrip_days_before` (45) and `documents.voucher_days_before` (7) in the business rules, CONFIRMED, same procedure as before; registry counts updated.
4. **Each document's status (J9)** — `App\Support\Documents\DocumentPlan::for(Booking)` returns the prototype's list, one row per document: `kind`, `name`, `recipient` (the resolved address or the reason there is none), `trigger` (prototype wording, with live dates and days), `date`, `status`, `document_id` / `version` when issued, `delivery_id` when sent, and `can_preview` / `can_issue` / `can_resend`.
   - Statuses, computed from facts, never stored: **SENT** (a delivery succeeded), **FAILED** (the last delivery failed), **BLOCKED** (no recipient), **SCHEDULED** (the trigger date is in the future and the booking qualifies), **WAITING** (the condition is not met yet, e.g. no deposit), **NOT NEEDED** (e.g. reminders once the cruise balance is paid), **NOT CONTRACTED** (the voucher without a transfer extra), **DUE** (the date has passed and the job has not sent it yet).
   - The preferences questionnaire row is listed as WAITING with "Arrives in Sprint 11", so the list matches the prototype without pretending it works.
   - Wire instructions are sent by hand (task 03), never on a trigger. They appear in the plan only once issued, as SENT or FAILED, so staff can see them next to the other documents.
   - `GET /api/rms/bookings/{booking}/documents/plan` — the list. `BookingPolicy::view`.
   - `GET /api/rms/documents?from&to&kind&status&q` — client documents across bookings for Documents & Manifests: one row per planned document per booking in the window (departure date), visibility as the bookings index, paginated. It uses the same `DocumentPlan`, so the two views can never disagree; add a query-count test and keep the per-booking work bounded (eager loads, no per-row queries).
5. **The seed never sends.** Task 02's seed issues documents for the demo bookings. Seeding must not fire the send listeners: dispatch the domain events only from the actions' normal path and have the seeders write the matching deliveries directly as `SENT` with their idempotency keys (and a `sent_at` from the seeded payment dates), so a fresh seed shows SENT rows and a later trigger for the same thing is correctly a no-op. Mailpit must be empty after `reset.sh`; add a test.
6. **Resources** with full PHPDoc, into `PanelResponseSchemasTest`.

## Don't
- Don't send from inside the writing transaction; after commit only.
- Don't send a reminder for extras or fees (their due date is shown, not chased — Sprint 11).
- Don't auto-issue anything on a cancelled, released or requested booking.

## Checks
- `composer check`.
- Each trigger fires once: a deposit confirming a booking sends invoice + summary + receipt, exactly one of each; the Stripe webhook delivered twice still sends one receipt; a manual CONFIRMED sends the invoice and summary.
- Adding an extra to a confirmed booking issues v2 with the reason and sends it; adding and removing the same extra in one request issues nothing new.
- Reminders with a travelled clock: day −22 nothing, day −21 one reminder, the same day again nothing, day −7 one more; cruise paid → none; OPS-007 extension moves them.
- Pre-trip at T−45 and the voucher at T−7, the voucher only with a transfer extra; a missed day is caught up.
- `DocumentPlan` statuses for each case, including BLOCKED (no email) and FAILED (a faked transport error).
- A fresh seed sends no email and shows the seeded documents as SENT.
- The client-documents list agrees with each booking's plan, and its query count stays flat.

## Report
Append **Task 04**: the trigger table and the domain events, the one-version-per-change rule, the schedule and its catch-up rule, the shape change, the status definitions, and the questionnaire placeholder. Git commands listed, not run.
