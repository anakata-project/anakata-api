# Task 03 · anakata-api · Deals and the pipeline
**Repo:** anakata-api · **Sprint:** 10 · **Needs:** task 02.

## Goal
The sales pipeline as doc 07 §5 defines it: deals the sales team moves through stages 1–4, a projection of the booking status for stages 5–7 that nobody can drag, LOST with a reason, and cash and forecast figures computed from the ledger.

## Read first
- `docs/requirements/08-dev-decisions.md`: **M4, M5**, and A4, B5, B9, D4, D5, G2, G5, H5, I9, L1, L9
- `07-three-system-integration-contract.md` §3 (Deal & pipeline row), §4.4 (`booking.created`, `booking.status_changed`, `payment.received`, `hold.expired`, `cruise.completed`), §5, §10 rules 2 and 7
- `prototype/crm_index.html`: `STAGES`, `STAGEMAP`, `DEALS`, `cashFromLedger`, `renderCash`, `renderPipe` (who may drop where, the LOST prompt), `openDeal`
- `Booking`, `Group`, `CharterEnquiry`, `BookingStatusChanged`, the Payments & Revenue KPI queries (Sprint 5), the charges SQL (I9), `BusinessTime`, `Permission::PipelineMoveStage`, `Permission::RecordsActOnAny`

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Schema.** `deals`: `contact_id`, `owner_id` (user, nullable — unassigned), `title`, `type` (enum `DealType`: FIT, GROUP, CHARTER, AGENCY), `stage` (enum `DealStage` stored only for NEW_LEAD, QUALIFYING, QUOTED, NEGOTIATION, LOST; null once bound unless LOST), `stage_entered_at`, `estimate` (integer USD, nullable), `lost_reason` (nullable, required for LOST), `booking_id` / `group_id` (nullable, at most one set), `charter_enquiry_id` (nullable), `notes` (nullable), audit columns. Add `deals` to the contact-bearing tables for merge and unmerge.
2. **Stage projection (M4)** — one SQL `CASE` in `DealStages`, used by every list and KPI:
   - unbound and stored LOST → LOST;
   - bound, every bound booking CANCELLED, CANCELLED_POSTPAID or RELEASED → LOST (computed, "by the RMS");
   - bound, any booking COMPLETED → WON_COMPLETED;
   - bound, any CONFIRMED, FULLY_PAID, ON_BOARD (or the OVERDUE flag on those) → BOOKING_CONFIRMED;
   - bound, any REQUESTED, PENDING_PAYMENT or ON_HOLD_AGENCY → DEPOSIT_PENDING;
   - otherwise the stored stage.
   Test every branch, and a group with bookings in mixed statuses (the most advanced live booking decides).
3. **Value.** Unbound: `estimate`, labelled "CRM ESTIMATE". Bound: the sum of the bound bookings' charges total from the I9 SQL, labelled "FROM RMS"; cancelled bookings count zero. Never stored.
4. **Creation and binding.**
   - A charter enquiry opens a NEW_LEAD deal (type CHARTER, unassigned), linked to the enquiry. Idempotent per enquiry.
   - A new booking (or a group, for several cabins) binds to the contact's single open deal in stages 1–4; with none or more than one, it opens a new bound deal (type from the booking: CHARTER, AGENCY when an agency is set, GROUP for a group, else FIT; owner = the booking owner). Idempotent per booking or group. Use a queued listener on the booking-created event; if creation does not dispatch one, add `BookingCreated` (after commit) — no other behaviour changes.
   - `POST /api/crm/deals` — staff create an unbound deal: contact, title, type, estimate, stage (1–4 only), owner defaults to the user.
   - `POST /api/crm/deals/{deal}/assign` — a user with `pipeline.move_stage` takes an unassigned deal; `records.act_on_any` assigns any deal to any active user. History `deal.assigned`.
   - `POST /api/crm/deals/{deal}/bind` — bind to a booking or group of the same contact (aliases resolved), only while unbound.
