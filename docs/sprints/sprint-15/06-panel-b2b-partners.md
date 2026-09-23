# Task 06 · anakata-panel · B2B Partners page

**Repo:** anakata-panel · **Sprint:** 15 · **Needs:** Task 02 (API), Task 04 (types v0.16.0).

## Goal

Build `/crm/sales/b2b-partners`, replacing the catch-all. Flip that nav item from `sprint: 'later'` to `sprint: 15` in `app/navigation/crm.ts` and extend `guards.test.ts` the same way as every other page this sprint and Sprint 14 added.

## Repo check to do first

- Confirm `app/pages/crm/sales/b2b-partners.vue` doesn't already exist.
- Confirm how the panel currently deep-links from a CRM page to the equivalent RMS record (Sprint 14's automation-to-alert and automation-to-journey cross-links, and the enrolment drawer's `/rms/reservations/bookings?open={reference}` link gated on `can('panel.rms')`, are the patterns to copy for "open this agency in the RMS" here).
- Confirm what `journeyHelpers.ts` (Sprint 14 Task 08) already exposes for rendering a single journey enrolment's status/step/next-due — this page needs the same small summary for the `b2b_partner_activation` enrolment and should reuse that helper rather than reimplement it.

## Do

1. **List view.** `GET /api/crm/b2b-partners`. One card or row per agency: name, status pill, matched contact name (linking to `/crm/sales/contacts?open={id}` when present), commission rate, revenue and commission accrued (as returned — don't reformat or recompute), open deal count, and the `b2b_partner_activation` journey status/step (reuse the Sprint 14 helper per the repo-check above).

2. **No-contact case, shown plainly.** An agency with no matched contact and no enrolment shows that state explicitly (e.g. "No CRM contact matched — journey not enrolled"), not blank fields that look like a loading failure. This is a correct API state (Task 02), not an error, and the UI should read that way.

3. **Detail view.** Opens from a row: deal history (from the API's `{agency}` endpoint) and the full journey enrolment detail — reuse whatever `journeys.vue`'s enrolments drawer already renders for one enrolment's steps and sends, rather than building a second version of that view.

4. **RMS deep link.** "Open in RMS" on each row/detail, gated `can('panel.rms')`, to the existing agency record in the RMS agent-portal screen (Sprint 11) — link out, don't duplicate that screen's edit capability here. This page has no write actions at all.

5. **No production-count KPI.** Per Task 02's API, there's no "producing partners" figure to show. Don't add a placeholder or computed approximation in the frontend either — if the number doesn't exist on the API response, it doesn't appear on the page.

## Don't

- Don't add any write action — approve, change rate, edit relationship — on this page. It's read-only by design (Task 02); anything editable belongs on the RMS agency screen.
- Don't invent visual treatment beyond what the panel's existing card/list components already offer. This is a straightforward list-plus-detail page; no new design-system work should be needed.

## Checks

`pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm build`. Fresh-clone typecheck/build against the released `anakata-ui` tag.

Browser, both themes, after a seed reset: agencies with a matched contact show their journey step; the seeded agency with no matched contact (if the Sprint 14/11 seed data has one — confirm, and if not, note in the REPORT that this case needs a seed addition) shows the explicit no-contact state; the RMS deep link opens the right agency record; no write control is present anywhere on the page.

## Report

Append **Task 06**: which existing components/helpers were reused, the no-contact display treatment, confirmation this page has zero write actions, and the seed-data note if the no-contact case needed a new fixture. Git commands listed, not run.
