# Sprint 15 · The shared inbox, B2B partners, portal payments, and Spanish

**Goal:** close the four product gaps left after Sprint 14. Two `sprint: 'later'` items in `anakata-panel/app/navigation/crm.ts` — `inbox` and `b2b-partners` — get real pages. Agents can pay through the portal instead of waiting for a staff-sent link. Internal screens (panel only — guest-facing engine and portal customer copy stay English, per the 12 Sep 2026 decision) can be read in Spanish.

This sprint does **not** cover go-live readiness (infrastructure, backups, monitoring, data migration, runbooks). That is a separate sprint with no engineering overlap with this one and should not be merged into it.

## Repo check this README relies on

Confirmed directly against `anakata-api`, `anakata-panel`, `anakata-ui`, and `anakata-engine` on `dev` (commits current as of 23 Sep 2026):

- `anakata-panel/app/navigation/crm.ts` — exactly two items are `sprint: 'later'`: `inbox` (`/crm/sales/inbox`) and `b2b-partners` (`/crm/sales/b2b-partners`). Every other CRM nav item carries a real sprint number.
- No sprint folder in `anakata-api/docs/sprints/` builds either page. Sprint 11's "B2B & Agent Portal" is the **RMS**-side agency/commission view (`/rms/...`), a different page from the CRM-side relationship view this sprint builds.
- No inbound-mail infrastructure exists anywhere in `anakata-api`. Sprint 7 built outbound only: a Graph mailer (production) / SMTP to Mailpit (local) from a single configured sending mailbox, `Reply-To` set to the legal-entity issuer email. There is no webhook, no polling job, and no `conversations`/`messages` table.
- `anakata-api/app/Actions/Payments/CreatePaymentLink.php` requires a staff `User $actor`. The portal has no such actor — `routes/api/portal.php` runs behind `portal.auth`, a separate guard for `AgencyUser`, not `User`. A portal-initiated payment link needs its own action, not a call-through.
- `anakata-panel/i18n/locales/` has one file, `en.json`. No `es.json`, no locale switcher, no persisted locale preference anywhere in the panel.
- `anakata-portal` was **not** available to check against for this README — Task 07 below is written from the API contract only and needs its own repo-check against that repo before it is treated as final.

## Don't

- Don't build WhatsApp. It stays TEC-005 (no provider chosen). The inbox is email-only this sprint, same pattern as every other channel field in the codebase (`channel: EMAIL; WhatsApp is TEC-005`).
- Don't translate the booking engine or the portal's guest/agent-facing copy. English-only for guests and agents is a confirmed decision (12 Sep 2026). Spanish is panel-internal only.
- Don't let a portal payment change any pricing, deposit, or balance rule. It is the same payment link and the same Stripe flow staff already trigger — only who can start it changes.
- Don't invent a production target metric for B2B Partners beyond what the RMS agency ledger already computes (revenue, commission accrued). If the prototype's "2 producing partners" style KPI has no real source, say so in the REPORT rather than typing a number.

## Tasks, in order

| # | Repo | Task |
|---|---|---|
| 1 | anakata-api | Inbound email capture, conversations schema, reply sending |
| 2 | anakata-api | CRM B2B Partners: agency-linked contacts, deals, journey status |
| 3 | anakata-api | Portal-initiated payment links |
| 4 | anakata-ui | Regenerate types, release |
| 5 | anakata-panel | Inbox page |
| 6 | anakata-panel | B2B Partners page |
| 7 | anakata-portal | Pay now on a request or booking |
| 8 | anakata-panel | Spanish locale |
| 9 | anakata-api / anakata-panel | E2E scenarios |

Each task appends its section to `docs/sprints/sprint-15/REPORT.md` in `anakata-api`, in order, the same convention every prior sprint follows.

## E2E scenarios this sprint adds (task 09)

`INBOX-01`, `INBOX-02`, `INBOX-03`, `B2B-04`, `B2B-05`, `PORTAL-PAY-01`, `PORTAL-PAY-02`, `LOCALE-01`, batch B20. P1 is every id except `LOCALE-01`. B20 was not run.