5. **Moving.** `PATCH /api/crm/deals/{deal}/stage` with `stage` and, for LOST, `reason`. Allowed only when: the user has `pipeline.move_stage`; the deal is theirs or they have `records.act_on_any` (an unassigned deal must be taken first); the deal is not bound (a bound deal follows its bookings entirely — if the prospect walks away, the request is released in the RMS and the deal becomes LOST from that; doc 07 §10 rule 7, the RMS wins); the target is NEW_LEAD, QUALIFYING, QUOTED, NEGOTIATION or LOST. Anything else is 422 with a sentence saying where to act instead ("This deal follows booking ANK-… — change it in the RMS."). Reopening a LOST unbound deal to a stage 1–4 is allowed with a reason. History `deal.stage_changed` with from, to and reason.
6. **SLA state per deal**, computed in the API from `stage_entered_at` and business hours: NEW_LEAD `crm.pipeline.sla_new_lead_business_hours` (4), QUALIFYING `crm.pipeline.sla_qualifying_business_days` (5), QUOTED `sla.response_hours` (the existing OPS-009 rule), NEGOTIATION `crm.pipeline.sla_negotiation_business_days` (7); `ok`, `warn` (last 25% of the window), `bad` (breached). Bound stages have no CRM SLA ("SYSTEM-SET").
7. **Probabilities.** `crm.pipeline.probability_new_lead` 5, `_qualifying` 15, `_quoted` 35, `_negotiation` 55, `_deposit_pending` 80 (percent, PENDING CLIENT); BOOKING_CONFIRMED and WON count 100, LOST 0 (code, not rules). Shape change for these and the SLA rules with the usual procedure (DML migration, `config-verify` before and after, registry rows and counts, BR fixtures).
8. **Endpoints** (`panel.crm`, the sensitive-data guard; every CRM user sees every deal, L1):
   - `GET /api/crm/pipeline` — the eight columns in order, each with its label, owner (CRM / RMS / RMS or CRM), SLA text or "SYSTEM-SET", the deals (id, title, contact name and id, type, owner, value and its label, SLA state, bound booking reference and status and departure date, whether the current user may move it), the column total, and the weighted total for stages 1–5. Filters `owner` (a user, `me` or `unassigned`), `type`, `q`.
   - `meta.kpis` (M5): collected, scheduled in, awaiting first payment, open pipeline (count and value of stages 1–5), weighted forecast, overdue — all computed in SQL. Collected, awaiting and overdue must equal Payments & Revenue's figures; a test compares them.
   - `GET /api/crm/pipeline/stage-map` — doc 07 §5 as a PHP registry (stage, owner, enters when, RMS statuses, leaves when), written as implemented.
   - `GET /api/crm/deals/{deal}` — the drawer: stage and owner, value and label, stage SLA, the bound booking summary (reference, status, departure, cabin, charges total, paid, balance — no guests, no payment rows), trade partner and applied offer codes when present, attribution (main channel, channel of origin, frozen UTM first touch), and the contact id.
9. **Timeline.** Deal created, bound, stage changed (stored stages) and marked lost appear on the contact timeline.
10. **Separation.** Deal code lives under `App\Actions\Crm` and `App\Support\Crm`; the arch test that forbids CRM controllers from using booking, payment, guest, extras and document actions still passes. Nothing here writes a booking.

## Don't
- Don't store stages 5–7, or a bound deal's value.
- Don't let a stage move change a booking, a hold or a payment.
- Don't compute a cash figure anywhere but the API's SQL.

## Checks
- `composer check`; `config-verify` before and after.
- Every projection branch; binding from a request, a manual reservation, a group and a charter; the open-deal rule (one, none, several).
- Moves: own vs other with and without `records.act_on_any`; bound deals refused with the RMS sentence; LOST without a reason refused.
- SLA states at the boundaries, across a weekend and a holiday.
- KPIs equal Payments & Revenue for the seeded ledger; the query count stays flat as deals grow.
- Merge and unmerge move deals.

## Report
Append **Task 03**: the schema, the projection and its tests, creation and binding, the move rules, SLA and probability rules with registry counts, the endpoints and the KPI comparison. Git commands listed, not run.
