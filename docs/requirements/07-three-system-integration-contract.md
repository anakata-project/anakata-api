# Anakata — Three-System Integration Contract

**Booking Engine ↔ RMS ↔ CRM**
Version 1.3 · 15 September 2026 · Hilo for Anakata
Source documents: ANK-COM-PRO-001-2026 *Procesos Comerciales v5*, invoice mockup v6, booking-engine prototype v3, RMS prototype v1.2, CRM prototype v2.

---

## 1. The one rule

**One field, one owner.** Exactly one system may write any given field. Every other system holds a read-only mirror kept current by events.

There is **no two-way field synchronisation anywhere in this design**, which means there is no last-writer-wins conflict to resolve, no merge logic, and no silent drift. A write attempted by a non-owner is rejected at the API boundary and raises a sync alert.

This is the single most important decision in the stack. Every ambiguity below resolves back to it.

---

## 2. What each system is for

| System | Owns | Never owns |
|---|---|---|
| **Booking Engine** (public site) | Presentation and capture. Behavioural events, form submissions, consent capture at the point of collection, UTM attribution. | Prices, availability, content — it renders what the RMS publishes. |
| **RMS** (reservations) | The commercial and financial truth: inventory, departures, rates, offers, bookings, statuses, payments, refunds, commissions, documents, guest personal data, policies. | People-level marketing state: consent, lifecycle, segments, journeys, conversations. |
| **CRM** (revenue engine) | People and the sales motion: contact identity, attribution, consent, lifecycle, segments, journeys, deals, tasks, conversations, campaign measurement, alerts. | Money. It never prices, never invoices, never accrues commission, never renders a document. |

Short version: **the RMS is the ledger, the engine is the shop window, the CRM is the relationship.**

---

## 3. Field ownership matrix

| Object | Field group | System of record | Mirrored to | Rule |
|---|---|---|---|---|
| Itinerary | Name, description, day plan, media, SEO | **RMS** | Engine (published feed) · CRM | The engine renders what the RMS publishes; nothing is authored on the site. |
| Departure | Date, yacht, itinerary, status, capacity | **RMS** | Engine · CRM | One row per yacht per Sunday. ANAMARA and ANATIVA are twin hulls. |
| Availability & holds | Cabin state, web hold (20 + 10 min), request hold | **RMS** | Engine · CRM | Derived from bookings, requests, holds and blocks — never typed anywhere. |
| Rates | Base rate by year and cabin type, discounts, supplements | **RMS** | Engine · CRM | Draft → publish with an approval reference and an append-only history. |
| Offers | Code, benefit, scope, windows, badge, terms | **RMS** | Engine · CRM (campaign mirror) | The CRM builds the audience and the creative; it cannot create a discount. |
| Booking | Reference, status, cabins, pax, totals, balance calendar | **RMS** | CRM (read) | The pipeline stage follows the booking status, never the reverse. |
| Payments & refunds | Amount, method, gateway reference, settlement | **RMS** | CRM (read) | Stripe is the gateway; the RMS holds the ledger. The CRM only delivers links. |
| Commission | Rate, cap breach, accrual, payout | **RMS** | CRM (read) | One ledger, one audit trail. The 12% cap is enforced in the RMS. |
| Documents | Invoice, summary, receipts, vouchers, manifests | **RMS** | CRM (delivery record) | One renderer, one numbering sequence, one template version. |
| Guest personal data | Passport, DOB, nationality, medical, dietary | **RMS** | **Not replicated** | Encrypted at rest; deliberately absent from the CRM (LEG-002). |
| Contact | Identity, email, phone, country, language, preferred channel | **CRM** | RMS (read, on the booking) | Email is the primary key across all three systems. |
| Attribution | Main channel, channel of origin, UTM first and last touch | **CRM** | RMS (written once at booking creation) | Trade attribution wins over marketing last-touch for commission. |
| Consent | Marketing, profiling, remarketing, WhatsApp · text version + timestamp | **CRM** | RMS · Engine · Exchange | Checked at send time, not at enrolment. |
| Lifecycle & segment | PROSPECT → MQL → SQL → BOOKED → GUEST → PAST GUEST | **CRM** | Engine (personalisation) | Derived from RMS status and engine behaviour — never typed. |
| Deal & pipeline | Stage, owner, value, SLA timers, loss reason | **CRM** | — | Stages 5–8 are set by RMS events. |
| Tasks | Work queue, due dates, escalation | **CRM** | — | Raised by system events; completion never writes to a booking. |
| Conversations | Email and WhatsApp threads, templates, message-ids | **CRM** | RMS (link only) | The RMS shows a link to the thread; it never stores a copy. |
| Partner relationship | Contacts, nurture journey, production targets | **CRM** | — | The agreement and the ledger stay in the RMS. |
| Behavioural events | Page, itinerary, departure, checkout, abandon, consent | **Engine** | CRM (all) · RMS (hold and request only) | Anonymous events stitch to a contact on email capture. |

