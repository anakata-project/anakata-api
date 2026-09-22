# Sprint 11 · Operations: manifests, guest experience, NPS, jobs, alerts, agent portal (RMS side)

**Goal:** the system runs the weeks around a voyage without anyone keeping a spreadsheet.
- **Manifests:** the DPNG passenger list and the captain's manifest are generated on time for every departure, versioned, exportable for the park authority, and restricted to the people allowed to see passports and health data. Missing passenger data is chased before the deadline.
- **Guest experience:** guests answer the pre-trip questionnaire, and the hotel manager gets a printable brief. The post-trip call and the NPS survey happen by themselves. A low score reaches management within the hour; a high score asks for a review, when consent allows.
- **Scheduled jobs:** every job in doc 07 §7 exists in the form this system needs. Voyages move to ON BOARD and COMPLETED by date.
- **Alerts:** one inbox for everything that needs attention — overdue balances, cap breaches, low occupancy, SLA breaches, NPS below 7, wires not received, manifest data missing, ledger drift — raised and resolved by facts.
- **B2B & Agent Portal (RMS side):** agency users, a preview of what the agent will see, and commissions from earned to paid.

Nothing here changes how bookings are priced or sold. The CRM stays read-only on money (L9). The agent-facing portal is still later (N10).

- **anakata-api:** alerts; voyage status and the scheduled jobs; manifests; preferences and the brief; NPS; agent portal RMS side.
- **anakata-ui:** regenerated types, release `v0.12.0`.
- **anakata-engine:** the questionnaire and survey pages behind the guest's link.
- **anakata-panel:** Alerts; Documents & Manifests (departure manifests); Guest Experience; B2B & Agent Portal.
- **E2E:** batches B12 and B13, and the ledger for Sprints 1–11.

## Before task 01
1. **Merge the e2e harness** (the `e2e/sprint-10` branch: batches, gate, ledger, reset snapshot) into `dev` through a PR. Then run **B1** in the cloud with `tests/e2e/RUN_PROMPT.md` as the real trial, and record the fresh-seed and restore times.
2. **Attach the Sprints 1–10 P1 ledger.** Run batches **B9 → B8 → B5 → B4 → B6 → B7 → B3 → B2 → B10 → B11**, one cloud session each. `runs/LEDGER.md` must show every P1 on gate-passing SHAs, no open `BUG`, and an empty *Pending scenario fixes*.
   - B8 and B9 come first: they confirm whether engine events reach the API, including the unload flush. If they don't, fix the engine before task 01.
   - Sprint 10's P1 run was never started, so B10 and B11 are its first walk.
3. **Copy `08-dev-decisions.md`** from this folder to `docs/requirements/` (adds section N).
4. **Ask the client** the questions below.

**Questions for the client:**
- **Voyage status by date (N2):** move FULLY PAID → ON BOARD on the departure date and ON BOARD → COMPLETED on the return date automatically? A booking still CONFIRMED on the departure date is not moved and raises a critical alert.
- **DPNG:** is the approved column set final, and is the list uploaded by hand to the park authority's system? Who receives the captain's manifest, and how (printed, or downloaded by the captain)?
- **Passenger-data chaser:** how many days before the DPNG due date? Default 10.
- **Questionnaire:** the final wording of the questions marked PENDIENTE in §6.2.
- **NPS emails (LEG-002):** is the survey a service message (sent to every guest with an email)? Is the public-review request marketing (sent only with marketing consent, the default)? Which review site?
- **Alert audiences:** who counts as "CEO + Operations" for NPS < 7 and ledger drift? Should critical alerts email immediately (default), or in a morning digest?
- **Commission payouts:** who records them, from what (bank transfer reference), and is the payout ever partial?

## Decisions this sprint implements
Recorded as **N1–N10** in `docs/requirements/08-dev-decisions.md`:
- N1: one alerts inbox.
- N2: voyage status by date.
- N3: doc 07 §7 as implemented.
- N4: manifests as departure documents.
- N5: passenger data is chased, not guessed.
- N6: preferences.
- N7: the hotel-manager brief.
- N8: NPS.
- N9: the agent portal's RMS side.
- N10: what Sprint 11 does not build.

## How this sprint is run
As before: one task at a time; plan → review → agent. **A task is not done until its REPORT section exists.** E2E uses the batched harness: each task that adds screens names the scenarios task 12 will write, and runs are one batch per session.

| # | Repo | Task |
|---|---|---|
| 01 | anakata-api | Alerts |
| 02 | anakata-api | Voyage status and the scheduled jobs |
| 03 | anakata-api | Manifests |
| 04 | anakata-api | Guest preferences and the hotel-manager brief |
| 05 | anakata-api | NPS and the post-trip touchpoints |
| 06 | anakata-api | Agent portal, RMS side |
| 07 | anakata-ui | Regenerate types, release `v0.12.0` |
| 08 | anakata-engine | Questionnaire and survey pages |
| 09 | anakata-panel | Alerts inbox; departure manifests |
| 10 | anakata-panel | Guest Experience |
| 11 | anakata-panel | B2B & Agent Portal |
| 12 | anakata-api | E2E scenarios, batches B12 and B13; ledger |

