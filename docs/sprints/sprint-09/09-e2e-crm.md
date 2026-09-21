# Task 09 · anakata-api · E2E scenarios for Sprint 9; P1 run
**Repo:** anakata-api (`tests/e2e/`, and a branch the agent pushes) · **Sprint:** 9 · **Needs:** tasks 01–08 merged, `anakata-ui v0.10.0` pushed, and the README's "Before task 01" done — the Sprint 5–8 e2e work attached.

## Execution
On the cloud machine, **started from a Cursor window that has only `anakata-api` open** (one git remote; `.cursor/environment.json` is correct as it is), with `GH_TOKEN`. If you authorised a local run in the README's "Before task 01", that authorisation covers this task too — say so at the top of the run report. Otherwise, if the agent cannot start or `up.sh` does not reach ALL UP, write the ENV report and stop. The agent works on `e2e/sprint-09`, commits with explicit paths, pushes that branch, never merges into `dev`.

## Do
1. **Fixtures.** Extend `reference-values.md`: the seeded contacts' lifecycles, lifetime values and segments read off the CRM, the registry counts after task 01's shape change, the scheduled jobs listed on Sync. Anything not read off a screen stays `⚠ UNVERIFIED — <source>`; report the split.
2. **Revisit** Contacts In (unchanged, but re-read), the engine scenarios that now also send events (WEB-*), and the BR registry counts.
3. **Ten new scenarios** — `scenarios/crm/`, tags `sprint-9`:

   | ID | P | Users | Script (intent; the screen wins) |
   |---|---|---|---|
   | CRM-01 | P1 | Carolina | Contacts list: the seeded contacts with lifecycle, value, segment and consent; filters |
   | CRM-02 | P1 | Carolina | Cancel a confirmed booking in the RMS → that contact's value and segment change in the CRM |
   | CRM-03 | P1 | Carolina | A contact's drawer: bookings open in the RMS; the timeline lists booking, payment, document and consent items; no passport, date of birth, nationality or note anywhere |
   | CRM-04 | P1 | Carolina | Merge two duplicates → bookings move to the older contact; the old link opens the survivor; undo restores exactly what moved |
   | CRM-05 | P1 | Guest + Carolina | With consent: browse the engine and submit a request → the anonymous events are stitched to the new contact; lifecycle SQL |
   | CRM-06 | P1 | Guest + Carolina | Without consent: browse and submit → no events recorded, no identifier in storage; the request still arrives |
   | CRM-07 | P2 | Guest + Carolina | Land with `?utm_source=…&utm_campaign=…`, book → the booking shows the attribution; it cannot be changed (`db-check`) |
   | CRM-08 | P2 | Carolina | Edit a contact's email to another contact's → the conflict offers the merge |
   | CRM-09 | P2 | Carolina | Sync: jobs show real last runs; a failed job appears and retries |
   | CRM-10 | P2 | Sales Exec | A Sales Exec sees all contacts but cannot merge |

4. **INDEX.md** — the P1 set grows by CRM-01 to CRM-06.
5. **The runs** — Sprint 9 P1 plus revisited files, then the full P1, Sprints 1–9; each separates **(i)** scenario or fixture fixes from **(ii)** application bugs. Never loosen an expectation.
6. **Sprint 9 summary** in the REPORT: what is done; open questions compiled from each task (do not write "none") — at least contact visibility, segment thresholds, one consent for GA4 and CRM tracking; what is still open; merge steps.

## Don't
- Don't change application code to make a scenario pass.
- Don't write personal data beyond the seeded demo names into a scenario or report.

## Report
Append **Task 09**: the scenarios, the revisited files, both run reports, the marker split, the open questions and the merge steps.
