# 06 · Build backlog, scope and non-functional requirements

## Not in the prototype — still to specify and build
1. **Commercial dashboard & automated reports (§9.1–9.2)** — occupancy by departure, RevPAB, ADR, booking lead time, channel mix, nationality, NPS, commissions outstanding; daily commercial summary (08:00 ECT), weekly occupancy (Mon 09:00), monthly financial and pipeline, quarterly agency report.
2. **Alerts inbox (§5.4 g)** — overdue (day 1), commission > 12%, low occupancy (<40% at T−90), SLA breaches, NPS < 7, wire not received. Today each alert is only visible inside its own module.
3. **Charter lifecycle (§3.3, §4.1.6–4.1.7)** — proposal/quote document, e-signature (TEC-003), charter-specific cancellation bands, charter deposit clock (5 business days).
4. **Waitlist automation (R-B5)** and the engine's "Limited Availability — Contact Us" state.
5. **Automated jobs** — nightly hold release, balance reminders (T−21 / T−7), pre-trip itinerary and questionnaire (T−45), transfer voucher (T−7), DPNG list (T−15/T−30), captain's manifest (T−7), NPS 24 h after disembarkation, cart recovery (24 h / 48 h / day 7).
6. **CRM** — contacts, segmentation, 7-stage pipeline and the automations in §5.4 (the RMS shows only the contacts that bookings create).
7. **Agent portal front end** — the RMS side is built (registration, approval, net rates, commissions); the agent-facing site with its own authentication is not.
8. **Payment gateway integration** — **Stripe** (confirmed 12 Sep 2026), 3DS, webhooks, wire instructions PDF, refund execution, PCI scope. Transactional email through **Microsoft Exchange**; charter e-signature provider still to be chosen (TEC-003).

## Suggested build order
**Phase 1 — foundations.** Auth and roles (own-records rule, finance and director flags) · rates, engine settings and business rules as data · inventory model (departures, cabins, holds, blocks) · bookings with states and the pricing engine · change history on everything.
**Phase 2 — money and guests.** Payments ledger, gateway integration and reconciliation · guests, consents and encryption · documents (invoice, summary, receipts) · groups and multi-cabin.
**Phase 3 — engine.** Itineraries, departures, offers and the feed with < 30 s sync · web booking request → REQUESTED → deposit link → CONFIRMED · holds.
**Phase 4 — operations.** Manifests and DPNG export · guest experience (questionnaire, hotel-manager brief, NPS) · automated jobs and alerts inbox · agent portal.
**Phase 5 — visibility.** Commercial dashboard, automated reports, low-occupancy alerts, Spanish for internal screens.

## Non-functional requirements
- **Availability SLA** — RMS change → public site in < 30 s (§10). 99.9% uptime in high season (ILTM, Virtuoso Travel Week).
- **Audit** — append-only history on bookings, rates, rules, settings, agent-portal actions; approval reference required for rate and rule changes; nothing deletable.
- **Security** — passports and medical data encrypted (AES-256 minimum), restricted by role; consent log kept 7 years; passport data deleted or anonymised 2 years after the cruise; EU passenger data on GDPR-adequate infrastructure; PCI scope kept in the gateway.
- **Server-side enforcement** — every permission, status transition, price, discount, commission cap and availability check must be enforced on the server; the UI is only a convenience.
- **Money** — a booking's price and deposit % are frozen at sale; later rate changes never alter sold bookings.
- **Language** — English only, guest-facing and internal (Anakata decision 12 Sep 2026, supersedes §8.0). Keep strings externalised so a second locale stays possible.
- **Documents** — generated as PDFs from the layouts in the prototype (mockup v6), attached to transactional emails, stored against the booking.

## How to use the prototype during development
- Treat the prototype as the reference for behaviour: when a question comes up, open the relevant tab and look. Where it is wrong or thin, `05-open-questions.md` says so.
- `examples/seed-data.json` is a complete fixture set (bookings in every state, payments, guests, groups, agencies, itineraries, departures, offers, rates, rules).
- The pricing and PNG-fee test values in `02-data-model.md` make good unit tests.
- Screenshots in `screenshots/` are the visual reference; the brand system is in Anakata Brand Guidelines 2025.01 and Brand System 2.0.
