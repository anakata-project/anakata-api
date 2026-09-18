# 03 · Business rules register
Source: ANK-COM-PRO-001-2026 v5 §12.1 (CEO-confirmed decisions), §3.4 (pricing), §4.1.4–4.1.7 (payments, cancellation), §2.5 (fees), §10 (SLA master).
"Configured in" = where a person changes it in the RMS. Everything marked **confirmed** is a CEO decision: it can be changed only with an approval reference, which the system logs.

| Source | Rule | Value | Status | Configured in |
|---|---|---|---|---|
| FIN-001 | Base rates 2027 — Suite / Owner's / Charter | 13,300 · 25,000 ppdo · 199,500 per week | confirmed | Rates & Promotions |
| FIN-001 | Annual increase | +5% per year | confirmed (re-confirmed 12 Sep 2026) | Rates (helper default 5%) |
| FIN-002 | Cabin deposit / balance | 10% · 90% at T−120 — deposit on cruise charges only | confirmed | Rates |
| — | Extras & collected fees due | up to 72 h before departure; on-board services settled during / after the cruise | Anakata 12 Sep 2026 | Business Rules |
| FIN-003 | Charter deposit / balance | 20% within 5 business days · 80% at T−120 | confirmed | Rates |
| §3.4.1 | Single supplement | +75% ppdo, always | confirmed | Rates |
| §3.4.1 | Triple sharing | −10% ppdo × 3, not festive, not with child rate | confirmed | Rates |
| OPS-004 | Child discount, ages 6–17 | −15% ppdo, max 1/adult, 2/couple, not festive | confirmed | Rates |
| §3.4.1 | Back-to-back | −5% both weeks, not festive — **cabin bookings only** (Anakata 12 Sep 2026) | confirmed | Rates |
| §3.4.1 | Festive supplement | +USD 750 pp · +USD 12,000 charter; blocks all discounts | confirmed | Rates |
| FIN-004 | PNG entry fee | foreign >12 200 · foreign ≤12 100 · CAN adult 100 · CAN minor 30 · nationals & residents 30 at any age (Anakata 12 Sep 2026) · TCT 20. The guest chooses whether Anakata collects them | confirmed | Engine Settings (amounts) · booking Extras tab (who collects) |
| FIN-005 | Agency commission cap | 12%; above → Director approval, booking blocked | confirmed | Business Rules |
| FIN-006 | Modification fee | none; date changes free, subject to availability | confirmed | Business Rules |
| OPS-001 | Duration | 7 nights, Sunday → Sunday | confirmed | locked (structural) |
| OPS-002 | Capacity | 9 cabins (8 suites + owner's), 16 PAX · ANAMARA and ANATIVA are identical twins | confirmed | Engine Settings (16/yacht) |
| OPS-003 | Home port | San Cristóbal (SCY) | confirmed | Itineraries (embark/disembark) |
| OPS-004 | Minimum age | 6 on departure day | confirmed | Engine Settings |
| OPS-005 | Travel insurance | passenger's responsibility; declaration mandatory at step 5 | confirmed | Guests tab (per passenger) |
| OPS-006 | Sales open / first cruise | 1 Nov 2026 / 7 Nov 2027 (re-confirmed 12 Sep 2026) | confirmed; rest of the 2027 calendar pending (PRO-001) | Departures |
| OPS-007 | Overdue balance | alert the team, never auto-cancel; decision + reason logged | confirmed | locked (behaviour) |
| OPS-008 | FIT vs groups | same rates, conditions, process; coordinator is the only difference | confirmed | locked |
| OPS-009 | Quote / first-response SLA | 24 h (FIT, groups, charter) | confirmed | Business Rules |
| OPS-013 | DPNG manifest | T−15 FIT / T−30 charter | confirmed | Business Rules |
| TEC-004 / OPS-010 | Holds | 48 business hours near-term / 5 business days long-lead | confirmed | Business Rules |
| R-B2 | Web checkout hold | 20 min + one silent 10 min extension | confirmed 12 Sep 2026 | Business Rules |
| R-B5 | Waitlist | FIFO, notified when a cabin frees | RMS spec | locked |
| R-B6 | Internal blocks | fam trip · maintenance · negotiation · courtesy; unsellable | RMS spec | Internal Blocks |
| §4.1.4 | Balance reminders | 21 and 7 days before the due date | confirmed | Business Rules |
| §4.1.5 | Cancellation penalties | ≥120 d 5% · 90–119 d 50% · 0–89 d 100% | system logic live; **customer-facing text being written by Anakata** (LEG-001) | Business Rules |
| §4.4 | Overbooking | never; last cabin on hold shows "Limited Availability — Contact Us" + waitlist | confirmed | engine + Departures |
| §4.4 | Hold release | nightly job releases expired holds; real-time path is primary | confirmed | job (to build) |
| §5.5 | Agent registration | approved within 2 business days; agents see net rates only | confirmed | B2B & Agent Portal |
| §6.4 | Consent & data | 4 consents + optional marketing, logged with IP/timestamp/version, 7 years; passports encrypted, deleted 2 years post-cruise; minors need guardian consent | **pending LEG-002** | Guests tab |
| §10 | Availability to site | < 30 s after any RMS change | confirmed | engine sync |
| §10 | Refund execution | 15 business days | confirmed 12 Sep 2026 | Business Rules |
| §10 | Low-occupancy alert | < 40% sold at 90 days before departure | confirmed | not built yet |
| §10 | Commission payment | 30 days after cruise completion | confirmed | Payments |
| RMS | Wire window | 72 h, then the pending payment is released | confirmed 12 Sep 2026 | Business Rules |
| RMS | Default agency commission | 10% | confirmed 12 Sep 2026 | Business Rules |

| — | Language | English only, guest-facing and internal (supersedes §8.0 Spanish as secondary) | Anakata 12 Sep 2026 | Engine Settings |
| OPS-002 | Guests per cabin | 3 | confirmed 12 Sep 2026 | Engine Settings |
| TEC-001/002/003 | Payments **Stripe** · transactional email **Microsoft Exchange** · e-signature TBD | — | Anakata 12 Sep 2026 | integrations |

**Implementation note.** Rules must be data, not code: the prototype keeps them in editable stores (rates, engine settings, policies) and every screen, document and calculation reads from them. Rules with a source code should carry that code in the database so the "differs from the confirmed value" check survives into production.
