# Task 04 · anakata-api · Agencies, commissions, the FIN-005 cap and `ON_HOLD_AGENCY`
**Repo:** anakata-api · **Sprint:** 5 · **Needs:** task 02.

## Goal
Travel-trade bookings carry an agency and a commission, frozen at sale. A commission above the configured cap blocks the booking at `ON_HOLD_AGENCY` until someone authorised approves it. Agency registrations are reviewed against a 2-business-day SLA. Commission accrues on the booking and becomes payable 30 days after the cruise.

## Read first
- `docs/requirements/08-dev-decisions.md`: **H4, H8, H10**, and G4
- `02-data-model.md` → Agency (§5.5); `01-functional-spec.md` §7 (B2B & Agent Portal)
- `03-business-rules.md`: FIN-005 (cap 12 %, above → Director approval, booking blocked), §10 (approval SLA 2 business days)
- `prototype/rms_index.html`: `AGENCIES`, `agStats`, `renderB2B` (the four KPIs, the registration table with its SLA chip, the partners table), `agDecide`, `agRegister`, `agSave`, `openAgency` (the agency panel and its portal preview), `isTradeMain`, and `saveNew`'s `x.comm` (`{agency, rate, approved}`) plus its blocked branch (`ON_HOLD_AGENCY` and the FIN-005 alert wording)
- `app/Support/BusinessHours.php` (the SLA is business days), `app/Support/Bookings/Transitions.php`

## Do
1. **`agencies` table and model.** `reference` (`AG-NNN`, the `ReferenceType::Agency` that already exists), name, contact, email, country, network, `commission_pct`, `payment_terms`, `status` (`PENDING · APPROVED · REJECTED`), `requested_at`, `decided_at`, `decided_by`, `decision_reason`, audit columns. Model `Agency`, morph alias `agency`, `historyLabel()` = reference.
   - `agency_users` (name, email, status `INVITED · ACTIVE · DISABLED`) as a small child table. The portal itself is a later sprint; this stores who would be invited.
2. **Registration and decision.**
   - `POST /api/rms/agencies` — staff register on an agency's behalf (`agencies.manage`), or the engine will later. Status `PENDING`, `requested_at` now. History `agency.registered`.
   - `GET /api/rms/agencies` — filters `status`, `q`; each row carries `sla_business_days_elapsed` and `sla_breached` computed with `BusinessHours` against `sla.agency_approval_business_days` (2), never a literal. Same helper the request queue uses; do not reimplement business days in a second place.
   - `POST /api/rms/agencies/{agency}/decide` `{ decision: APPROVED|REJECTED, reason? }` — `agencies.manage`. A rejection requires a reason (prototype prompts for one and sends it to the agency). Approval marks the agency users `INVITED`. History `agency.approved` / `agency.rejected` with the reason.
   - `PATCH /api/rms/agencies/{agency}` — name, contact, network, payment terms, `commission_pct`. Changing the rate above the cap is allowed on the agency record (it is a negotiation), but it never silently changes any existing booking — commissions are frozen (H8).
3. **Commission on a booking (H8).** Columns on `bookings`: `agency_id` (nullable FK), `commission_pct` (nullable), `commission_approved` (bool), `commission_approved_by`, `commission_approved_at`, `commission_reason`.
   - Set at creation (task 10 adds the panel fields; `CreateReservation` accepts `agency_id` and `commission_pct` now). Defaults come from the agency, whose own default comes from `commission.default_pct` (10).
   - An agency is only accepted when the main channel is a trade channel (`MainChannel::isTrade()`), matching the prototype's `isTradeMain` gate. Otherwise 422.
   - Frozen like the price: later changes to the agency's rate never touch a sold booking.
   - `commission_amount` is derived: `round(total × commission_pct / 100)`, recomputed if the total changes on a move (G4) — record that this follows the price rather than being frozen separately.
4. **The cap (FIN-005).**
   - `commission_pct > commission.cap_pct` → the booking is created at **`ON_HOLD_AGENCY`** instead of PENDING_PAYMENT, `commission_approved = false`. It still holds its cabin (a `BOOKING` claim, G9): the sale exists, the commission is what is blocked.
   - `Transitions` gains `ON_HOLD_AGENCY → CONFIRMED, RELEASED` (the prototype's table) and **nothing else leaves it**. A blocked booking cannot reach CONFIRMED while `commission_approved` is false, even if the money settles: `ApplyPaymentEffects` records the payment, skips the transition, and writes one history entry saying why ("Deposit settled — CONFIRMED blocked: commission 15 % above the 12 % cap (FIN-005)"). Add that branch in task 02's class, not a second decision point.
   - `POST /api/rms/bookings/{booking}/commission-approval` `{ approve: bool, reason }` — permission `commissions.override_cap`, reason mandatory. Approving sets the flag, writes `booking.commission_approved`, and then re-runs `ApplyPaymentEffects` so a booking whose deposit already settled confirms immediately. Rejecting leaves it on hold and records the reason.
   - History wording from the prototype's System line: "HELD — commission 15 % above 12 % cap · Director alert sent (FIN-005)".
5. **Accrual.** `GET /api/rms/commissions?from&to&status` — rows of booking, agency, rate, amount, payable date (`departure date + commission.payable_days_after_cruise`), and a status: `ACCRUED` (booking not yet COMPLETED), `PAYABLE` (COMPLETED and the payable date passed), `BLOCKED` (over cap, unapproved), `CANCELLED` (the booking is cancelled). No payment of commissions this sprint — record that paying them out is a later sprint, and that the list is the accrual view the prototype shows.
   - Permission `bookings.view_all`.
   - `GET /api/rms/agencies/{agency}` returns the agency with its bookings, revenue and accrued commission — the prototype's `agStats` — plus the fields the portal preview needs (net rate = public − commission), with a note in the REPORT that agents never see public prices.
6. **Seed (local/testing).** The prototype's agencies: at least one APPROVED at 10 % with `ANK-2026-0007` attached (its channel is already AGENCY → B2B), and one PENDING registration whose SLA is already breached, so the panel has both states. One blocked example at a rate above the cap, attached to a booking at `ON_HOLD_AGENCY`, so task 09 and the E2E have one. Idempotent, values from the config document.

## Don't
- Don't build the agent portal, its login, or net-rate pricing for agents (later sprint). Only the preview data.
- Don't pay or settle commissions; there is no commission ledger this sprint.
- Don't let an approved cap override change the stored `commission_pct` — approval approves the rate that was sold.

## Checks
- `composer check`.
- SLA: an agency registered three business days ago is breached; two business days is the boundary; holidays and weekends come from the same business-hours config.
- A booking at 10 % via a trade channel accrues; the same rate on a D2C channel is 422.
- At 15 %: created `ON_HOLD_AGENCY`, holds its cabin, a settled deposit does **not** confirm it and writes the explanation entry; approval with a reason then confirms it in one step; rejection leaves it on hold.
- An agency rate change afterwards does not alter the booking's commission.
- A move that changes the total changes `commission_amount` and nothing else.

## Report
Append **Task 04**: the agency model and SLA, the freezing rule, the cap behaviour and its exact history wording, where `ApplyPaymentEffects` refuses, the accrual statuses, and what the agent portal still owes. Git commands listed, not run.
