# Sprint 10 · CRM sales motion: pipeline, tasks, consent, campaigns, delivery

**Goal:** the CRM starts running the sales process on top of the people records Sprint 9 built, and consent becomes a real register.
- **Pipeline & Forecast:** deals in eight stages. Stages 1–4 are moved by the sales team; stages 5–7 follow the booking status and cannot be dragged; LOST needs a reason. Cash figures and the weighted forecast come from the ledger, computed in the API.
- **Tasks & SLA:** every SLA in the commercial process raises a task with an owner and a due time — request response, charter quote, overdue balance, commission cap, wire window, refund decision, subject request — and the task closes itself when the RMS shows the condition has cleared. No task ever changes a booking (OPS-007).
- **Consent & Data Rights:** a per-contact consent register that every future send must check, the personal-data map, and subject requests (access, erasure, rectification, objection) with a 30-day SLA.
- **Campaigns & Offers:** campaigns measure the offers the RMS publishes — redemptions and revenue from the bookings that carry the offer code, attributed bookings from the frozen UTM — and never create a discount.
- **Documents & Delivery:** the delivery log of every document the RMS issued, read-only, each row linking to the booking in the RMS.

The CRM still reads the booking tables directly (B9), never writes money (L9), and never renders or sends a document. Journeys, segments, automations, the inbox, CRM B2B partners and alerts are not in this sprint (M10).

- **anakata-api:** Sprint 9 follow-ups; consent register; deals and the pipeline; tasks; subject requests; campaigns and the delivery log.
- **anakata-ui:** regenerated types, release `v0.11.0`.
- **anakata-panel:** Pipeline & Forecast; Tasks & SLA; Consent & Data Rights; Campaigns & Offers; Documents & Delivery.
- **anakata-engine:** no change this sprint.
- **E2E:** CRM scenarios and a P1 run.

## Before task 01
Sprint 9 is code-complete but not verified on a screen. Do these first; tasks 01 onwards read the data those sprints produce.

1. **Attach the Sprints 1–9 P1 run.** Use the run prompt with the relaxed SHA gate (origin/dev must be the reviewed commit or a descendant whose diff outside `tests/e2e/runs` is empty). Every P1 has a result, every `BUG` is fixed, and the run file is on `e2e/sprint-09`. Do not apply the proposed wordings from the `2026-09-21-1833-cloud-deploy` run: it ran on pre-Sprint-9 code, and its BR-01 / BR-02 counts are stale.
2. **Add the missing Task 08 section to `docs/sprints/sprint-09/REPORT.md`** (the Sprint 9 summary says it has none): what was built; the LEG-002 open decision that the session touch is sent with checkout without analytics consent; whether the `pagehide` / `visibilitychange` beacon reached the API; that ingest dedupes on unique `event_id`; the localStorage session id against doc 07 §6's cookie; consent stored in the browser only; the banner copy says "cookies"; git commands.
3. **Fix the e2e scripts so a reused machine cannot test old code.** In `tests/e2e/bin/_lib.sh`, `ensure_sibling` must fetch and check out the requested ref when the folder already exists (and fail if the working tree is dirty), and `up.sh` must print the four HEAD SHAs. `.env` is rebuilt from the testing env unless `E2E_KEEP_ENV=1` is set. One small commit; git commands listed, not run.
4. **Copy `08-dev-decisions.md`** from this folder to `docs/requirements/` (adds section M).
5. **Ask the client** the questions below.

