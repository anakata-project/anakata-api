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
