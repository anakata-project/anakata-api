# Task 04 · anakata-api · Checkout: web holds, the two paths, the Stripe deposit and its fallback, waitlist, charter enquiries
**Repo:** anakata-api · **Sprint:** 8 · **Needs:** task 03.

## Goal
The engine can hold cabins while a guest decides, then submit either path: a request the team follows up, or a request with an online deposit that confirms the booking when Stripe settles it. An unfinished payment leaves a request, never a lost booking. Waitlist entries and charter enquiries arrive in the RMS.

## Read first
- `docs/requirements/08-dev-decisions.md`: **K5, K6, K7, K8, K10**, and B3, F1, F3, G2, G3, G5, G9, H4, H7, H10, I4, I6
- `04-booking-engine-contract.md` sync rules 3, 4 and 6, and the changes list (nationality, park-fee choice, Stripe only)
- `booking_engine_SPEC.md` §3 steps 4–6, §6, §7
- `prototype/booking_engine_index.html`: step 4 validation, step 5 fields and the two paths, the submission comment block
- `ClaimService` (holds, `convert`, the release job), `CreateBookingRequest`, `ResolveContact`, `RecordConsent`, `ApplyPaymentEffects`, `TransitionBooking`, the Stripe gateway, `AddWaitlistEntry`, business rules `holds.web_minutes` (20) / `holds.web_extension_minutes` (10)

## Do
1. **Checkout sessions (K5).** `checkout_sessions`: opaque token (32+ random bytes; stored hashed), `departure_id`, the chosen cabins and parties, `status` (`HOLDING · SUBMITTED · RELEASED · EXPIRED`), `expires_at`, `extended` (bool), `ip_hash` (hashed, not the raw IP), timestamps. The session is the holder of its claims.
   - `POST /api/engine/checkout` `{ departure_id, cabins: [{ code, adults, children }] }` → validates the party rules the engine enforces (SPEC §6: ≤ 3 per cabin, a child never alone in a cabin, children within the engine-settings ages, ≤ 9 cabins, ≤ 16 guests, no duplicate cabin), takes the departure lock, claims every cabin as `HOLD` type `WEB` expiring in `holds.web_minutes`, and returns `{ token, expires_at, quote }`. A cabin already taken → 409 with the cabins that are no longer available, and nothing held.
   - `POST /api/engine/checkout/{token}/extend` — the one **silent** extension (`holds.web_extension_minutes`), allowed once, only while HOLDING and not expired. The engine calls it on activity near expiry; the guest never sees it.
   - `DELETE /api/engine/checkout/{token}` — releases the claims on abandon (the engine calls it on leaving the flow; `sendBeacon`-friendly). Expiry needs nothing new: the existing release job frees expired holds every minute.
   - **Abuse limits:** at most two HOLDING sessions per `ip_hash`, and a tighter rate limit on creating sessions. Starting a third releases the oldest. Record bot protection (a challenge on step 5) as the README client question.
2. **Submission — both paths create requests (K6).** `POST /api/engine/checkout/{token}/submit` with contact (first name, last name, email required; phone optional, starting with `+`), preferred channel, travel-advisor flag, notes, marketing opt-in, per-guest nationality and Ecuador residency (K8), the PNG and TCT collection choices, the accepted declarations, `promo_code`, `path: PAY_LATER | PAY_DEPOSIT`, and `expected_total` (K7).
   - **Re-price on the server (K7).** Quote again with the current rates, offers, promo code and `online_deposit = (path == PAY_DEPOSIT)`. If the total differs from `expected_total`, return 409 with the new quote and write nothing — the engine shows the new price and asks again (the Sprint 4 `confirm_total` pattern).
   - **One transaction**, in the H10 order: departure lock → contact (`ResolveContact`) → for more than one cabin a group, coordinator = the contact (G2) → one booking per cabin as `REQUESTED` with an `ANK-R-` request reference (G3/B3), channel `D2C` / origin "Hotel Booking Engine" (the Sprint 4 seed map for WEB_DIRECT), the frozen quote lines including any discounts, `promo_code` and `online_deposit` stored on the booking (task 02), `png_collected` / `tct_collected` from the guest's choice (I10), guest rows with nationality and residency (names blank except the lead guest from the contact), the booking request row with SLA and `hold_rule`.
   - **Claims:** convert the session's WEB holds into each booking's `HOLD` type `REQUEST` with the business-hours expiry (G5) — `ClaimService::convert`, never release-then-claim, so no other checkout can take the cabin in between. The session becomes SUBMITTED.
   - **Consents (I6):** `RecordConsent` with source `ENGINE`, the request's IP, the current versions. **Default rule** (record it; LEG-001/002 may change it): `PAY_DEPOSIT` requires all four declarations at this step, because money moves now; `PAY_LATER` requires the privacy policy (we are storing their data) and the travel-insurance declaration (OPS-005: "declaration mandatory at step 5"), and the other two are accepted on the "Complete your reservation" page before any deposit (task 05). Marketing is optional and never pre-checked.
   - History `booking.requested` as for staff-created requests, with "via the booking engine".
