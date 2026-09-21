# Task 02 · anakata-api · Pricing with offers, the online-deposit advantage and promo codes
**Repo:** anakata-api · **Sprint:** 8 · **Needs:** task 01.

## Goal
Decision B2, made real: the one pricing engine in the API gains, after doc 02's per-cabin steps, the booking-level offers, the online-deposit advantage and a promo code — in that order, respecting each offer's combinable flag and the optional cap. The applied discounts are frozen on the booking like every other price line (G4).

## Read first
- `docs/requirements/08-dev-decisions.md`: **K1, K2, K7**, and B2, E7, E8, G4, H8
- `booking_engine_SPEC.md` §4 (the order, "the online advantage is applied before the promo code"), §5, §10 items 1–4
- `02-data-model.md` → the pricing engine steps 1–7
- `prototype/booking_engine_index.html`: `priceAll()` — the single function that owns engine pricing; port its behaviour, not its structure
- `app/Services/Pricing/CabinPricer.php`, `ReservationQuoter`, `CurrentConfig::businessRules()->discounts`

## Do
1. **The order (K1)** — `ReservationQuoter` (the single quoting service since Sprint 4) runs, for a quote context `{ channel, booking_date, online_deposit: bool, promo_code: ?string }`:
   1. doc 02 steps 1–6 per cabin, unchanged (`CabinPricer`); **a festive departure stops here** — no offer, advantage or code applies (B2: "festive blocks all discounts");
   2. booking-level offers (`Offer::applicableTo` without a code): PCT on the cruise total, AMT per cabin, CREDIT as a non-price line (below);
   3. the online-deposit advantage, `discounts.online_deposit_discount_pct` of the running total, only when `online_deposit` is true;
   4. the promo code, if given and applicable: PCT on the running total (so it is calculated on the already-reduced figure), AMT per guest or per cabin as the offer states.
   - **Stacking:** a non-combinable offer or code cannot apply together with another discount of steps 2–4. When two would apply and one is not combinable, the guest gets the larger saving, and the quote says which was dropped and why ("ANAKATA10 cannot be combined with the Shoulder season offer — the larger discount was kept"). Record this rule as a default pending the client (README question 1).
   - **Cap:** when `discounts.max_total_discount_pct` is set, the total of steps 2–4 never exceeds that share of the step-1 total; the last discount applied is reduced, with a line saying so.
   - Rounding: the existing `Rounding::halfUp`, per line.
2. **Lines, not a number.** Each applied discount is its own price line with a stable `code` (the offer code, `online_deposit`), a label (the offer's `price_line`, or the engine settings perk text for the online advantage), and a negative amount. A CREDIT offer is a zero-amount line with its text ("Opening season credit — on-board ancillaries"): it is value, not a price reduction. A VALUE offer is the same. These lines are what the engine's price panel shows and what the invoice prints (Sprint 7 prints `price_lines` already).
3. **The deposit** is `deposit_pct` of the **discounted** cruise total (B2), still on cruise charges only (I9).
4. **Freezing (G4).** When a booking is created from a quote, the discount lines are part of the frozen `price_lines` and the total. A later offer change never touches a sold booking. A **move** (Sprint 4 `MoveBooking`) re-quotes: the booking's original promo code and online-advantage flag are carried into the move quote (store `promo_code` and `online_deposit` on the booking at sale), and offers are re-evaluated for the new departure — the preview shows the difference as today. Record it.
5. **COMM offers (K2).** A B2B/ALL COMM offer that applies to a trade booking adds its percentage to the booking's `commission_pct` at sale (H8 frozen), and the FIN-005 cap check runs on the sum — so a COMM offer can put a booking on `ON_HOLD_AGENCY`. The commission line in the REPORT names the offer. COMM never changes the guest's price.
6. **Promo validation endpoint for the engine** is task 03's; this task gives it the method: `PromoCode::check(code, context)` → `{ valid, reason, offer }`, reasons in the prototype's wording ("This code is not valid"; "This code does not apply to festive departures").
7. **Staff quotes.** New Reservation (Sprint 4 task 08) passes its main channel into the context, so D2C bookings pick up applicable D2C offers and trade bookings B2B ones, with no promo code and `online_deposit: false`. The quote response already lists lines; nothing changes in the panel this sprint. Record that staff cannot enter a promo code yet.

## Don't
- Don't let any discount apply on a festive departure.
- Don't compute a discount in a controller; `ReservationQuoter` is the only place.
- Don't change the eight doc 02 reference prices — they stay the tests for steps 1–6.

## Checks
- `composer check`; the eight doc 02 reference prices unchanged.
- The prototype walkthrough (2 adults, Suite, a November 2027 Western Realm departure with −12 %, `ANAKATA10`, both paths): the lines and totals equal what the prototype's `priceAll()` shows for the same inputs — compute the expected values from the prototype's rules in the test's comments.
- Order: the online advantage before the promo code, visibly (a PCT code on the reduced figure).
- Festive: every discount refused; `EARLY500` refused with its reason.
- Stacking: a non-combinable pair keeps the larger and explains; the cap trims the last discount.
- A CREDIT offer adds a zero line; the deposit is on the discounted cruise total.
- Freezing: publishing a change to an applied offer does not alter the booking; a move carries the code and flag and re-evaluates offers.
- COMM: a 2 % COMM offer on a 10 % agency booking gives 12 % (at the cap, not over it); a COMM pushing it to 13 % puts the booking on `ON_HOLD_AGENCY`.

## Report
Append **Task 02**: the order and where each step lives, stacking and the cap as defaults pending the client, lines and credits, the deposit base, freezing and moves, COMM and FIN-005, and staff quotes. Git commands listed, not run.
