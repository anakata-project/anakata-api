# Sprint 7 · Documents and email

**Goal:** the RMS issues the documents a guest receives, and sends them.
- The booking confirmation & invoice, the final invoice, the booking summary, payment confirmations, the transfer voucher, the pre-trip itinerary and the wire instructions are hand-written HTML templates in the code, reproducing invoice mockup v6.
- The same HTML serves twice: staff preview it in the panel and print it with the browser's **Print / save PDF**, exactly as the prototype does; the server turns it into the PDF that is stored and emailed, with a pure-PHP PDF library.
- An issued document is immutable: the PDF and the data it was rendered from are kept. A change to what the booking is charged issues a new version with a reason.
- Documents are sent by email when their trigger happens: a deposit that confirms the booking, a payment, the booking becoming fully paid, the balance reminders before the due date, T−45, T−7. Every send is recorded, and a replayed event never sends twice.
- Staff can preview any document, print it from the browser, download the stored PDF, resend it, and email a payment link or wire instructions.
- The booking panel's Documents tab lists every document with its trigger, date and status. The Documents & Manifests view lists client documents across all bookings.

Email goes through Microsoft Exchange (TEC-002) via Microsoft Graph in production, and Mailpit locally. The public "Complete your reservation" page — billing data, the four declarations, guest details — is built with the booking engine in Sprint 8. Departure manifests and the DPNG export are Sprint 11. The preferences questionnaire is Sprint 11.

- **anakata-api:**
  - the PHP PDF library, the document store, numbering and the issuer details
  - the document templates, preview, issue and re-issue, billing details
  - email delivery: the Graph mailer, the delivery log, recipients, manual sends
  - triggers and the schedule: what is sent when, and each document's status
- **anakata-ui:** regenerated types, release `v0.8.0`.
- **anakata-panel:**
  - the booking panel's Documents tab, receipts on the Payments tab, "send by email" for payment links, billing details
  - Client documents on Documents & Manifests
- **E2E:** document and email scenarios and a P1 run.

## Before task 01
The e2e verification has fallen two sprints behind, and this sprint puts documents on top of the money and guest data those runs are meant to check. An invoice built on an unverified balance is a worse place to find a bug than a list screen. So:

1. **Fix the cloud agent.** Sprint 5's run could not start in the cloud because the workspace has four git remotes and the cloud environment requires exactly one; nothing in the repo shows that was fixed. Fix it (`.cursor/environment.json` / the repo remote setup) and confirm a cloud agent starts.
2. **Close Sprint 5 on the cloud machine:** PAY-05, PAY-08, PAY-10, PAY-03 with the cfo@ context, BKG-02 / 06 / 09, then the full P1 set. Attach the reports; fix any `BUG`.
3. **Run Sprint 6 task 10** (it has not run): the GST/EXT scenarios, the rewrites of `BKG-01` and `PAY-06` for the task 04 charges, and the full P1 set for Sprints 1–6.
4. **Copy `08-dev-decisions.md`** from this folder to `docs/requirements/` (adds section J).
5. **Ask the client** the questions below. The sprint builds with the defaults and flags them.

**Questions for the client:**
- **Microsoft 365 / Graph (TEC-002):** the sending mailbox (e.g. info@anakata.co), and an app registration with `Mail.Send` permission (tenant id, client id, client secret) for test and production. Until then everything goes to Mailpit.
- **LEG-004, bank details** for PONTOS LLC. Every invoice prints wire instructions; until they arrive they print `[TBD]`.
- **Invoice numbering:** does the accountant need a unique number per issued invoice (so a re-issue gets a new number), or one number per booking with versions (the default this sprint)? PONTOS LLC is a US entity, so there is no VAT invoice rule, but the accountant may still have one.
- **The pre-trip itinerary** still says "Weather and packing list: [content pending — guest experience team]" in the prototype. Who provides that copy?

## Decisions this sprint implements
Recorded as **J1–J10** in `docs/requirements/08-dev-decisions.md`:
- J1: templates are code; the browser prints previews; a PHP library makes the server PDFs (supersedes A8).
- J2: issued documents are immutable, versioned, and keep their snapshot.
- J3: numbering.
- J4: the issuer and bank details are business-rule values.
- J5: email goes through Laravel's mailer — Graph in production, Mailpit locally — and every send is recorded once.
- J6: who receives what.
- J7: the triggers.
- J8: billing details are entered by staff until Sprint 8.
- J9: a document's status is computed from facts.
- J10: documents never compute money.

## How this sprint is run
As before: one task at a time; plan → review → agent; each task appends to `REPORT.md`.

