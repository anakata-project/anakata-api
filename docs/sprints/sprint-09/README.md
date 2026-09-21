# Sprint 9 · CRM core: people, identity, attribution, behaviour

**Goal:** the CRM half of the panel starts working, on top of the booking data the RMS already holds.
- **Contacts** become the CRM's people records: identity, type, language, preferred channel, and fields that are derived — never typed — from the bookings: lifecycle, lifetime value, segment, and the marketing-consent summary.
- **Identity resolution:** normalised email is the key, phone numbers are normalised to E.164, and staff can merge duplicates into the oldest contact. The losing id lives on as an alias forever, so nothing ever points at a dead record, and a merge can be undone for 30 days.
- **Attribution is written once:** UTM first and last touch travel from the engine onto the booking at creation and are frozen there; the contact keeps its own first and last touch.
- **Behavioural events** from the engine — consented, first-party, whitelisted — build each contact's timeline and the Web & Engine Activity view, and anonymous sessions are stitched to a contact when the visitor identifies themself.
- **Sync & Field Ownership** shows the contract as it actually is in this system — one application, no event bus (B9) — the ownership matrix, the health of every scheduled job, failed side effects with retry, and the identity-resolution log.

The CRM reads booking tables directly (B9): no mirrors, no consumers. It never writes money and never sees sensitive guest data. Pipeline, tasks, the consent register and subject requests, campaigns, and documents & delivery are Sprint 10.

- **anakata-api:** contacts as CRM records with derived fields; identity resolution and merge; attribution and behavioural events; the CRM read API (timeline, activity, sync).
- **anakata-ui:** regenerated types, release `v0.10.0`.
- **anakata-panel:** CRM Contacts; Web & Engine Activity; Sync & Field Ownership.
- **anakata-engine:** consented event emission, the session identifier, UTM capture.
- **E2E:** CRM scenarios and a P1 run.

## Before task 01 — the e2e runs have not happened since Sprint 4
Sprint 7's task 08 stopped at ENV for the same reason as Sprints 5 and 6: the cloud agent was started from the **four-repository workspace**, and Cursor's cloud refuses a workspace with four git remotes. Sprint 8's task 11 has not run. So Sprints 5–8 — payments, guests, documents and the public engine — have never been walked on a screen after `reset.sh`, and 66 `⚠ UNVERIFIED` markers are waiting.

This is the single most important item in this sprint, and it is not a Cursor task. Pick one of these and do it before task 01:

1. **Cloud, launched correctly.** Close the multi-root workspace. Open a Cursor window on the **`anakata-api` folder only** (File → Open Folder → `anakata-api`), confirm that `git remote -v` in that window shows only `anakata-api`, add `GH_TOKEN` as a secret, and start the Cloud Agent from that window with Sprint 8 task 11's plan. `.cursor/environment.json` is correct as it is — do not change it.
2. **Or authorise a local run.** If the cloud keeps refusing, tell Cursor explicitly that a local run of the e2e stack is allowed for this one catch-up (Docker, `tests/e2e/bin/up.sh`, `reset.sh`). Until now every task has been told never to fall back to a local run without your say-so; this is that say-so, if you choose it.

Either way the goal is the same: the Sprint 5, 6, 7 and 8 scenario work done, both P1 runs attached, every `BUG` fixed, before CRM work starts reading the data those sprints produce.

Then:
- **Copy `08-dev-decisions.md`** from this folder to `docs/requirements/` (adds section L).
- **Ask the client** the questions below.

