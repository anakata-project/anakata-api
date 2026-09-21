# Task 05 · anakata-api · The "Complete your reservation" page's API
**Repo:** anakata-api · **Sprint:** 8 · **Needs:** task 04.

## Goal
Sprint 7 (J8) deferred this page: before paying a deposit or balance link, the guest opens a page of their own where they add billing details, accept the declarations and complete passenger details — then continue to Stripe. This task is the API behind it; task 10 builds the page in the engine.

## Read first
- `docs/requirements/08-dev-decisions.md`: **K9**, and I1, I2, I3, I5, I6, J6, J8
- `prototype/rms_index.html`: `confirmReq` ("The link page captures the four required declarations … and billing data"), `drGuests`' note ("Guests complete missing details via the 'Complete your reservation' link"), `guestForm` (the fields and the guardian block)
- Sprint 6: `UpdateGuest` and its validation, `RecordConsent`, `Masking`; Sprint 7: `SendPaymentRequest`, the payment-link and reminder emails, billing on the booking

## Do
1. **The link (K9).** `booking_access_tokens`: `booking_id`, token (32+ random bytes, stored hashed), `purpose` (`COMPLETE`), `expires_at` (the departure date, Galápagos end of day), `revoked_at`, audit columns. One active token per booking; issuing a new one revokes the old. A cancelled, released or deleted booking's token stops working.
   - The page URL is the engine's origin + `/complete/{token}` (config: `ENGINE_URL`). The token is never logged; requests carrying it are rate-limited per token and per IP; unknown or expired tokens return the same 404, so the endpoint cannot be used to probe.
2. **Emails point to the page.** Sprint 7's payment-link email and the reminder's "Pay balance securely" button now link to the page (issuing the token if the booking has none), not straight to Stripe. The page forwards to the open Stripe link when the guest continues. Record the change; update the Sprint 7 mail tests.
3. **Endpoints** under `/api/engine/complete/{token}` (no session; the token is the credential; `X-Robots-Tag: noindex` on every response):
   - `GET` → what the page shows: booking reference, yacht, departure and return dates, itinerary name, cabin label(s) — for a group, every booking in the group, since the coordinator receives all communications (G2/J6); the amount due now and what it is (deposit or balance) from the ledger and charges helpers (J10's rule: never computed on the page); the billing details; the declarations with their current versions and whether each is accepted; each guest's editable fields; `can_pay` and `pay_url` (the booking's open Stripe link for that amount, when one exists).
   - Guest fields shown: names, date of birth, nationality, Ecuador residency, passport expiry, email, insurance declaration, and the guardian block for minors. **The passport number is write-only here:** the response says only `passport_on_file: bool`, never the value or a masked value — this page has no staff permission to reveal anything (I2). Medical, dietary and accessibility notes are not collected on this page; they stay with the operations team (and the preferences questionnaire, Sprint 11).
   - `PUT …/billing` → the Sprint 7 billing fields.
   - `PUT …/guests/{guest}` → through Sprint 6's guest update rules and validation (future DOB, nationality from the country list, expiry after DOB; the guardian block when the guest is a minor today). A passport number sent here replaces the stored one; empty leaves it. History records the actor as "Guest (self-service)" and names the fields changed, never the values (D5).
   - `POST …/declarations` `{ documents: [...] }` → `RecordConsent` with source `PAYMENT_LINK`, the request's IP, the current versions. Nothing pre-checked; each document is an explicit acceptance.
   - `can_pay` is true only when the four required declarations are accepted (task 04's default rule: a pay-later request accepts the other two here) **and** an open link exists. Billing and passenger details are encouraged but not required to pay — record that as a default, since the prototype says guests *can* complete them there.
4. **Staff side.** Booking panel Payments tab (task 07 adds the UI) can copy the page link: `POST /api/rms/bookings/{booking}/complete-link` (own-records) issues or returns the active token URL. Revoking is automatic on cancellation and on re-issue.
5. **Resources** typed, into `EngineResponseSchemasTest`.

## Don't
- Don't ever return a passport number, masked or not, from these endpoints.
- Don't collect medical notes here.
- Don't let the page compute or show a figure the API did not send.
- Don't make the token guessable or long-lived beyond the departure.

## Checks
- `composer check`.
- Token: hashed at rest; expired, revoked, cancelled-booking and unknown tokens all 404 identically; rate limits.
- GET shape for a single booking and a group; the passport never present; `passport_on_file` correct.
- Guest update: validation, guardian rules, history actor and field names only, encrypted passport written.
- Declarations: rows with source PAYMENT_LINK and IP; `can_pay` false until all four and an open link.
- Emails: the payment-link and reminder emails carry the page URL; the page's `pay_url` is the Stripe link.

## Report
Append **Task 05**: the token and its lifetime, the email change, what the page reads and writes, the write-only passport, the declarations and `can_pay` rule, and the staff copy-link endpoint. Git commands listed, not run.