| # | Repo | Task |
|---|---|---|
| 01 | anakata-api | The PHP PDF library, the document store, numbering, the issuer details |
| 02 | anakata-api | The document templates, preview, issue and re-issue, billing details |
| 03 | anakata-api | Email delivery: the Graph mailer, the delivery log, recipients, manual sends |
| 04 | anakata-api | Triggers and the schedule; each document's status |
| 05 | anakata-ui | Regenerate types, release `v0.8.0` |
| 06 | anakata-panel | Booking panel: Documents tab, receipts, payment-link email, billing details |
| 07 | anakata-panel | Documents & Manifests: client documents |
| 08 | anakata-api | E2E scenarios for Sprint 7; P1 run |

Dependencies:
- 01 → 02 → 03 → 04 in order.
- 05 needs 01–04.
- 06–07 need 05; 07 needs 06 (it opens the booking panel on its Documents tab).
- 08 needs everything.

## Context every task needs
- Rules in each repo's `.cursor/rules/`. Decisions: `08-dev-decisions.md`, sections A–J. In particular **J1**, which supersedes **A8** (headless Chromium is not used), **A9** (integrations behind interfaces, Mailpit locally), **B9** (one application; external integrations keep doc 07's intent), **H1–H10**, **I9** (the charges model).
- The sources:
  - `01-functional-spec.md` §4 (booking panel: the Documents tab), §12 (Documents & Manifests: client documents, and the invoice structure of mockup v6)
  - `03-business-rules.md` §4.1.4 (reminders 21 and 7 days before the due date), TEC-001/002
  - `06-build-backlog.md` (documents as PDFs from the prototype layouts; the wire instructions PDF)
  - `07-three-system-integration-contract.md`: the Documents row ("One renderer, one numbering sequence, one template version" — here, one set of hand-written templates in the code), the Stripe and Exchange rows, and rule 6 ("A replayed `payment.received` must not send a second receipt")
  - `05-decisions-and-open-questions.md` row 8 (PONTOS LLC, EIN, address)
  - screenshots `16-invoice.png`, `17-invoice-totals.png`, `04-documents-manifests.png`
- The prototype `prototype/rms_index.html`:
  - `drDocs`, `docRow`, `DSTC`, `docsFor` (the full list of documents, their triggers, recipients and statuses), `viewDoc`
  - the `docmodal` and its "Print / save PDF" button (`window.print()`)
  - `docHead`, `invoiceHtml` (the mockup v6 structure), `vesselRows`, `feeRows`, `tbl`, `num`, `summaryHtml`, `receiptHtml`, `reminderHtml`, `pretripHtml`, `voucherHtml`
  - the `.paper`, `.dh`, `.dsec`, `.dkv`, `.dt`, `.dsubt`, `.dtot`, `.dgrand`, `.dnote`, `.dfoot` styles
  - `confirmReq`'s description of the deposit link, and `drPayments`' receipt link
- What already exists: Mailpit in `docker-compose.yml` and the e2e stack, `tests/e2e/bin/mail-latest.sh`, Laravel notifications (invitation, password reset) over SMTP; business rules `payments.balance_reminder_days` (21, 7) and `payments.extras_due_hours`; the extras catalogue's `triggers_transfer_voucher`; `Ledger`, the charges helpers, `ReferenceService`, `History`.
- API in Docker only; git read-only for Cursor; compatibility check before any package or image; frontends verified on a fresh clone; **tags pushed**; no hand-written type overlays for fields the API can type.
- **E2E rule:** screen facts gathered after `tests/e2e/bin/reset.sh`, on the cloud machine; emails checked in Mailpit.

## E2E scenarios this sprint adds (task 08)
`DOC-01` … `DOC-10`, listed in task 08.

## Definition of done for the sprint
- **Rendering:** the booking confirmation & invoice for a seeded booking with extras and collected fees — both the browser print and the server PDF — reproduces mockup v6 section by section — issuer, guest & billing, cruise details, vessel charges, Galápagos fees (collected and information-only), ancillary services, the three subtotals and the invoice total, the payment schedule, cancellation, insurance, payment history, wire instructions — and every figure matches the booking's Overview.
- **Immutability:** an issued PDF never changes. Adding an extra after the invoice was issued creates version 2 with the reason, and both versions stay downloadable.
- **Sending:** confirming a booking by deposit sends the invoice and the booking summary; each settled payment sends one payment confirmation; reaching FULLY_PAID sends the final invoice; the reminders go out 21 and 7 days before the due date only while the cruise balance is open. Replaying a payment event sends nothing new. Everything arrives in Mailpit locally.
- **Visibility:** the Documents tab and the client-documents view show each document's trigger, date and status — sent, scheduled, waiting, not needed, not contracted, blocked (with why) or failed — and staff can preview, print from the browser, download the stored PDF, resend and re-issue with a reason. Printing an older version prints what was issued then.
- **Payment links** can be emailed from the Payments tab, and settled payments show their receipt.
- All checks pass on fresh clones. `anakata-ui` `v0.8.0` is tagged and pushed. The cloud P1 run (Sprints 1–7) is attached with no open `BUG`.
