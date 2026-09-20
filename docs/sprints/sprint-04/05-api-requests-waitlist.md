# Task 05 · anakata-api · Requests and their holds, the waitlist
**Repo:** anakata-api · **Sprint:** 4 (read `README.md` in this folder first)
**Needs:** task 04.

## Goal
A request is a booking in REQUESTED that holds its cabin with a business-hours expiry and carries its request details: preferred channel, advisor flag, notes and the contact SLA.
- Staff confirm it (→ PENDING_PAYMENT) or release it with a reason.
- An expired hold frees the cabin automatically (recorded by System), but **the request itself is never cancelled automatically**: it stays in the queue, marked "hold expired", for the team to confirm or release (prototype notice, G9).
- The waitlist records who wants a full departure, first in, first out.

## Read first
- `docs/requirements/08-dev-decisions.md`: **G3, G5, G7, G9**
- `01-functional-spec.md` §1 (Booking Requests) and §9 (Holds & Waitlist); `03-business-rules.md` OPS-009, TEC-004, R-B5
- `prototype/rms_index.html`:
  - `v-req` and `renderReq` (the columns and the strings "VIA WHATSAPP", "TRAVEL ADVISOR", "46 business hours", "SLA BREACH — 26h")
  - `confirmReq`, `releaseReq`
  - `v-hold` (the holds and waitlist tables)
- `app/Support/BusinessHours.php` (task 02), `app/Services/Inventory/ClaimService.php`, `app/Actions/Bookings/TransitionBooking.php` (task 04)

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Request details:** table `booking_requests`, 1:1 with `bookings`:
   - `booking_id` (unique), `preferred_channel` (`EMAIL` / `WHATSAPP` / `PHONE`), `travel_advisor` (bool), `notes`
   - `submitted_at` (timestamp), `sla_due_at` (= submitted + 24 **calendar** hours, OPS-009, from `sla.response_hours`)
   - `hold_rule` (`NEAR_TERM` / `LONG_LEAD`)
   - audit, timestamps
2. **`CreateBookingRequest` Action.** The engine sprint's web endpoint and the seed call it; there is **no RMS endpoint** to create requests (the prototype has none).
   - Input as in task 03's create, one cabin, plus the request details.
   - One transaction:
     1. re-quote
     2. draw `ReferenceType::Request` → `request_reference`, with `reference` null (G3)
     3. status REQUESTED
     4. compute `BusinessHours::holdExpiry(now, departure date, rules)`
     5. `ClaimService::claim(... HOLD, HoldType::Request, expires_at)`
     6. write the request row
     7. history `booking.requested`
   - A conflict → 409, as for reservations.
3. **Expiry frees the cabin, not the request (G9).**
   - Add `hold_expired_at` (nullable timestamp) to `booking_requests`.
   - When `ClaimService` releases an expired HOLD (the job or the pre-insert cleanup), dispatch a **synchronous** event `HoldExpired($holder, $claim)` inside the same transaction.
   - A listener: if the holder is a Booking in REQUESTED, set `hold_expired_at` and write history `request.hold_expired` as System ("Hold expired (TEC-004) — cabin returned to inventory; the request stays open for review"). **Do not change the status.**
   - Notifying the owner: record it in the same history entry for now; the email arrives with the email sprint. Add `TODO(Sprint 7): notify the owner`.
   - This is deliberately in-transaction, not queued: the request's hold state must never disagree with the claim. Note it as a documented exception to A4 in `laravel.mdc`, next to the History section.
4. **Queue:** `GET /api/rms/requests` (`bookings.view_all` or own) with `from` / `to` filters. Rows:
   - `display_reference`, contact `{ name, preferred_channel }`, `travel_advisor`
   - party (prototype wording: "2 adults + 1 child · 1 cabin")
   - departure date · cabin label, estimated value (= total)
   - `hold: { expires_at, rule, remaining_business_minutes, expired: bool }` (from `BusinessHours`, so the panel can show "46 business hours" / "4 business days", or "Hold expired — cabin not held")
   - `sla: { due_at, remaining_minutes, breached: bool }`
   - `can_act`

   Ordered by SLA due, soonest first. The empty queue message comes from the panel.
   - `meta.rules`: `{ near_term_business_hours, long_lead_business_days, near_term_max_days, response_hours, business_day_minutes, cabin_deposit_pct }` from the published documents, so the panel's notice never hard-codes 48 / 5 / 24 / 10 % (Sales Execs can't read the business-rules endpoint).
