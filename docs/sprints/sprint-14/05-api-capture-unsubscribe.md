# Task 05 · anakata-api · Lead capture, unsubscribe and cart recovery
**Repo:** anakata-api · **Sprint:** 14 · **Needs:** task 04.

## Goal
The two ends of the marketing relationship: an address given on purpose (Q6), and a way out that works in one click (Q7).

## Read first
- `docs/requirements/08-dev-decisions.md`: **Q6, Q7**, Q2, and L6, L7, M1, M2, K9 (token links)
- The engine checkout payload and `ResolveContact`; the consent register and its capture points; the suppression rule from task 01

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Lead capture (Q6).** The checkout details step may send `marketing_lead` — an email, a first name, and the tick with its consent text version — before a booking exists. When present: resolve or create the contact, write a register row (capture point ENGINE_FORM, the checkout text version), stitch the session (L7), and enrol in the nurture journey. When absent: nothing is stored, and the abandoned checkout stays an anonymous event (L6). The endpoint is on the engine routes, throttled like the rest, and never returns whether the address was already known.
2. **Shape change.** `legal.consent_versions.checkout_marketing` (PENDING LEG-002), usual procedure with registry rows and counts.
3. **Unsubscribe (Q7).** `GET /api/engine/unsubscribe/{token}` returns only what the page needs (that the token is valid, and the contact's first name at most); `POST …/unsubscribe/{token}` withdraws MARKETING through `RecordContactConsent` (capture point UNSUBSCRIBE), suppresses the contact, exits every marketing enrolment with reason "unsubscribed", and confirms. The token identifies the contact and nothing else, does not expire, and is single-purpose. A second click is idempotent and says so.
   - `{{unsubscribe_link}}` resolves to that token per contact.
   - A test asserts the page and its payload expose no booking, no address and no other contact detail.
4. **Cart recovery.** A transactional-looking sequence is not available here: recovery is marketing, so it runs as steps of the nurture journey's abandoned-checkout branch, gated by consent and suppression like any other step, at the prototype's 24 h, 48 h and day 7 — confirmed by the client (README question). Enrolment comes from the abandoned-checkout segment (task 01), which by construction contains only contacts who ticked the box.
5. **Hard bounces.** The mailer's failure reasons that mean "this address does not exist" add the contact to suppression with reason HARD_BOUNCE, once, with history. A soft failure does not.
6. **Erasure.** Sprint 10's erasure already clears the contact; add the suppression hash check so an erased person cannot be re-enrolled by a later journey trigger, and state the interaction in the REPORT.

## Don't
- Don't store an address without the tick.
- Don't put anything but the token on the unsubscribe URL.
- Don't recover a cart for someone who never asked.

## Checks
- `composer check`; `config-verify` before and after.
- With the tick: contact, register row, stitched session, enrolment. Without it: no contact, no row, no enrolment.
- Unsubscribe withdraws, suppresses, exits enrolments, is idempotent, and leaks nothing.
- A hard bounce suppresses once; a soft one does not.
- An erased contact is not re-enrolled.

## Report
Append **Task 05**: the capture endpoint and its consent row, the rule added, the unsubscribe token and what it exposes, where cart recovery lives, bounces, and the erasure interaction. Git commands listed, not run.