**Questions for the client:**
- **Who sees which contacts.** The default this sprint (L1): every user with CRM access sees every contact; bookings and money stay own-records. This also closes the Sprint 4 question about the RMS contact search. Confirm, or say which roles should see only their own.
- **Segment thresholds:** HIGH above USD 20,000 lifetime value, MID from USD 8,000 (the prototype's values). Built as business rules, PENDING CLIENT.
- **Behavioural tracking and cookies (LEG-002):** the default is that the engine records no behavioural events and sets no persistent identifier until the visitor consents to analytics, using the same banner as GA4. Confirm that one consent covers both, or whether first-party CRM tracking needs its own choice.

## Decisions this sprint implements
Recorded as **L1–L10** in `docs/requirements/08-dev-decisions.md`:
- L1: contacts are shared people records.
- L2: derived, never typed.
- L3: identity keys.
- L4: merges.
- L5: no bus, and what Sync & Field Ownership shows instead.
- L6: behavioural events are first-party, consented and whitelisted.
- L7: stitching.
- L8: attribution written once.
- L9: the CRM never reaches money or sensitive data.
- L10: language.

## How this sprint is run
As before: one task at a time; plan → review → agent; each task appends to `REPORT.md`.

| # | Repo | Task |
|---|---|---|
| 01 | anakata-api | Contacts as CRM records; the derived fields |
| 02 | anakata-api | Identity resolution, aliases, merge and unmerge |
| 03 | anakata-api | Attribution and behavioural events |
| 04 | anakata-api | The CRM read API: timeline, activity, sync and field ownership |
| 05 | anakata-ui | Regenerate types, release `v0.10.0` |
| 06 | anakata-panel | CRM Contacts: list, profile, timeline, merge |
| 07 | anakata-panel | Web & Engine Activity; Sync & Field Ownership |
| 08 | anakata-engine | Consented events, the session identifier, UTM capture |
| 09 | anakata-api | E2E scenarios for Sprint 9; P1 run |

Dependencies:
- 01 → 02 → 03 → 04 in order.
- 05 needs 01–04.
- 06–08 need 05; 07 needs 06.
- 09 needs everything.

## Context every task needs
- Rules in each repo's `.cursor/rules/`. Decisions: `08-dev-decisions.md`, sections A–L. In particular **A2/A3** (one application, one database), **A4**, **B9** (no mirrors, no bus, no outbox), **D4/D5**, **I1–I3** (sensitive data), **I6** (the consent log), **K3/K8** (the engine's public API and what it collects).
- The sources:
  - `07-three-system-integration-contract.md` — §2 (what each system is for), §3 (the ownership matrix), §4.2 and §4.4 (the events that feed the CRM), §6 (identity resolution and the merge rule), §7 (scheduled jobs), §8 (the personal data map), §10 (build implications: no write path to money, attribution written once)
  - `01-functional-spec.md` §8 (Contacts In, which the RMS already has)
  - screenshots `crm-02-sync.png`, `crm-06-activity.png`
- The prototype `prototype/crm_index.html`: `v-contacts` (its notice, filters and columns), `CONTACTS`, `renderContacts`, `openContact`, `ltvOf`, `segOf`, `v-activity`, `ACTIVITY`, `renderActivity`, `v-sync`, `OWNERSHIP`, `EVENTCAT`, `renderSync`, the attribution-model table in `v-camp`.
- What already exists: `contacts` (name, email unique and normalised by `Contact::normalizeEmail`, phone, country, preferred channel), `ResolveContact`, Contacts In, `GuardCrmSensitiveData`, the CRM arch tests, `routes/api/crm.php` (a stub), the consent log (I6), deliveries (J5), change history, and the engine's consent-gated `track()` (Sprint 8 task 10).
- API in Docker only; git read-only for Cursor; compatibility check before any package; frontends verified on a fresh clone; **tags pushed**; no hand-written type overlays for fields the API can type; no runtime copies of API tables in a frontend.
- **E2E rule:** screen facts gathered after `tests/e2e/bin/reset.sh`, on the cloud machine launched from `anakata-api` alone (or locally, only if you have authorised it above).

## E2E scenarios this sprint adds (task 09)
`CRM-01` … `CRM-10`, listed in task 09.

## Definition of done for the sprint
- **Contacts:** every contact shows its lifecycle, lifetime value, segment and consent summary computed from the bookings and the consent log; cancelling a booking moves a contact's value and segment by itself.
- **Identity:** an email in any case or a phone in any format finds the same contact; merging two duplicates repoints every booking, request, waitlist entry, charter enquiry and event to the survivor, the old id still resolves, and an unmerge within 30 days restores exactly what moved.
- **Attribution:** a booking made from the engine carries its UTM first and last touch, and nothing can change them afterwards.
- **Behaviour:** with consent, an anonymous visitor's itinerary and checkout events appear on the contact's timeline once they submit a request; without consent, nothing is recorded and no identifier is stored.
- **Separation:** no CRM response contains a passport number, date of birth, nationality or note, and no CRM endpoint can create or change money.
- **Sync & Field Ownership** shows the real health of every scheduled job and any failed side effect, with retry.
- All checks pass on fresh clones. `anakata-ui` `v0.10.0` is tagged and pushed. The P1 run (Sprints 1–9) is attached with no open `BUG`.