---

## 4. Event catalogue

Transport: HTTPS webhooks with an outbox pattern on each emitter. Delivery is **at-least-once**; every event carries `event_id`, `occurred_at`, `actor` and a typed payload; consumers dedupe on `event_id`. Three retries with exponential back-off, then a `[SYNC]` alert to the tech owner.

### 4.1 Engine → RMS (the only two events that touch inventory)

| Event | Payload | Effect in the RMS |
|---|---|---|
| `hold.placed` | departure, cabin, session, 20-min TTL | Cabin locked; extension of 10 min available once. |
| `request.submitted` | guest, departure, cabins, pax, notes, attribution, consent | Creates a booking in status `REQUESTED` with the hold converted (48 h near-term / 5 business days long-term). |

### 4.2 Engine → CRM

| Event | Effect in the CRM |
|---|---|
| `lead.captured` | Contact created · welcome automation · nurture enrolment. |
| `view_itinerary` / `view_departure` | Timeline · intent score · segment recompute. |
| `begin_checkout` | Abandoned-checkout segment armed. |
| `abandon_checkout` | Cart-recovery journey (24 h / 48 h / day 7). |
| `charter_inquiry.submitted` | High-value alert · deal created in NEW LEAD · 24 h quote SLA task. |
| `newsletter.subscribe` | Contact + marketing consent. |
| `consent.captured` | Consent register entry — governs every later send. |
| `identity.stitched` | Anonymous session back-filled onto the contact timeline. |

### 4.3 RMS → Engine (the published feed)

Itineraries, departures, live availability, rates, offers and engine settings. Pull or push; the engine holds no authored content of its own.

### 4.4 RMS → CRM

| Event | Effect in the CRM |
|---|---|
| `booking.created` | Deal binds to the booking · transactional journey starts · stage → DEPOSIT PENDING. |
| `booking.status_changed` | Pipeline stage moves · lifecycle updates. |
| `payment.received` | Stage → BOOKING CONFIRMED · receipt delivered · cash KPI recomputed. |
| `payment.overdue` | Overdue alert + task · **no auto-cancel** (OPS-007). |
| `refund.issued` | Timeline · LTV recalculated · 15-day SLA closed. |
| `document.generated` | Delivery queued on the preferred channel · supersedes the prior version. |
| `extras.added` | Invoice re-issued · extras journey exits. |
| `commission.cap_breach` | Booking blocked from CONFIRMED · Director alert and task (FIN-005). |
| `agency.approved` | Partner activation journey starts · lifecycle → AGENT. |
| `occupancy.below_threshold` | Occupancy alert · action-plan task (< 40% at 90 days). |
| `hold.expired` | Deal → LOST · win-back journey. |
| `cruise.completed` | Stage → WON · NPS at +24 h · re-engagement at +6 months. |
| `offer.published` | Campaign mirror created in the CRM. |

### 4.5 CRM → RMS

| Event | Effect in the RMS |
|---|---|
| `contact.identity_resolved` | Bookings repoint to the surviving `contact_id`; the losing id is kept as an alias forever. |
| `contact.consent_changed` | Suppression applied; transactional sends continue (contract basis). |
| `deal.attribution_set` | Main channel, channel of origin and UTM written **once** onto the booking at creation. |
| `nps.recorded` | Guest record annotated. |
| `partner.owner_set` | Relationship owner on the partner record. |

### 4.6 CRM → Engine

`segment.changed` → on-site personalisation, suppression list, Meta/Google remarketing audiences.

### 4.7 External providers

| Provider | Role | Status |
|---|---|---|
| **Stripe** | Card payments and payment links. Links are created by the RMS, delivered by the CRM. Anakata stores no card data. | Confirmed (TEC-001) |
| **Microsoft Exchange** | All transactional and marketing email. | Confirmed (TEC-002) |
| **WhatsApp Business API** | Second channel in the CRM inbox. 24 h free-form window enforced; outside it, approved templates only. | **Provider pending (TEC-005)** |
| E-signature | Charter contracts and partner rate agreements. | **Pending (TEC-003)** |

