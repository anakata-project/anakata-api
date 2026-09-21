# Task 10 · anakata-engine · Private charter, waitlist, "Complete your reservation", analytics
**Repo:** anakata-engine (plus the sprint REPORT) · **Sprint:** 8 · **Needs:** task 09.

## Goal
The engine's remaining pages: the private-charter enquiry, the waitlist form, the guest's "Complete your reservation" page, and analytics that load only with consent.

## Read first
- `booking_engine_SPEC.md` §2 (charter), §6 (charter capacity), §8 (analytics); `04-booking-engine-contract.md`
- `prototype/booking_engine_index.html`: `submitCharter` and the charter page; the waitlist alert it replaces; `ga()`
- `prototype/rms_index.html`: `guestForm` (the fields and the guardian block the guest page mirrors)
- This sprint's REPORT tasks 04 and 05

## Do
1. **Private charter** (top navigation, outside the six steps): the prototype's page and form — dates or a departure, guests (the form rejects more than the yacht maximum from settings), contact, message — posting to `POST /api/engine/charter-enquiries`. The confirmation copy and the response SLA from the engine settings' charter block. `track('charter_inquiry_submit')`.
2. **Waitlist form**, opened from FULL · WAITLIST and LIMITED AVAILABILITY rows and from party-fit rows: departure (prefilled), cabin category, name, email, adults, children, notes → `POST /api/engine/waitlist`. Replaces the prototype's browser alert. Shown only where the feed says the waitlist is on.
3. **"Complete your reservation"** at `/complete/{token}` (SSR, `noindex`):
   - Reads `GET /api/engine/complete/{token}`; an invalid or expired link shows one neutral message with the reservations email from settings — no detail about why.
   - Sections: the booking(s) and what is due now (the API's amount and wording); billing details; the declarations (each unchecked until the guest ticks it, with its version); passenger details (names, date of birth, nationality from the API's country list, Ecuador residency, passport number, passport expiry, email, insurance declaration, and the guardian block for a guest under 18 today).
   - **The passport field is write-only:** when `passport_on_file` is true, show "Passport number on file" and an empty field to replace it; never a value.
   - Each section saves on its own (`PUT`/`POST` from task 05), with field errors from the API.
   - **Continue to payment** is enabled only when the API's `can_pay` is true and goes to its `pay_url`. When there is no open link, say the team will send it.
4. **Analytics (README client question):** the `track()` function from task 08 now pushes GA4 events through `dataLayer` / `gtag`, **only after the visitor consents** through a minimal consent banner (analytics on/off; the choice stored per visitor). Before consent, or if refused, no analytics script loads and `track()` does nothing. The GA4 measurement id comes from runtime config (empty = analytics off). Record that the banner's wording and the cookie policy are LEG-002 items.
5. **Error and empty states** everywhere the API can refuse: rate limits ("Please try again in a moment"), network failure, and a hold lost mid-flow — in the prototype's tone.

## Don't
- Don't load any analytics script before consent.
- Don't show a passport number, masked or not.
- Don't reveal why a "Complete your reservation" link is invalid.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build.
- Unit tests: consent gating of `track()`, the charter guest limit, the complete-page section states.
- Browser, both themes and at phone width, against the e2e seed: a charter enquiry appears in the RMS; a waitlist entry appears in Holds & Waitlist with source ENGINE; the "Complete your reservation" link from a payment-link email (Mailpit) opens, saves billing, declarations and a guest with a passport, shows "on file" afterwards, and continues to Stripe only when allowed; analytics requests appear only after consenting (network panel).

## Report
Append **Task 10**: the charter and waitlist pages, the complete page and its write-only passport, the consent-gated analytics and the LEG-002 items. Git commands listed, not run.
