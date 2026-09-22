# Task 05 · anakata-api · NPS and the post-trip touchpoints
**Repo:** anakata-api · **Sprint:** 11 · **Needs:** task 04.

## Goal
Completing a voyage starts the post-trip sequence by itself: the personal call (MKT-006), the survey 24 hours after return, an alert and a reply task for a low score, a review request for a high score when consent allows — and the CRM shows each contact's latest score (N8).

## Read first
- `docs/requirements/08-dev-decisions.md`: **N8**, N1, N2, J5, K9, L2, M2, M6
- doc 01 §6.3; doc 03 MKT-006
- `prototype/rms_index.html`: `NPS_Q` (the six questions), `npsPanel` (KPIs, the empty state and its rules text, the responses table), `npsForm` (the staff form with the call notes), `saveNps`
- Task 02's COMPLETED transition, task 04's token and engine endpoint pattern, `RaiseTask`, `RaiseAlert`, `ConsentGate`, `ContactDerived` (the CRM contact's NPS, `null` since Sprint 9)

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Rules.** `nps.survey_hours_after_return` (24), `nps.alert_below` (7), `nps.review_request_from` (8), `nps.review_url` (placeholder, PENDING CLIENT). Shape change with the usual procedure and counts.
2. **Schema.** `guest_responses`: `guest_id`, `booking_id`, `score` (1–10), `recommend` (0–10, nullable), `why`, `best`, `better`, `crew` (text, max 1,000 each), `call_notes` (nullable), `source` (GUEST_LINK, STAFF), `recorded_by`, `responded_at`, audit columns. **Unique on guest and booking** — one response per guest per voyage; a second is 409. Not encrypted (no health data), but the text is guest content: the resource never returns it to the CRM, only the score (step 7).
3. **Post-trip call task.** On COMPLETED (the existing `BookingStatusChanged`), raise task kind POST_TRIP_CALL, key `post-trip-call:{booking}`, owner the booking owner, needs `guest_experience.manage`, due return date + 2 business days; it closes when completed by hand (the call happened) or when a staff response with call notes is recorded.
4. **Survey.** `anakata:nps-survey`, hourly, with the run hooks: for COMPLETED bookings whose return date + `nps.survey_hours_after_return` has passed and whose survey is unsent, send each guest with an email a link (token purpose SURVEY, scoped to the guest, expiring 60 days after return), delivery kind `SURVEY`, key `survey:{guest}`; guests without an email are covered by the lead's link, which lists them. Sent as a service message (PENDING LEG-002), not through the consent gate.
5. **Engine endpoints.** `GET /api/engine/survey/{token}` (booking reference, itinerary, departure, the covered guests with whether each has responded) and `POST /api/engine/survey/{token}/guests/{guest}` with the six answers.
6. **Acting on the score** (one action for guest and staff responses):
   - score < `nps.alert_below`: CRITICAL alert NPS_LOW (audience `guest_experience.manage`, emailed per N1) and task NPS_REPLY, due 24 hours later, owner the booking owner; the alert resolves when the task closes.
   - score ≥ `nps.review_request_from`: if `ConsentGate::allows(contact, MARKETING)` for the booking's contact, send the review request (delivery kind `REVIEW_REQUEST`, key `review:{guest}`, containing `nps.review_url`); otherwise record in history that it was not sent and why. Never for a staff-recorded response without an email.
   - History on the booking: "Post-trip survey recorded — score n", plus what followed.
7. **CRM.** `ContactDerived` and the contact resources: `nps` becomes the latest score among the contact's bookings' responses (by `responded_at`), or null. The list column and the drawer row, which say "—" today, now show it — no panel change beyond the value. The CRM never receives the free-text answers.
8. **Staff.** `POST /api/rms/bookings/{booking}/guest-responses` (`guest_experience.manage`; booking must be COMPLETED) with the guest, the answers and call notes. `GET /api/rms/guest-experience/nps?from=&to=` — KPIs (average score, responses, alerts below the threshold, review requests sent) and the responses table (booking, guest, score with its class, recommend, best, better, crew), plus the empty-state facts (first expected survey date, the rules with their values).

## Don't
- Don't send the review request without marketing consent.
- Don't return free-text answers from any CRM route.
- Don't record two responses for one guest and voyage.

## Checks
- `composer check`; `config-verify` before and after.
- Completion raises the call task once; the survey sends at the hour, once per guest; token scope and expiry.
- Score 6 → alert, email, task; closing the task resolves the alert. Score 9 with and without marketing consent. Score 7 → neither.
- The CRM `nps` value (latest wins) and the absence of text in CRM resources.
- Staff response only on COMPLETED; uniqueness.

## Report
Append **Task 05**: rules and counts, the schema, the call task, the survey job and tokens, the score actions, the CRM value, the endpoints. Git commands listed, not run.
