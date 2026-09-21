# Task 08 · anakata-panel · Booking panel: Extras tab and charges; the extras catalogue editor
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 6 · **Needs:** tasks 06 and 07.

## Goal
The Extras tab lists contracted services and the Galápagos fee choices. Overview shows what the booking is charged, line by line, all from the API. The extras catalogue gets its editor in Rates & Promotions, published like the other configuration documents.

## Read first
- `prototype/rms_index.html`: `drExtras` (both notes, the table, the subtotal row, the add form, the fee checkboxes and their exact wording, the "re-issue" notice), `anPick`, `addAnc`, `rmAnc`, `setFee`, and `renderAncCat` with the `anccat` panel in `v-rates`
- Sprint 2's Rates and Engine Settings editors in the panel (the publish bar, base version, approval reference, the 409 handling) — the catalogue editor reuses that flow
- This sprint's REPORT task 04 for the charges fields and the OVERDUE change

## Do
1. **Enable the Extras tab.** Extract `BookingExtrasTab.vue`, lazy-loaded.
   - The prototype's intro note.
   - Table: service (and note), qty, rate, amount, remove. Amount per row and the "Ancillary subtotal" come from the API (`qty × rate` is computed there; the panel prints it).
   - **Add a service** for `can_act`: a select of **active** catalogue items (from the API), quantity (defaulting to the guest count from the API summary, as the prototype does), rate prefilled with the catalogue price and required when the item is on request, note. Remove with a `ReasonModal`-style confirmation, never `window.confirm`.
   - Adding or removing is refused by the API on cancelled or released bookings; hide the form there.
2. **Galápagos fees.** The two checkboxes with the prototype wording:
   - "Guest pays the PNG park entry fee to Anakata (USD {amount} — by nationality, see Guests). Unchecked = paid directly at SCY airport on arrival." The amount is the API's known PNG total; when some guests are pending, add "{n} guests pending data".
   - "Anakata manages the TCT transit card (USD {tct} × {n}). Unchecked = …" — the per-person amount and count from the API.
   - Toggling PATCHes the fees endpoint; disabled without `can_act`.
   - The prototype's footnote, with the due-before-departure hours from the API (not 72).
   - The prototype's "re-issue an updated invoice" notice is **not** shown yet (invoices are Sprint 7); record that.
3. **Overview money rows** (after task 07's rows):
   - Cruise (`total`), Extras (`extras_total`), Galápagos fees collected (`fees_collected_total`, with "pending data" when `png_pending_count > 0`), a rule, **Charges total** (`charges_total`), then the Sprint 5 Paid / pledged / Balance rows, now against `charges_total`.
   - Deposit row unchanged — a share of the cruise only — and label it that way ("Deposit {pct}% of cruise charges").
   - When extras or fees are outstanding, a line "Extras and fees due by {extras_due_at}".
   - The OVERDUE pill and OPS-007 block keep reading `overdue` from the API; its meaning changed in task 04 (cruise part only), and the panel needs no change for it. Say so in the REPORT.
   - A FULLY_PAID booking with a new extra shows a balance and stays FULLY_PAID. Nothing in the panel suggests the status changed.
4. **After every write** (extra added or removed, fee toggled): refresh the tab, the booking (Overview money rows) and the list.
5. **The catalogue editor** in Rates & Promotions (`extras.manage` to edit, `panel.rms` to read; the prototype's "ADMIN / DIRECTOR" pill):
   - Table: code, name, unit, price (or "on request"), transfer voucher, active.
   - Add an item, edit name, unit, price and flags; codes cannot be changed once published (the API refuses it — the panel shows the code read-only for existing items). Deactivate instead of delete.
   - Publish through the existing publish bar: validate, approval reference, base version, 409 on a stale version. Reuse the component; do not copy it.
6. **Helpers, tested:** `feeLabel(kind, amounts)` for the two checkbox sentences; `chargesRows(booking)` shaping the Overview rows (labels and values only, no arithmetic); `extraAddDefaults(item, guestCount)`.

## Don't
- Don't total anything in the panel.
- Don't show invoice or re-issue language (Sprint 7).
- Don't hard-code the due-before-departure hours, the TCT amount or catalogue prices.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.7.0`.
- Browser, both themes, after `reset.sh`:
  - Add flights × 2 to a CONFIRMED booking → Extras subtotal, Charges total and Balance update; the deposit does not.
  - An on-request item needs a rate; an inactive item is not offered.
  - Switch PNG collection on for a booking with complete guests → the fees row and balance update; with a pending guest, "pending data" shows.
  - `ANK-2026-0005` (FULLY_PAID) gains an extra → balance > 0, status still FULLY PAID.
  - Publish a catalogue price change → the existing booking extra keeps its rate.

## Report
Append **Task 08**: the tab, the fee sentences and their sources, the Overview charges rows, what OVERDUE now means on screen, the catalogue editor's reuse of the publish flow, and the invoice notice deferred to Sprint 7. Git commands listed, not run.
