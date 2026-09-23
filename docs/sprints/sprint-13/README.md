# Sprint 13 · The agent portal

**Goal:** the last piece of the system that is specified but not built (doc 06 item 7). The RMS side already has registration, approval, net rates, commissions and a preview of what the agent will see. This sprint gives agents the site itself.
- **Their own sign-in:** agency users become real accounts with invitations, passwords, resets and lockout — on a guard that is entirely separate from staff (P1, P2).
- **Their own view:** net rates by year, availability for the weeks they sell, their bookings, their commissions from earned to paid, and the sales materials the team uploads (P3, P6, P9).
- **Asking for a booking:** the portal creates a booking request the same way the engine does, with the agency and its frozen commission attached, under the same availability, cap and SLA rules (P4).
- **Control and audit:** staff can suspend portal access without touching the approval decision, and every agent action is on the agency's history (P5, P8).

Agents cannot pay through the portal, and nothing about money changes. Cart recovery, Spanish internal screens and an e-signature provider stay later (P10).

- **anakata-api:** the portal guard and invitations; the portal API; requests from the portal; sales materials; suspension and audit.
- **anakata-ui:** regenerated types, release `v0.14.0`.
- **anakata-portal:** a new Nuxt app — sign-in, rates and availability, bookings and commissions, materials.
- **anakata-panel:** portal access management on the agency drawer.
- **E2E:** the portal joins the stack; batches B16 and B17, and the ledger for Sprints 1–13.

## Before task 01
1. **Close Sprint 12.** Its REPORT sections are complete, `anakata-ui v0.13.0` is pushed, and the work is merged to `dev` in all four repos.
2. **Attach the Sprints 1–12 P1 ledger** (`runs/LEDGER.md`: every P1 on gate-passing SHAs, no open `BUG`, *Pending scenario fixes* empty).
3. **Create `anakata-portal`** on the `anakata-project` org with the same visibility as the engine, from the engine's structure (Nuxt, the `anakata-ui` layer, the same lint, typecheck, test and build scripts). Task 06 fills it; this step only creates the empty repo and the `dev` branch, so the e2e machine can clone four siblings.
4. **Copy `08-dev-decisions.md`** from this folder to `docs/requirements/` (adds section P).
5. **Ask the client** the questions below.

**Questions for the client:**
- **What an agent may do with a request (P4):** may they request a hold on a cabin, or only send a request the team answers within the OPS-009 SLA? The default is a request, no hold.
- **Availability detail:** should the portal show exact cabins left, or only available / limited / sold out, as the engine does? The default is the engine's labels.
- **Sales materials:** who uploads them, and are any specific to one agency rather than shared?
- **Invitation validity and password rules:** the default is 14 days and the staff password policy.
- **Suspension (P8):** who may suspend portal access, and does a suspended agency's team keep receiving commission statements? The default is Admin only, and yes.
- **Lead guest names (P9):** confirm that the agent sees the lead guest's name and nothing else about passengers.
- **Portal domain:** which hostname the portal runs on, for the invitation links and CORS.

## Decisions this sprint implements
Recorded as **P1–P10** in `docs/requirements/08-dev-decisions.md`:
- P1: agents are not users.
- P2: an invitation is a single-use link with an expiry.
- P3: everything is scoped to the agency, on the server.
- P4: the portal reads, and can ask.
- P5: agent actions are audited.
- P6: sales materials are files the RMS controls.
- P7: the portal is its own app.
- P8: access can be suspended without judging the agency.
- P9: an agent sees their commercial relationship, not the guests.
- P10: what Sprint 13 does not build.

## How this sprint is run
As before: one task at a time; plan → review → agent. **A task is not done until its REPORT section exists.** E2E uses the batched harness, one batch per session.

| # | Repo | Task |
|---|---|---|
| 01 | anakata-api | The portal guard, invitations and sessions |
| 02 | anakata-api | The portal API: rates, availability, bookings, commissions |
| 03 | anakata-api | Booking requests from the portal |
| 04 | anakata-api | Sales materials, suspension and the agent audit |
| 05 | anakata-ui | Regenerate types, release `v0.14.0` |
| 06 | anakata-portal | The app: scaffold, sign-in, invitation and reset |
| 07 | anakata-portal | Rates, availability and materials |
| 08 | anakata-portal | Bookings, commissions and the request form |
| 09 | anakata-panel | Portal access on the agency drawer |
| 10 | anakata-api | The e2e stack learns the portal |
| 11 | anakata-api | E2E scenarios, batches B16 and B17; ledger |

Dependencies: 01 → 02 → 03 → 04; 05 needs 01–04; 06 → 07 → 08 need 05; 09 needs 05; 10 needs 06 (a buildable app); 11 needs everything.

## Context every task needs
- Rules in each repo's `.cursor/rules/`. Decisions `08-dev-decisions.md` sections A–P. In particular **D4 / D5** (permissions and history), **G2** (one booking = one cabin), **H8** (commission frozen on the booking), **K9** (token links), **K10**, **L9**, **M6** (tasks), **N1** (alerts), **N9** (the RMS preview, which the portal must match exactly).
- **Sources:** doc 01 §5.5 (the agent portal), §10 (the 2 business-day approval SLA, OPS-009); doc 03 FIN-005; doc 06 item 7 and the non-functional rules (audit, server-side enforcement, English only); the prototype `rms_index.html` `openAgency` portal preview, which is the contract for what the portal shows.
- **What already exists:** agencies with approval, commission and payment terms; `agency_users` (name, email, status — no credentials yet); `PortalPreview` and `Agency::netOf`; commissions with accrual statuses and payouts; the reservation path for a REQUESTED booking with an agency; Sanctum, the staff auth flows (invite, accept, reset, throttle) to mirror; `Availability` and the engine feed labels.
- **Working rules:** API in Docker only; git read-only for Cursor; frontends verified on a fresh clone; tags pushed; no hand-written type overlays; no runtime copies of API tables in a frontend.

## E2E scenarios this sprint adds (task 11)
`PORT-01` … `PORT-06` (sign-in, invitation, scoping, rates, materials, suspension) and `PREQ-01` … `PREQ-04` (requests, cap, SLA, audit), in batches B16 and B17.

## Definition of done for the sprint
- **Identity:** an approved agency's user accepts an invitation, signs in, resets a password, and is locked out after repeated failures. A disabled user and a suspended agency cannot sign in, and live sessions end.
- **Scoping:** no portal response contains another agency's data or any published rate; another agency's reference is a 404.
- **Separation:** an agent's session is refused by every `/api/rms`, `/api/crm` and `/api/privacy` route, and a staff session is refused by every `/api/portal` route.
- **Requests:** a request from the portal creates a REQUESTED booking with the agency and its frozen commission, raises the OPS-009 task, and lands on the team's Booking Requests screen; a rate above the cap still goes to ON_HOLD_AGENCY.
- **Materials and audit:** uploads appear in the portal, downloads are audited, and the agency's history shows sign-ins, requests and downloads.
- **Release:** all checks pass on fresh clones; `anakata-ui v0.14.0` is tagged and pushed; the e2e stack brings up four apps; the ledger shows Sprints 1–13 P1 clean; every task has its REPORT section.
