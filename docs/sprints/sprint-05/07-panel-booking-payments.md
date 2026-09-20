# Task 07 · anakata-panel · Booking panel: Payments tab, real Paid / Balance, OPS-007
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 5 · **Needs:** task 06 (`v0.6.0`).

## Goal
The booking panel stops saying "Payments arrive in Sprint 5". Overview shows what was paid and what is owed, flags an overdue balance and offers the OPS-007 decision. The Payments tab, disabled since Sprint 4, becomes the per-booking ledger with the finance actions.

## Read first
- Sprint 4 task 07 in the REPORT (the panel's structure, the reason modal, own-records handling) and the Sprint 4 booking panel itself
- `prototype/rms_index.html`: `drOverview`'s money rows and its overdue block, `drPayments` (the per-booking ledger table, the "record payment" form, "Mark received", the receipt link), `resolveOverdue`, `recPay`, `markWire`
- `01-functional-spec.md` §4, the Overview and Payments bullets
- This sprint's REPORT tasks 01–05 for the exact API shapes and wording

## Do
1. **Overview money rows.**
   - Paid = `paid` from the API. Delete `bookings.paidZero` and the "Payments arrive in Sprint 5" note.
   - When `pledged > 0`, a line under Paid: "USD 2,660 awaiting wire · window ends {datetime}" from `wire_window_ends_at`, in the warn tone. A pledged wire is never added to Paid.
   - Balance keeps `format(balance)` and its due date, and takes the coral tone when `overdue` is true.
   - Deposit row: "Deposit {deposit_pct}% · USD {deposit_amount}", with a `--ok` tick when `paid >= deposit_amount`.
2. **The overdue block (OPS-007).** When `overdue` is true, a `.warnbox` above the transitions:
   - "Balance overdue by {overdue_days} days — USD {balance}. OPS-007: the team decides; nothing is cancelled automatically."
   - Two buttons, both requiring `bookings.overdue_decision` and `can_act`: **Grant extension** (date picker for the new due date, plus a required reason) and **Cancel per policy** (required reason, and a line saying a refund request will be created when money has been paid — task 05 wording, not invented).
   - Both go through the existing `ReasonModal` pattern; the extension modal adds the date field. Never a `window.confirm`.
   - After either, refresh the panel and the list.
3. **The Payments tab** (`BOOKING_TABS` — remove the disabled flag and the "Arrives in Sprint 5" tooltip):
   - `GET /api/rms/bookings/{id}/payments`. Columns from the prototype: date, type, method, reference, amount, status. Refunds render with the minus sign and the coral tone.
   - Awaiting-wire rows show **Mark received** for `payments.mark_wire_received`; everyone else sees the `AWAITING WIRE` pill. The action asks for the bank reference in a small modal (required), posts, then refreshes the panel, the ledger and the bookings list, because the status may have changed.
   - **Record payment** form, for `payments.record`: kind, method, amount (prefilled with the outstanding balance, or the deposit when nothing is paid), optional value date and note. Warnings from the API (the overpayment warning) render in a `.warnbox` above the form and do not block. Field errors through `applyApiFormError`.
   - **Payment link**, for `payments.record`: "Create deposit link" / "Create balance link" → `POST /api/rms/bookings/{id}/payment-link`, then show the link with a copy button and its status pill. An open link can be cancelled. Say plainly that sending it is manual this sprint ("Copy the link — sending it by email arrives in Sprint 7"). If the API reports Stripe test mode, the tab says so.
   - Empty state: "No payments yet."
   - The receipt link in the prototype is **not** built here (documents are Sprint 7); leave the column without it and record that.
4. **Header and list.** The booking panel header gains an `OVERDUE` pill next to the status pill when `overdue` is true. The bookings list (Sprint 4 task 07) gains the same pill in its status cell and an "Overdue only" toggle wired to `overdue=1`. No client-side overdue arithmetic anywhere — the flag comes from the API (it is a Galápagos-calendar rule).
5. **Helpers, tested** (`app/components/payments/paymentHelpers.ts`):
   - `paymentKindLabel`, `paymentMethodLabel`, `paymentStatusPillClass` — from the API's labels where they are sent; a map only for pill classes.
   - `signedMoney(amount)` → the coral minus form for refunds.
   - `overdueNotice(overdueDays, balance)` → the warnbox sentence.
   - `defaultPaymentAmount(booking)` → deposit when nothing is paid, otherwise the balance.
6. **i18n and CSS.** All strings under `payments.*` and `bookings.*`. Reuse `.list`, `.kv`, `.warnbox`, `.pill`; add only what the prototype's payments table needs.

## Don't
- Don't compute paid, balance, overdue or the wire window in the panel.
- Don't show a "receipt" or "invoice" action (Sprint 7).
- Don't offer the record-payment form to users without `payments.record` — a Sales Exec sees the ledger read-only.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.6.0`.
- Browser, both themes, after `tests/e2e/bin/reset.sh`:
  - Carolina opens `ANK-2026-0005` (fully paid): Paid equals the total, Balance zero, ledger shows deposit and balance.
  - `ANK-2026-0014`: the wire row is awaiting, Paid is still zero, the window line shows; Mark received (as cfo@ and as Carolina) settles it and the booking becomes CONFIRMED without a page reload.
  - `ANK-2026-0018` shows the OVERDUE pill and the OPS-007 block; an extension with a reason clears it; History shows both entries.
  - Lucía on Mateo's booking: ledger visible, no record form, no mark-received, OPS-007 buttons disabled.
  - cfo@ (External finance, RMS only): can mark a wire received; has no transitions.

## Report
Append **Task 07**: the Overview rows, the OPS-007 block and its wording, what the Payments tab does and does not do (no receipts), the permissions each control needs, and the deleted `paidZero` string. Git commands listed, not run.
