# Task 03 · anakata-api · Email delivery: the Graph mailer, the delivery log, recipients, manual sends
**Repo:** anakata-api · **Sprint:** 7 · **Needs:** task 02.

## Goal
Documents and payment requests reach the guest by email. Production sends through Microsoft Exchange via Microsoft Graph; local and e2e send to Mailpit. Every send is recorded once, with its recipient and outcome, so nothing is sent twice and nothing fails silently.

## Read first
- `docs/requirements/08-dev-decisions.md`: **J5, J6, J7**, and A9, B9, G2 (groups), H8 (agencies)
- `07-three-system-integration-contract.md`: the Exchange row, rule 6 (idempotency), "all customer communication in English"
- `prototype/rms_index.html`: `docsFor` (the recipient column: client, "Client + agent", lead guest, "Each passenger with email"), `reminderHtml` (it is an email, not a PDF), `confirmReq`'s deposit-link wording
- The existing `UserInvitation` / `ResetPasswordNotification` (they already send over SMTP to Mailpit)

## Do
1. **The Graph mailer (J5).** All mail goes through Laravel's `Mail`, so the transport is configuration:
   - A `graph` mailer for production: Microsoft Graph `sendMail` with client-credentials auth (tenant id, client id, client secret, sending mailbox). Compatibility check for a maintained Laravel transport package; if none fits PHP 8.4 cleanly, a small custom Symfony transport calling the Graph endpoint through Laravel's HTTP client. Record the choice.
   - `smtp` to Mailpit stays the default in local, testing and e2e. `MAIL_MAILER=graph` only when the Graph keys exist; a misconfigured production must fail loudly at send time and mark the delivery failed — never fall back to another transport.
   - `.env.example` documents the Graph keys (empty). The README lists them as a client question. A test with a faked HTTP client proves the Graph transport builds the right request (recipients, subject, HTML body, PDF attachment as base64, the sending mailbox).
2. **The delivery log.** `deliveries` table: `booking_id`, `document_id` (nullable), `kind` (`INVOICE`, `FINAL_INVOICE`, `SUMMARY`, `RECEIPT`, `REMINDER`, `VOUCHER`, `PRETRIP`, `PAYMENT_LINK`, `WIRE_INSTRUCTIONS`), `idempotency_key` (unique), `to`, `cc` (JSON lists), `subject`, `status` (`QUEUED · SENT · FAILED · BLOCKED`), `error`, `blocked_reason`, `sent_at`, `triggered_by` (`SYSTEM` / user), audit columns.
   - Rows are append-only for everything except the status lifecycle (`QUEUED → SENT | FAILED`), enforced like the payments table (H1: a trigger that freezes the identifying columns).
   - **Idempotency (doc 07 rule 6).** The key is built from what the send is *about*: `receipt:{payment_id}`, `invoice:{document_id}`, `reminder:{booking_id}:{due_date}:{days}`, `pretrip:{booking_id}:{departure_date}`, and so on. Inserting a delivery with an existing key is a no-op that returns the existing row. A replayed event, a double-clicked button or a re-run job cannot send twice. A deliberate staff **resend** gets a new key (`resend:{document_id}:{uuid}`) and is recorded as a resend.
3. **Recipients (J6)** — one resolver, `App\Support\Documents\Recipients`, with the prototype's rules:
   - the client of record's email (the booking contact, or the billing email when set);
   - a group booking goes to the group coordinator (OPS-008: "the coordinator receives all communications");
   - an agency booking's invoice and final invoice copy the agency contact;
   - the booking summary goes to the lead guest when the lead guest has an email, otherwise to the client;
   - no usable address → the delivery is written as `BLOCKED` with `blocked_reason` ("No email address for the client of record"), visible in the Documents tab. Never silently skipped.
4. **Sending.** `SendDocument` (and `SendPaymentRequest` for the two non-document kinds): resolve recipients → insert the delivery (idempotent) → queue a job → the job sends a Mailable with the HTML email body and the PDF attached (read from the stored file, never re-rendered) → `SENT` with `sent_at`, or `FAILED` with the error. Queued on Horizon, after commit. History `document.sent` / `document.send_failed` on the booking.
   - Email bodies are short English Blade templates per kind (the reminder is the prototype's `reminderHtml`, an email with a "Pay balance securely" button that links to the booking's open balance payment link when one exists, otherwise no button and the instruction to contact the team).
   - `From` is the configured sending mailbox; `Reply-To` is the issuer email from `legal_entity`.
5. **Manual sends — endpoints.**
   - `POST /api/rms/documents/{document}/send` — resend an issued document. Own-records on its booking.
   - `POST /api/rms/payment-links/{link}/send` — email an open payment link (the Sprint 5 "sending is manual until Sprint 7" gap): amount, what it is for (deposit / balance), the link, the due date. Permission `payments.record`, like creating the link.
   - `POST /api/rms/bookings/{booking}/wire-instructions/send` — issues the wire-instructions document (task 02) and emails it as the attachment, with a short body giving the amount and the payment reference. Permission `payments.record`. While any bank value is `[TBD]` (LEG-004), the response carries a visible warning for staff, and the document itself marks the bank details as placeholders. Record it.
   - `GET /api/rms/bookings/{booking}/deliveries` — the log, newest first. `BookingPolicy::view`.
   - Resources with full PHPDoc, into `PanelResponseSchemasTest`.

## Don't
- Don't send anything automatically yet (task 04 decides the triggers).
- Don't re-render a PDF to attach it; attach the stored file produced by the PHP library (task 01).
- Don't send marketing of any kind. These are transactional messages only; marketing consent is Sprint 9.
- Don't write any email outside Laravel's mailer.

## Checks
- `composer check`.
- Idempotency: the same key twice sends once; a resend gets its own row and sends.
- Recipients: client, group coordinator, agency cc, lead guest for the summary, and BLOCKED with the reason when there is no address.
- The attachment is byte-identical to the stored file (compare sha256).
- Failure: a faked transport exception leaves the delivery FAILED with the error and writes `document.send_failed`; no retry storm (Horizon's normal retries, then FAILED).
- Graph transport request shape with a faked HTTP client.
- Mail ends up in Mailpit in the Docker suite (one integration test, tagged so it can be skipped where Mailpit is absent).

## Report
Append **Task 03**: the Graph transport choice and its keys, the delivery log and idempotency keys, the recipient rules, the manual sends and the LEG-004 warning on wire instructions, and what stays on Mailpit. Git commands listed, not run.
