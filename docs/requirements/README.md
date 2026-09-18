# Anakata — RMS + CRM functional prototypes & developer handoff
**Version 1.3 · 15 September 2026 · HILO People, Inc. → Anakata development team**
Confidential — Anakata / Hilo. Contract ANK-CP-CON-004-2026.

---

## What this is
Two **working, clickable prototypes** — the Reservation Management System (RMS) and the CRM — plus the **integration contract** that binds them to the public booking engine, so the three behave as one automated sales pipeline.

`docs/07-three-system-integration-contract.md` is the load-bearing document of this version: it names, field by field, which system owns what, and event by event, how the other two find out. Read it before building anything.

The RMS is a **working, clickable prototype** of Anakata's Reservation Management System — the back office the commercial and operations teams use, and the system that feeds the public booking engine at anakata.co.

It is a **specification you can run**, not production code. Every screen, rule, calculation and document in it is derived from **ANK-COM-PRO-001-2026 Procesos Comerciales v5** (the contractual spec), the approved invoice mockup **v6**, and the booking-engine prototype (v3 flow, 14 Jul 2026). Where the source documents disagree or leave something open, the prototype flags it on screen. Anakata answered 20 of those questions on 12 Sep 2026 — the answers are applied throughout and recorded in `docs/05-decisions-and-open-questions.md`, together with what is still open.

Build the real system from the source document plus this package: the prototype settles the interaction design, the field-level data model, the calculations and the document layouts, so those do not have to be re-litigated in development.

## How to open it
Open `prototype/index.html` (RMS) and `prototype/crm.html` (CRM) in any modern browser (Chrome, Safari, Firefox). No server, no build step, no dependencies beyond two Google Fonts. Everything runs in memory: **reloading resets all data**.

Try this path first:
1. **Role switcher** (top right) — Admin (Carolina, director + finance) · Manager (Mateo) · Sales Exec (Lucía). Permissions change live.
2. **Bookings → ANK-2026-0005** — the booking panel with its six tabs (Overview, Guests, Extras, Payments, Documents, History).
3. **Documents tab → Booking Confirmation & Invoice → Preview** — the invoice generated from live data; it reproduces mockup v6 exactly (USD 28,520 for ANK-2026-0003), issued by PONTOS LLC. In the Extras tab, switch the park fee off and the invoice moves it to an information-only block.
4. **＋ New Reservation** — add a second cabin, watch the live quote, availability check and group creation.
5. **Rates & Promotions** — change a price, see the price-check table, publish with an approval reference.
6. **Admin → Business Rules** (Admin only) — every rule, its source code in the spec, and what differs from the confirmed value.

Then open `prototype/crm.html` and go straight to **System → Sync & Field Ownership**: the ownership matrix, the event catalogue, the live event bus with replay, identity resolution, the scheduled jobs, and an explicit list of what was deleted from the CRM as duplication and what was added to close gaps. After that, **Pipeline & Forecast** (stages 5–7 are locked because the RMS sets them) and **Tasks & SLA** (every commercial SLA as a work queue).

## Contents
```
prototype/index.html                  the RMS prototype (single file, ~400 KB)
prototype/crm.html                    the CRM prototype (single file, ~228 KB)
docs/01-functional-spec.md            module-by-module functional spec
docs/02-data-model.md                 entities, fields, states, derived values
docs/03-business-rules.md             every rule, its source (FIN-/OPS-/TEC-), where it is configured
docs/04-booking-engine-contract.md    what the RMS must publish to anakata.co, and the sync rules
docs/05-decisions-and-open-questions.md  Anakata's decisions of 12 Sep 2026 + what is still open
docs/06-build-backlog.md              suggested build order, what is NOT in the prototype, non-functional requirements
docs/07-three-system-integration-contract.md  ENGINE ↔ RMS ↔ CRM: field ownership, event catalogue, identity, jobs, data map
examples/booking-engine-feed.json     live export of the feed contract (from the prototype)
examples/seed-data.json               every entity with realistic sample data (use as fixtures / test data)
screenshots/*.png                     18 RMS screenshots + 8 CRM screenshots (crm-*.png)
```

## What is real vs mocked
| Real in the prototype | Mocked / out of scope |
|---|---|
| Data model, states, permissions, all calculations (pricing, deposits, fees, commissions, penalties) | Persistence — no backend, no database |
| Document layouts and content (invoice, summary, receipts, reminders, manifests) | Sending — no email, PDF is the browser's "print to PDF" |
| Rules engine, validation, approval flows and audit logging | Authentication — the role is a dropdown |
| The booking-engine feed (see `examples/booking-engine-feed.json`) | Real-time sync to anakata.co and the payment gateway |
| The three-system contract: ownership matrix, event catalogue, stage↔status map, consent model | The event bus itself — the CRM's bus view is a simulation, not a transport |
| Availability logic (no overbooking, holds, blocks) | Automated jobs (nightly hold release, reminders, alerts) |

## Reference documents (not included here — in the Anakata project)
- ANK-COM-PRO-001-2026 Procesos Comerciales **v5** — the contractual spec. Section references throughout these docs (§4.3, §6.2, FIN-001…) point at it.
- ANK20270042 Booking Confirmation & Invoice **MOCKUP v6** + Booking Summary (guest) — document layouts.
- Anakata Brand Guidelines 2025.01 and Brand System 2.0 — the visual system the prototype follows.
- Anakata D2C Pre-Launch Build Roadmap — where the RMS sits in the wider programme.

## The one rule the three systems are built on
**One field, one owner.** Exactly one system may write any given field; the others hold read-only mirrors kept current by events. There is no two-way field sync anywhere in this design, so there is no merge logic and no silent drift. In practice: the **RMS is the ledger** (inventory, rates, offers, bookings, payments, commissions, documents, guest personal data), the **booking engine is the shop window** (it renders what the RMS publishes and captures behaviour), and the **CRM is the relationship** (identity, attribution, consent, lifecycle, segments, journeys, deals, tasks, conversations). The CRM has no write path to money — that is an authorisation rule, not a UI convention.

## Questions
Eric Schvartzman — eric@hilopeople.com — HILO People, Inc. (Madrid · Miami)
