# Task 06 · anakata-panel · Booking panel: Documents tab, receipts, payment-link email, billing details
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 7 · **Needs:** task 05 (`v0.8.0`).

## Goal
The last disabled tab opens. Staff see every document a booking has or will have, with its trigger, date and status; preview any of them; resend, and re-issue with a reason. The Payments tab gains receipts and "send by email" for payment links, and the invoice's billing details can be edited.

## Read first
- `prototype/rms_index.html`: `drDocs` (its note and columns), `docRow`, `DSTC` (the pill for each status), `viewDoc` / `showDoc` and the `docmodal` with its "Print / save PDF" button (`window.print()`), `drPayments`' receipt link
- Sprint 5 task 07 and Sprint 6 tasks 07–08 in their REPORTs (how tabs are extracted, lazy-loaded and refreshed; the shared refresh handler)
- This sprint's REPORT tasks 02–04 for the endpoints, statuses and wording

## Do
1. **Enable the Documents tab** (remove the Sprint 7 tooltip from `BOOKING_TABS`; after this no tab is disabled). Extract `BookingDocumentsTab.vue`, lazy-loaded.
   - The prototype note ("Documents the system generates for this booking (§4.5). All client documents in English; PDFs follow the approved mockup v6.").
   - Table from `GET …/documents/plan`: document (and "to {recipient}" beneath, or the blocked reason in coral), trigger, date, status pill (the prototype's `DSTC` classes — presentation only), actions. The trigger wording and recipient come from the API.
   - **Preview** (when `can_preview`), as the prototype's `docmodal`: fetch the document's HTML — `GET /documents/{id}/html` for an issued version (rendered from its stored snapshot), or `GET …/documents/{kind}/html` for one not issued yet — and show it in a modal inside an `<iframe srcdoc>` with `sandbox="allow-same-origin allow-modals"` (no scripts). The modal has:
     - **Print / save PDF** — calls `print()` on the iframe's window, so the browser's own print dialog prints just the document (the template's print CSS does the page layout). This is how staff get a PDF of any document, issued or not.
     - **Download PDF** — for an issued version only: the stored file from `GET /documents/{id}/file`, the same file that was emailed.
     - No PDF library in the panel.
   - **Versions:** an issued invoice with several versions shows "v2 · {reason}" and a small list to open earlier versions.
   - **Resend** (when `can_resend`): a confirmation modal ("Send {name} to {recipient} again?"), then `POST /documents/{id}/send`.
   - **Re-issue** (when `can_issue` and a version exists): the `ReasonModal`, reason required, then `POST …/issue`. The first issue of a document that is WAITING or DUE is also offered when the API allows it (`can_issue`), without a reason.
   - FAILED rows show the error beneath and offer Resend; BLOCKED rows explain why and link to the billing details (below).
   - After every action: refresh the plan, the booking, and the list, through the shared refresh handler.
2. **Payments tab.**
   - A **receipt** link on each settled positive payment row (prototype `drPayments`), opening the issued payment confirmation in the same preview modal. Remove the Sprint 5 note that receipts arrive in Sprint 7.
   - Open payment links gain **Send by email** (`payments.record`): a confirmation modal showing the recipient, then `POST /payment-links/{id}/send`. Replace "Copy the link — sending it by email arrives in Sprint 7" with the delivery status of the last send.
   - For a PENDING_PAYMENT booking, **Send wire instructions** (`payments.record`). When the API returns the LEG-004 warning, show it in a `.warnbox` before and after sending — the bank details are placeholders and the recipient must not act on them. Say so plainly.
3. **Billing details.** On Overview, a "Billing" block (name, address, email, phone; defaults shown muted when empty) with an edit form for `can_act`, `PATCH …/billing`, field errors through `applyApiFormError`, and the note that guests will be able to complete these themselves from Sprint 8.
4. **Extras tab.** Sprint 6 deferred the prototype's notice "This booking already has an invoice — changes here re-issue an updated invoice to the client". Show it now, when the plan says an invoice has been issued (from the API, not guessed).
5. **Helpers, tested:** `documentStatusPillClass(status)`, `documentRowActions(row)` (which buttons, from the row's `can_*` flags only), `versionLabel(document)`.

## Don't
- Don't add a PDF library to the panel; the browser prints the HTML and the server supplies the stored PDF.
- Don't compute statuses, dates, recipients or triggers in the panel.
- Don't use `window.confirm`.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.8.0`.
- Browser, both themes, after `reset.sh`:
  - A seeded CONFIRMED booking: invoice and summary SENT, receipts SENT, reminders SCHEDULED with their dates, pre-trip SCHEDULED, voucher NOT CONTRACTED (or SCHEDULED where a transfer extra exists), questionnaire WAITING "Arrives in Sprint 11".
  - Preview the invoice: the HTML matches Overview's totals; "Print / save PDF" opens the browser dialog showing only the document on A4; "Download PDF" gives the stored file.
  - Print an older invoice version: it prints what was issued then, not today's figures.
  - Add an extra → a new invoice version with the reason appears, and it arrives in Mailpit.
  - Remove the client's email → the rows turn BLOCKED with the reason; restore it through Billing → resend works.
  - Send a payment link by email; it arrives in Mailpit with the link.
  - Send wire instructions: the LEG-004 warning is visible.
  - Lucía on Mateo's booking: preview allowed, no resend / re-issue / billing edit.

## Report
Append **Task 06**: the tab, its actions and their permissions, the preview approach, the Payments tab additions and the LEG-004 warning, billing details, and the Extras notice. Git commands listed, not run.
