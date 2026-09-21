# Sprint 7 · Report
Each task appends its section below.

## Task 01 · The PDF library, the document store, numbering, the issuer details

### Library
**dompdf/dompdf v3.1.6**, required directly (not `barryvdh/laravel-dompdf`). PHP 8.4, Laravel 13; 3.1.x is PHP 8.4-compatible. We already needed one wrapper (`PdfRenderer::render`), so the Laravel facade added nothing. Compatibility check first; never `--ignore-platform-reqs`.

mPDF stays the documented fallback if task 02’s invoice tables will not lay out. Not installed.

Page setup is in one place: A4, margins 12 mm / 13 mm from the prototype `.paper` padding. Remote resources, PHP and JavaScript are off. Oswald, Archivo and IBM Plex Mono are registered from bundled TTF files; each family ships its `OFL.txt`. Tests use the real renderer. A failure throws `PdfRenderException` and stores nothing.

### Templates are code
Blade views live in `resources/views/documents/`. No template table, editor or stored versions. This task adds only `proof.blade.php` (the prototype `docHead` plus one line, table layout, no flexbox/grid). `DocumentView` maps every kind to `documents.proof`; task 02 replaces the map.

### Store
`documents` is append-only (H1 / J2): triggers refuse every UPDATE and DELETE; the model throws as well. A correction is a new version.

Receipts are **per payment**:
- unique `(booking_id, kind, payment_key, version)` where `payment_key` is a stored generated `COALESCE(payment_id, 0)`
- unique `(payment_id, kind)` — the same receipt cannot be issued twice (doc 07 rule 6)
- **Receipts are never re-versioned.** A wrong payment is corrected by a new ledger row, which gets its own receipt.
- Receipt path: `{booking_id}/RECEIPT-{payment_id}-v{version}.pdf`
- Other kinds: `{booking_id}/{kind}-v{version}.pdf`

`IssueDocument` is the only write path: lock (departure → booking → document → reference counter), render, **write the file first**, insert the row, `document.issued` on the booking. If the transaction fails after the write, the file is deleted. Previews are not this action (task 02).

Documents are financial records, kept seven years, outside `anakata:retention`.

### Numbering
`ReferenceType::Invoice` draws `INV-YYYY-NNNN` (Galápagos year, 4-digit pad, same upsert as every other reference). Drawn at version 1, kept by later versions. The final invoice is a different kind and gets its own number.

**Open (README / accountant):** one number per booking-kind, kept across versions. A number-per-version change is this enum plus the “copy from v1” branch.

### Issuer and bank
Business-rules shape change. New top-level `legal_entity` (existing `legal.consent_versions` untouched); `bank` nested so task 02 can read `legal_entity.bank`.

- Issuer CONFIRMED (decision row 8 + prototype “Issued by”): PONTOS LLC (a limited liability company), 430 Grand Bay Drive Apt 1108 / Key Biscayne FL 33149 United States, info@anakata.co, anakata.co, EIN 42-4742064.
- Bank PENDING CLIENT (LEG-004): all five fields `[TBD]`.

DML-only migration publishes as System when keys are missing. `anakata:config-verify` fails on a latest document without `legal_entity` and passes after.

**Registry totals:** all **65** · here **40** · other_pages **15** · locked **10** · differs_or_flagged **21**.

### Production checklist
Swap the `documents` disk (`config/filesystems.php`) to an S3-compatible driver before production. Local and e2e stay on `storage/app/documents`.

### Files
See git commands below. Do not run them from the agent.

```bash
git add composer.json
git add composer.lock
git add app/Exceptions/PdfRenderException.php
git add app/Enums/DocumentKind.php
git add app/Enums/ReferenceType.php
git add app/Services/Documents/PdfRenderer.php
git add app/Services/Documents/DocumentView.php
git add app/Actions/Documents/IssueDocument.php
git add app/Models/Document.php
git add app/Models/Booking.php
git add app/Providers/AppServiceProvider.php
git add app/Support/Config/Documents/BankRules.php
git add app/Support/Config/Documents/LegalEntityRules.php
git add app/Support/Config/Documents/BusinessRulesDocument.php
git add app/Support/BusinessRules/Registry.php
git add config/filesystems.php
git add database/migrations/2026_09_21_200037_create_documents_table.php
git add database/migrations/2026_09_21_200038_add_legal_entity_to_business_rules.php
git add resources/views/documents/proof.blade.php
git add resources/fonts/documents/Archivo/Archivo-Regular.ttf
git add resources/fonts/documents/Archivo/OFL.txt
git add resources/fonts/documents/Oswald/Oswald-Regular.ttf
git add resources/fonts/documents/Oswald/OFL.txt
git add resources/fonts/documents/IBMPlexMono/IBMPlexMono-Regular.ttf
git add resources/fonts/documents/IBMPlexMono/OFL.txt
git add tests/Unit/Services/Documents/PdfRendererTest.php
git add tests/Feature/Documents/IssueDocumentTest.php
git add tests/Feature/Documents/DocumentTriggersTest.php
git add tests/Feature/Config/AddLegalEntityToBusinessRulesMigrationTest.php
git add tests/Feature/Config/BusinessRulesSeederTest.php
git add tests/Feature/Config/BusinessRulesEndpointsTest.php
git add tests/Feature/Config/BusinessRulesDocumentTest.php
git add tests/Feature/References/ReferenceServiceTest.php
git add tests/Concurrency/IssueDocumentConcurrencyTest.php
git add tests/e2e/fixtures/reference-values.md
git add tests/e2e/scenarios/config/BR-01-fresh-seed-registry.md
git add docs/sprints/sprint-07/REPORT.md
git commit -m "$(cat <<'EOF'
Add the PDF document store, INV numbering, and issuer business rules.

EOF
)"
```

## Task 02 · Document templates, preview, issue, billing

### Templates are code (J1)
Hand-written Blade in `resources/views/documents/`. Shared `layout.blade.php` carries the ported prototype CSS (tables only, no flexbox/grid, simple class selectors, `@page` A4 + `@media print`). Invoice and final invoice share `invoice.blade.php` plus section partials (`head`, `issued-by`, `guest-billing`, `cruise`, `lines`, `fees`, `ancillary`, `totals`, `schedule`, `history`, `wire`, `footer`). One view each for summary, receipt, voucher, pretrip, wire-instructions. `proof.blade.php` stays for `PdfRendererTest`.

`DocumentView` maps `INVOICE` / `FINAL_INVOICE` → `documents.invoice` and the other kinds to their views.

**Two HTML constraints**
- **Library layout:** every two-column block is a two-cell `.dg2` table. Fonts for the stored PDF stay registered from `resources/fonts/documents/` in `PdfRenderer` (chroot, remote off).
- **Print CSS:** `@media print` + `@page` A4 so the panel iframe `print()` in task 06 matches the server PDF. The HTML is the document only (no panel chrome). Page-break rules keep section heads with their tables.

**Fonts — `/html` vs PDF.** The panel (task 06) shows `/html` inside `<iframe srcdoc>`. Relative `/fonts/…` URLs would resolve against the **panel** origin and 404 if we copied files to the API `public/`. We do **not** copy fonts to `public/`. `DocumentHtml::render(..., $inlineFonts = true)` replaces the `/* DOCUMENT_FONTS */` placeholder with `@font-face` for Oswald, Archivo and IBM Plex Mono as **base64 `data:` URIs**. `PdfRenderer` / `IssueDocument` render without that replacement and keep the local TTF files via chroot, so stored PDFs stay small. Task 06 verifies the fonts render in the iframe.

mPDF stays the documented fallback. Invoice tables laid out on dompdf; no switch.

### Snapshots never compute money (J10)
One builder per kind under `app/Support/Documents/Snapshots/`. `SnapshotFactory::build(Booking, DocumentKind, ?Payment, bool $fresh)` is the only dispatcher. Blade formats with `Money::formatDocument` (`26,600.00`); it does not add, multiply or round.

