# Task 09 · anakata-api · E2E scenarios, batch B20

**Repo:** anakata-api · **Sprint:** 15 · **Needs:** Tasks 01–08 done, REPORT sections exist. Follows the exact discipline Sprint 14 Task 10 established: write scenario files, helpers, and fixture rows; do not run `bin/batch.sh` or `ledger.sh`; do not send mail to any address outside `@anakata.test`; no application code changes if a scenario would fail.

## Repo check to do first

Same standard Sprint 14 Task 10 set: every count or behavior claim in the fixtures and scenarios below needs a source citation (a seeder line, a test assertion, an actual class signature) — not a restated assumption. In particular:

- The exact current HEAD commit of all four repos (and `anakata-portal`, not checked anywhere else in this sprint) at the time this task is actually written.
- Whether Task 01's inbound-capture job needs a fixture email (an actual message injected into a fake mailbox/fixture) rather than a live inbound send — almost certainly yes, given e2e scenarios never send real mail. Design a `setup.sh` helper (`inject-inbound-email <contact-email> <subject> <body>`) that writes directly to wherever Task 01's capture job reads from, bypassing the real Graph/poll mechanism, the same way Sprint 14's `journey-due` helper bypassed the real 15-minute scheduler tick rather than waiting for it.
- The actual seed state for B2B partners after Task 02/06 — which seeded agency (if any) is the no-contact case Task 06 needs to demonstrate, confirmed against the seeder rather than assumed to already exist.

## Helpers in `tests/e2e/bin/setup.sh`

Add to the existing three from Sprint 14:

- `inject-inbound-email <email> <subject> <body>` — per the repo-check above; must refuse any `from` address not ending `@anakata.test` reversed (i.e. this simulates mail *arriving* from a guest, so the constraint is the guest's own address stays within the test domain, not the mailbox being written to).
- `portal-pay <request-or-booking-reference> <kind>` — calls `CreatePortalPaymentLink` directly as a seeded `AgencyUser`, printing the created link and its `status`, so scenarios can exercise Task 03 without a live portal browser session every time.

## Fixtures in `tests/e2e/fixtures/reference-values.md`

New section **Sprint 15**. Source every number from a seeder or test file, marked `⚠ UNVERIFIED` where it can't be, exactly as Sprint 14 Task 10 did — do not copy a screen, do not invent a plausible-looking count.

## New scenarios

Tag `sprint-15`, same header shape as Sprint 14's CRM-tagged files (Why, Steps, Expected E1…, Cross-checks, Notes).

**Batch B20**

- `INBOX-01` — P1, Carolina. Inject an inbound email from a seeded contact's address. It appears in the inbox list, unread, matched to that contact. Opening it marks it read and shows it on the contact's timeline as one `conversation.message` line.
- `INBOX-02` — P1, Carolina. Reply from the thread view. Mailpit receives it (local mail transport), addressed to the original sender, with correct `In-Reply-To` threading (assert via `db-check.sh` against the stored `message_id`, since Mailpit's UI won't show that header cleanly).
- `INBOX-03` — P1, Carolina. Inject an inbound email from an address matching no contact. It appears unlinked. Link it to a contact via search; it then shows that contact's name on the list and on their timeline.
- `B2B-04` — P1, Carolina. A seeded agency with a matched contact and an active `b2b_partner_activation` enrolment shows the correct step and next-due on the B2B Partners page. Cross-check against the same figures the Journeys enrolments drawer would show for that enrolment (Sprint 14) — they must agree, since both read the same underlying rows.
- `B2B-05` — P1, Carolina. The seeded no-contact agency (per the repo-check above; add one to the seeder if none exists, and say so in the REPORT) shows the explicit no-contact, no-enrolment state, not blank fields.
- `PORTAL-PAY-01` — P1, two contexts (portal agent + Carolina). An agent creates a deposit payment link from the portal for their own agency's request. Pay it (reuse `replay-stripe-checkout.sh`, per Sprint 14's pattern, on that link). The RMS booking drawer shows the payment settled with portal attribution. The request's status updates exactly as it would for a staff-created link.
- `PORTAL-PAY-02` — P1, portal agent. Attempting to create a payment link for a booking belonging to a different seeded agency is refused (403 at the API; the portal UI does not even offer the action, per Task 07 — assert both).
- `LOCALE-01` — P2, Carolina. Switch the panel to Spanish. The CRM, RMS, and both new Sprint 15 pages render without leftover English strings (spot-check, not exhaustive — this is a sampling scenario, not a full-key walk). Switch back; English is unaffected. Reload; the Spanish choice persisted.

## INDEX and report

Add these 8 rows to `tests/e2e/scenarios/INDEX.md`, batch **B20**. `LOCALE-01` stays P2; the rest are P1.

Append **Task 09** to `docs/sprints/sprint-15/REPORT.md`: helpers, fixtures, scenario files, that B20 was not run, and a **Sprint 15 summary** — what's done, every open question from Tasks 01–08 in one list (the webhook-vs-poll decision, the missing production-KPI definition, whatever the Task 07 repo-check turned up about the actual `anakata-portal` structure, the date/number-formatting decision from Task 08), what's still unbuilt after this sprint (go-live readiness remains completely unplanned — this sprint doesn't touch it), and the full merge command list per repo, in order. List git commands; do not run them.

## Stop

Same as every prior sprint's e2e task: no application edit if a scenario would fail, no browser walk substituting for the actual e2e run, no `bin/batch.sh`, no real recipient.
