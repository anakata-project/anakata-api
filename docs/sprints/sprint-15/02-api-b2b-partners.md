# Task 02 · anakata-api · CRM B2B Partners

**Repo:** anakata-api · **Sprint:** 15 · **Needs:** Sprint 5 (`Agency`, commissions), Sprint 9 (contacts, identity), Sprint 10 (`Deal`), Sprint 14 (`b2b_partner_activation` journey).

## Goal

The CRM nav item `b2b-partners` (`/crm/sales/b2b-partners`) has been `sprint: 'later'` since it was first added and no sprint has built it. Sprint 11 built the RMS-side "B2B & Agent Portal" — agency users, portal preview, commissions — but that lives under `/rms/...` and is a different page for a different audience (ops staff managing the agency record). This page is the CRM-side relationship view: the contact who represents each agency, their deal history, and where they sit in the `b2b_partner_activation` journey Sprint 14 built.

This task adds no new source data. Every field on this page already exists on `Agency`, `Contact`, `Deal`, or the journey tables — the work is joining them into one read model, not computing anything new.

## Repo check to do first

- Confirm `App\Support\Crm\ContactDerived` (Task 03 of Sprint 14 cited this as already matching an agency's contact by its email) still resolves the agency-to-contact link the way that task described, and that it's the right place to read from rather than re-deriving the match here.
- Confirm the exact fields `Agency` and `AgencyResource` (Sprint 5 / Sprint 11) expose for revenue and accrued commission, since this page reads them rather than recomputing.
- Confirm `Deal` has an `agency_id` or equivalent link, or whether deals only relate to an agency indirectly through the booking's channel/agency reference — this determines whether "deal history" on this page is a direct query or a join through bookings.

## Do

1. **Read model, no new tables.** `App\Http\Controllers\Crm\B2bPartnerController`, `panel.crm` guard, same shape as `AutomationController`:
   - `GET /api/crm/b2b-partners` — one row per `Agency` that has a resolved CRM contact (per `ContactDerived`'s existing match). Each row: agency name, status, commission rate, the matched contact's name and id, revenue and commission accrued (from the existing `Agency`/`AgencyResource` fields — don't recompute), the agency's `b2b_partner_activation` enrolment status and current step (from `journey_enrolments` via the existing `JourneyController::forContact`-style query, scoped to that one journey key), and open deal count.
   - An agency with **no** matched CRM contact (per Sprint 03's note: "if there is no such contact, do not create one and do not enrol") still appears on this list, with the contact fields null and a note that the journey never enrolled — don't hide agencies the journey engine correctly skipped; that's exactly the case ops needs visibility into.
   - `GET /api/crm/b2b-partners/{agency}` — the row above plus the contact's deal history (from `Deal`, filtered to that agency) and its full journey enrolment (steps, sends, next due) the same shape `JourneyController::forContact` already returns for one enrolment.

2. **No write path.** This page is read-only. Approving an agency, changing its commission rate, or editing the relationship stays in the RMS agency screen (Sprint 5/11) — this task adds no PATCH/POST beyond what's already covered by the existing agency endpoints. If the panel needs a shortcut link to the RMS agency record, that's a frontend concern (Task 06), not a new API write.

3. **Production metric — don't invent one.** The prototype's B2B tab showed a "2 producing partners" style count. If there's no defined source for "producing" (e.g., "has a booking in the last 12 months" is a guess, not a confirmed rule), don't ship a computed KPI for it. Say in the REPORT that this needs a business-rule definition before it can be built, the same way Sprint 14 declined to invent the prototype's "19% request rate" journey conversion figures.

## Don't

- Don't create a CRM contact for an agency that doesn't have one. That's Sprint 14's `b2b_partner_activation` rule (Q-series decision, Task 03) and this page must not work around it.
- Don't let this controller write to `Agency`, `commission_pct`, or anything money-related. `panel.crm` guards reads; any write to those fields needs `panel.rms` and already has its endpoint.

## Tests

- An agency with a matched contact and an active `b2b_partner_activation` enrolment shows the current step and next due date.
- An agency with no matched contact appears on the list with null contact fields and no enrolment, not omitted.
- The endpoint returns no commission-rate write capability (a PATCH attempt against this controller doesn't exist / 404s, not 403 — there's genuinely no route).
- `GET .../{agency}` for an agency the signed-in user can't view (no `panel.crm`) is 403.

## Report

Append **Task 02**: the read model and its sources (name every field's origin table — this page computes nothing new), the no-contact case and why it's shown not hidden, the deliberately-omitted production KPI and what decision it's waiting on. Git commands listed, not run.
