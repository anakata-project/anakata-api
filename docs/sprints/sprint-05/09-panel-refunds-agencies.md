# Task 09 · anakata-panel · Refund Approvals and B2B & Agent Portal
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 5 · **Needs:** tasks 06, 07 (it opens the booking panel).

## Goal
Two views: the Director's refund queue, and the agency list with its registrations, commissions and portal preview.

## Read first
- `prototype/rms_index.html`: `renderRefunds` and `approveRefund` (columns and wording), `v-b2b` / `renderB2B` (the four KPIs, the registration table with its SLA chip, the travel-trade partners table and its note), `agDecide`, `agRegister` / `agSave`, `openAgency` (the agency panel, its stats and the portal preview)
- `01-functional-spec.md` §7 and §10
- This sprint's REPORT tasks 04 and 05 for the API shapes and the exact internal wording

## Do
### Refund Approvals (Operations)
1. Page at the Refund Approvals route, `DateRangeFilter` on the cancellation date, `GET /api/rms/refunds`.
2. Table from the prototype: booking, cancelled, days before departure, band → penalty %, penalty, "USD {refund_due} of USD {paid} paid", SLA, action.
   - The band label comes from the API (task 05 builds it from the configured bands). Never a hard-coded "≥120 days".
   - SLA cell: business days remaining to `due_by`, breached in coral, using the same `.slat` classes as the request queue.
3. **Approve / Reject** for `refunds.approve` — `ReasonModal`, reason required for both, then refresh. A rejected request stays visible with its reason.
4. **Execute** for `refunds.execute`, on approved rows only: a modal with method, optional amount (prefilled with `refund_due`, cannot exceed it) and an optional external reference; the note says the refund itself is made in the payment platform and recorded here (task 05's decision — read it, don't restate it from memory). After executing, the row shows `EXECUTED` and the booking's ledger carries the negative row.
5. Status filter chips (Pending · Approved · Executed · Rejected). Empty states in the prototype's tone.
6. Row click opens the booking panel (Payments tab) so the money behind the request is one click away.

### B2B & Agent Portal (Commercial)
7. Page at the B2B route, `DateRangeFilter`, `GET /api/rms/agencies`.
8. **KPIs:** approved agencies ("portal access active"), registrations to review (warn tone when non-zero, "SLA: 2 business days (§10)" with the number from the API), agency revenue, commission accrued ("payable {n} days post-cruise").
9. **Registration requests panel**, only when there are pending rows: agency (with contact and email), network · country, requested date, SLA chip, commission asked, Approve / Reject buttons for `agencies.manage`. Rejection requires a reason (`ReasonModal`); approval says what happens next in the toast — an invite is recorded, not sent (the portal and its emails are later sprints). Say that plainly rather than implying an email went out.
10. **Travel-trade partners table:** agency, network, commission (with an over-cap pill), payment terms, bookings, revenue, commission accrued, status. Row opens an agency slideover.
11. **Agency slideover:** the stats, its bookings (each opening the booking panel), its users with their invite status, an edit form for `agencies.manage` (name, contact, network, payment terms, commission %), and the **portal preview** section: what the agent would see — net rates (public − commission), their bookings, commission history. Label it clearly as a preview of a portal that does not exist yet, and repeat the prototype's note that agents never see public prices and the client of record is always the end guest.
12. **＋ Register agency** for `agencies.manage`: the prototype's fields, commission prefilled from the API's default, over-cap entry allowed with a visible warning that bookings at that rate will be held for approval (FIN-005).

### Both
13. Nav items appear only for the permissions that can use them (`refunds.approve` or `refunds.execute`; `agencies.manage` or `bookings.view_all`), through the existing `itemAllowed` mechanism.
14. Helpers, tested: `refundSlaDisplay(dueBy, now)` (reuse the request-queue SLA helper rather than writing a second one), `agencySlaDisplay(elapsed, limit, breached)`, `commissionPillClass(rate, cap)`, `netRate(total, pct)` for the preview only.

## Don't
- Don't compute penalties, bands or refund amounts in the panel.
- Don't build the agent-facing portal, its login or its emails.
- Don't show customer-facing cancellation text anywhere (LEG-001 is unpublished).

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build.
- Browser, both themes, after `reset.sh`: cancel a paid booking in the booking panel, then find its request in the queue with the right band and amounts; approve as Carolina; execute as cfo@; the booking's ledger shows the negative row and Paid drops.
- A user with neither refund permission does not see the nav item.
- Agencies: the breached pending registration shows a coral SLA chip; approving it moves it to the partners table; the over-cap agency shows its pill, and its blocked booking is reachable from Payments & Revenue.

## Report
Append **Task 09**: the refund queue columns and where each number comes from, the approve/execute split in the UI, the agency SLA display, what the portal preview is (and is not), and the invite wording. Git commands listed, not run.
