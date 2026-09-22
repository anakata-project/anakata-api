# Task 11 · anakata-panel · B2B & Agent Portal
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 11 · **Needs:** task 10.

## Goal
The B2B page covers the whole RMS side of the agent relationship: users, what the agent will see, and commissions through to payment.

## Read first
- This sprint's REPORT task 06
- `prototype/rms_index.html`: `renderB2B`, `openAgency`; screenshot `06-agent-portal.png`
- `app/pages/rms/commercial/b2b.vue` and its helpers (registrations, partners, KPIs already built), the Payments & Revenue commissions list

## Do
1. **KPIs.** Add commission payable and commission paid from `meta.kpis`. Commission accrued now excludes paid, as the API sends it.
2. **Agency drawer.** Extend the existing drawer:
   - **Portal users:** name, email and status with the API's labels (Invite on portal launch / Invite on approval / Active / Disabled). Add user and Disable / Enable with `agencies.manage`. A line says invitations are sent when the agent portal launches.
   - **Portal preview:** from `GET …/portal-preview`, in the prototype's layout:
     - the NET RATES table (years × Suite / Owner's Suite / Charter) with the heading "public prices never shown";
     - MY BOOKINGS with net due;
     - MY COMMISSIONS with payable date and status pill;
     - SALES MATERIALS.
     Read-only.
   - **Commissions:** the agency's bookings with rate, amount, payable date, status (BLOCKED / EARNED ON COMPLETION / PAYABLE / PAID / CANCELLED), and **Record payout** on PAYABLE rows for `commissions.record_payout`. The payout modal shows the amount (fixed), date paid, bank reference, and the API's refusal sentence. PAID rows show the payout date and reference.
3. **Payments & Revenue.** The commissions list uses the new status labels and shows PAID. No other change.
4. **The prototype note** under the partners table, in i18n: agents see net rates only; the client of record is always the end guest; every agent-portal action is logged.
5. **Helpers, tested:** `commissionStatusClass` (all five), `agencyUserStatusLabel`.

## Don't
- Don't compute net rates, amounts, payable dates or statuses in the panel.
- Don't show a public price in the preview.
- Don't allow editing an amount in the payout modal.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.12.0`.
- Browser, both themes, after `reset.sh`:
  - an approved agency's drawer (users, preview, commissions);
  - add and disable a user;
  - the preview's net rate equals the published rate minus the agency's commission (check one by hand against Rates);
  - a completed trade booking past its payable date (setup helper) shows PAYABLE, record a payout → PAID and the KPIs move;
  - payout refused as Mateo;
  - the payable date is 30 days after the return date.

## Report
Append **Task 11**: KPIs, the drawer sections, payouts, the Payments & Revenue labels, helpers, and the browser pass. Git commands listed, not run.