| Figure | Source |
|---|---|
| Vessel subtotal | `bookings.total` |
| Fees subtotal | `feesCollected*` |
| Ancillary | `extrasTotal*` |
| Invoice total | `chargesTotal*` |
| Paid | `Ledger::paid*` |
| Balance | `balance*` |
| Cruise outstanding | `cruiseOutstanding*` |
| Deposit amount | `depositAmount()` |
| Vessel rows | frozen `price_lines` (`code`, `label`, `amount`) — never `CabinPricer` / `quote()` |
| PNG rows | guests grouped by stored `png_category` label + engine `exempt_under_age`, stored `png_fee` |
| TCT | guest count × stored `tct_rate_usd` (current engine TCT only when uncollected / information-only) |
| Extras | frozen `booking_extras` (`name`, `qty`, `rate_usd`) |
| Payment history | ledger rows (`reference`, `paid_at`, `method->label()`, `amount`; refunds stay negative) |
| Cancellation | `CurrentConfig` `cancellation.bands` + `CancellationPenalty::label` |
| Issuer / bank | `legal_entity` / `legal_entity.bank` |
| Due hours / wire window | `payments.extras_due_hours` / `payments.wire_window_hours` |

Personal data: names only. Every snapshot asserts `SensitiveFields::keysIn($snapshot) === []`.

**Receipt balance:** `chargesTotal* −` the sum of settled ledger rows **up to and including this payment** (`paid_at`, then `id`), not `balance()` today.

**Voucher:** 422 without an extra whose catalogue item has `triggers_transfer_voucher`. Arrival = departure minus one day when an `HPRE` extra exists, else departure day. Transfer line follows the prototype (airport → hotel → pier vs airport → pier).

**Wire document:** amount = `depositAmount()` when `PENDING_PAYMENT`, else `balance*`. Bank block from `legal_entity.bank`. Payment reference = `{booking reference} — include in all transfers`. If any bank field is `[TBD]`, `placeholders: true` and the template states the details must not be used.

### Lock-before-snapshot
`PrepareIssueDocument` opens the transaction and calls `BookingMutationLock::acquire` **before** building the snapshot, then builds with the `*Fresh()` helpers, then calls `IssueDocument`. `IssueDocument`'s own `acquire` is a nested re-lock of the same rows (no-op wait), then it stamps `number` / `version` / `issued_at` / `kind` into the snapshot **before** `view()->render()` and stores that same array. Preview GETs may use the cached methods — they store nothing.

A snapshot built outside the lock can freeze stale figures into an immutable document (J2). `PrepareIssueDocumentConcurrencyTest` records a payment on a second connection while the issue transaction still holds the lock: the payment waits with **1205**, never 1213, and the stored snapshot's `paid` stays `0` (the concurrent payment is not half-reflected). After commit the payment records normally.

### Invoice mapping (mockup v6 / `invoiceHtml`)
1. Header — `BOOKING CONFIRMATION & INVOICE` / `FINAL INVOICE`, booking reference, invoice number + version (null / next on preview; stamped on issue), date, balance due date, status `AWAITING DEPOSIT` / `DEPOSIT RECEIVED` / `PAID IN FULL` (final always paid in full).
2. Issued by — `legal_entity`.
3. Guest & billing — first two guest names; billing address (empty → `[captured at payment link]`); email/phone fall back to the contact; agent when `agency_id` set; group + coordinator when present.
4. Cruise — **real yacht** (not hard-coded ANAMARA), embark/return from itinerary + `returnDate()`, itinerary name, **duration from `itinerary.nights`** (not “7 Nights”), guest names.
5. Vessel charges — frozen `price_lines`.
6. Galápagos fees — collected table + information-only table and the prototype notes.
7. Ancillary — extras or “No additional services contracted.”
8. Three subtotals + invoice total.
9. Payment schedule — prototype wording; extras due hours from rules; `✓ RECEIVED` when deposit/cruise is received.
10. Cancellation (`CancellationPenalty::label`) + OPS-005 insurance sentence.
11. Payment history + current balance.
12. Wire block from `legal_entity.bank` + payment reference.
13. Footer from `legal_entity` (not hard-coded emails).

**Deviations from the prototype (required):** real yacht; nights from the itinerary; stored price lines instead of a re-quote; issuer/bank from rules; table layout instead of `.dg2` flex.

