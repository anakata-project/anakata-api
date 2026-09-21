# Task 08 · anakata-api · E2E scenarios for Sprint 7; P1 run
**Repo:** anakata-api (`tests/e2e/`, and a branch the cloud agent pushes) · **Sprint:** 7 · **Needs:** tasks 01–07 merged, `anakata-ui v0.8.0` pushed, the README's "Before task 01" done (the cloud agent starts; Sprints 5–6 runs attached).

## Goal
Ten document and email scenarios, the earlier files the document seed changes, and a run — on the cloud machine, with screens read after `reset.sh` and emails read from Mailpit.

## Execution — cloud machine only
Same rules as Sprint 6 task 10: the whole task runs on the cloud machine; if the agent cannot start or `up.sh` fails, write ENV and stop; never guess values and never fall back to a local run unless the user asks. The agent works on its own branch (e.g. `e2e/sprint-07`), commits with explicit paths, pushes that branch, never merges into `dev`.

## Read first
- `tests/e2e/README.md`, `_TEMPLATE.md`, `INDEX.md`, `fixtures/reference-values.md`, `bin/mail-latest.sh`
- The Sprint 5 close-out and Sprint 6 run reports (the current screen truth)
- This sprint's REPORT tasks 01–07

## Do
1. **Mail helpers.** Extend `bin/mail-latest.sh` (or add `bin/mail-find.sh`) to find a message by recipient and subject, print its subject, recipients and attachment names, and save an attachment's sha256 — read-only against Mailpit's API. Scenarios compare an emailed PDF's sha256 with the stored document's (through `bin/db-check.sh`), so "the email carries the issued file" is checked, not assumed.
2. **Fixtures.** Extend `reference-values.md`: the seeded issued documents per booking (kind, version, number), the planned statuses on a fresh seed with their dates, the issuer block, the `[TBD]` bank values, and the registry counts after tasks 01 and 04's shape changes. Anything not read off a screen or Mailpit in this task stays `⚠ UNVERIFIED — <source>`; report the count split into leftovers and new.
3. **Revisit earlier files the seed changes:** BR-01/02 (registry counts), the Payments tab scenarios (receipts now appear; the "sending by email arrives in Sprint 7" copy is gone), the Extras scenarios (the re-issue notice now shows). Re-read the rest against a fresh seed.
4. **Ten new scenarios**, `scenarios/documents/`, tags `sprint-7`:

   | ID | P | Users | Script (intent; the screen and Mailpit win) |
   |---|---|---|---|
   | DOC-01 | P1 | Carolina | Documents tab on a seeded CONFIRMED booking: each row's trigger, date and status |
   | DOC-02 | P1 | Carolina | Preview the invoice: the three subtotals and total match Overview; the "Print / save PDF" and (for an issued version) "Download PDF" controls are present. The browser's print dialog itself is not automated — record that |
   | DOC-03 | P1 | Carolina then cfo@ | cfo@ marks the seeded wire received → the booking confirms → invoice, summary and receipt arrive in Mailpit, once each; the invoice attachment's sha256 matches the stored document |
   | DOC-04 | P1 | Carolina | Pay a deposit link through the Stripe replay twice → one receipt email, not two |
   | DOC-05 | P1 | Carolina | Add an extra to a confirmed booking → invoice v2 with the reason, emailed; v1 still opens unchanged |
   | DOC-06 | P2 | Carolina | Remove the client's email → rows BLOCKED with the reason; restore it in Billing → resend arrives |
   | DOC-07 | P2 | Carolina | Send a payment link by email; the message carries the link and the amount |
   | DOC-08 | P2 | Carolina | Wire instructions: the LEG-004 warning is shown; the email arrives with the wire-instructions PDF attached, and the document marks the bank details as placeholders |
   | DOC-09 | P2 | Carolina | Documents & Manifests: filter by status and kind; a row opens the booking on Documents |
   | DOC-10 | P2 | Carolina | Reminders: with the documents-due command run against a booking whose due date is 21 days out (a local/testing fixture command, the `inventory:expire-hold` precedent — add it if tasks 01–04 did not), one reminder arrives; running it again sends nothing |

5. **INDEX.md.** Add the rows; the P1 set grows by DOC-01 to DOC-05.
6. **The runs.** `up.sh` to ALL UP, `reset.sh` before every scenario that reads screen facts, Mailpit cleared at the start of each scenario that reads mail:
   - Sprint 7 P1 plus the revisited files → `runs/YYYY-MM-DD-HHMM-sprint7-p1.md`;
   - the full P1 set, Sprints 1–7 → a second run file.
   - Each separates **(i)** scenario or fixture fixes from **(ii)** application bugs. Only (ii) goes back to code. Never loosen an expectation.
7. **Sprint 7 summary** in the REPORT, the same shape as before: what is done; open questions compiled from each task (do not write "none"); what is still open outside the sprint; merge steps for the user.

## Don't
- Don't change application code to make a scenario pass.
- Don't write passport numbers or other sensitive guest data into a scenario, fixture, run report or caption.
- Don't send email anywhere but Mailpit; the e2e stack has no Graph keys and must not get any.

## Report
Append **Task 08**: the scenarios, the mail helper, the revisited files, both run reports, the split marker counts, the open questions and the merge steps. The agent pushes its branch; the user merges.
