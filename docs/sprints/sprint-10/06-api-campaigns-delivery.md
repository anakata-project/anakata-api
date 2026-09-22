# Task 06 · anakata-api · Campaigns and the delivery log
**Repo:** anakata-api · **Sprint:** 10 · **Needs:** task 05.

## Goal
Two read-mostly CRM surfaces over RMS data: campaigns that measure what each published offer and UTM campaign produced in bookings, and a delivery log of every document the RMS issued and sent.

## Read first
- `docs/requirements/08-dev-decisions.md`: **M8, M9**, and J2, J5, J6, J9, K2, L8, L9
- `07-three-system-integration-contract.md` §3 (Offers, Documents, Attribution rows), §9 (Documents & Delivery replaced Invoices)
- `prototype/crm_index.html`: `v-camp`, `CAMPMETA`, `renderCamp`, the attribution-model table; `v-docs`, `renderDocs`, `openDoc`
- `Offer` (status, windows, derived EXPIRED), bookings' `price_lines` (applied offers carry the offer code as the line code) and `promo_code`, the frozen `utm_first` / `utm_last` on bookings, `Document`, `Delivery`, `DocumentPlan`

## Do
All commands run as `docker compose exec app sh -c "…"`.

### Campaigns (M8)
1. **Schema.** `campaigns`: `name`, `offer_id` (nullable — a campaign may be UTM-only), `utm_campaign` (nullable, stored lower-case and trimmed, unique when set; at least one of offer or key is required), `audience` (text, max 500 — a description, not a segment), `media_spend` (integer USD, default 0), `status` (ACTIVE, ARCHIVED), `owner_id`, audit columns. History `campaign.*` on every change, including spend with before and after (spend is not ledger money, M8).
2. **Endpoints** (`panel.crm`; writes need new permission `campaigns.manage`, Admin and Manager by default):
   - `GET /api/crm/campaigns` — each campaign with its offer read live from `offers` (code, name, type, value text, channel, status with EXPIRED derived, booking and travel windows) and the measures, all in SQL:
     - **redeemed** — sold bookings (the L2 sold statuses) whose `price_lines` contain a line with the offer's code, or whose `promo_code` is the offer's code;
     - **revenue** — the charges total (I9 SQL) of those bookings;
     - **attributed (first touch)** and **attributed (last touch)** — sold bookings whose frozen `utm_first.campaign` / `utm_last.campaign` equals the key, case-insensitive; with their revenue;
     - **trade** — how many of the redeemed bookings carry an agency (trade attribution; both are shown, neither overwrites the other);
     - **ROAS** — revenue ÷ media spend, null when spend is 0;
     - **sends** and **clicks** — null, with `meta.notes.sends` = "Marketing email is not built yet" (M8).
   - `GET /api/crm/campaigns/offers-without-campaign` — live and pending offers no active campaign covers, so the panel can offer "Create campaign".
   - `POST`, `PATCH`, `POST …/{id}/archive`.
   - `GET /api/crm/campaigns/{id}/bookings` — the bookings behind each measure (reference, departure, status, charges total, which measure they count in), paginated.
   - `GET /api/crm/campaigns/attribution-model` — the prototype's attribution-model table as a PHP registry, written as implemented (captured by, stored on, used for).
3. **No discount path.** Nothing here creates or edits an offer; an arch test forbids CRM controllers from using `App\Actions\Offers`.

### Documents & Delivery (M9)
4. **`GET /api/crm/deliveries`** (`panel.crm`) — every delivery, newest first, paginated: booking reference and id, client (the contact's name), document kind label, document version and its reason (from `documents`) or the delivery kind when there is no document (payment link, reminder), channel (EMAIL; WhatsApp is TEC-005), status (QUEUED, SENT, FAILED, BLOCKED), sent or created time, the error's first line or the blocked reason, recipients as a count (not addresses), triggered by (system, or the user's name), and whether a later version superseded it. Filters: status, kind, `from` / `to`, booking reference, contact. `meta.kpis`: sent today, failed, blocked, queued for more than 15 minutes — in SQL. `meta.notes.engagement` = "Opens and downloads are not tracked (LEG-002)".
5. **Link, don't act.** Each row carries the RMS deep link to the booking's Documents tab. No resend, render or issue endpoint under `/api/crm` (the failed-delivery Resend on Sync from Sprint 9 stays as it is). The guard and the sensitive-field walk cover the resource.

## Don't
- Don't create, edit, approve or price an offer.
- Don't compute revenue from anything but the sold bookings' charges SQL.
- Don't return recipient addresses, PDFs or snapshots from the CRM.

## Checks
- `composer check`.
- Redemption by price line and by promo code; a cancelled booking drops out; a value-add offer's zero line still counts as a redemption.
- UTM attribution first vs last, case-insensitive; a booking counted by offer and by UTM appears in both measures.
- ROAS null at zero spend; spend history.
- Delivery list agrees with the booking's documents view for the seeded bookings; filters; KPIs; supersession.
- Arch tests; the sensitive-field walk; the CRM schema test.

## Report
Append **Task 06**: the campaign schema and measures with their SQL sources, what is not measured and why, the delivery log fields and KPIs, and the link-only rule. Git commands listed, not run.