### Billing (J8)
Nullable `billing_name`, `billing_address`, `billing_email`, `billing_phone` on `bookings`. `UpdateBookingBilling` takes the booking lock and writes `booking.billing_changed` only when something changed. `PATCH /api/rms/bookings/{booking}/billing` — own-records (`updateBilling` = `ownsOrMayActOnAny`; Lucía can preview Mateo's booking, cannot edit billing). The four fields are on `BookingResource`. Seed persists `845 Ocean Drive…` and `+1 305 555 0198` on `ANK-2026-0003`. Contact name/email remain the display defaults when columns are empty.

The public capture page is **Sprint 8**.

### Endpoints
| Method | Path | Auth | Result |
|---|---|---|---|
| GET | `/bookings/{booking}/documents` | `view` | issued rows, all versions, newest first |
| GET | `/bookings/{booking}/documents/{kind}/html` | `view` | live HTML, not stored; base64 `@font-face` |
| GET | `/bookings/{booking}/receipts/{payment}/html` | `view` | live receipt HTML; same font inlining |
| POST | `/bookings/{booking}/documents/{kind}/issue` | `issueDocument` (own-records) | `{ reason?, payment_id? }` → `DocumentResource` (201). `payment_id` required when `RECEIPT`. Reason required for version > 1 |
| GET | `/documents/{document}/html` | `view` on the document’s booking | HTML **from stored snapshot**; same font inlining |
| GET | `/documents/{document}/file` | `view` | stored PDF |

`DocumentResource` (`$wrap = null`): `id`, `booking_id`, `kind`, `kind_label`, `number`, `version`, `reason`, `payment_id`, `issued_at`, `file_sha256`, `issued_by`. In `PanelResponseSchemasTest`. Preview of `RECEIPT` at `…/documents/RECEIPT/html` is 422 (use `/receipts/{payment}/html`).

No send, no `/documents/plan` (task 04).

### Seed
`DemoDocumentsSeeder` runs after `DemoGuestsSeeder` and `DemoExtrasSeeder`. Idempotent, `system: true`, through `PrepareIssueDocument` (never sends): invoice + summary for every `CONFIRMED` and `FULLY_PAID` booking (skip if a row exists); one receipt per seeded settled positive payment (unique `(payment_id, kind)` makes a re-seed a no-op).

### Fidelity vs screenshots 16 / 17
Compared the constructed invoice HTML/PDF against `16-invoice.png` and `17-invoice-totals.png`, section by section. Same section order, labels, money with cents, status line, issued-by / guest-billing pair, collected vs information-only fees, three subtotals + invoice total, schedule wording.

**Library-forced / required differences**
- Two-cell tables instead of the prototype's `.dg2` flex/grid columns (spacing is a hair different).
- Font metric drift (dompdf vs the browser's Oswald / Archivo / IBM Plex Mono).
- Page break: constructed booking with extras + collected/information fees is **2 A4 pages**. The mockup is a single scrolled panel view with chrome (Print / save PDF, Close) that our HTML does not include.
- Real yacht and itinerary nights, not the prototype's hard-coded ANAMARA / “7 Nights”.
- Bank block prints `[TBD]` until LEG-004.
- Panel iframe print of the same HTML is verified in task 06.

### Invoice PDF page count
**2** pages for the constructed booking with extras (FLT × 2) used by `DocumentTemplatesTest`.

### Checks
`composer check` (Pest, Pint, Larastan ≥ 6) passed.

### Notes for later
- `seed-data.json` gives `ANK-2026-0003` FLT/HPRE/HPOST and collected PNG/TCT, but `DemoExtrasSeeder` only attaches FLT→0011, HPRE→0007, PNG→0009. Fidelity / page-count tests construct a booking rather than changing the Sprint 6 extras seed.
- Task 06: Documents tab, iframe `srcdoc` font check, browser print.
- Task 03: email. Task 04: auto-issue and `/documents/plan`. Sprint 8: public billing capture.

### Files
See git commands below. Do not run them from the agent.

```bash
git add app/Actions/Bookings/UpdateBookingBilling.php
git add app/Actions/Documents/IssueDocument.php
git add app/Actions/Documents/PrepareIssueDocument.php
git add app/Http/Controllers/Rms/BookingBillingController.php
git add app/Http/Controllers/Rms/DocumentController.php
git add app/Http/Requests/Rms/IssueDocumentRequest.php
git add app/Http/Requests/Rms/UpdateBookingBillingRequest.php
git add app/Http/Resources/Rms/BookingResource.php
git add app/Http/Resources/Rms/DocumentResource.php
git add app/Models/Booking.php
git add app/Policies/BookingPolicy.php
git add app/Services/Documents/DocumentHtml.php
git add app/Services/Documents/DocumentView.php
git add app/Support/Documents/Snapshots/DocumentFacts.php
git add app/Support/Documents/Snapshots/InvoiceSnapshot.php
git add app/Support/Documents/Snapshots/PretripSnapshot.php
git add app/Support/Documents/Snapshots/ReceiptSnapshot.php
git add app/Support/Documents/Snapshots/SnapshotFactory.php
git add app/Support/Documents/Snapshots/SummarySnapshot.php
git add app/Support/Documents/Snapshots/VoucherSnapshot.php
git add app/Support/Documents/Snapshots/WireInstructionsSnapshot.php
git add app/Support/Money.php
git add database/migrations/2026_09_21_200039_add_billing_details_to_bookings.php
git add database/seeders/DatabaseSeeder.php
git add database/seeders/DemoBookingsSeeder.php
git add database/seeders/DemoDocumentsSeeder.php
git add resources/views/documents/invoice.blade.php
git add resources/views/documents/layout.blade.php
git add resources/views/documents/partials
git add resources/views/documents/pretrip.blade.php
git add resources/views/documents/receipt.blade.php
git add resources/views/documents/summary.blade.php
git add resources/views/documents/voucher.blade.php
git add resources/views/documents/wire-instructions.blade.php
git add routes/api/rms.php
git add tests/Concurrency/IssueDocumentConcurrencyTest.php
git add tests/Concurrency/PrepareIssueDocumentConcurrencyTest.php
git add tests/Feature/Bookings/UpdateBookingBillingTest.php
git add tests/Feature/Documents/DemoDocumentsSeederTest.php
git add tests/Feature/Documents/DocumentEndpointsTest.php
git add tests/Feature/Documents/DocumentSnapshotTest.php
git add tests/Feature/Documents/DocumentTemplatesTest.php
git add tests/Feature/Documents/DocumentTriggersTest.php
git add tests/Feature/Documents/IssueDocumentTest.php
git add tests/Feature/OpenApi/PanelResponseSchemasTest.php
git add tests/Feature/__snapshots__
git add docs/sprints/sprint-07/REPORT.md
git commit -m "$(cat <<'EOF'
Add document templates, preview/issue endpoints, and booking billing.

EOF
)"
```

## Task 03 · Email delivery (SMTP)

### Transport
Laravel’s `smtp` mailer only. No Microsoft Graph package, no `graph` mailer, no Graph env keys. **Deviation from J5 / TEC-002 / the task file:** production is ordinary SMTP (`MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD`), not Exchange via Graph. TEC-002 stays a client question.

Local, testing and e2e stay on Mailpit (`mailpit:1025`, UI `:8025`). Pest uses `MAIL_MAILER=array` except the tagged Mailpit test, which switches to SMTP and self-skips if Mailpit is down. Document sends never use the `failover` mailer. `From` is `MAIL_FROM_ADDRESS`. `Reply-To` is `legal_entity.email`.

### Delivery log
`deliveries` is append-only except the status lifecycle (`QUEUED → SENT | FAILED`), H1-style: DELETE refused; identifying columns frozen. `BLOCKED` is written once and not updated.

**Idempotency keys**

| Kind | First send | Staff resend |
|---|---|---|
| Invoice / final / summary / voucher / wire | `{kind_lower}:{document_id}` | `resend:{document_id}:{uuid}` |
| Receipt | `receipt:{payment_id}` | `resend:{document_id}:{uuid}` |
| Reminder | `reminder:{booking_id}:{due_date}:{days}` | `resend:reminder:{booking_id}:{due_date}:{days}:{uuid}` |
| Pre-trip | `pretrip:{booking_id}:{departure_date}` | `resend:{document_id}:{uuid}` |
| Payment link | `payment_link:{link_id}` | `resend:payment_link:{link_id}:{uuid}` |

Inserting an existing key is a no-op. **BLOCKED uses `{key}:blocked`** so adding an email later can still send on the real key.

### Recipients (J6)
`App\Support\Documents\Recipients`: billing email when set, else contact; group bookings go to the coordinator; invoices CC the agency; the summary goes to the lead guest when they have an email. No usable address → `BLOCKED` with the reason, never skipped.

### Sending
`SendDocument` / `SendPaymentRequest` resolve recipients, insert the delivery, dispatch `SendDeliveryJob` after commit. The job attaches the stored PDF (never re-rendered). History: `document.sent` / `document.send_failed`, or `payment_request.sent` / `payment_request.send_failed`.

`SendDeliveryJob`: `$tries = 3`, `$backoff = [60, 300]`. Intermediate failures leave the row `QUEUED`. `failed()` is the only place that writes `FAILED` / `send_failed`. Already-`SENT` returns without sending again. Horizon’s supervisor `tries: 1` is left alone; this job’s `$tries` wins.

Reminder and payment-link mailables exist so task 04 can call them. This task never schedules them.

### Manual sends

| Method | Path | Auth |
|---|---|---|
| POST | `/api/rms/documents/{document}/send` | `issueDocument` (own-records) |
| POST | `/api/rms/payment-links/{link}/send` | `payments.record`; open links only |
| POST | `/api/rms/bookings/{booking}/wire-instructions/send` | `payments.record` |
| GET | `/api/rms/bookings/{booking}/deliveries` | `view` |

The HTTP response is `QUEUED` (201); the job then marks `SENT`. Wire send issues the document if none exists. LEG-004 warning: `Bank details are placeholders (LEG-004) and must not be used.`

### Checks
`composer check` (Pest 842, Pint, Larastan ≥ 6) passed.

### Notes for later
- Task 04: auto-send on triggers; seed deliveries as already `SENT`; `/documents/plan`.
- Task 06: Documents tab resend / payment-link email.

### Files
See git commands below. Do not run them from the agent.

```bash
git add .env.example
git add README.md
git add app/Actions/Documents/RecordDelivery.php
git add app/Actions/Documents/SendDocument.php
git add app/Actions/Documents/SendPaymentRequest.php
git add app/Enums/DeliveryKind.php
git add app/Enums/DeliveryStatus.php
git add app/Enums/DeliveryTriggeredBy.php
git add app/Http/Controllers/Rms/DeliveryController.php
git add app/Http/Controllers/Rms/DocumentController.php
git add app/Http/Controllers/Rms/PaymentLinkController.php
git add app/Http/Requests/Rms/SendDocumentRequest.php
git add app/Http/Requests/Rms/SendPaymentLinkRequest.php
git add app/Http/Requests/Rms/SendWireInstructionsRequest.php
git add app/Http/Resources/Rms/DeliveryResource.php
git add app/Jobs/SendDeliveryJob.php
git add app/Mail/Documents
git add app/Models/Booking.php
git add app/Models/Delivery.php
git add app/Models/Document.php
git add app/Support/Documents/DeliveryKey.php
git add app/Support/Documents/DeliverySubject.php
git add app/Support/Documents/IssuerMail.php
git add app/Support/Documents/RecipientSet.php
git add app/Support/Documents/Recipients.php
git add app/Support/Documents/WireWarning.php
git add database/factories/DeliveryFactory.php
git add database/migrations/2026_09_21_200040_create_deliveries_table.php
git add resources/views/mail/documents
git add routes/api/rms.php
git add tests/Feature/Documents/DeliveryEndpointsTest.php
git add tests/Feature/Documents/DeliveryTriggersTest.php
git add tests/Feature/Documents/MailpitDeliveryTest.php
git add tests/Feature/Documents/RecipientsTest.php
git add tests/Feature/Documents/SendDocumentTest.php
git add tests/Feature/OpenApi/PanelResponseSchemasTest.php
git add docs/sprints/sprint-07/REPORT.md
git commit -m "$(cat <<'EOF'
Add SMTP document email, the delivery log, and manual send endpoints.

EOF
)"
```

## Task 04 · Triggers, schedule, and document status

### Domain events (J7)
Three after-commit events, queued listeners (`ShouldQueue`, sync in tests). Never listen to `change_history`. Never send inside the writing transaction.

| Event | Raised from | Documents |
|---|---|---|
| `BookingStatusChanged` | `TransitionBooking` when `$from !== $to` (deposit confirm, manual CONFIRMED/FULLY_PAID, `DecideCommissionCap` unblocking ON_HOLD via `ApplyPaymentEffects`) | CONFIRMED / confirmed-or-later (not PENDING / ON_HOLD): invoice + summary. FULLY_PAID: final invoice. Cancelled / released / requested: nothing. |
| `PaymentSettled` | Newly settled positive rows from `RecordPayment` (not `AWAITING_WIRE`), `MarkWireReceived`, `SettleGatewayPayment` (skip idempotent “gateway id already exists”) | Receipt for that payment. If the booking is FULLY_PAID, fresh balance is zero, a final invoice already exists, and charge-side totals differ: final invoice vN+1, reason `Balance paid after charges changed`. |
| `BookingChargesChanged` | Top-level extras/fees/move/guest actions only (not `ApplyPng`) | New invoice version only when an invoice already exists, the booking is confirmed-or-later, and **charge-side** totals differ (`vessel`, `fees_collected`, `extras`, `charges_total`, `information_total` — not `paid` / `balance`). |

### Issue-once (retry-safe)
`IssueOnce` for `INVOICE`, `SUMMARY`, `FINAL_INVOICE`: if no document of that kind exists, `PrepareIssueDocument` then `SendDocument`; if one exists, do **not** issue again — `SendDocument` the latest version with `DeliveryKey::forDocument` (no-op when already SENT/FAILED/QUEUED). A retried CONFIRMED listener after a successful issue keeps invoice **v1** and one delivery.

Charge-change and the FULLY_PAID-after-charges path are the only automatic v2+ issues (they pass a reason).

### One version per change
The charges listener re-reads the booking and compares a fresh invoice snapshot to the latest version. Two events in one request (add + remove netting to zero) see the same final booking: the first issues at most once, the second is a no-op.

### Final invoice after later charges
I9: adding a charge after FULLY_PAID does not move the status back. The confirmation invoice may already have been re-issued by `SendOnBookingChargesChanged`. When the new balance is paid, `SendOnPaymentSettled` issues the next final-invoice version (`Balance paid after charges changed`) and sends it once, plus the extra’s receipt.

### Schedule
`anakata:documents-due`, daily in `Pacific/Galapagos`, `--dry-run` lists and writes nothing.

**Reminders** (CONFIRMED or ON_HOLD_AGENCY, cruise balance open): eligibility from **current facts only** (never `change_history`) — `sendDate <= today`, `balanceDueDate() > today`, cruise still open. Catch-up sends **only the smallest eligible N**. Copy uses **actual days remaining** (`due − today`), not N; the key stays `reminder:{booking_id}:{due_date}:{N}`. OPS-007 extensions move the keys. After the due date, or once `cruiseOutstanding() === 0`, nothing is sent.

**Pre-trip** at `departure − documents.pretrip_days_before` (45). **Voucher** at T−7 only with a transfer-triggering extra. Schedule keys `pretrip:{booking_id}:{departure_date}` / `voucher:{booking_id}:{departure_date}` so a re-run is a no-op.

### Shape change
`documents.pretrip_days_before` 45 and `documents.voucher_days_before` 7 on `BusinessRulesDocument`. DML migration publishes as System. Registry: all **67** · here **42**.

`fromArray()` defaults a missing documents object to **0 / 0** (same lenient-zero pattern as other ints) so ConfigPublisher records a real 0 → 45 / 0 → 7 change. `initial()` stays 45 / 7. `anakata:config-verify` still fails a latest row that is missing the keys.

### DocumentPlan statuses (J9)
Computed, never stored. One builder for `GET /bookings/{booking}/documents/plan` (`view`) and `GET /documents` (`viewAny`, departure window, paginated).

SENT · FAILED · BLOCKED · SCHEDULED · WAITING · NOT NEEDED · NOT CONTRACTED · DUE.

Questionnaire is always WAITING, trigger `T−45 · Arrives in Sprint 11`, all `can_*` false. Wire instructions appear only once issued.

### Seed never sends
`DemoDocumentsSeeder` writes `SENT` deliveries with `DeliveryKey::forDocument` (demo fixture emails often fail RFC validation; the seeder still marks SENT so a later trigger for the same key is a no-op). No `SendDocument`, no events. `Mail::fake()` asserts nothing sent.

### Checks
`composer check` (Pest 869, Pint, Larastan ≥ 6) passed.

### Notes for later
- Task 05 regenerates OpenAPI types (`DocumentPlanRowResource`).
- Task 06 Documents tab consumes `/documents/plan`.
- Task 07 Client documents consumes `GET /documents`.
- Task 08 e2e: Mailpit empty after `reset.sh`, DOC scenarios.
- Questionnaire send remains Sprint 11.

### Files
See git commands below. Do not run them from the agent.

```bash
git add app/Actions/Bookings/MoveBooking.php
git add app/Actions/Bookings/TransitionBooking.php
git add app/Actions/Documents/SendDocument.php
git add app/Actions/Extras/AddBookingExtra.php
git add app/Actions/Extras/RemoveBookingExtra.php
git add app/Actions/Extras/UpdateBookingFees.php
git add app/Actions/Guests/AddGuest.php
git add app/Actions/Guests/RemoveGuest.php
git add app/Actions/Guests/UpdateGuest.php
git add app/Actions/Payments/MarkWireReceived.php
git add app/Actions/Payments/RecordPayment.php
git add app/Actions/Payments/SettleGatewayPayment.php
git add app/Console/Commands/DocumentsDueCommand.php
git add app/Enums/DocumentPlanKind.php
git add app/Enums/DocumentPlanStatus.php
git add app/Events/BookingChargesChanged.php
git add app/Events/BookingStatusChanged.php
git add app/Events/PaymentSettled.php
git add app/Http/Controllers/Rms/ClientDocumentController.php
git add app/Http/Controllers/Rms/DocumentController.php
git add app/Http/Requests/Rms/IndexClientDocumentsRequest.php
git add app/Http/Resources/Rms/DocumentPlanRowResource.php
git add app/Listeners/SendOnBookingChargesChanged.php
git add app/Listeners/SendOnBookingStatusChanged.php
git add app/Listeners/SendOnPaymentSettled.php
git add app/Mail/Documents/DeliveryMailFactory.php
git add app/Providers/AppServiceProvider.php
git add app/Support/BusinessRules/Registry.php
git add app/Support/Config/Documents/BusinessRulesDocument.php
git add app/Support/Config/Documents/DocumentsRules.php
git add app/Support/Documents/ChargeSideTotals.php
git add app/Support/Documents/DeliveryKey.php
git add app/Support/Documents/DocumentPlan.php
git add app/Support/Documents/DocumentPlanRow.php
git add app/Support/Documents/IssueOnce.php
git add app/Support/Documents/Snapshots/DocumentFacts.php
git add database/migrations/2026_09_21_200041_add_documents_schedule_to_business_rules.php
git add database/seeders/DemoDocumentsSeeder.php
git add routes/api/rms.php
git add routes/console.php
git add tests/Feature/Config/AddDocumentsScheduleToBusinessRulesMigrationTest.php
git add tests/Feature/Config/BusinessRulesEndpointsTest.php
git add tests/Feature/Config/BusinessRulesSeederTest.php
git add tests/Feature/Documents/ClientDocumentsIndexTest.php
git add tests/Feature/Documents/DemoDocumentsSeederTest.php
git add tests/Feature/Documents/DocumentChargeChangeTest.php
git add tests/Feature/Documents/DocumentEndpointsTest.php
git add tests/Feature/Documents/DocumentEventTriggersTest.php
git add tests/Feature/Documents/DocumentPlanTest.php
git add tests/Feature/Documents/DocumentsDueCommandTest.php
git add tests/Feature/OpenApi/PanelResponseSchemasTest.php
git add tests/Feature/Payments/RecordPaymentTest.php
git add tests/Feature/Payments/StripeWebhookTest.php
git add docs/sprints/sprint-07/REPORT.md
git commit -m "$(cat <<'EOF'
Wire document triggers, the daily schedule, and computed document status.

EOF
)"
```

## Task 05 · anakata-ui · Regenerate types, release `v0.8.0`

### What was built
PHPDoc / OpenAPI prelude on the API so Scramble emits the Sprint 7 document, delivery, plan, billing and send shapes, then types regenerated against `http://localhost:8000/docs/api.json`. Layer `0.7.1` → `0.8.0`. Types only: no composables, components, or panel behaviour.

Calendar dates stay `string` (`YYYY-MM-DD`). Instants stay ISO strings.

HTML preview (`GET …/html`) and PDF download (`GET …/file`) return `text/html` / `application/pdf`. They have no JSON body type — the panel uses the URL.

### API prelude

| Target | What landed |
|---|---|
| `DeliveryResource.warning` | Was `type: null` (public `?string $warning = null`). Local `@var string\|null` so Scramble emits `string \| null` (LEG-004). |
| `document.issue` / `document.send` / `paymentLink.send` / `delivery.sendWire` | `#[DocumentedResponse(status: 201, …)]`. Runtime already 201 via `wasRecentlyCreated`. |
| `PanelResponseSchemasTest` | `DocumentResource` keys; `DocumentPlanRowResource` keys + `can_*` booleans; `DeliveryResource` keys + `warning`; Booking billing keys; `GET /documents` `from`/`to`; named enums `DocumentKind` (includes `WIRE_INSTRUCTIONS`), `DocumentPlanKind`, `DocumentPlanStatus`; send/issue 201 JSON; HTML/PDF content types. |

`DeliveryKind` and `DeliveryStatus` have no FormRequest schema. Resource fields for those stay `string`; the layer leftovers close the unions.

`can_preview` / `can_issue` / `can_resend` already emit `boolean`. Billing scalars already emit `string \| null`. No extra PHPDoc on those.

### Line counts

| File | Before | After |
|---|---|---|
| `app/types/api.d.ts` | 7492 | 8220 |
| `app/types/inventory.ts` | 247 | 247 |
| `app/types/config.ts` | 401 | 428 |
| `app/types/bookings.ts` | 208 | 208 |
| `app/types/payments.ts` | 161 | 161 |
| `app/types/index.ts` | 204 | 219 |
| `app/types/anakata-augment.d.ts` | 17 | 17 |
| `app/types/guests.ts` | 101 | 101 |
| `app/types/extras.ts` | 24 | 24 |
| `app/types/documents.ts` | — | 59 |

### Schema → alias (`app/types/documents.ts`)

| Alias | Source |
|---|---|
| `DocumentKind` | named enum schema (includes `WIRE_INSTRUCTIONS`) |
| `DocumentPlanKind` | named enum schema — extra alias: plan/client `kind` is not `DocumentKind` |
| `DocumentStatus` | named `DocumentPlanStatus` schema |
| `IssuedDocument` | `DocumentResource` + `kind: DocumentKind` |
| `DocumentPlanRow` | `DocumentPlanRowResource` + `kind` / `status` overlays. `can_*` stay generated booleans |
| `ClientDocumentRow` | same as `DocumentPlanRow` (`GET /documents` paginates that resource) |
| `DeliveryKind` | leftover (`INVOICE` … `WIRE_INSTRUCTIONS`, plus `REMINDER` / `PAYMENT_LINK`) — mirrors `App\Enums\DeliveryKind` |
| `DeliveryStatus` | leftover (`QUEUED` \| `SENT` \| `FAILED` \| `BLOCKED`) — mirrors `App\Enums\DeliveryStatus` |
| `Delivery` | `DeliveryResource` + kind/status overlays. `warning: string \| null` comes through |

### Booking

Same `Booking` alias. Scalars `billing_name`, `billing_address`, `billing_email`, `billing_phone` come through from `BookingResource`. No new overlays.

### Business rules

`BusinessRulesDocument` leftover gains sibling `legal_entity` (`LegalEntity` / `BankDetails`) and `documents` (`DocumentsRules`). `legal.consent_versions` is unchanged. `RuleGroup` is unchanged (`documents.*` is `pricing_payments`; `legal_entity.*` is `legal`).

### Kept leftovers

No inventory leftovers retired. New leftovers listed above, each with a `Mirrors App\…` comment.

No document-kind / status / label runtime list in the layer. Names and trigger wording come from the API.

### Pins

Panel and engine README rows now say `` `extends: ['../anakata-ui']` (`v0.8.0`) ``. **Neither pin is enforced** — the apps resolve the sibling folder, so the version line is documentation only.

### Files touched
**anakata-api (prelude)**
- `app/Http/Resources/Rms/DeliveryResource.php`
- `app/Http/Controllers/Rms/DocumentController.php`
- `app/Http/Controllers/Rms/DeliveryController.php`
- `app/Http/Controllers/Rms/PaymentLinkController.php`
- `tests/Feature/OpenApi/PanelResponseSchemasTest.php`

**anakata-ui**
- `app/types/api.d.ts`
- `app/types/documents.ts` (new)
- `app/types/config.ts`
- `app/types/index.ts`
- `package.json` (`0.8.0`)
- `CHANGELOG.md`
- `README.md`

**anakata-panel / anakata-engine**
- `README.md` (documentation pin only)

**anakata-api (this report)**
- `docs/sprints/sprint-07/REPORT.md`

### Deviations
- `DocumentPlanKind` is an extra alias (not in the task list) because the plan and client-documents tables iterate `DocumentPlanKind` (`REMINDER`, `QUESTIONNAIRE`), not `DocumentKind`.
- `ClientDocumentRow` is the same schema as `DocumentPlanRow`.
- `LegalEntity` / `BankDetails` / `DocumentsRules` are extra leftovers so `BusinessRulesDocument` stays readable.

### Open questions
None.

### Notes for later
- Task 06: Documents tab from `DocumentPlanRow`; preview URLs only (no JSON type); billing from `Booking`; payment-link / wire send from `Delivery.warning`.
- Task 07: Client documents from `ClientDocumentRow` / `GET /documents`.

### Quality
- anakata-api: `composer check` inside Docker — 869 tests (5944 assertions), Pint (851 files), Larastan level 6 (0 errors).
- anakata-ui: lint, typecheck, test (35), build — pass.
- anakata-panel / anakata-engine: typecheck — pass.
- Fresh clone into `/tmp/anakata-fresh/{anakata-ui,anakata-panel,anakata-engine}` (sibling layout). Overlayed the working trees. Confirmed the ui clone is **0.8.0** and has **no** `app/types/nuxt.d.ts`.
  - ui / panel / engine: typecheck pass
  - panel / engine: build pass
  - **OVERLAY CLONE OK**
  - **Repeat this clone after the pushes below**, checking out `anakata-ui` at `v0.8.0` with **no** overlay.

### Git commands for the user

Do **not** run these in the agent. Explicit paths only (never `-A`). Run in this order.

```bash
# 1. anakata-api prelude (OpenAPI typing — not this report)
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Http/Resources/Rms/DeliveryResource.php \
  app/Http/Controllers/Rms/DocumentController.php \
  app/Http/Controllers/Rms/DeliveryController.php \
  app/Http/Controllers/Rms/PaymentLinkController.php \
  tests/Feature/OpenApi/PanelResponseSchemasTest.php
git commit -m "$(cat <<'EOF'
Type Sprint 7 document, delivery and billing OpenAPI responses.

Send and issue emit 201 DeliveryResource / DocumentResource; wire
send keeps warning as string|null so the layer can regenerate it.
EOF
)"
```

```bash
# 2. anakata-ui — commit, then tag, then push HEAD and the tag
cd /home/mohammad/Code/iconic/anakata/anakata-ui
git add \
  package.json \
  CHANGELOG.md \
  README.md \
  app/types/api.d.ts \
  app/types/config.ts \
  app/types/index.ts \
  app/types/documents.ts
git commit -m "$(cat <<'EOF'
Regenerate API types for documents, deliveries and billing.

Sprint 7 aliases live in documents.ts; Booking keeps the same
name. legal_entity and documents leftovers stay in config.ts.
EOF
)"
git tag v0.8.0
git push origin HEAD
git push origin v0.8.0
```

```bash
# 3. anakata-panel
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add README.md
git commit -m "$(cat <<'EOF'
Document the layer pin as v0.8.0.

extends still resolves the sibling folder; the version is documentation only.
EOF
)"
git push origin HEAD
```

```bash
# 4. anakata-engine
cd /home/mohammad/Code/iconic/anakata/anakata-engine
git add README.md
git commit -m "$(cat <<'EOF'
Document the layer pin as v0.8.0.

extends still resolves the sibling folder; the version is documentation only.
EOF
)"
git push origin HEAD
```

```bash
# 5. anakata-api report
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-07/REPORT.md
git commit -m "$(cat <<'EOF'
Record sprint 7 task 05: regenerated UI API types.
EOF
)"
git push origin HEAD
```

```bash
# 6. Fresh-clone repeat — after the pushes, no working-tree overlay
rm -rf /tmp/anakata-fresh
mkdir -p /tmp/anakata-fresh
git clone https://github.com/anakata-project/anakata-ui.git /tmp/anakata-fresh/anakata-ui
git -C /tmp/anakata-fresh/anakata-ui checkout v0.8.0
git clone https://github.com/anakata-project/anakata-panel.git /tmp/anakata-fresh/anakata-panel
git clone https://github.com/anakata-project/anakata-engine.git /tmp/anakata-fresh/anakata-engine
# then in each: pnpm install
# ui / panel / engine: pnpm typecheck
# panel / engine: pnpm build
```

## Task 06 · anakata-panel · Booking panel Documents tab, receipts, payment-link email, billing details

### Tab
Documents is enabled (`disabled: false`, `arrivesSprint: null`). No booking tab is disabled. `BookingDocumentsTab` mounts on first visit (`v-else-if="tab === 'documents'"`) and emits `updated` into the existing `onPaymentsUpdated`, so Overview, the list, and History reload.

On mount: `GET /api/rms/bookings/{id}/documents/plan` and `GET /api/rms/bookings/{id}/documents`. The note, columns, name, `to {recipient}`, trigger, date and status are the plan row as sent. Pill classes are presentation only (`documentStatusPillClass` / prototype `DSTC`; `FAILED` uses `p-canc`). Questionnaire stays WAITING with the API trigger “Arrives in Sprint 11” and no buttons.

Helpers live in `app/components/documents/` so task 07 can import them: `documentStatusPillClass`, `documentRowActions` (`can_*` only), `versionLabel`, `invoiceHasBeenIssued`, `issuedVersionsFor`. Types re-exported from `app/types/api.ts`: `DocumentPlanRow`, `IssuedDocument`, `Delivery`, `DocumentKind`, `DocumentStatus`.

### Actions and permissions
Buttons only when the matching `can_*` is true.

| Action | When | Request |
|---|---|---|
| Preview | `can_preview` | Issued: `GET /documents/{id}/html`. Live: `GET …/documents/{kind}/html`. Receipt with no `document_id`: `GET …/receipts/{payment_id}/html` (never the kind URL). |
| Resend | `can_resend` | Confirm (“Send {name} to {recipient} again?” — plan facts), then `POST /documents/{id}/send`. |
| Issue | `can_issue` and `version === null` | `POST …/documents/{kind}/issue` with no reason. |
| Re-issue | `can_issue` and a version exists | `ReasonModal`, reason required, then the same POST with `{ reason }`. |

FAILED: `error` under the row and Resend. BLOCKED: why plus “Edit billing details” (`emit('openBilling')` → Overview). After every write: reload plan + issued list and `emit('updated')`.

Versions: more than one issued row for that kind (invoice: same `kind`; receipt: same `payment_id`) shows `versionLabel` and earlier versions that open snapshot HTML on that issued id.

No `window.confirm`. `DocumentConfirmModal` is the shared confirm (same shape as `ConfirmRequestModal`). Payment-link and wire confirms stay neutral — they do not quote a panel-computed recipient.

### Preview
`DocumentPreviewModal`: HTML fetched as **text** with native `fetch` + `credentials: 'include'` against `runtimeConfig.public.apiBase` (`documentFetch.ts`). Layer `useApi()` / `$fetch` stay JSON. `<iframe srcdoc>` with `sandbox="allow-same-origin allow-modals"` (no scripts). **Print / save PDF** → `iframe.contentWindow.print()`. **Download PDF** only when an issued `document_id` exists: `GET /documents/{id}/file` as blob, filename from `Content-Disposition` (fallback `{kind}-v{version}.pdf`). No PDF library.

### Payments tab
- **Receipt** on each settled `amount > 0` row that has an issued `RECEIPT` for that `payment_id`. Same preview modal on `GET /documents/{id}/html`.
- Sprint 7 copy removed (`payments.linkManual` / `bookings.linkManual`).
- **Send by email** on each `OPEN` link when `payments.record`. Neutral confirm: “Email this payment link to the client?” Then `POST /payment-links/{id}/send`. The 201 `Delivery.to` is the recipient shown afterwards.
- **Last payment-link email once, at tab level.** `GET …/deliveries`, latest `PAYMENT_LINK` row: `Last payment-link email: {status} to {to} · {date}`. Hidden until one exists. Deliveries have no `payment_link_id`.
- **Send wire instructions** when `status === 'PENDING_PAYMENT'` and `payments.record`. Neutral confirm plus **Preview** (`GET …/documents/WIRE_INSTRUCTIONS/html`). Then `POST …/wire-instructions/send`. After send, show `Delivery.to`. If `Delivery.warning` is set, that exact string goes in a `.warnbox` and stays. Warning only from the send response — no LEG-004 logic, no `legal_entity.bank` read.

### Billing
Overview **Billing** block: name, address, email, phone. Empty billing fields show contact defaults muted (`contact.name` / `contact.email` / `contact.phone`; address stays muted empty). Edit when `can_act`. `PATCH /api/rms/bookings/{id}/billing` with `applyApiFormError`. Note: guests complete these themselves from Sprint 8.

Lucía on Mateo’s booking: `can_act` false → read-only; plan `can_resend` / `can_issue` false → preview only (API flags; not re-derived).

### Extras notice
`GET …/documents/plan` on the extras tab. Notice only when an invoice row has `document_id`. Not from booking status.

### History
`document.issued`, `document.sent`, `document.send_failed`, `booking.billing_changed`, `payment_request.sent`, `payment_request.send_failed` go through `after.what` (same extras pattern), then `compactDiff`. Tests in `describe.test.ts`.

### Files touched
**anakata-panel**
- `app/components/bookings/bookingHelpers.ts` / `tests/unit/bookingHelpers.test.ts`
- `app/components/bookings/BookingPanel.vue`
- `app/components/bookings/BookingBillingBlock.vue` (new)
- `app/components/documents/BookingDocumentsTab.vue` (new)
- `app/components/documents/DocumentPreviewModal.vue` (new)
- `app/components/documents/DocumentConfirmModal.vue` (new)
- `app/components/documents/documentHelpers.ts` (new)
- `app/components/documents/documentFetch.ts` (new)
- `tests/unit/documentHelpers.test.ts` (new)
- `tests/unit/documentFetch.test.ts` (new)
- `app/components/payments/BookingPaymentsTab.vue`
- `app/components/extras/BookingExtrasTab.vue`
- `app/components/history/describe.ts` / `tests/unit/describe.test.ts`
- `app/types/api.ts`
- `app/assets/css/bookings.css` / `eslint.config.mjs`
- `i18n/locales/en.json`

**anakata-api (this report)**
- `docs/sprints/sprint-07/REPORT.md`

No anakata-ui change.

### Deviations
- HTML/PDF use native `fetch` in the panel (`documentFetch.ts`), not a layer `responseType` pass-through.
- `invoiceHasBeenIssued` / `issuedVersionsFor` are extra helpers so the extras notice and version list stay out of the Vue files.
- Local compose could not write `storage/app/documents/{id}` (`Unable to create a directory`). Issue from the panel returned 500 and the tab showed that error. Environment, not a panel bug.

### Open questions
None.

### Notes for later
- Task 07: Documents & Manifests page — reuse `DocumentPreviewModal`, `documentHelpers`, `documentFetch`.
- Task 08: E2E. This task did not add scenarios.
- After `reset.sh` (e2e stack): seeded SENT invoice/summary/receipts, Mailpit for issue / payment-link / extras re-issue, Lucía preview-only, print A4, Download PDF, older-version snapshot. Not run here — no e2e `up.sh` / `reset.sh`.
- Repeat the sibling fresh clone at tagged `v0.8.0` **after** the pushes, with **no** overlay (task 05 still lists that).

### Quality
- anakata-panel: `pnpm lint`, `typecheck`, `test` (221), `build` — pass.
- Overlay clone against sibling `v0.8.0` (working-tree overlay; no `app/types/nuxt.d.ts`) — typecheck + build OK from task 05 / this session.
- Browser (Carolina, `ANK-2026-0003` Harrison & Whitfield, light theme; dark earlier in the same session). Local seed had no DemoDocuments SENT rows and no billing email:
  - Documents tab: BLOCKED + billing links, then after PATCH billing → DUE / SCHEDULED / WAITING / NOT CONTRACTED; questionnaire WAITING, no buttons; Preview + Issue from `can_*`.
  - Invoice preview: iframe HTML (PONTOS LLC / ANAMARA / vessel USD 26,600); Print enabled; no Download (unissued).
  - Extras: no invoice-issued notice (no `document_id`).
  - Payments: Sprint 7 copy gone; no receipt without an issued RECEIPT; no last-email line without a PAYMENT_LINK delivery; wire block hidden (not `PENDING_PAYMENT`).
  - Overview billing edit + save (`harrison@example.test`).
  - Issue POST reached the API; local disk permission failed (see Deviations). Print dialog, Download PDF, Mailpit, Lucía login, and `reset.sh` seeded SENT plan were not walked.

### Git commands for the user

Do **not** run these in the agent. Explicit paths only (never `-A`).

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  app/components/bookings/bookingHelpers.ts \
  tests/unit/bookingHelpers.test.ts \
  app/components/bookings/BookingPanel.vue \
  app/components/bookings/BookingBillingBlock.vue \
  app/components/documents/BookingDocumentsTab.vue \
  app/components/documents/DocumentPreviewModal.vue \
  app/components/documents/DocumentConfirmModal.vue \
  app/components/documents/documentHelpers.ts \
  app/components/documents/documentFetch.ts \
  tests/unit/documentHelpers.test.ts \
  tests/unit/documentFetch.test.ts \
  app/components/payments/BookingPaymentsTab.vue \
  app/components/extras/BookingExtrasTab.vue \
  app/components/history/describe.ts \
  tests/unit/describe.test.ts \
  app/types/api.ts \
  app/assets/css/bookings.css \
  eslint.config.mjs \
  i18n/locales/en.json
git commit -m "$(cat <<'EOF'
Open the booking Documents tab and wire plan, preview and send.

Receipts, payment-link email and billing sit on the existing
Payments and Overview tabs; statuses stay API facts.
EOF
)"
git push origin HEAD
```

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-07/REPORT.md
git commit -m "$(cat <<'EOF'
Record sprint 7 task 06: booking panel Documents tab.
EOF
)"
git push origin HEAD
```

## Task 07 · anakata-panel · Documents & Manifests: client documents

### API prelude (so the page can read the row)
`DocumentPlanRow` now carries `bookingReference` (`?string`) and `client` (`string`) after `bookingId`. `DocumentPlan::row()` fills `$booking->displayReference()` and `$booking->contact->name` (contact was already `loadMissing`'d). Every `new DocumentPlanRow` still goes through `row()`. `DocumentPlanRowResource` emits `booking_reference` and `client` (PHPDoc + `toArray`). Same resource on the booking plan.

`GET /api/rms/documents` adds pagination `meta.filters` `{ kinds, statuses: [{ value, label }] }` via `Resource::collection()->additional()`. Labels come from PHP `label()`: kinds already had it; `DocumentPlanStatus::label()` returns `$this->value`. Typed on `#[DocumentedResponse]`. No status-count KPIs — the API does not send them.

`PanelResponseSchemasTest` asserts the two resource keys and OpenAPI `meta.filters`. `ClientDocumentsIndexTest` asserts reference, client, and the full filter lists. The existing flat query-count still passes (contact was already eager-loaded). `DocumentPlanTest` GET plan asserts the two fields.

### anakata-ui `v0.8.1`
Types regenerated against `http://localhost:8000/docs/api.json`. Layer `0.8.0` → `0.8.1`. Types only.

| File | Before | After |
|---|---|---|
| `app/types/api.d.ts` | 8220 | 8227 |
| `app/types/documents.ts` | 59 | 61 |
| `app/types/index.ts` | 219 | 220 |
| `app/types/inventory.ts` | 247 | 247 |
| `app/types/config.ts` | 401 | 428 |
| `app/types/bookings.ts` | 208 | 208 |
| `app/types/payments.ts` | 161 | 161 |
| `app/types/anakata-augment.d.ts` | 17 | 17 |
| `app/types/guests.ts` | 101 | 101 |
| `app/types/extras.ts` | 24 | 24 |

`ClientDocumentFilters` picks `operations['clientDocument.index']` `meta.filters`. **No leftover overlay.** Delivery leftovers unchanged. Panel and engine README rows now say `` `extends: ['../anakata-ui']` (`v0.8.1`) ``. Neither pin is enforced.

### Page
`/rms/operations/documents` replaces the placeholder. One `DateRangeFilter` on departure date (noun: documents). Client-documents table from `GET /api/rms/documents`: `booking_reference`, `client`, document, trigger, date, status pill, Preview / Resend. Kind and status chips from `meta.filters` only — local All chip, no hand-written kind/status arrays, no i18n copies of those labels. Search `q` (300 ms). Paginated. Row click `GET /bookings/{id}` and opens `BookingPanel` with `initial-tab="documents"`. No per-booking join.

Preview / Resend reuse task 06: `DocumentPreviewModal`, `DocumentConfirmModal`, `documentRowActions`, `documentStatusPillClass`. `previewTargetFor` extracted into `documentHelpers.ts` (issued html/file; unissued RECEIPT → receipt html; else kind html). `BookingDocumentsTab` uses the same helper.

**KPI:** skipped. The API sends filter enums, not per-status counts. Do not count in the panel.

**Manifests:** labelled section “Departure manifests — arrives in Sprint 11”. Nothing fake.

**Nav:** `permission: 'panel.rms'`. Guards test updated (`sprint: 7`).

### Files touched
**anakata-api**
- `app/Enums/DocumentPlanStatus.php`
- `app/Http/Controllers/Rms/ClientDocumentController.php`
- `app/Http/Resources/Rms/DocumentPlanRowResource.php`
- `app/Support/Documents/DocumentPlan.php`
- `app/Support/Documents/DocumentPlanRow.php`
- `tests/Feature/Documents/ClientDocumentsIndexTest.php`
- `tests/Feature/Documents/DocumentPlanTest.php`
- `tests/Feature/OpenApi/PanelResponseSchemasTest.php`

**anakata-ui**
- `app/types/api.d.ts`
- `app/types/documents.ts`
- `app/types/index.ts`
- `package.json` (`0.8.1`)
- `CHANGELOG.md`
- `README.md`

**anakata-panel**
- `app/pages/rms/operations/documents.vue` (new)
- `app/assets/css/documents.css` (new)
- `app/components/documents/documentHelpers.ts` / `tests/unit/documentHelpers.test.ts`
- `app/components/documents/BookingDocumentsTab.vue`
- `app/navigation/rms.ts`
- `app/types/api.ts`
- `i18n/locales/en.json`
- `nuxt.config.ts`
- `eslint.config.mjs` (`doc-chip-stack`)
- `tests/unit/guards.test.ts`
- `README.md` (documentation pin only)

**anakata-engine**
- `README.md` (documentation pin only)

**anakata-api (this report)**
- `docs/sprints/sprint-07/REPORT.md`

### Deviations
- The task file said chips from “the API's enums” and a fresh clone against `v0.8.0`. Approved plan: small API prelude + `v0.8.1` (`booking_reference`, `client`, `meta.filters`) so the page does not join bookings.
- `DocumentPlanStatus::label()` added so status chips can use PHP `label()` like kinds.
- KPI row omitted (API has filters, not counts).
- `reset.sh` not run — no e2e stack this task. Browser used the local seed.

### Open questions
None.

### Notes for later
- Task 08: e2e DOC-09. This task did not add scenarios.
- After `reset.sh`: seeded SENT / FAILED rows, Mailpit resend, Lucía / `panel.rms` gate.
- Repeat the sibling fresh clone at tagged `v0.8.1` **after** the pushes, with **no** overlay (listed below).

### Quality
- anakata-api: `composer check` inside Docker — 869 tests (5961 assertions), Pint (851 files), Larastan level 6 (0 errors). Pint `--dirty` is unusable in the container (no git); ran Pint on the explicit PHP paths.
- anakata-ui: lint, typecheck, test, build — pass.
- anakata-panel: lint, typecheck, test (223), build — pass.
- Overlay clone (step 1, this session) into `/tmp/anakata-fresh/{anakata-ui,anakata-panel,anakata-engine}` (sibling layout). Overlayed the working trees. Confirmed the ui clone is **0.8.1** and has **no** `app/types/nuxt.d.ts`.
  - ui / panel / engine: typecheck pass
  - panel / engine: build pass
  - **OVERLAY CLONE OK**
- Tag clone (step 2): **pending** until the user pushes `v0.8.1`. Then run the commands in §6 below (user, or agent in a follow-up). No overlay.
- Browser (Carolina, local seed, light then dark; no `reset.sh`):
  - Page: 122 documents, kind chips from `meta.filters` (Booking Confirmation & Invoice … Wire Instructions), status chips SENT / FAILED / BLOCKED / SCHEDULED / WAITING / NOT NEEDED / NOT CONTRACTED / DUE. Columns `ANK-2026-0003` / Harrison & Whitfield. Manifests placeholder.
  - FAILED: empty (no seeded FAILED rows).
  - BLOCKED: 58 rows (Brand Family — “No email address for the client of record”).
  - SCHEDULED: 3 rows on Harrison & Whitfield.
  - SENT: empty. No Resend button on local seed (no issued `can_resend` row). Confirm modal is wired.
  - Row click opens Harrison & Whitfield on the Documents tab.
  - Preview: Detailed pre-trip itinerary HTML (Anakata / day-by-day). Print / save PDF enabled. No Download (unissued).

### Git commands for the user

Do **not** run these in the agent. Explicit paths only (never `-A`). Run in this order.

```bash
# 1. anakata-api prelude (OpenAPI + row fields — not this report)
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Enums/DocumentPlanStatus.php \
  app/Http/Controllers/Rms/ClientDocumentController.php \
  app/Http/Resources/Rms/DocumentPlanRowResource.php \
  app/Support/Documents/DocumentPlan.php \
  app/Support/Documents/DocumentPlanRow.php \
  tests/Feature/Documents/ClientDocumentsIndexTest.php \
  tests/Feature/Documents/DocumentPlanTest.php \
  tests/Feature/OpenApi/PanelResponseSchemasTest.php
git commit -m "$(cat <<'EOF'
Expose booking reference, client and document filter enums.

The client-documents list and the booking plan share the same
row resource; chips read meta.filters from PHP label().
EOF
)"
```

```bash
# 2. anakata-ui — commit, then tag, then push HEAD and the tag
cd /home/mohammad/Code/iconic/anakata/anakata-ui
git add \
  package.json \
  CHANGELOG.md \
  README.md \
  app/types/api.d.ts \
  app/types/documents.ts \
  app/types/index.ts
git commit -m "$(cat <<'EOF'
Regenerate API types for client-document list fields.

booking_reference, client and meta.filters come through from
the generated spec. ClientDocumentFilters is not a leftover.
EOF
)"
git tag v0.8.1
git push origin HEAD
git push origin v0.8.1
```

```bash
# 3. anakata-panel
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  README.md \
  app/pages/rms/operations/documents.vue \
  app/assets/css/documents.css \
  app/components/documents/documentHelpers.ts \
  app/components/documents/BookingDocumentsTab.vue \
  tests/unit/documentHelpers.test.ts \
  tests/unit/guards.test.ts \
  app/navigation/rms.ts \
  app/types/api.ts \
  i18n/locales/en.json \
  nuxt.config.ts \
  eslint.config.mjs
git commit -m "$(cat <<'EOF'
Replace Documents & Manifests with the client-documents list.

Rows come from GET /documents; chips from meta.filters; preview
and resend reuse the booking-tab helpers.
EOF
)"
git push origin HEAD
```

```bash
# 4. anakata-engine
cd /home/mohammad/Code/iconic/anakata/anakata-engine
git add README.md
git commit -m "$(cat <<'EOF'
Document the layer pin as v0.8.1.

extends still resolves the sibling folder; the version is documentation only.
EOF
)"
git push origin HEAD
```

```bash
# 5. anakata-api report
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-07/REPORT.md
git commit -m "$(cat <<'EOF'
Record sprint 7 task 07: client-documents page and v0.8.1.
EOF
)"
git push origin HEAD
```

```bash
# 6. Fresh-clone repeat — after the pushes, no working-tree overlay
rm -rf /tmp/anakata-fresh
mkdir -p /tmp/anakata-fresh
git clone https://github.com/anakata-project/anakata-ui.git /tmp/anakata-fresh/anakata-ui
git -C /tmp/anakata-fresh/anakata-ui checkout v0.8.1
git clone https://github.com/anakata-project/anakata-panel.git /tmp/anakata-fresh/anakata-panel
git clone https://github.com/anakata-project/anakata-engine.git /tmp/anakata-fresh/anakata-engine
# then in each: pnpm install
# ui / panel / engine: pnpm typecheck
# panel / engine: pnpm build
```
