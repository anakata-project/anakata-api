# Task 08 · anakata-panel · Payments & Revenue
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 5 · **Needs:** tasks 06 and 07.

## Goal
The Commercial section's Payments & Revenue view: what has been collected, what is still coming, what is overdue, what commission has accrued, the full ledger, and the Stripe reconciliation.

## Read first
- `prototype/rms_index.html` → `v-pay` and `renderPay`: the five KPIs and their sub-labels, the "Pending payments — when the money arrives" table, "Commissions — earned, payable & blocked" with its FIN-005 note, the ledger table, and the reconciliation panel with its three KPIs and the wire note
- `01-functional-spec.md` §5
- Sprint 4 task 07's page structure (`DateRangeFilter`, stacked `.panel`s, the drawer-count pattern) — this page follows it exactly

## Do
1. **The page** replaces the placeholder at the Payments & Revenue route. One `DateRangeFilter` across every panel (noun: payments), applied as `from`/`to` to each call. The API filters on Galápagos calendar days; the panel never filters money client-side.
2. **KPIs** from `GET /api/rms/payments`'s `meta.kpis` — never summed in the panel:
   - Collected to date ("deposits + balances, all channels")
   - Of which deposits ("{cabin_deposit_pct}% cabins · {charter_deposit_pct}% charter", the percentages from the API's meta, not literals)
   - Pending payments ("{n} payments · due at T−{cabin_balance_days} per booking")
   - Overdue, in coral ("OPS-007 manual review — never auto-cancel")
   - Commission accrued ("payable {commission.payable_days_after_cruise} days post-cruise")
3. **Pending payments panel.** Rows from the bookings index with an outstanding balance (the API adds a `pending_payment=1` filter, or reuses `overdue` plus balance — whichever task 01/02 exposed; use it, do not re-derive). Columns: booking, client, segment pill, amount due, due date — and for PENDING_PAYMENT rows the wire window instead of the date, exactly as the prototype does. Status pill, with the OVERDUE pill from task 07. Row click opens the booking panel on its Payments tab.
4. **Commissions panel.** `GET /api/rms/commissions`. Columns: partner, booking, rate, commission, payable, status. Blocked rows (over the cap) render with the coral `BLOCKED >12% · Director approval (FIN-005)` pill and link to the booking so someone can approve it. The note under the table interpolates the cap and the payable days from the API.
5. **Ledger panel.** `GET /api/rms/payments`, paginated, newest first. Columns: date, booking, client, type, method, reference, amount, status. Refund amounts in coral with a minus. Awaiting-wire rows offer **Mark received** inline for `payments.mark_wire_received` (the same modal component as task 07 — one implementation, imported, not copied). Booking reference opens the booking panel on its Payments tab.
6. **Reconciliation panel.** `GET /api/rms/payments/reconciliation`:
   - Three KPIs: gateway transactions, matched to RMS, discrepancies (coral when non-zero).
   - A table of unmatched and to-review rows: gateway, id, date, amount, reference, description, and an **Apply to booking** action for `payments.record` — a modal that takes a booking (search by reference, the existing contact-search pattern) and a kind, then posts to the apply endpoint and refreshes both this panel and the ledger.
   - The wire note from the prototype, and the Stripe mode from the API's meta ("Stripe test mode" when that is what it is — say it plainly rather than implying live reconciliation).
7. **Permissions.** The whole view needs `bookings.view_all`; without it, the nav item is hidden (same `itemAllowed` mechanism as Booking Requests). Finance-only users (cfo@) see everything here and can mark wires; they cannot apply gateway rows unless they also hold `payments.record`. Record the matrix in the REPORT.
8. **Helpers, tested:** `paymentsKpiCards(meta)` (label/value/sub-label shaping, no arithmetic), `reconciliationTone(count)`, `pendingDueLabel(booking)` (wire window vs T−days, both from API fields).

## Don't
- Don't total anything in the panel. Every number on this page is an API number.
- Don't build reports or exports (the six required reports are a later sprint; note them in the REPORT as still owed).
- Don't show live-mode language while the API reports test mode.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build.
- Browser, both themes, after `reset.sh`: the five KPIs match the seeded ledger; the pending table lists `ANK-2026-0014` with its wire window and `ANK-2026-0018` as overdue; the commissions table shows the 10 % accrual and the blocked over-cap row; the ledger paginates; marking a wire received from the ledger updates the KPIs.
- Reconciliation with the seeded unmatched gateway row: applying it to a booking creates the payment and the discrepancy count drops to zero.
- As cfo@: the view is visible and Mark received works. As Lucía (no `bookings.view_all` in a custom test role): the nav item is hidden.

## Report
Append **Task 08**: the KPI sources, the permission matrix, what the reconciliation panel can and cannot do, and the reports still owed. Git commands listed, not run.
