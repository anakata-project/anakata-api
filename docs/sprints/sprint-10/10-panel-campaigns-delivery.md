# Task 10 · anakata-panel · Campaigns & Offers; Documents & Delivery; navigation markers
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 10 · **Needs:** task 09.

## Goal
The last two CRM screens of the sprint — campaign measurement over RMS offers and the delivery log — and navigation that tells the truth about what is not built.

## Read first
- `prototype/crm_index.html`: `v-camp` (notice, campaign cards, attribution-model table and hint), `renderCamp`; `v-docs` (notice, delivery log, hint), `renderDocs`, `openDoc`
- This sprint's REPORT task 06; screenshots `crm-04-campaigns.png`, `crm-05-docs.png`
- `app/navigation/crm.ts`, `app/navigation/types.ts`, `PlaceholderPage.vue`

## Do
1. **Campaigns & Offers** — `app/pages/crm/marketing/campaigns.vue`:
   - Notice (i18n): offers are created and published in the RMS; the CRM measures what each campaign produced from the bookings that carry the offer code or the campaign's UTM key; it never creates a discount.
   - Campaign cards (the prototype's `campcard`): name and offer status pill; code · value; windows · channel with PUBLISHED IN RMS; audience; the measures from the API — redeemed, revenue, attributed first / last touch, trade, media spend, ROAS — with "—" where the API sends null and the sends note shown once. "Bookings" opens the list behind the measures, each reference linking to the RMS booking.
   - "Offers without a campaign" panel with Create campaign (`campaigns.manage`): name, offer (preselected), UTM key, audience, media spend. Edit and archive on each card.
   - The attribution-model table and hint from `GET …/attribution-model`.
2. **Documents & Delivery** — `app/pages/crm/sales/documents.vue`:
   - Notice (i18n): documents are issued once by the RMS; the CRM shows what was sent, when and what happened, and never renders or resends. Keep the LEG-004 bank-details sentence only if the API's issuer rule is still PENDING (read it from the business rules the panel already loads; otherwise leave it out).
   - KPI row from `meta.kpis`; the engagement note once.
   - The delivery log: booking, client, document with RMS-RENDERED badge, version and reason, channel tag, status pill with time, error or blocked reason, triggered by, superseded marker, and **Open in RMS** (the deep link from the API). Filters: status, kind, date range, booking reference.
   - No drawer copy about "why the CRM does not render" beyond one hint line; no Resend button.
3. **Navigation markers (M10).** `NavItem.sprint` becomes `number | 'later'`. Inbox, B2B Partners (CRM), Journeys, Segments and Automations are `'later'`; `PlaceholderPage` says "Planned for a later sprint" for those. Alerts stays 11. Update the navigation tests.
4. **Helpers, tested:** `deliveryStatusClass(status)`, `formatMeasure(value)` ("—" for null).

## Don't
- Don't compute a measure, a KPI or ROAS in the panel.
- Don't show recipient addresses, or offer a resend.
- Don't create or edit an offer from the CRM.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.11.0`.
- Browser, both themes, after `reset.sh`: create a campaign on OPENING-27 with a UTM key; its redeemed and revenue equal the seeded bookings that carry the code; book on the engine with `?utm_campaign=<key>` → attributed first touch rises by one; cancel a redeemed booking in the RMS → redeemed and revenue drop; the delivery log shows the seeded sends and a failed one; Open in RMS lands on the booking's Documents tab; placeholder pages say "Planned for a later sprint".

## Report
Append **Task 10**: both screens, the navigation change, helpers, and the browser pass. Git commands listed, not run.
