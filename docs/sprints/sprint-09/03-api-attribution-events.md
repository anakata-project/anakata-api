# Task 03 · anakata-api · Attribution and behavioural events
**Repo:** anakata-api · **Sprint:** 9 · **Needs:** task 02.

## Goal
The engine can tell the API what a consenting visitor does, in a small fixed vocabulary, and where they came from. Those events build the contact's timeline once the visitor identifies themself, and a booking made from the engine carries its marketing attribution, written once and never changed.

## Read first
- `docs/requirements/08-dev-decisions.md`: **L6, L7, L8**, and K3, K8, I6, A4
- `07-three-system-integration-contract.md` §3 (Attribution, Behavioural events rows), §4.2 (Engine → CRM), §6 point 4 (the anonymous session), §8 (behavioural events: 24 months raw, then aggregated), §10 point 3 (attribution written once)
- `booking_engine_SPEC.md` §8 (the event names and parameters)
- `prototype/crm_index.html`: `ACTIVITY`, `v-activity`'s notice; the attribution-model table in `v-camp`
- The engine API (`routes/api/engine.php`), `IpHash`, checkout submit, waitlist, charter enquiries, the complete page

## Do
1. **The vocabulary (L6).** An enum of accepted event names: the SPEC §8 list (`search_availability`, `view_itinerary`, `select_departure`, `view_itinerary_detail`, `view_route_map`, `begin_checkout`, `begin_booking_request`, `select_payment_path`, `apply_promotion`, `remove_promotion`, `promo_invalid`, `booking_form_invalid`, `submit_booking_request`, `abandon_cart`, `charter_inquiry_submit`) plus `view_departure` and `page_view`. For each, a **whitelist of parameters** with types: itinerary code, departure id, step, cabin count, path, currency, value, coupon code (only for the promotion events — never a free-text field), page path (without the query string). Anything else is dropped. No names, emails, phone numbers or free text are accepted in any event.
2. **Ingest** — `POST /api/engine/events` `{ session_id, events: [{ name, occurred_at, params }] }`, up to 25 events per call, its own rate limiter per IP and per session.
   - `session_id` is a random identifier the engine creates **only after analytics consent** (task 08); the API validates its shape and never derives it from the IP. Events are stored in `behavioural_events`: `session_id`, `contact_id` (nullable), `name`, `params` (JSON, whitelisted), `occurred_at` (clamped to the last 24 hours and not in the future), `received_at`. No IP, no user agent.
   - Idempotency: each event carries a client-generated `event_id`; a unique index on it makes a retried batch a no-op.
3. **Stitching (L7).** Checkout submit, waitlist, charter enquiry and the complete page accept an optional `session_id`. When a request resolves a contact and carries a session id: set `contact_id` on that session's events (back-fill), record one `identity.stitched` event with the count, and mark the contact as having identified engine behaviour (for task 01's MQL rule — replace the `TODO(task 03)`). A session is stitched to at most one contact; a second, different contact on the same session is recorded as a new stitch from that moment on, never rewriting the first.
4. **Attribution (L8).**
   - The engine sends `attribution: { first_touch, last_touch }` with checkout submit, each touch being `{ source, medium, campaign, content, term, landing_path, captured_at }` from UTM parameters (task 08 captures them). Whitelist the keys; cap lengths.
   - **Booking:** columns `utm_first` and `utm_last` (JSON, nullable), set by the submit action **at creation and never again** — a database trigger refuses any change to them once set, as with the append-only columns elsewhere (H1's pattern). Staff-created bookings have none.
   - **Contact:** `first_touch` is set once, the first time a contact gets one; `last_touch` is updated with each identified submission. Both are CRM facts, not booking facts.
   - **Trade wins for commission:** an agency booking's commission follows the agency and FIN-005 (Sprint 5), never the marketing touch; both are stored and neither overwrites the other. Record it.
5. **Retention.** `anakata:events-retention` (daily, Galápagos): raw events older than 24 months are rolled into `behavioural_event_daily` (date, name, itinerary code, count) and deleted. Unstitched anonymous events older than 30 days are deleted without aggregation beyond the daily counts. `--dry-run`. Record LEG-002.
6. **Resources** typed, into `EngineResponseSchemasTest`.

## Don't
- Don't accept any event name or parameter outside the whitelists.
- Don't store an IP, a user agent or any free text from the visitor in an event.
- Don't change a booking's attribution after creation.

## Checks
- `composer check`.
- Whitelists: an unknown name refused; unknown params dropped; a free-text value refused; the coupon only on promotion events.
- Ingest: batch limit; replayed batch is a no-op; timestamps clamped; rate limits.
- Stitching: a session's events back-filled on submit, one `identity.stitched`, MQL for a contact with no booking; a second contact on the same session does not rewrite the first.
- Attribution: stored on the booking at creation; an UPDATE of `utm_first`/`utm_last` refused by the trigger; contact first touch set once, last touch updated.
- Retention: aggregation and deletion at the boundaries; unstitched 30-day deletion; dry run writes nothing.

## Report
Append **Task 03**: the vocabulary and parameter whitelist, ingest and idempotency, stitching, attribution and its trigger, the trade-wins rule, retention and LEG-002. Git commands listed, not run.
