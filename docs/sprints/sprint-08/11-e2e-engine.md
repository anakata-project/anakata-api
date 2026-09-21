# Task 11 · anakata-api · E2E scenarios for Sprint 8; P1 run
**Repo:** anakata-api (`tests/e2e/`, and a branch the cloud agent pushes) · **Sprint:** 8 · **Needs:** tasks 01–10 merged, `anakata-ui v0.9.0` pushed, the README's "Before task 01" done.

## Goal
Sixteen scenarios for the public engine and offers, the earlier files this sprint changes, and a run on the cloud machine — the first run that walks the engine as a guest.

## Execution — cloud machine only
Launch the Cloud Agent **from `anakata-api` alone** (one git remote; the four-repo workspace is what failed in Sprints 5–6; `.cursor/environment.json` is correct as it is), with `GH_TOKEN` for `install.sh`. The whole task runs there: reading screens, writing scenarios, updating fixtures, both runs. If the agent cannot start or `up.sh` does not reach ALL UP, write the ENV report and stop; never guess values; never fall back to a local run. The agent works on `e2e/sprint-08`, commits with explicit paths, pushes that branch, never merges into `dev`.

`up.sh` must bring up the engine (port 3000) alongside the API, panel, MySQL, Redis and Mailpit, and report it before ALL UP. The engine points at the e2e API.

## Read first
- `tests/e2e/README.md`, `_TEMPLATE.md`, `INDEX.md`, `fixtures/reference-values.md`, `bin/replay-stripe-checkout.sh`, `bin/mail-find.sh`
- The Sprint 7 run reports (the current screen truth) and this sprint's REPORT tasks 01–10

## Do
1. **Fixtures.** Extend `reference-values.md`: the seeded offers and promo codes (placeholders, local/testing only), the engine walkthrough's expected lines and totals for both paths — **read off the engine and cross-checked against the RMS booking**, never computed by hand in the fixture — the departures' labels after reset, and the registry counts if any rule moved. Anything not read off a screen, Mailpit or the RMS stays `⚠ UNVERIFIED — <source>`; report the split between leftovers and new markers.
2. **A replay helper for Checkout Sessions.** Extend `bin/replay-stripe-checkout.sh` (or add a sibling) to replay `checkout.session.completed` and `checkout.session.expired` for an engine session, signed with the e2e webhook secret, so both outcomes can be driven without live Stripe. Test mode only.
3. **Revisit earlier files this sprint changes:** Booking Requests (web requests now appear; charter enquiries panel), Holds & Waitlist (web holds and ENGINE waitlist entries), Payments and Documents (the payment-link and reminder emails now link to the complete page), Rates & Promotions (the offers panel), New Reservation (D2C/B2B offers may now add lines to staff quotes — re-read the prices), ENG-01/02 if engine settings wording moved.
4. **Sixteen new scenarios** — `scenarios/web/` and `scenarios/offers/`, tags `sprint-8`:

   | ID | P | Users | Script (intent; the screen wins) |
   |---|---|---|---|
   | WEB-01 | P1 | Guest | Search 2 adults, November 2027 range → the itineraries and departures shown match the RMS's on-sale departures; labels match the RMS availability |
   | WEB-02 | P1 | Guest + Carolina | A draft itinerary, a hidden departure, a paused offer and a promo code never appear on the engine (feed and pages) |
   | WEB-03 | P1 | Guest + Carolina | Block a cabin in the RMS → within 30 seconds the engine shows one cabin fewer; block the rest → FULL · WAITLIST; only held cabins → LIMITED AVAILABILITY with waitlist |
   | WEB-04 | P1 | Guest | Trip details: tabs, rail rules from settings, Route map on Western Realm; no map tab where none is supplied |
   | WEB-05 | P1 | Guest + Carolina | Step 4 holds the cabins (RMS calendar shows the web hold); leaving the flow releases them |
   | WEB-06 | P1 | Guest | The prototype walkthrough with `ANAKATA10` on both paths: every line and total on step 5 equals the server's quote |
   | WEB-07 | P1 | Guest + Carolina | Pay later → `ANK-R-` request in Booking Requests with SLA, nationalities, fee choices, consents with IP; confirmation shows the reference |
   | WEB-08 | P1 | Guest + Carolina | Pay deposit → Checkout Session replayed as completed → booking CONFIRMED with `ANK-`; invoice, summary and receipt in Mailpit once each |
   | WEB-09 | P2 | Guest + Carolina | Pay deposit → session replayed as expired → the request remains, the online advantage removed, history says why |
   | WEB-10 | P2 | Guest | A festive departure refuses every discount; `EARLY500` is removed with its reason |
   | WEB-11 | P2 | Guest + Carolina | A cabin taken in the RMS between steps 3 and 4 → the engine names it and asks to re-pick |
   | WEB-12 | P2 | Guest + Carolina | "Complete your reservation" from the payment-link email: billing, declarations, a guest's passport → "on file"; continue to payment only when allowed; the passport is ciphertext in the database (`db-check`) |
   | OFF-01 | P1 | Carolina + Director | A PCT offer is PENDING DIRECTOR until approved with a reason, then LIVE on the engine within 30 seconds |
   | OFF-02 | P2 | Carolina | Pause a live offer → gone from the engine within 30 seconds; resume |
   | OFF-03 | P2 | Carolina | Festive itinerary cannot be selected; a B2B offer never shows publicly |
   | OFF-04 | P2 | Guest + Carolina | Charter enquiry and waitlist from the engine arrive in the RMS |

   Wording from the screens after `reset.sh`, not from this table. Guest steps run in a fresh browser context with no staff session.
5. **INDEX.md.** Add the rows; the P1 set grows by WEB-01 to WEB-08 and OFF-01.
6. **The runs.** `up.sh` to ALL UP, `reset.sh` before every scenario that reads screen facts, Mailpit cleared at the start of every mail scenario:
   - Sprint 8 P1 plus the revisited files → `runs/YYYY-MM-DD-HHMM-sprint8-p1.md`;
   - the full P1 set, Sprints 1–8 → a second run file.
   - Each separates **(i)** scenario or fixture fixes from **(ii)** application bugs. Only (ii) goes back to code. Never loosen an expectation.
7. **Sprint 8 summary** in the REPORT, the same shape as before: what is done; open questions compiled from each task (do not write "none") — at least discount stacking and the cap, the Option 2 perk, the real offers and codes, the route maps, bot protection, GA4 and cookie consent, production domains; what is still open outside the sprint; merge steps for the user.

## Don't
- Don't change application code to make a scenario pass.
- Don't run a live-mode Stripe payment or use real card data.
- Don't write passport numbers or other sensitive guest data into a scenario, fixture, report or caption.

## Report
Append **Task 11**: the scenarios, the replay helper, the revisited files, both run reports, the split marker counts, the open questions and the merge steps. The agent pushes its branch; the user merges.
