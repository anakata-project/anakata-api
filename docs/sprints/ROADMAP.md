# Anakata build roadmap
Follows the build order in `docs/requirements/06-build-backlog.md`. Each sprint is a folder `sprint-NN/` with a `README.md` and ordered task files given to Cursor one at a time. Each sprint has its **API tasks first**, then the frontend parts that consume it. A sprint's frontend work starts only after its API part is merged and the OpenAPI types are regenerated.

| Sprint | anakata-api | Frontends |
|---|---|---|
| **0** | Docker extension, one database + test database, packages, module skeleton, health, CORS/Sanctum config, CI | anakata-ui: tokens, Nuxt UI theme, fonts, base `Ank*` components, style guide · panel: shell with RMS ⇄ CRM switch, both navigations, placeholders · engine: shell |
| **1** | Users, login (Sanctum), `Permission` enum + `roles` table (finance/director flags and `panel.rms` / `panel.crm` system access as permissions), own-records policy, append-only history, outbox + event envelope, sequence service for references | panel: login, role-aware navigation and system switch, role editor (Admin), topbar, theme toggle |
| **2** | Rates, business rules (33, with source codes), engine settings — as data, draft → publish with approval reference + history; seed from seed-data.json | panel · RMS: Rates & Promotions (incl. price check), Business Rules, Engine Settings, Permissions |
| **3** | Yachts, cabins, itineraries, departures (generate season, Sunday-only, locks), internal blocks, holds, waitlist, **computed availability** | panel · RMS: Calendar, Yacht Layout, Itineraries, Departures, Internal Blocks, Holds & Waitlist |
| **4** | Bookings, state machine, **pricing engine** (doc 02 + dev decision B2), groups, booking requests, date change, deleted/released audit | panel · RMS: Bookings list + Groups, booking panel (Overview, History), New Reservation, Booking Requests |
| **5** | Payments ledger, Stripe (links, webhooks), wire marking, reconciliation, commissions (12% cap), refunds + penalty bands | panel · RMS: Payments & Revenue, Payments tab, Refund Approvals |
| **6** | Guests (encrypted, dedicated-key cast via `SENSITIVE_DATA_KEY`), consents log, guardian consent, PNG/TCT fees + collection choice, extras catalogue + booking extras | panel · RMS: Guests tab, Extras tab, Contacts In |
| **7** | Documents: invoice (mockup v6), summary, receipts, reminders; PDF rendering; email via Graph (Mailpit locally) | panel · RMS: Documents tab, Documents & Manifests (client documents) |
| **8** | Engine API: published feed, offers + promo codes, 20+10 min holds, server repricing, request submission, waitlist capture, <30 s availability push | engine: the full 6-step flow + charter enquiry, exactly as the prototype, with the doc 04 changes |
| **9** | CRM core: contacts, identity resolution + aliases, consumers for RMS/engine events, attribution written once, behavioural events | panel · CRM: contacts, timeline, Web & Engine Activity, Sync & Field Ownership |
| **10** | CRM pipeline (stages 5–7 locked), tasks & SLA, consent register + subject requests, campaigns mirror, documents & delivery | panel · CRM: Pipeline & Forecast, Tasks & SLA, Consent & Data Rights, Campaigns & Offers, Documents & Delivery |
| **11** | Operations: manifests + DPNG export, guest preferences, hotel-manager brief, NPS, all scheduled jobs (doc 07 §7), alerts | panel · RMS: manifests, Guest Experience, B2B & Agent Portal (RMS side), alerts |
| Later | Agent portal front end, commercial dashboard and automated reports, charter lifecycle, e-signature | — |