3. **`PAY_LATER`** stops here: the response is `{ path, references, email }` for step 6. The team sees the requests in Booking Requests with their SLA; the deposit link is sent from the panel as in Sprints 5 and 7.
4. **`PAY_DEPOSIT` (K6).** After the transaction commits, create a **Stripe Checkout Session** for the deposits (Sprint 5 used Payment Links for staff links; a session suits an immediate payment and carries its own expiry). Extend the gateway interface and fake.
   - Amount: the sum of the bookings' deposits, from the stored quote (the online advantage included). Line items per booking; metadata carries the booking ids and each amount.
   - Expiry: Stripe requires 30 minutes to 24 hours; use 30 minutes from creation, and make sure each booking's request hold outlasts it (the business-hours hold always does — assert it).
   - Response: `{ path, references, checkout_url }`; the engine redirects.
   - **Settlement:** extend Sprint 5's webhook job for `checkout.session.completed` from these sessions: one SETTLED payment per booking with the metadata amount, through the existing `SettleGatewayPayment` path (locks, ledger, history, `ApplyPaymentEffects`). One PaymentIntent now funds several ledger rows, but `stripe_gateway_id` is unique: decide how the rows are keyed (for example `{payment_intent}#{booking reference}` stored in `gateway_id`, with reconciliation matching the PaymentIntent by prefix and summing) and record it; the reconciliation test must still match.
   - **Confirmation:** `ApplyPaymentEffects` gains the case "a REQUESTED booking whose deposit has settled → CONFIRMED" (through `TransitionBooking`, which converts the claim to BOOKING and draws the `ANK-` reference per G3). This applies to any request, not only web ones: a deposit settled on a request confirms it. Sprint 7's listeners then issue and send the invoice, summary and receipt.
   - **Fallback — never lose the booking (K6).** When the session expires (`checkout.session.expired`, or the session's own expiry passing with no settlement, checked by the minute job), each booking stays REQUESTED — it already is one — and loses the online-deposit advantage: re-quote without it, replace the stored lines and total, clear `online_deposit`, history "Online deposit not completed — the online advantage was removed; the request stays open for the team". No email to the guest this sprint; the team follows up within the SLA.
5. **Waitlist (K10).** `POST /api/engine/waitlist` `{ departure_id, cabin_category, contact, adults, children, notes }` → `AddWaitlistEntry` with a new `source = ENGINE`. Only for departures whose waitlist is on (the feed says so). Rate-limited.
6. **Charter enquiries (K10).** `charter_enquiries` table: preferred dates or departure, guests (≤ `guests.max_per_yacht`, the charter form's rule), contact, message, source, `status` (`NEW · CONTACTED · CLOSED`), audit columns. `POST /api/engine/charter-enquiries` → store, contact through `ResolveContact`, email the reservations mailbox (a config value) through Sprint 7's mail path, history. `GET /api/rms/charter-enquiries` and a status update for the panel (task 07), `panel.rms` to read, `bookings.create` to update. The charter quote and deal are later sprints.
7. **Responses** typed, into `EngineResponseSchemasTest` / `PanelResponseSchemasTest`.

## Don't
- Don't release a web hold and claim again; convert.
- Don't create a PENDING_PAYMENT booking from the engine; both paths are requests until money settles.
- Don't cancel, release or delete a request because a payment was not completed.
- Don't trust any price, total or discount sent by the engine.

## Checks
- `composer check`.
- Holds: create → cabins held; a second checkout for the same cabin → 409; extend once then refused; abandon → released; expiry → released by the job; a third session from one IP releases the oldest.
- Party rules: each SPEC §6 rule refused with its field.
- Re-price: a rates or offer change between quote and submit → 409 with the new quote, nothing written.
- Submit PAY_LATER: requests with `ANK-R-`, group for several cabins, claims converted (never a moment with the cabin free — a concurrency test on the Sprint 4 harness), guests with nationality, fee choices, consents with source ENGINE and IP, SLA.
- PAY_DEPOSIT: session created with per-booking metadata; replayed webhook settles once; bookings → CONFIRMED with `ANK-` references; invoice/summary/receipt listeners fire (Mail::fake); reconciliation still matches.
- Fallback: expired session → still REQUESTED, advantage removed, history written, claims untouched.
- Consents default rule per path; marketing never assumed.
- Waitlist from the engine only where enabled; charter enquiry stored and emailed; both rate-limited.

## Report
Append **Task 04**: sessions and the abuse limits, the submission transaction and claim conversion, the consent rule per path, the Checkout Session choice and the multi-booking ledger keying, the new REQUESTED → CONFIRMED payment effect, the fallback, waitlist and charter enquiries. Git commands listed, not run.
