# Task 10 · anakata-panel · New Reservation: agency, commission, deposit method
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 5 · **Needs:** tasks 04 and 06.

## Goal
Finish the New Reservation modal. Sprint 4 left three fields out with the note "Agency and commission are added in Sprint 5": the agency selector, the commission field with its cap behaviour, and the deposit method. This task adds them and nothing else.

## Read first
- Sprint 4 task 08 in the REPORT (what the modal does today, the trade `.notice` placeholder it renders, the quote → create flow, the contact notice)
- `prototype/rms_index.html` → `#newmodal`: `nb-agent` (the agency select and its `data-comm`), `nb-comm` with `commCheck`, `#commwarn`, `nbChan`'s trade branch, the "Payment method for deposit" field, and `saveNew`'s `blocked` branch with its FIN-005 alert
- This sprint's REPORT task 04 (the cap behaviour and its wording) and task 03 (payment links)

## Do
1. **Trade branch.** When `isTradeMain(main_channel)` is true (the API's `trade` flag from `form-options`, as Sprint 4 established), replace the Sprint 4 placeholder notice with the real fields:
   - **Agency** select — approved agencies from `GET /api/rms/agencies?status=APPROVED`, plus "＋ New agency…", which opens the registration modal from task 09 (imported, not copied) and selects the new agency once it is created, as PENDING. A booking may be created against a pending agency; record that decision, since the prototype allows it.
   - **Commission %** — prefilled from the selected agency's rate, falling back to the API default. Editable.
   - Neither field is shown for a non-trade channel, and both are cleared from the payload when the channel changes away from trade (the same reset discipline as `back_to_back` in Sprint 4).
2. **The cap warning.** When the entered rate exceeds the cap (from `form-options`, never a literal 12), show the prototype's `#commwarn` in the warn tone, with the API's cap value, saying what will happen: the booking is created and holds its cabin, but stays `ON_HOLD_AGENCY` and cannot be confirmed until someone with `commissions.override_cap` approves it. **Do not block Create** — the prototype creates the booking and holds it, and task 04's API does the same.
   - After creating an over-cap booking, the success toast carries the same message, and the booking panel opens on a booking showing the `ON_HOLD_AGENCY` pill.
3. **Deposit method.** A select: "Card — payment link" or "Wire transfer ({wire_window_hours}h · PENDING_PAYMENT)", the hours from the API. It does not change the booking's status (creation is PENDING_PAYMENT either way, or `ON_HOLD_AGENCY` when blocked). What it does:
   - **Card — payment link:** after a successful create, call `POST /api/rms/bookings/{id}/payment-link` for the deposit and show the link in the success state with a copy button, plus the line that sending it is manual until Sprint 7. A failure to create the link never undoes the booking — show it as a warning with a retry from the Payments tab.
   - **Wire transfer:** no payment row is created at creation (finance records it when it arrives). The success state says the wire instructions are issued manually this sprint and shows the window.
   - Record both behaviours in the REPORT; neither invents a payment.
4. **`form-options` gains what this needs** (small API prelude, PHPDoc only, plus `PanelResponseSchemasTest`): `commission: { cap_pct, default_pct }` and `payments: { wire_window_hours }`. The panel must not read the business-rules document (the Sprint 4 rule: config endpoints need config permissions).
5. **Helpers, tested:** `commissionWarning(rate, cap)` → the message or null; `depositMethodOptions(wireWindowHours)`; `agencyOptionLabel(agency)` (name — network, with the over-cap marker).

## Don't
- Don't recompute the deposit amount, the cap or the segment in the panel.
- Don't block creation on the cap.
- Don't send anything by email.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build.
- Browser, both themes, after `reset.sh`:
  - D2C channel: no agency or commission fields.
  - "B2B – Travel Advisor" + an approved agency at 10 %: creates normally, the booking shows the agency and accrues commission in Payments & Revenue.
  - The same at 15 %: the warning appears, Create still works, the booking is `ON_HOLD_AGENCY`, and approving the commission (task 07's booking panel, or the commissions list) confirms it once the deposit settles.
  - Card method: a link is created and copyable. Wire method: no payment row, the window is stated.
  - Changing the channel from trade to D2C clears the agency and commission from the payload.

## Report
Append **Task 10**: the three fields, the cap behaviour in the UI, what each deposit method does after create, the `form-options` additions, and the pending-agency decision. Git commands listed, not run.
