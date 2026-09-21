# Task 02 · anakata-api · The document templates, preview, issue and re-issue, billing details
**Repo:** anakata-api · **Sprint:** 7 · **Needs:** task 01.

## Goal
Seven hardcoded HTML templates rendered from snapshots of the booking — the booking confirmation & invoice (mockup v6), the final invoice, the booking summary, the payment confirmation, the transfer voucher, the pre-trip itinerary and the wire instructions. The same HTML serves two purposes: the panel shows it for staff to print with the browser, and the server turns it into the PDF that is stored and emailed. Plus the billing details an invoice needs.

## Read first
- `docs/requirements/08-dev-decisions.md`: **J1, J2, J3, J4, J8, J10**, and G2, G4, H2, I3, I4, I9, I10
- `01-functional-spec.md` §12 (the invoice structure) and §4 (Documents tab); screenshots `16-invoice.png` and `17-invoice-totals.png`
- `06-build-backlog.md` ("wire instructions PDF")
- `prototype/rms_index.html`: `invoiceHtml` (read it line by line — it is the template), `vesselRows`, `feeRows`, `tbl`, `num`, `summaryHtml`, `receiptHtml`, `voucherHtml`, `pretripHtml`, `confirmReq` (the wire option), the `docmodal` with its "Print / save PDF" button, and the document CSS
- The Sprint 6 charges helpers (`chargesTotal`, `extrasTotal`, `feesCollectedTotal`, `cruiseOutstanding` and their fresh variants), `Ledger`, the guests' stored PNG categories and fees, the extras catalogue's `triggers_transfer_voucher`

