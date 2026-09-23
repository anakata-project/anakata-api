# Sprint 14 · Journeys, segments and the automation catalogue

**Goal:** the CRM stops being a record of what happened and starts doing the follow-up. This is §5.4 of doc 01 — the part deferred in Sprint 10 as M10.
- **Segments:** audiences as rules over data the system already has, counted and listed by SQL, never stored (Q1). One of them is suppression, and it wins over everything (Q2).
- **Journeys:** the eight sequences the prototype describes — nurture, request to deposit, the payment calendar, extras, pre-trip, re-engagement, partner activation, win-back — as triggers, ordered steps and exits, with consent and suppression re-checked at every step (Q3, Q4).
- **The automation catalogue:** every automatic message the system sends, in one list, with what triggers it, where it lives, and a switch for the ones it is safe to stop (Q5).
- **Cart recovery, honestly:** an address typed into checkout is kept only if the person asks for it, with its own consent text (Q6). Every marketing message carries a one-click unsubscribe (Q7).

Journeys send, enrol and exit. They never change a booking (Q9). The shared inbox stays later (Q10).

- **anakata-api:** segments and suppression; the catalogue; the journey engine; templates; lead capture and unsubscribe.
- **anakata-ui:** regenerated types, release `v0.15.0`.
- **anakata-engine:** the checkout marketing tick and the unsubscribe page.
- **anakata-panel:** Journeys, Segments and Automations.
- **E2E:** batches B18 and B19, and the ledger for Sprints 1–14.

## Before task 01
1. **Close Sprint 13** (REPORT sections complete, `anakata-ui v0.14.0` pushed, everything merged to `dev` in all five repos).
2. **Attach the Sprints 1–13 P1 ledger.**
3. **Copy `08-dev-decisions.md`** from this folder to `docs/requirements/` (adds section Q).
4. **Settle the legal questions below with the client.** This sprint sends marketing email for the first time; LEG-002 has been open since Sprint 8 and now blocks real sends, not just wording.

**Questions for the client:**
- **LEG-002, now blocking:** the consent text versions for the checkout marketing tick and the unsubscribe page, and confirmation that a single opt-in (no confirmation email) is acceptable in the markets Anakata sells to.
- **Which journeys may run:** the eight are built; which are switched on at go-live, and who owns each one?
- **Sender identity:** which address marketing email comes from, and whether it differs from the transactional one (this affects deliverability and the Exchange setup, TEC-001).
- **Frequency:** a cap on marketing messages per contact per month? The default is none, with suppression and consent as the only limits.
- **Journey copy:** who writes the step text, and who approves a template version before it sends.
- **Paid audiences (Q10):** should segments ever be pushed to Meta or Google? The default is no, and it needs its own consent decision.
- **Cart recovery timing:** the prototype says 24 h, 48 h and day 7. Confirm, given that these only reach people who ticked the box.

## Decisions this sprint implements
**Q1–Q10** in `docs/requirements/08-dev-decisions.md`: a segment is a rule; suppression wins; a journey is a trigger, steps and an exit; marketing needs consent and transactional needs a reason; one catalogue of every automatic message; cart recovery only with a deliberate address; one-click unsubscribe; versioned templates; journeys never write a booking; and what is still not built.

## How this sprint is run
One task at a time; plan → review → agent. **A task is not done until its REPORT section exists.** E2E uses the batched harness.

| # | Repo | Task |
|---|---|---|
| 01 | anakata-api | Segments and suppression |
| 02 | anakata-api | The automation catalogue |
| 03 | anakata-api | The journey engine |
| 04 | anakata-api | Templates, preview and test sends |
| 05 | anakata-api | Lead capture, unsubscribe and cart recovery |
| 06 | anakata-ui | Regenerate types, release `v0.15.0` |
| 07 | anakata-engine | The checkout marketing tick and the unsubscribe page |
| 08 | anakata-panel | Journeys |
| 09 | anakata-panel | Segments and Automations |
| 10 | anakata-api | E2E scenarios, batches B18 and B19; ledger |

Dependencies: 01 → 02 → 03 → 04 → 05; 06 needs 01–05; 07–09 need 06; 10 needs everything.

## Context every task needs
- Rules in each repo's `.cursor/rules/`. Decisions A–Q. In particular **L2** (derived in SQL), **L6 / L7** (consent before anything is stored, stitching), **L8** (frozen attribution), **M1 / M2** (the consent register and the gate), **M6** (tasks), **M8** (campaign measurement), **N1** (alerts), **N8** (NPS), **J5** (delivery idempotency), **O3** (staff email versus customer deliveries).
- **Sources:** doc 01 §5.4 (journeys, segments, automations); doc 03 MKT-006, OPS-007, OPS-009; the prototype `crm_index.html` — `JOURNEYS` and `renderJourneys`, `SEGMENTS` and `renderSegments`, `AUTOS` and `renderAutos`, and the suppression line under each journey.
- **What already exists:** the consent register and gate; behavioural events with stitching; contacts with lifecycle, segment and LTV derived in SQL; campaigns measured from bookings; deliveries with idempotency keys and the Graph mailer; CRM tasks and alerts; the complete page and the questionnaire and survey token pages as the pattern for a public page.
- **Working rules:** API in Docker only; git read-only for Cursor; frontends verified on a fresh clone; tags pushed; no hand-written type overlays; no runtime copies of API tables in a frontend; no sensitive field in any CRM response.

## E2E scenarios this sprint adds (task 10)
`SEG-01` … `SEG-03`, `JRN-01` … `JRN-05`, `AUTO-01`, `AUTO-02`, `UNSUB-01`, `CART-01`, `CART-02`, in batches B18 (segments and automations) and B19 (journeys, unsubscribe and cart recovery).

## Definition of done for the sprint
- **Segments:** each definition's count equals the rows it lists; suppression is subtracted from every marketing audience; no membership is stored.
- **Journeys:** a contact enrols on its trigger, receives step one, moves to step two on time, exits on its exit condition, and stops immediately when consent is withdrawn mid-sequence.
- **Consent:** no marketing message reaches a contact without a register row; every marketing message carries a working one-click unsubscribe; unsubscribing withdraws consent, suppresses the contact and stops every journey.
- **Catalogue:** every automatic message the system sends appears with its trigger and where it lives; switching one off stops the message and nothing else.
- **Cart recovery:** an address is kept only with the tick; without it nothing is stored and nothing is sent.
- **Release:** checks pass on fresh clones; `anakata-ui v0.15.0` is tagged and pushed; the ledger shows Sprints 1–14 P1 clean; every task has its REPORT section.
