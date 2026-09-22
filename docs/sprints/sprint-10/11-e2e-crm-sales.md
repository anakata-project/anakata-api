# Task 11 · anakata-api · E2E scenarios for Sprint 10; P1 run
**Repo:** anakata-api (`tests/e2e/`, and a branch the agent pushes) · **Sprint:** 10 · **Needs:** tasks 01–10 merged, `anakata-ui v0.11.0` pushed, and the Sprints 1–9 P1 run from the README's "Before task 01" attached.

## Execution
On the cloud machine started from a Cursor window with only `anakata-api` open (one git remote), or locally if you authorise it — say which at the top of the run report.

**Step 0, the code gate.** In all four repos: `git fetch origin && git checkout dev && git reset --hard origin/dev` (ui at tag `v0.11.0`). Print the four SHAs and subjects at the top of the report. For each repo, the SHA must be the reviewed commit named in this task's plan or a descendant of it whose diff outside `tests/e2e/runs` is empty (`git merge-base --is-ancestor` and `git diff --stat <reviewed> origin/dev -- . ':(exclude)tests/e2e/runs'`). Otherwise write the ENV report and stop. `INDEX.md` must list the Sprint 10 IDs below.

The agent works on `e2e/sprint-10`, commits with explicit paths (`git add -f` for run files), pushes that branch, never merges into `dev`.

## Do
1. **Fixtures.** Extend `reference-values.md`: the registry counts after this sprint's shape changes; the seeded deals and their stages; the pipeline KPIs (which must equal Payments & Revenue); the register counts per purpose; the seeded deliveries. Anything not read off a screen stays `⚠ UNVERIFIED — <source>`; report the split.
2. **Revisit** CRM-01 (lifecycle unaffected by the register switch), CRM-03 (the drawer's consent block changed), CRM-08 (Review merge now from `conflicting_contact`), the Activity scenarios (drawer by id), and BR-01.
3. **New scenarios** — tags `sprint-10`:

   | ID | P | Users | Script (intent; the screen wins) |
   |---|---|---|---|
   | PIPE-01 | P1 | Carolina | Pipeline KPIs equal Payments & Revenue; the stage map is shown; bound deals are locked |
   | PIPE-02 | P1 | Lucía | Move an own deal 1 → 3; cannot move Mateo's; LOST asks for a reason |
   | PIPE-03 | P1 | Guest + Carolina | Engine request → a DEPOSIT PENDING deal; confirm the deposit in the RMS → BOOKING CONFIRMED |
   | PIPE-04 | P2 | Carolina | Charter enquiry → an unassigned NEW LEAD deal; take it; bind it after a charter booking |
   | TASK-01 | P1 | Guest + Lucía | Engine request → a REQUEST_RESPONSE task due in 24 business hours; release in the RMS → auto-closed |
   | TASK-02 | P1 | Carolina | Overdue balance and a commission-cap hold each raise one task with the right "needs" permission; replay of the sweep raises nothing new |
   | TASK-03 | P1 | Lucía | Complete a task → the contact's timeline shows it; the booking's history shows nothing new |
   | TASK-04 | P2 | Mateo | Manual task, reassign, All tab hidden without `records.act_on_any` |
   | PRIV-01 | P1 | Guest + Carolina | Engine request with marketing opt-in → register row (engine form) and I6 row; the contact shows OPTED IN |
   | PRIV-02 | P1 | Carolina | Objection request → marketing, profiling and remarketing withdrawn; register count drops |
   | PRIV-03 | P1 | Carolina | Access export: contact, consents, bookings, payments, documents, events; no passport, date of birth, nationality or note |
   | PRIV-04 | P1 | Carolina | Erasure refused with an upcoming departure; allowed for a past-only contact; documents and payments kept |
   | PRIV-05 | P2 | Mateo | No subject-requests panel; 403 on `/api/privacy` |
   | CAMP-01 | P1 | Carolina | Campaign on OPENING-27: redeemed and revenue equal the seeded bookings with the code; cancel one → both drop |
   | CAMP-02 | P2 | Guest + Carolina | Book with `?utm_campaign=<key>` → attributed first touch +1 |
   | DLV-01 | P1 | Carolina | Delivery log matches a booking's Documents tab; Open in RMS lands there; no resend offered |

4. **INDEX.md** — the P1 set grows by PIPE-01–03, TASK-01–03, PRIV-01–04, CAMP-01, DLV-01.
5. **The runs** — Sprint 10 P1 plus revisited files, then the full P1, Sprints 1–10; each separates **(i)** scenario or fixture fixes from **(ii)** application bugs. Every NOT RUN P1 has a reason. Never loosen an expectation. Proposed wording is copied from the screen character for character.
6. **Sprint 10 summary** in the REPORT: what is done; the open questions compiled from each task (do not write "none") — at least stage probabilities and SLAs, subject-request ownership and SLA, erasure with upcoming travel, profiling / remarketing / WhatsApp capture, tracking in email, the "later" scope; what is still open; merge steps.

## Don't
- Don't change application code to make a scenario pass.
- Don't write personal data beyond the seeded demo names into a scenario or report.

## Report
Append **Task 11**: the code gate output, the scenarios, the revisited files, both run reports, the marker split, the open questions and the merge steps.
