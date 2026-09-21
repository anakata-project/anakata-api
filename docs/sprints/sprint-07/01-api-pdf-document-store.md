# Task 01 · anakata-api · The PDF library, the document store, numbering, the issuer details
**Repo:** anakata-api · **Sprint:** 7 (read `README.md` in this folder first)
**Needs:** the README's "Before task 01" done.

## Goal
The machinery every document needs, proved on one trivial view: turn a hardcoded HTML template into a PDF on the server with a pure-PHP library, store the issued file with the data it came from, number it, and never change it again. Task 02 writes the real templates.

## Read first
- `docs/requirements/08-dev-decisions.md`: **J1, J2, J3, J4** (J1 supersedes A8), and H1 (append-only), E1–E8 (config documents), the Sprint 4 task 02 shape-change procedure
- `06-build-backlog.md` (documents "generated as PDFs from the layouts in the prototype (mockup v6), attached to transactional emails, stored against the booking")
- `07-three-system-integration-contract.md`, the Documents row
- `05-decisions-and-open-questions.md` row 8 (PONTOS LLC, address, EIN) and the LEG-004 row
- `prototype/rms_index.html`: `docHead`, and the `.paper` / `.d*` styles

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **The PDF library (J1).** A pure-PHP library — no Chromium, no Node, no extra container. Default **dompdf** (through `barryvdh/laravel-dompdf` or dompdf directly); **mPDF** is the alternative if dompdf cannot lay out the invoice tables cleanly. Compatibility check first (PHP 8.4, the existing lock file); never `--ignore-platform-reqs`. Record which one and why.
   - One wrapper, `App\Services\Documents\PdfRenderer::render(string $html): string` (PDF bytes). Page setup in one place: A4, the margins the prototype's `.paper` implies, fonts registered with the library (bundled font files in the repo, no remote fonts), remote resources disabled.
   - The library is pure PHP, so tests use the real renderer — no fake. A render failure throws a typed exception; nothing half-issued is stored.
2. **Templates are code, not data.** The HTML templates are Blade views in `resources/views/documents/`, written by hand from the prototype. There is no template editor, no template table, no template versioning: changing a template is a code change, reviewed like any other. Record this.
   - They must use what the library supports: table-based layout, no flexbox or grid, simple selectors. Task 02 carries this rule.
3. **The document store (J2).** `documents` table: `booking_id`, `kind` (enum, filled by task 02: `INVOICE`, `FINAL_INVOICE`, `SUMMARY`, `RECEIPT`, `VOUCHER`, `PRETRIP`, `WIRE_INSTRUCTIONS`), `number` (nullable — numbered kinds only, J3), `version` (1, 2, …), `reason` (nullable; required for version > 1), `payment_id` (nullable, for receipts), `snapshot` (JSON — the exact data the template was rendered from), `file_path`, `file_sha256`, `issued_at`, `issued_by` (nullable — System for automatic issues), audit columns.
   - **Append-only:** a trigger refuses UPDATE and DELETE (H1's pattern). An issued document is never edited, re-rendered or removed; a correction is a new version.
   - Unique `(booking_id, kind, version)` for per-booking kinds, and unique `(payment_id, kind)` for receipts — the same receipt cannot be issued twice (doc 07 rule 6 starts here).
   - Files on a dedicated `documents` disk (local in dev and e2e; an S3-compatible disk later — production checklist). Path by booking and document id; never overwritten.
   - Retention: documents are financial records, kept seven years, outside the Sprint 6 retention job. They carry passenger names, never passport numbers, dates of birth or notes — task 02 asserts it.
4. **`IssueDocument`** — the single write path: build snapshot → render the Blade view from the snapshot → PDF → store file → insert row → history `document.issued` on the booking ("Booking Confirmation & Invoice issued — v2: Extra added"). In a transaction; the file is written first and deleted if the transaction rolls back, so no row ever points at a missing file.
   - It takes the booking lock (H10/I10: departure → booking → document row → reference counters), because it reads the charges and draws a number.
   - **Preview** is separate and stores nothing: task 02 returns the same rendered HTML for the panel, where staff print it with the browser.
5. **Numbering (J3).** The booking confirmation & invoice and the final invoice are numbered `INV-YYYY-NNNN` through `ReferenceService` — a new reference type, single-statement upsert, the year of first issue. Drawn at version 1, kept by later versions; the final invoice gets its own number. Other kinds are identified by booking reference and date. Record the open accountant question (README).
6. **Issuer and bank details (J4) — a business-rules shape change**, same procedure as Sprint 4 task 02 and Sprint 6 task 03:
   - `legal_entity`: `name` ("PONTOS LLC (a limited liability company)"), `address_lines`, `email`, `website`, `ein`; `bank`: `bank_name`, `account_name`, `account_number`, `routing`, `swift`, each defaulting to `"[TBD]"`.
   - Registry rows: issuer values CONFIRMED (decision row 8); bank values PENDING CLIENT (LEG-004). Record the new registry totals and update the BR fixtures.
   - DML-only migration publishing the new version as System with hard-coded defaults; `anakata:config-verify` fails before and passes after.
7. **A proof view.** A minimal testing-only view (the `docHead` block and one line) used by this task's tests. Task 02 writes the real ones.

## Don't
- Don't add Gotenberg, Browsershot, Chromium or any service to `docker-compose.yml`.
- Don't build a template editor, a template table or template versioning.
- Don't store previews.
- Don't write the real templates or send email (tasks 02–03).

## Checks
- `composer check`, `anakata:config-verify` before and after the migration.
- The trigger refuses update and delete on `documents`.
- `IssueDocument` with the real library: a PDF starting with `%PDF` is stored, sha matches, snapshot stored, number drawn once for v1 and kept for v2, v2 without a reason refused, rollback leaves no row and no file.
- A receipt for the same payment cannot be issued twice; the second attempt returns the existing document.
- Concurrency (Sprint 4 harness): two issues on the same booking serialise; numbers are distinct; never 1213.

## Report
Append **Task 01** to `docs/sprints/sprint-07/REPORT.md`: the library chosen and why, templates as code, the store and why it is append-only, the file handling on rollback, numbering and the accountant question, the issuer/bank shape change and new registry counts, and the production checklist item (the documents disk). List the git commands; do not run them.