**Questions for the client:**
- **Pipeline stage probabilities** (NEW LEAD 5%, QUALIFYING 15%, QUOTED 35%, NEGOTIATION 55%, DEPOSIT PENDING 80% — the prototype's values) and **stage SLAs** (first response 4 business hours, qualifying 5 business days, negotiation 7 business days). Built as business rules, PENDING CLIENT.
- **Who handles subject requests** and whether 30 days is the SLA under LOPDP (LEG-002). Default: Admin only, 30 days.
- **Erasure while someone is travelling:** the default refuses erasure until the last booking's return date has passed. Confirm.
- **Profiling, remarketing and WhatsApp consent:** the engine only captures marketing (and analytics through the banner). Where should the other three be captured, and with what text?
- **Open and download tracking** in transactional email: not built. Confirm it stays off, or give the LEG-002 position.
- **Scope:** journeys, segments, automations, the inbox and CRM B2B partners are in the prototype but not in any sprint yet. Confirm they stay "later", or say which matter before go-live.

## Decisions this sprint implements
Recorded as **M1–M10** in `docs/requirements/08-dev-decisions.md`:
- M1: the consent register is per contact and append-only.
- M2: consent is checked at send time, through one gate.
- M3: analytics consent reaches the register at stitching.
- M4: deals — stages 1–4 are records, 5–7 are a projection.
- M5: pipeline money is read, never written.
- M6: tasks are raised by the system or a person and never touch a booking.
- M7: subject requests are a data-protection function with their own permission.
- M8: campaigns measure RMS offers.
- M9: Documents & Delivery reads what the RMS issued and sent.
- M10: what Sprint 10 does not build.

## How this sprint is run
As before: one task at a time; plan → review → agent. **Every task appends its own section to `REPORT.md` before it is considered done** — Sprint 9 lost task 08's section, so a task whose section is missing is not finished.

| # | Repo | Task |
|---|---|---|
| 01 | anakata-api | Sprint 9 follow-ups |
| 02 | anakata-api | The consent register and the send-time gate |
| 03 | anakata-api | Deals and the pipeline |
| 04 | anakata-api | Tasks and SLAs |
| 05 | anakata-api | Subject requests |
| 06 | anakata-api | Campaigns and the delivery log |
| 07 | anakata-ui | Regenerate types, release `v0.11.0` |
| 08 | anakata-panel | Pipeline & Forecast; Tasks & SLA |
| 09 | anakata-panel | Consent & Data Rights |
| 10 | anakata-panel | Campaigns & Offers; Documents & Delivery; navigation markers |
| 11 | anakata-api | E2E scenarios for Sprint 10; P1 run |

Dependencies:
- 01 → 02 → 03 → 04 → 05 → 06 in order (04 links tasks to deals; 05 raises tasks and writes the register).
- 07 needs 01–06.
- 08–10 need 07, in order.
- 11 needs everything.

## Context every task needs
- Rules in each repo's `.cursor/rules/`. Decisions: `08-dev-decisions.md`, sections A–M. In particular **A4** (events are side effects only; CRM tasks raised by booking changes), **B5** (stages 5–7 locked, LOST by event or person), **B9**, **D4/D5** (permissions, change history), **G5** (business hours), **H5** (OVERDUE is a derived flag), **I6** (the booking consent log), **J2/J5/J9** (documents, deliveries, status from facts), **K2** (offers), **L1–L10**.
- The sources:
  - `07-three-system-integration-contract.md` — §2, §3 (the Consent, Lifecycle, Deal & pipeline, Tasks rows), §4.4 and §4.5, §5 (pipeline stage ↔ booking status), §8 (personal data map), §10 (rules 2, 4, 6, 7)
  - screenshots `crm-01-pipeline.png`, `crm-03-tasks.png`, `crm-04-campaigns.png`, `crm-05-docs.png`, `crm-07-privacy.png`
- The prototype `prototype/crm_index.html`: `v-pipe`, `STAGES`, `STAGEMAP`, `DEALS`, `cashFromLedger`, `renderCash`, `renderPipe`, `openDeal`; `v-tasks`, `TASKS`, `renderTasks`, `doneTask`; `v-privacy` (consent register, data map, subject requests); `v-camp`, `CAMPMETA`, `renderCamp` and the attribution-model table; `v-docs`, `renderDocs`, `openDoc`.
- What already exists: contacts with derived fields and the one consent-summary method (Sprint 9 task 01), aliases and merges, behavioural events and stitching, the timeline, `consents` (I6), `deliveries` and `documents` (J2, J5), `offers` and bookings' frozen `price_lines` / `promo_code` (K2), `charter_enquiries`, `refund_requests`, the OVERDUE flag and `anakata:flag-overdue`, `BusinessTime`, the domain events (`BookingStatusChanged`, `PaymentSettled`, `BookingChargesChanged`…), permissions `pipeline.move_stage` and `records.act_on_any`, `scheduled_runs`.
- API in Docker only; git read-only for Cursor; compatibility check before any package; frontends verified on a fresh clone; **tags pushed**; no hand-written type overlays for fields the API can type; no runtime copies of API tables in a frontend.
- **E2E rule:** screen facts gathered after `tests/e2e/bin/reset.sh`, on a machine whose four HEAD SHAs are printed in the run report and checked against the reviewed commits.

## E2E scenarios this sprint adds (task 11)
`PIPE-01` … `PIPE-04`, `TASK-01` … `TASK-04`, `PRIV-01` … `PRIV-05`, `CAMP-01`, `CAMP-02`, `DLV-01`, listed in task 11.

## Definition of done for the sprint
- **Pipeline:** a deal in stages 1–4 moves only by its owner (or `records.act_on_any`); a bound deal's stage follows the booking status and cannot be dragged; LOST needs a reason; the cash figures equal Payments & Revenue for the same bookings.
- **Tasks:** a web request, a charter enquiry, an overdue balance, a commission-cap hold, a wire window and a refund request each raise one task with the right owner and due time; each closes itself when the RMS clears the condition; completing a task never changes a booking.
- **Consent:** the register shows every purpose with its latest state and history; the engine's marketing opt-in and a stitched session land in it; a withdrawal is effective at the next gate check.
- **Subject requests:** an access export contains the contact, consents, bookings, payments, documents and events and no passenger record; an erasure anonymises the contact and keeps the financial record; both show their SLA.
- **Campaigns:** redemptions and revenue equal the sold bookings carrying the offer code; attributed bookings equal the bookings with the campaign key in their frozen UTM.
- **Documents & Delivery:** every delivery row matches the booking's Documents tab.
- **Separation:** no CRM response contains a passport number, date of birth, nationality or note; no CRM endpoint creates or changes money or a document.
- All checks pass on fresh clones. `anakata-ui` `v0.11.0` is tagged and pushed. The P1 run (Sprints 1–10) is attached with no open `BUG`, and every task has its REPORT section.