---

## 5. Pipeline stage ↔ booking status

| CRM stage | Owner | Enters when | RMS status | Leaves when |
|---|---|---|---|---|
| 1 · NEW LEAD | CRM | `lead.captured` or `charter_inquiry.submitted` | — | Sales exec qualifies |
| 2 · QUALIFYING | CRM | Sales exec accepts the lead | — | Budget, dates and party confirmed |
| 3 · SQL — QUOTED | CRM | Quote issued from the RMS | — | Client engages on the quote |
| 4 · NEGOTIATION | CRM | Terms under discussion | — | Request or booking created |
| 5 · DEPOSIT PENDING | **RMS** | `booking.created` with an active hold | `REQUESTED` · `PENDING_PAYMENT` | Deposit verified, or hold expires |
| 6 · BOOKING CONFIRMED | **RMS** | `payment.received` — deposit verified | `CONFIRMED` · `OVERDUE` · `FULLY_PAID` | Cruise completes or booking cancels |
| 7 · WON — COMPLETED | **RMS** | `cruise.completed` | `COMPLETED` | Never — becomes a past guest |
| 8 · LOST | RMS / CRM | `booking.cancelled`, `hold.expired`, or a person marks it lost with a reason | `CANCELLED` | Re-engagement at 6 months |

Stages 5–7 are **not draggable** in the CRM. This is what stops the pipeline and the reservations system describing different worlds.

---

## 6. Identity resolution

1. **Email (normalised)** — primary key across all three systems. Case-folded; plus-addressing preserved.
2. **Booking reference** — a request or booking created in the RMS carries the CRM `contact_id`; if absent, the RMS creates a contact stub and the CRM claims it on the next email match.
3. **Phone / WhatsApp (E.164)** — stitches WhatsApp threads to a contact that arrived by email.
4. **Anonymous engine session** — first-party cookie; back-filled onto the timeline the moment an email is captured.

**Merge rule:** duplicates merge into the oldest `contact_id`; the losing id is kept as an alias forever so no RMS booking ever points at a dead record. Merges are logged and reversible for 30 days.

---

## 7. Scheduled jobs

| Job | Cadence | Purpose |
|---|---|---|
| Ledger reconcile | Nightly 02:00 ECT | Rebuilds CRM cash KPIs from every RMS payment. Drift is reported, never silently corrected. |
| Commission leakage scan | Nightly 02:30 | Trade bookings with no partner ID; partners over the 12% cap; missing rate agreements. |
| Hold expiry sweep | Every 5 min | RMS releases expired holds and emits `hold.expired`. |
| Occupancy check | Daily 07:00 | Departures under 40% sold at 90 days out. |
| Document version check | Hourly | Re-queues delivery when a document version is behind the latest RMS event. |
| Segment recompute | Every 15 min | Rebuilds membership; pushes audience deltas. |
| Consent sweep | Daily 03:00 | Propagates unsubscribes and erasure to all three systems and the email provider. |

---

## 8. Personal data map

| Data | Stored in | In the CRM? | Retention |
|---|---|---|---|
| Name, email, phone, country, language | CRM | Yes | 3 years without a booking, then purge |
| Passport number, DOB, nationality | RMS | **Never** | Encrypted AES-256 · 7 years (fiscal) |
| Medical, dietary, mobility notes | RMS | **Never** | Purged 90 days after disembarkation |
| Card data | Stripe | No — token only | Held by Stripe; Anakata stores no PAN |
| Invoices, payments, commissions | RMS | Reference only | 7 years |
| Conversations (email / WhatsApp) | CRM | Yes | 5 years |
| Behavioural events | CRM | Yes | 24 months raw, then aggregated |

Subject requests (access, erasure, rectification, objection) are assembled across the CRM and the RMS and answered as one export. Erasure anonymises personal data but **retains financial documents for 7 years** as a legal obligation. Full architecture pending client input (LEG-002).

---

## 9. Changes made to the CRM in v2

### Deleted — duplication removed

