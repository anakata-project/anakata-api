# Task 06 · anakata-api · Agent portal, RMS side
**Repo:** anakata-api · **Sprint:** 11 · **Needs:** task 05.

## Goal
Finish what the RMS needs before an agent-facing portal can exist (N9): agency users the team can manage, a preview of exactly what an agency will see, and commissions that go from earned to paid with an append-only payout. Fix the payable date.

## Read first
- `docs/requirements/08-dev-decisions.md`: **N9**, D4, D5, H1, H8 (commission frozen on the booking), FIN-005 in doc 03, doc 03 §10 (commission payable 30 days after cruise completion), doc 01 §5.5
- `prototype/rms_index.html`: `renderB2B` (KPIs, registrations, partners table, note), `openAgency` (users, portal preview: net rates by year, my bookings with net due, my commissions with payable date and status, sales materials)
- `Agency`, `AgencyUser`, `AgencyResource` (already lists users and bookings), `CommissionResource`, `App\Support\Commissions\Accrual`, `CommissionAccrualStatus`, the rates tables and the pricing engine's public per-person rates

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Fix the payable date.** `Accrual::payableDate` adds `commission.payable_days_after_cruise` to the **departure** date; doc 03 §10 and the prototype (`addDays(depDate, 37)`) count from the end of the cruise. Use the return date. Add a regression test and state the change in the report — every PAYABLE date shown so far was 7 days early.
2. **Statuses (N9).** `CommissionAccrualStatus` gains `EARNED_ON_COMPLETION` (rename of today's ACCRUED label in the API output only if the value is not stored anywhere — check; otherwise keep ACCRUED and map the label) and `PAID`. Order of precedence: CANCELLED, BLOCKED, PAID (a payout exists), PAYABLE (COMPLETED and payable date reached), EARNED_ON_COMPLETION. One place computes it.
3. **Payouts.** `commission_payouts`: `booking_id` (unique — one payout per booking; partial payouts are PENDING CLIENT, so refuse them), `amount` (integer USD, must equal the booking's commission amount from the frozen `commission_pct` and the charges SQL), `paid_on` (date), `bank_reference`, `recorded_by`, audit columns; triggers refuse update and delete. `POST /api/rms/commissions/{booking}/payout` with new permission `commissions.record_payout` (finance, Admin by default); only when the status is PAYABLE; history on the booking and the agency. It never changes the booking's figures or status. A correction is out of scope (say so; PENDING CLIENT).
4. **KPIs.** The agencies list `meta.kpis` adds `commission_payable` (sum of PAYABLE) and `commission_paid` (sum of payouts), and `commission_accrued` excludes PAID. All in SQL.
5. **Agency users.** `POST /api/rms/agencies/{agency}/users` (name, email; unique per agency) and `PATCH …/users/{user}` (name, status ACTIVE / DISABLED), `agencies.manage`. Status on creation is `INVITE_ON_PORTAL_LAUNCH` for an approved agency and `INVITE_ON_APPROVAL` otherwise; approving an agency moves its users to `INVITE_ON_PORTAL_LAUNCH`. **Nothing is emailed** (the portal does not exist, N10). History on the agency.
6. **Portal preview.** `GET /api/rms/agencies/{agency}/portal-preview` (`agencies.manage`), exactly what the agency will see and nothing more:
   - **net rates** per published year for Suite per person, Owner's Suite per person and Charter per week: public rate × (1 − agency commission %), rounded as the pricing engine rounds; the public rate itself is **not** in the response;
   - **my bookings**: reference, lead guest name, departure date, status, and net due (balance × (1 − commission %)) — no payment rows, no guest data beyond the name;
   - **my commissions**: reference, rate, commission amount, payable date, status;
   - **sales materials**: a fixed list with "assets pending upload".
   A test asserts that no public price appears anywhere in the payload.
7. **Audit.** Every change to an agency, its users, a cap decision and a payout is in change history (doc 06 "agent-portal actions logged").

## Don't
- Don't build the agent-facing portal, its authentication or its invitations.
- Don't allow partial or edited payouts.
- Don't change a booking's commission rate after sale.

## Checks
- `composer check`.
- The payable date regression; each status and the precedence; PAYABLE only on and after return + 30.
- Payout: permission, only PAYABLE, amount must match, one per booking, immutable, status becomes PAID, KPIs move.
- Users: create, disable, approval moves their status, nothing emailed.
- The preview: net rates for each year and product, no public price in the JSON, bookings and commissions scoped to the agency.

## Report
Append **Task 06**: the payable-date fix and its effect, statuses and precedence, payouts, KPIs, users, the preview and its no-public-price test. Git commands listed, not run.