## Do
1. **The templates are hand-written Blade views** in `resources/views/documents/`: one layout carrying the ported prototype CSS, one partial per section so the invoice and the final invoice share them. No template engine of our own, no stored templates, no template versions (J1).
   - **Two constraints on the HTML**, because the same markup goes to the browser and to the PHP library:
     - **Layout the library can render:** tables for every column layout (the prototype's `.dg2` two-column blocks become two-cell tables), no flexbox, no grid, simple class selectors, fonts from the files registered in task 01. Check each template in both the browser and the generated PDF.
     - **Print CSS:** an `@media print` block so the browser's "Print / save PDF" gives A4 pages that look like the server PDF — no panel chrome, page breaks between sections where the prototype would overflow.
2. **Snapshots (J10).** One builder per kind, `App\Support\Documents\Snapshots\*`, each returning a plain array that is the only input its template receives. Every figure comes from the existing helpers — the invoice's three subtotals are `total` (vessel), collected fees and `extras_total`; the invoice total is `charges_total`; paid is `Ledger::paid`; the balance is `balance()`; the cruise balance is `cruise_outstanding`. A template never adds, multiplies or rounds money. A test asserts, for several seeded bookings, that each snapshot's totals equal the booking's own API figures.
   - Read through the **fresh** variants when issuing, so an issue that follows a write in the same request never uses a stale aggregate.
3. **The booking confirmation & invoice** — `invoiceHtml`, section by section:
   - Header: title, reference, invoice number and version, date, balance due date, and the status line ("AWAITING DEPOSIT" / "DEPOSIT RECEIVED" / "PAID IN FULL").
   - Issued by: from the business rules `legal_entity` (task 01) — never literals.
   - Guest & billing: the guests' names (first two, as the prototype), the billing details (below), the agent when there is one, the group and its coordinator.
   - Cruise details: the booking's real yacht (the prototype always prints ANAMARA), embarkation and return dates from the departure and `returnDate()`, itinerary name, duration from the itinerary's nights (not a hard-coded "7 Nights"), the guests.
   - Vessel charges: the booking's frozen `price_lines` (G4). The prototype re-quotes to rebuild them; we never re-quote.
   - Galápagos fees: the collected rows (PNG grouped by the stored category label with count, rate and amount; TCT with count, stored rate and amount) and, separately, the information-only rows for fees the guest pays directly, with the prototype's note.
   - Ancillary services: the booking extras with their frozen rates.
   - The three subtotals and the invoice total.
   - Payment schedule: deposit (share of cruise charges, ✓ when received), cruise balance with its due date, extras & collected fees due before departure — prototype wording, due hours from the business rules.
   - Cancellation: the bands, built with Sprint 5's `CancellationPenalty::label` so the words match Refund Approvals. Insurance: the OPS-005 sentence.
   - Payment history: the ledger rows (reference, date, method, amount; refunds negative), total paid, current balance due.
   - Wire instructions: from `legal_entity.bank` (`[TBD]` until LEG-004), and "Payment reference: {booking reference} — include in all transfers".
   - Footer.
   - Personal data: names only. No passport numbers, dates of birth or notes — assert it with `SensitiveFields`.
4. **The other six:**
   - **Final invoice** — the invoice template with the final title and "PAID IN FULL"; its own number (J3).
   - **Booking summary** — `summaryHtml`.
   - **Payment confirmation** — `receiptHtml`, one per settled positive payment, showing the balance after that payment (the ledger up to and including it), not today's balance.
   - **Transfer voucher** — `voucherHtml`, only when the booking has an extra whose catalogue item `triggers_transfer_voucher`; arrival the day before when a pre-cruise hotel is booked, as the prototype does.
   - **Pre-trip itinerary** — `pretripHtml`, from the itinerary (description, day plan, included, not included); the "Before you travel" block keeps the prototype's pending-content line until the client supplies it.
   - **Wire instructions** — the backlog's "wire instructions PDF": amount due (the deposit for a PENDING_PAYMENT booking, otherwise the open balance), the bank block from `legal_entity.bank`, the payment reference, the wire window from `payments.wire_window_hours`. While any bank value is `[TBD]` the document says plainly that the bank details are placeholders and must not be used.
5. **Billing details (J8).** Booking columns `billing_name`, `billing_address` (multi-line), `billing_email`, `billing_phone`, all nullable; the contact's name and email are the defaults. `PATCH /api/rms/bookings/{booking}/billing`, own-records, booking lock, history `booking.billing_changed`. When the address is empty the invoice prints "[captured at payment link]" as the prototype does. The public page that captures these is Sprint 8.
6. **Endpoints.**
   - `GET /api/rms/bookings/{booking}/documents/{kind}/html` (and `…/receipts/{payment}/html`) → the rendered HTML (`text/html`), built now, stored nowhere. This is what the panel shows and the browser prints. For an issued document, `GET /api/rms/documents/{document}/html` renders **from the stored snapshot**, so printing an old version prints what was issued. `BookingPolicy::view`.
   - `POST /api/rms/bookings/{booking}/documents/{kind}/issue` `{ reason? }` → version 1, or the next version (reason required then). Own-records. Returns the document resource.
   - `GET /api/rms/documents/{document}/file` → the stored PDF of that version. `BookingPolicy::view`.
   - `GET /api/rms/bookings/{booking}/documents` → issued documents, all versions, newest first. (Task 04 adds the planned-status list.)
   - `DocumentResource` with full PHPDoc, into `PanelResponseSchemasTest`.
7. **Seed.** Issue the invoice and summary for the seeded CONFIRMED and FULLY_PAID bookings, and a receipt for each seeded settled payment, through `IssueDocument`, so the Documents tab has history on a fresh seed. The seed issues only — it never sends; task 04 records the matching deliveries as already sent.

## Don't
- Don't build a template editor or store templates.
- Don't re-quote a booking to build the vessel rows, and don't compute any figure in a template.
- Don't use flexbox or grid in document templates.
- Don't print passport numbers, dates of birth or notes on any document.
- Don't send anything (task 03) or decide when documents are issued automatically (task 04).

## Checks
- `composer check`.
- Snapshot totals equal the booking's API figures for: a cabin booking, a charter, a booking with extras, one with collected PNG and TCT, one with an information-only fee, one with a refund.
- No sensitive key in any snapshot.
- The receipt's balance is the balance after that payment.
- Version 2 needs a reason, keeps the number, and leaves version 1's file untouched (sha unchanged); version 1's HTML endpoint still renders version 1's snapshot.
- The voucher is refused (422) without a transfer-triggering extra.
- Per template: a rendered-HTML snapshot test (so a template change shows in review) and a real PDF generated by the library that starts with `%PDF`; the invoice PDF has the expected page count for a seeded booking with extras.
- Fidelity: compare the invoice PDF and the browser print of the same HTML against screenshots 16 and 17, section by section, and list any difference the library forces in the REPORT.

## Report
Append **Task 02**: templates as hand-written views and the two HTML constraints, the snapshot rule and where each figure comes from, the section-by-section invoice mapping, deviations from the prototype (real yacht, nights from the itinerary, stored price lines instead of a re-quote), the receipt balance rule, the wire-instructions document, billing details and the Sprint 8 page, the endpoints, and the fidelity differences against the mockup. Git commands listed, not run.