| Removed | Why | Replaced by |
|---|---|---|
| CRM-side invoice and document generator | Two renderers means two template versions and two numbering sequences | Documents & Delivery tab — the RMS renders, the CRM delivers and records |
| Hardcoded cash figures (`RMS_CASH`) | Claimed to be live but was typed | Every cash KPI computed from the mirrored payment ledger |
| CRM-side commission arithmetic | Accrued / paid / pending were typed into the partner table | Derived from the RMS ledger, capped at 12%, no CRM write path |
| Free-text acquisition channel (6 invented values) | Did not match the engine or the RMS | The shared enums: main channel (8) and channel of origin (40) |
| Manual LTV and segment values | Typed per contact | Computed from RMS bookings |
| "Open booking" placeholder button | Explained a shared record instead of opening one | Deep link resolving the RMS reference from the thread |
| Duplicate partner lifecycle | Ran alongside the RMS approval workflow | RMS status is authoritative; the CRM keeps the relationship stage |
| Drag-anywhere pipeline | Let the pipeline disagree with the bookings | Stages 5–7 system-set and locked |

### Added — gaps closed

| Added | What it does |
|---|---|
| **Sync & Field Ownership** tab | The contract itself: ownership matrix, event catalogue, live bus with replay, identity resolution, scheduled jobs |
| **Tasks & SLA** tab | Every commercial SLA becomes a task with an owner, a due time and an escalation |
| **Web & Engine Activity** tab | The engine's behavioural stream, stitched to contacts, feeding segments and recovery journeys |
| **Campaigns & Offers** tab | Offers mirrored from the RMS; redemption and revenue measured against the ledger |
| **Consent & Data Rights** tab | Consent register with basis and capture point, personal-data map, subject-request flows |
| **Documents & Delivery** tab | Replaces Invoices: version, reason, channel, delivery state, engagement |
| RMS binding on every deal | Booking reference, departure, cabin and live status on the card and in the drawer |
| Shared enums | The same main-channel and channel-of-origin lists as the RMS and the engine |
| Extras journey | Extras close 72 h before departure, then sell on board and invoice after the cruise |
| Win-back journey | Expired holds and cancellations now have a three-step follow-up |
| Suppression segment | Unsubscribes, withdrawn consent, erasure and hard bounces excluded from every send |
| Sync failure alert | An event undelivered after three retries raises an alert and a task |

### Aligned to confirmed decisions

Stripe (TEC-001) · Microsoft Exchange (TEC-002) · WhatsApp provider pending (TEC-005) · e-signature pending (TEC-003) · **all customer communication in English** · yachts **ANAMARA / ANATIVA**, twin hulls · issuer **PONTOS LLC**, 430 Grand Bay Drive Apt 1108, Key Biscayne, FL 33149, **EIN 42-4742064**, bank details pending (LEG-004) · deposit 10% **on cabin charges only** · balance at T−120 · commission cap 12%, default 10% · request SLA 24 h · quote SLA 24 h for FIT, groups and charter (OPS-009) · refund SLA 15 days · web hold 20 min + 10 min extension · request hold 48 h near-term / 5 business days long-term · extras due 72 h before departure and sold on board thereafter · annual rate increase 5% · **no automation ever cancels a booking** (OPS-007).

---

## 10. Build implications

Things the development team should take from this document rather than infer:

1. **Build the event bus first.** Both prototypes assume it. Without it the three systems are three spreadsheets.
2. **The CRM has no write path to money.** No endpoint in the CRM service may create a price, an invoice, a commission accrual or a payment. This is an authorisation rule, not a UI convention.
3. **Attribution is written once.** `deal.attribution_set` fires at booking creation and never again; later marketing touches update the CRM only.
4. **Consent is evaluated at send time.** Not at enrolment, not at segment build.
5. **Passport and medical data must not be reachable from the CRM service account.** Enforce at the database role level, not in application code.
6. **Idempotency keys are mandatory** on every consumer. A replayed `payment.received` must not send a second receipt.
7. **The pipeline is a projection.** If a CRM stage and an RMS status ever disagree, the RMS wins and the CRM self-heals on the next reconcile.

---

## 11. Still open with the client

| Ref | Item |
|---|---|
| LEG-001 | Cancellation policy text (bands modelled as 120 d / 5%, 90 d / 50%, else 100%) |
| LEG-002 | LOPDP / GDPR architecture |
| LEG-004 | PONTOS LLC bank details for wire instructions |
| TEC-003 | E-signature provider |
| TEC-005 | WhatsApp Business API provider |
| PRO-001 | 2027 departure calendar confirmation |
| — | On-board charging mechanics for extras sold during the cruise |
| — | Photography / media licensing for engine and campaign use |
