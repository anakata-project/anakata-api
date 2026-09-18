# 05 · Decisions (12 Sep 2026) and what is still open

Anakata answered the 20 questions raised by the prototype on **12 September 2026**. Everything in section A is already applied to the prototype, the documents and the feed. Section B is what still blocks work.

## A · Decisions taken — applied
| # | Question | Decision | Applied where |
|---|---|---|---|
| 1 | Park entry fee on the invoice | **The guest chooses**: pay it to Anakata (invoiced) or directly at SCY airport on arrival | Per-booking switch in the booking's Extras tab. Collected fees are invoiced and due with the balance; fees the guest pays directly print as an information-only block and are excluded from the total. Same switch for the TCT card |
| 2 | What the deposit covers | **Cruise (cabin) charges only** — not extras. Extras and collected fees are due **up to 72 h before departure**; spa / bar / boutique may also be taken on board and settled during or after the cruise | Deposit % applies to cabin charges; the invoice payment schedule and the quote show the extras line with the 72 h due date; `extras_due_hours` is editable in Business Rules |
| 3 | Annual rate increase | **5% per year** (FIN-001 stands) | Rates helper default is 5%; published 2028–29 rates unchanged |
| 4 | Park fee by age | Confirmed — use FIN-004 | RMS charges by age and nationality per FIN-004. **The booking engine must still capture nationality and use the ≤12 cutoff** (see 04-booking-engine-contract) |
| 5 | Park fee — Ecuadorian / resident minors | **USD 30** — same as the adult rate | Applied; matches FIN-004's national rate |
| 6 | Cancellation policy text (LEG-001) | Being written by Anakata, will be added | Bands stay live as system logic; the customer-facing text remains unpublished |
| 7 | LOPDP / GDPR architecture (LEG-002) | Being written, will be added | Consent log, encryption and retention are built as specified; the legal architecture follows |
| 8 | Invoicing entity | **PONTOS LLC**, 430 Grand Bay Drive, Apt 1108, Key Biscayne, FL 33149, USA · **EIN 42-4742064** | On every invoice. Bank details still pending (LEG-004) |
| 9 | Yacht names | **ANAMARA** and **ANATIVA** — **identical twins**, same hull, layout, cabin numbering and rates | Renamed everywhere (calendar, departures, manifests, documents). Because the yachts are identical, inventory, pricing and deck plans are symmetric; only the name differs |
| 10 | Itinerary names | **Use the engine's names** | Western Realm · Northern Passage · Festive Expeditions is now the single set; the booking summary's "Inner Islands — Western & Central Galápagos" is retired |
| 11 | Cabin numbering | **Suite 01–08 + Owner's Suite** | Already the RMS convention; the engine's 201–208 / 301 deck plan must change |
| 12 | Fleet schedule / 2027 calendar | To be added later | Departures beyond the seeded weeks stay provisional |
| 13 | Season start | **Keep OPS-006**: sales open 1 Nov 2026, first cruise 7 Nov 2027 | Applied; the rest of the 2027 itinerary calendar is still pending (PRO-001) |
| 14 | DPNG list format | **Use the prototype's columns** | Note on the export changed from "provisional" to approved |
| 15 | Pre-trip questionnaire fields | OK as built | 13 fields live; §6.2's pending wording can still be refined |
| 16 | Max guests per cabin | **3** confirmed | Now a confirmed rule rather than an engine assumption |
| 17 | RMS values with no decision code | **Confirmed**: wire window 72 h · web hold 20 + 10 min · refund SLA 15 business days · default commission 10% | Business Rules now shows them as confirmed, with those as the source values |
| 18 | Platforms | **Stripe** (payments) · **Microsoft Exchange** (transactional email) · e-signature TBD | PayPal and BNPL removed from the payment methods and reconciliation; e-signature stays open |
| 19 | Back-to-back discount | **Cabin bookings only** | Rules and quoting updated |
| 20 | Language | **All English** — guest-facing and internal | Supersedes §8.0 (Spanish as secondary). The ES locale row is marked "not planned" |

## B · Still open
| # | Item | Blocks | Owner |
|---|---|---|---|
| B1 | **Cancellation policy text** (LEG-001) — being written by Anakata | T&C, invoice text, engine copy | Anakata / PBP Law |
| B2 | **LOPDP / GDPR architecture** (LEG-002) — being written by Anakata | Passport and health data storage — go-live blocker | Anakata / PBP Law |
| B3 | **PONTOS LLC bank details** (LEG-004) — to be added later | Wire instructions on invoices | Anakata legal |
| B4 | **Fleet schedule + 2027 itinerary calendar** (PRO-001) — to be added later | Departures, inventory, revenue plan | Anakata ops |
| B5 | **E-signature provider** (TEC-003) | Charter contracts | Anakata / Hilo |
| B6 | **On-board charging** — extras can be added during the cruise; how they are captured and settled on board (guest folio, card on file, post-cruise invoice) is not specified | Extras module, final invoice, payment ledger | Anakata ops / Hilo |
| B7 | **Photography** — every itinerary card still uses a placeholder gradient | Engine launch | Anakata brand |
