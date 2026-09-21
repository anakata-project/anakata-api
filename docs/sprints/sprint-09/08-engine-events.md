# Task 08 · anakata-engine · Consented events, the session identifier, UTM capture
**Repo:** anakata-engine (plus the sprint REPORT) · **Sprint:** 9 · **Needs:** task 05 (`v0.10.0`).

## Goal
The engine sends its behavioural events to the API — only with consent, only the whitelisted vocabulary — and carries the visitor's UTM attribution and session into every identifying submission.

## Read first
- `docs/requirements/08-dev-decisions.md`: **L6, L7, L8**, K7, K8
- This sprint's REPORT task 03 (the vocabulary, parameters, ingest, stitching and attribution)
- The engine's `useTrack`, the consent banner and `analyticsAllowed` (Sprint 8 task 10), the checkout, waitlist, charter and complete pages

## Do
1. **Consent (L6).** The same banner and choice that gate GA4 gate the CRM events (README client question: one consent for both by default). Before consent or after refusal: no session identifier is created, nothing is stored in `localStorage` for tracking, and nothing is sent. Withdrawing consent deletes the stored identifier and first touch.
2. **The session identifier.** After consent, a random identifier in first-party `localStorage` (never a third-party cookie, never derived from anything about the device), rotated after 30 days of inactivity.
3. **Sending.** `track()` now fans out: GA4 (as before) and a small queue to `POST /api/engine/events` — batched (up to 25, flushed every few seconds and on `pagehide` with `sendBeacon`), each event with a client `event_id`, `occurred_at`, and only the whitelisted parameters for its name (build the payload per event type; never pass arbitrary objects through). Add `page_view` on route changes (path only, no query string) and `view_departure` where a departure row is opened. Failures are dropped silently after one retry — analytics must never break the booking flow.
4. **UTM capture (L8).** On landing, read `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term` with the landing path:
   - always keep the **current session's** touch in `sessionStorage` — it travels with a submission as part of that submission;
   - with consent, keep the **first touch** in `localStorage` across visits, and update the last touch on each new UTM landing.
   - Checkout submit sends `attribution: { first_touch, last_touch }` (without consent, both are the current session's touch or empty) and `session_id` when there is one. Waitlist, charter and the complete page send `session_id` too.
5. **Tests:** consent gating (no identifier, no request, no storage before consent; withdrawal clears), per-event payload builders drop unknown parameters, batching and `sendBeacon`, UTM first/last rules with and without consent.

## Don't
- Don't send or store anything for tracking before consent.
- Don't send names, emails, phone numbers or free text in any event.
- Don't let a tracking failure affect the flow.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build.
- Browser, both themes, against the e2e seed: with consent, browsing sends batched events (network panel) and a submitted request stitches them to the new contact (CRM activity); without consent, no request to `/events` and nothing in storage; a `?utm_source=…` landing followed by a booking shows the attribution on the booking.

## Report
Append **Task 08**: consent gating, the identifier's lifetime, batching and payload builders, UTM rules with and without consent. Git commands listed, not run.