5. **Actions on a request** (task 04's transition underneath, with the own-records rule):
   - `POST /api/rms/requests/{booking}/confirm` → REQUESTED → PENDING_PAYMENT (claims converted, or re-claimed after an expired hold, as in task 04; a taken cabin → 409), history "Status REQUESTED → PENDING PAYMENT · deposit link to be sent via WHATSAPP" (prototype wording, "to be sent" since payments are Sprint 5), with `TODO(Sprint 5): send the deposit link`.
   - `POST /api/rms/requests/{booking}/release` `{ reason }` (required) → RELEASED, claims released (if any are left), history `booking.released` with the client name.
6. **Holds list:** `GET /api/rms/holds`, the active HOLD claims grouped by holder:
   - `type` (REQUEST now; WEB and AGENCY show later through the same shape)
   - client / agent label
   - departure date · yacht, cabin label(s)
   - `expires_at`, `remaining_business_minutes`
   - `rule` text: "TEC-004 · 48 business hours (near-term)" / "TEC-004 · 5 business days (long-lead)" from `hold_rule` and the rules document
   - a link reference (the booking's display reference)
   - Ordered by expiry. Needs `panel.rms`.
7. **Waitlist (G7):** table `waitlist_entries`:
   - `id`, `departure_id`, `cabin_category` (`SUITE` / `OWNER`), `contact_id`, `adults`, `children`, `notes`
   - `notified_at`, `notified_by`, `notified_channel` (nullable)
   - `removed_at`, `removed_by`, `removed_reason` (nullable)
   - audit, timestamps

   Model `WaitlistEntry`, morph alias `waitlist_entry`.
   - `GET /api/rms/waitlist?from&to&departure_id&include_removed=0`. Rows: contact (name, email), departure, cabin type, **position** (FIFO by `created_at` among the active entries of that departure and category), since, notified info, and `cabin_available` (a cabin of that category is currently free on that departure, from `Availability`, so staff know when to act).
   - `POST /api/rms/waitlist` `{ departure_id, cabin_category, client, adults, children, notes? }` needs `bookings.create`. It is refused (422) when the departure's `waitlist_enabled` is false: "The waitlist is off for this departure."
   - `POST /{entry}/notify` `{ channel }` records `notified_*` and history `waitlist.notified`. **No email is sent in Sprint 4** (email arrives with the documents and email sprint); the panel button says "Mark notified". Note it as a deviation from the prototype's "Notify now".
   - `POST /{entry}/remove` `{ reason }`: soft remove, history.
   - History events: `waitlist.added`, `waitlist.notified`, `waitlist.removed`.
8. **Test helper command (local/testing only):** `php artisan inventory:expire-hold {reference}` sets the active hold of that booking (or block) to `expires_at = now − 1 minute`, then runs the release for it, so browser checks and e2e scenarios can see an expired hold without waiting days. It refuses to run outside `local` / `testing`. This is the one allowed way to force expiry; `db-check` stays read-only.
9. **Demo seed (local/testing):**
   - The two seed-data requests (`ANK-R-2026-0041` WhatsApp, 2 adults, "Anniversary on board"; `ANK-R-2026-0042` email, 2 + 1, travel advisor, the fore-cabin note).
   - Keep their references; `ensureAtLeast(Request, 2026, 42)`.
   - **Timestamps relative to now**, so the demo is always live: 0041 submitted 5 hours ago (SLA 19 h left); 0042 submitted 50 hours ago (SLA breached by 26 h).
   - Hold expiries are computed by `BusinessHours`, not copied from the seed strings.
   - The two prototype waitlist entries on the 19 Dec 2027 festive departures (Suite and Owner's Suite).
   - **Relative timestamps and a fixed departure year** mean the demo is valid until Nov 2027. Add that to the existing note about the demo dates.
10. **Tests:**
   - request creation: reference, hold claim with the business-hours expiry (near-term and long-lead), the request row, the SLA
   - the job expiring a request's hold → cabin free, request still REQUESTED with `hold_expired_at`, history by System, no audit row
   - the pre-insert cleanup path → the same result
   - confirming after expiry: re-claims when the cabin is free; 409 when someone else took it
   - confirm (claim converted, still `ANK-R`) and release (reason required, audit row)
   - own-records on both actions
   - holds list shape and rule texts
   - waitlist: add (and refused when disabled), positions after a removal, `cabin_available` toggling when a block is released, notify, remove, history
   - seed determinism: SLA values relative to a frozen now
   - `inventory:expire-hold`: works in testing, refuses in production
   - `meta.rules` values

## Out of scope
The web request endpoint and the web checkout hold (engine sprint). Agency holds (Sprint 5). Emails (documents and email sprint). Waitlist automation (backlog).

## Acceptance criteria
- [ ] After `migrate:fresh --seed`, `GET /api/rms/requests` shows the two requests: one within SLA, one breached, both with long-lead holds (their departures are more than 120 days away).
- [ ] Travelling past a request's hold expiry and running `inventory:release-expired-holds` frees the cabin (actor System). The request stays REQUESTED and shows "hold expired" in the queue. Confirming it afterwards re-claims the cabin.
- [ ] `composer check` passes; every new response is typed.
- [ ] A "Task 05" section in `REPORT.md` covering:
  - the in-transaction event exception and the "never auto-cancel" rule
  - the waitlist position rule
  - the "Mark notified" deviation
  - the demo timestamp rule
