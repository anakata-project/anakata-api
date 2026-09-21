# Task 09 · anakata-engine · Steps 4–6: cabins, details and the two paths, confirmation
**Repo:** anakata-engine (plus the sprint REPORT) · **Sprint:** 8 · **Needs:** task 08.

## Goal
The second half of the flow: the guest picks cabins on the deck plan (held by the RMS as they continue), gives their details and chooses a path, and lands on a confirmation that says exactly what happened — with every price confirmed by the server.

## Read first
- `booking_engine_SPEC.md` §3 steps 4–6, §4, §5, §6; `04-booking-engine-contract.md` rules 3, 4, 6 and the changes list (Suite 01–08 + Owner's Suite, nationality, park-fee choice, deposit wording, Stripe only)
- `prototype/booking_engine_index.html`: `startCabins`, `setNCab`, `setPC`, `renderCabins`, `cabProblems`, `physAvail`, `checkAvailability`, `priceAll`, `priceCabin`, `priceTotals`, `promoDiscount`, `applyPromo`, `renderPricePanel`, `renderPayBox`, `setPay`, `toDetails`, `submitRequest`, `fireAbandon`, `markBad`, `val`, and the confirmation copy for both paths
- This sprint's REPORT tasks 03 and 04 (the endpoints, the 409 cases, the consent rule per path)

## Do
1. **Step 4 — Cabins & Layout:** cabin count from the minimum up to the party size (maximum from settings), guests auto-distributed and adjustable per cabin, and the deck plan with the **12 Sep naming** — Suite 01–08 and the Owner's Suite — from `GET /api/engine/departures/{id}/cabins` (only `bookable` cabins can be picked). The prototype's live validation, word for word, blocks continuing (`cabProblems`).
   - **Continuing places the hold:** `POST /api/engine/checkout` with the chosen cabins. A 409 names the cabins just taken; the plan refreshes and the guest re-picks — never a silent failure. The token and `expires_at` go into the flow state.
   - **The silent extension:** when the guest is active and the hold is within a few minutes of expiry, call `…/extend` once. If the hold expires anyway, tell the guest plainly that the cabins were released and offer to re-check availability.
   - Going back from step 4 or leaving the flow releases the hold (`DELETE`, sent with `navigator.sendBeacon` on page hide).
2. **The price panel** from step 4 on (the prototype's `renderPricePanel`): the engine may render its own estimate instantly, but it replaces it with the server's `POST /api/engine/quote` result as soon as it arrives, and **only the server's lines and totals are ever submitted or shown as final** (K7). Lines come from the API — offer lines, the online advantage, the promo code, credits — with their labels. Informational fees (TCT, PNG) show as information with amounts from settings; the PNG category estimate uses the guests' nationality (step 5) and the 12-year cutoff, with the wording that the final category depends on each guest's date of birth.
3. **Step 5 — Your Details:** first name, last name, email (required), phone (optional, must start with `+`), preferred channel, travel-advisor, notes, marketing opt-in (unchecked); the prototype's missing-field behaviour ("⚠ Please complete the highlighted fields above to continue", scroll and focus).
   - **New per doc 04:** each guest's nationality and "resident of Ecuador" (the country list from the API — no local copy); the **PNG choice** ("Anakata collects the park fee" versus "I will pay at SCY airport") and the **TCT choice**; the deposit wording "on cruise charges only; extras and collected fees are due up to {hours} before departure" with the hours from settings.
   - **The declarations** per task 04's rule: for "pay the deposit online" all four are required here; for "pay later" the privacy policy and the travel-insurance declaration are required here and the page says the other two come with the deposit link. Each is its own unchecked checkbox with the document version from settings.
   - **Promo code:** `POST /api/engine/promo/check`; the prototype's behaviour (Enter or Apply, lock and Remove, "This code is not valid", removal with a reason when a festive departure excludes it). Validity is the server's answer only.
   - **The two paths**, side by side as the prototype shows, each with its CTA; selecting one re-quotes (the online advantage appears or disappears as a server line). The "Pay today" box: USD 0, or the server's deposit.
   - **Submit:** `POST /api/engine/checkout/{token}/submit` with the server total the guest saw as `expected_total`. A 409 price change shows the new price and asks again; a 409 hold loss returns to step 4. `PAY_LATER` → step 6. `PAY_DEPOSIT` → redirect to the returned Stripe `checkout_url`.
4. **Step 6 — Confirmation:**
   - Pay later: "Request received / We have received your booking", the `ANK-R-` reference(s), the email, and the three next-step cards (prototype wording, with the SLA hours from settings).
   - Pay deposit: Stripe returns to a confirmation route with the session id. The page asks the API for the booking state and **shows "Deposit paid — booking confirmed" only when the API reports the booking CONFIRMED** (the webhook, not the redirect, settles money — H7). While the webhook is still arriving, show "Confirming your payment…" and poll briefly; if the session expired unpaid, show that the request is safe and the team will contact them (K6). Add a small `GET /api/engine/checkout/{token}/status` in the API if task 04 did not; record it.
5. **Analytics hooks** (the `track()` no-op from task 08): `begin_checkout`, `begin_booking_request`, `select_payment_path`, `apply_promotion`, `remove_promotion`, `promo_invalid`, `booking_form_invalid`, `submit_booking_request` (with the SPEC §8 parameters, channel `WEB_DIRECT`), `abandon_cart` (leaving from the details step, as `fireAbandon`). `purchase` fires only on the confirmed state in step 6, never on submission (SPEC §7).

## Don't
- Don't submit or display as final any price the server did not return.
- Don't show "confirmed" because Stripe redirected; show it because the API says so.
- Don't keep a hold the guest has left.
- Don't copy the country list, the declaration texts or the fee amounts into the engine.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build.
- Unit tests: `cabProblems` rules, guest distribution, the extension timing, path switching re-quotes, promo state transitions.
- Browser, both themes and at phone width, against the e2e seed with Stripe in test mode (or the replay script where keys are absent): the prototype walkthrough to the end on both paths with `ANAKATA10`; a cabin taken in the RMS between steps 3 and 4 → the 409 is handled; abandoning releases the hold (check the RMS calendar); a rates change between quote and submit → the new price is shown; pay later → the request appears in Booking Requests with nationalities, fee choices and consents; pay deposit → confirmed only after the webhook, with the `ANK-` reference; an expired payment → the request remains.

## Report
Append **Task 09**: the hold lifecycle in the UI, the estimate-then-server price rule, the new fields, the declarations per path, confirmation from the API's state, and the analytics events. Git commands listed, not run.
