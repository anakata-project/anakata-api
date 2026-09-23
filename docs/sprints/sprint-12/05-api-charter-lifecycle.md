# Task 05 · anakata-api · The charter lifecycle
**Repo:** anakata-api · **Sprint:** 12 · **Needs:** task 04.

## Goal
A charter goes from enquiry to booking on one path: quote, proposal, acceptance, deposit clock — with acceptance recorded properly while the e-signature provider is still open (O5, O6, O7).

## Read first
- `docs/requirements/08-dev-decisions.md`: **O5, O6, O7**, and E8, H9 (the penalty band freeze), I6 (the consent log), J2, J3, J7, K9 (the client link), K10, M6, N1
- doc 01 §3.3, §4.1.6–4.1.7; doc 03 FIN-001, FIN-003, §4.1.5, OPS-007, OPS-009, TEC-003
- `CharterEnquiry` and its statuses, `UpdateCharterEnquiryStatus`, the document pipeline and numbering, `BookingAccessToken` and its purposes, `cancellation.bands` and the refund request, the reservation path for a charter (one booking, nine claims, no cabin — G2)

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Statuses.** `CharterEnquiryStatus` gains QUOTED, ACCEPTED and DECLINED, keeping NEW, CONTACTED and CLOSED. Allowed moves: NEW → CONTACTED → QUOTED → ACCEPTED or DECLINED; CLOSED from any; nothing leaves ACCEPTED once a booking exists. History on every move, with a reason for DECLINED and CLOSED.
2. **Shape changes**, usual procedure with registry rows and counts:
   - `cancellation.charter_bands` — the same three bands as the cabin list by default, PENDING CLIENT (O6, LEG-001);
   - `charter.deposit_business_days` — 5 (FIN-003);
   - `charter.proposal_valid_business_days` — 10, PENDING CLIENT (how long a proposal stands).
3. **The proposal (O5).** `IssueCharterProposal` creates an immutable, numbered document version (J2, J3) for the enquiry: yacht, week (departure and return), guests, the price from the rates table with the festive supplement where it applies, what is included and excluded, the deposit (20% within `charter.deposit_business_days` of acceptance), the balance at T−120, and the charter cancellation bands as text. Re-issuing makes a new version with a reason; the old one stays. `POST /api/rms/charter-enquiries/{enquiry}/proposal` needs `bookings.create`, and moves the enquiry to QUOTED. Sending it uses the normal delivery path (kind `CHARTER_PROPOSAL`, key `charter-proposal:{enquiry}:{version}`) to the enquiry's contact.
4. **Acceptance (O5).** A token (purpose `CHARTER_PROPOSAL`, expiring at the end of the proposal's validity) opens the engine page (task 10):
   - `GET /api/engine/charter-proposal/{token}` — the proposal's own HTML plus its version, the price summary and the validity date;
   - `POST /api/engine/charter-proposal/{token}/accept` — a typed full name and the tick of the terms. Records a consent-log row (I6) for the proposal document and version with the time and IP, writes `accepted_at`, `accepted_name` and the version on the enquiry, moves it to ACCEPTED, and creates the charter booking through the normal reservation path with the proposal's frozen price and deposit. A second accept is 409; an expired or superseded version is 410 with the sentence that a new proposal is needed.
   - `POST …/decline` with an optional reason moves the enquiry to DECLINED.
   This is not an electronic signature (TEC-003); the REPORT says so plainly.
5. **The deposit clock (O7).** The created booking's deposit due date is acceptance + `charter.deposit_business_days` business days. When it passes unpaid: CRM task `CHARTER_DEPOSIT` (owner the enquiry's owner, needs `bookings.overdue_decision`, key `charter-deposit:{booking}`) and WARN alert `CHARTER_DEPOSIT_DUE` (audience `bookings.overdue_decision`), both closing when the deposit settles or the booking leaves PENDING_PAYMENT. Nothing auto-cancels (OPS-007).
6. **Charter bands (O6).** `H9`'s penalty lookup picks `cancellation.charter_bands` when the booking is a charter, the cabin list otherwise. The refund request still freezes the band it used, and names which list it came from. Existing refund requests are untouched.
7. **Enquiry list.** `GET /api/rms/charter-enquiries` gains the status filter, the latest proposal version with its state (sent, accepted, declined, expired), the SLA state from OPS-009, and the created booking's reference where there is one.

## Don't
- Don't call the typed acceptance a signature.
- Don't create the booking before acceptance, or change its price afterwards.
- Don't auto-cancel on a missed deposit.

## Checks
- `composer check`; `config-verify` before and after.
- Status moves and their refusals; re-issuing gives a new version and the old one still opens.
- Acceptance: the consent row (document, version, time, IP), the booking with the frozen price and the deposit due date, a second accept 409, an expired version 410.
- The deposit clock across a weekend; the task and alert raised once and closed by the payment.
- A charter cancellation uses the charter bands and a cabin cancellation still uses the cabin bands; both freeze what they used.

## Report
Append **Task 05**: the statuses, the three rules and counts, the proposal and its numbering, acceptance and what is recorded (and that it is not an e-signature), the deposit clock, the band split. Git commands listed, not run.