Dependencies:
- 01 → 02 → 03 → 04 → 05 → 06 in order (every later task raises alerts; 05 needs 02's COMPLETED transition and 04's links).
- 07 needs 01–06.
- 08–11 need 07; 08 can run beside 09–11.
- 12 needs everything.

## Context every task needs
- Rules in each repo's `.cursor/rules/`. Decisions `08-dev-decisions.md` sections A–N. In particular:
  - **B4 / I1 / I7:** retention, the sensitive-data key, the retention job.
  - **D4 / D5:** permissions, change history.
  - **G5:** business time.
  - **H5 / H6:** the OVERDUE flag, wires.
  - **J2 / J5 / J7 / J9:** documents, deliveries, triggers, status from facts.
  - **L9:** the CRM never writes money.
  - **M2:** the consent gate.
  - **M6:** tasks.
- **Sources:**
  - doc 01 §4.5 (documents and manifests), §5.5 (agent portal), §6.2–6.4 (questionnaire, NPS, restricted data)
  - doc 03 (FIN-005, §10 commission payment, MKT-006, OPS-006)
  - doc 06 backlog items 2 (alerts inbox) and 5 (automated jobs)
  - doc 07 §7 (scheduled jobs), §8 (personal data)
  - screenshots `04-documents-manifests.png`, `05-guest-experience.png`, `06-agent-portal.png`
- **The prototype `prototype/rms_index.html`:**
  - Documents & Manifests: `v-docs`, `renderDocs` (the manifests table), `manifestRows`, `dpngHtml`, `captainHtml`
  - Guest Experience: `v-gx`, `PREF_Q`, `NPS_Q`, `renderGX`, `hmBriefHtml`, `npsPanel`, `prefForm`, `npsForm`
  - B2B & Agent Portal: `v-b2b`, `renderB2B`, `openAgency` (portal users, preview, commissions)
- **What already exists:**
  - scheduling and runs: `AnakataSchedule`, the `scheduled_runs` hooks, `anakata:documents-due` (reminders, pre-trip, voucher), `anakata:flag-overdue`, `anakata:crm-tasks`, `anakata:retention`
  - bookings and payments: `Transitions` (ON_BOARD / COMPLETED by date, by hand today), `TransitionBooking`, `RecordConsent`, `ConsentGate`, `RaiseTask` / `CloseTask`, Stripe events and the payments ledger
  - rules and documents: business rules `manifests.*`, `alerts.low_occupancy_*`, `commission.payable_days_after_cruise`, `documents.pretrip_days_before`; `DocumentPlanKind::Questionnaire`
  - access and agencies: `BookingAccessToken` (purpose COMPLETE), agencies with `agency_users`, the bookings' frozen `commission_pct` and cap approval, `guests.view_sensitive`
- **Working rules:**
  - API in Docker only.
  - Git read-only for Cursor.
  - Frontends verified on a fresh clone; tags pushed.
  - No hand-written type overlays; no runtime copies of API tables in a frontend.
  - No sensitive field in any CRM response.

## E2E scenarios this sprint adds (task 12)
`ALRT-01` … `ALRT-03`, `JOB-01`, `JOB-02`, `MAN-01` … `MAN-03`, `GX-01` … `GX-04`, `NPS-01` … `NPS-03`, `B2B-01` … `B2B-03`, listed in task 12, in batches B12 (alerts, jobs, manifests) and B13 (guest experience, NPS, agent portal).

## Definition of done for the sprint
- **Manifests:** each departure with passengers shows both manifests with due dates and completeness; the DPNG list downloads as PDF, CSV and XLSX; both are refused without `guests.view_sensitive`; the chaser is sent once per booking.
- **Guest experience:** a guest answers the questionnaire from the link; the brief prints with restricted items only for `guests.view_sensitive`; the answers are purged with the medical notes.
- **Voyage and NPS:** a FULLY PAID voyage moves to ON BOARD and COMPLETED by date; completion raises the post-trip call task; the survey is sent 24 hours after return; a score of 6 raises a critical alert and a task; a score of 9 sends a review request only with marketing consent; the CRM contact shows the latest score.
- **Alerts:** every kind in the registry is raised by its condition, resolves when the condition clears, reaches only its audience, and a critical one emails once.
- **Jobs:** every doc 07 §7 job is listed on Sync with its last run, or marked "not needed" with the reason.
- **Agent portal:** users listed, the preview shows net rates only, and commissions move BLOCKED → EARNED → PAYABLE → PAID with an append-only payout.
- **Release:** all checks pass on fresh clones. `anakata-ui` `v0.12.0` is tagged and pushed. The ledger shows Sprints 1–11 P1 clean, and every task has its REPORT section.
