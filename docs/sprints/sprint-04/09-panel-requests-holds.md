# Task 09 · anakata-panel · Booking Requests and Holds & Waitlist
**Repo:** anakata-panel · **Sprint:** 4 (read `../anakata-api/docs/sprints/sprint-04/README.md` first)
**Needs:** task 07 (the booking panel).

## Goal
Two queues from the prototype:
- **Booking Requests** (`/rms/reservations/booking-requests`): the incoming requests with their hold expiry and contact SLA; Confirm and Release.
- **Holds & Waitlist** (`/rms/operations/holds`): the active holds, and the waitlist with add, mark notified and remove.

## Read first
- `prototype/rms_index.html`:
  - `v-req`: the notice, verbatim, including "No request is ever auto-cancelled without team review; expiries release the hold and notify the owner.", and the table header
  - `renderReq`: the row, "VIA WHATSAPP", the "TRAVEL ADVISOR" pill, `.slat.ok` / `.slat.bad`, the two buttons, the empty messages "No open requests — the queue is clear." / "No requests in this date range."
  - `confirmReq` / `releaseReq`: texts
  - `v-hold`: the two panels "Active holds" and "Waitlist", their columns, the hold type pills
  - the request badge on the navigation (`reqbadge`)
- `../anakata-api/docs/sprints/sprint-04/REPORT.md`, task 05: the queue, holds and waitlist shapes, `hold.expired`, `sla.breached`, `cabin_available`, the "Mark notified" deviation

## Do
1. **Booking Requests page:**
   - the notice, verbatim, with its numbers from the list's `meta.rules` (task 05), never hard-coded
   - the date-range filter; the panel "Incoming requests"; the table as `renderReq`
   - **Hold expires:**
     - "46 business hours" when under 72 business hours remain, else "4 business days" (from `remaining_business_minutes` and the business-day length). Put the formatting rule in a pure helper and test it.
     - when `hold.expired`: coral mono "HOLD EXPIRED — CABIN NOT HELD"
   - **Contact SLA:** `.slat.ok` "19h", or `.slat.bad` "SLA BREACH — 26h", from the API minutes. Refresh the countdowns every minute without refetching; refetch every 5 minutes.
   - **Buttons** (with `can_act`):
     - "Confirm · send deposit link": a confirm modal with the prototype's text adapted ("The deposit link is sent when payments arrive (Sprint 5)."). POST; toast; refresh. A 409 (cabin taken after expiry) shows the API text.
     - "Release": a reason modal (required) → POST; toast "Hold released, cabin returned to inventory."; refresh.
   - Row → booking panel.
   - **Navigation badge:** the count of open requests next to "Booking Requests" in the sidebar, refreshed every 5 minutes and after actions. Add the badge in the navigation shell (prototype `reqbadge` style).
2. **Holds & Waitlist page** (`/rms/operations/holds`, replacing the placeholder):
   - **"Active holds"** (`GET /api/rms/holds`), columns Type · Client / Agent · Departure · Cabin · Expires · Rule, as in the prototype.
     - Type pill: "REQUEST" now; WEB "WEB 20-MIN" and AGENCY later through the same mapping, with the minute values from data.
     - Expires: live business-time remaining (same helper).
     - Row → the holder's booking panel.
   - **"Waitlist"** (`GET /api/rms/waitlist`), columns Contact · Departure · Cabin type · Position · Since, plus actions.
     - When `cabin_available`, a `--ok` mono "CABIN FREE — NOTIFY".
     - Actions: **"Mark notified"** (a modal with the channel select; note the deviation from "Notify now"), and **Remove** (reason modal).
     - Notified rows show "Notified {date} via {channel} by {name}" in mono `--iv38`.
     - "＋ Add to waitlist" (`bookings.create`): a modal with departure (full ones first, then the rest), cabin type (Suite / Owner's Suite), client (the same contact fields and suggestions as task 08), adults/children, notes. A 422 for a disabled waitlist shows the API text.
   - the date-range filter over both panels
3. **Booking panel:** its request section (task 07) uses the same helpers and modals as this page (one implementation), and shows "Hold expired" when applicable.
4. **Tests:**
   - the hold-remaining formatter (hours vs days, expired)
   - the SLA formatter (ok / breach)
   - the countdown tick (a pure function of `now`)
   - the hold-type pill mapping
   - the waitlist row status helper (notified / cabin free / waiting)
5. **Browser check:**
   - After `reset.sh`: two requests, one in SLA (green), one breached (coral). Both holds show business days; the badge shows 2.
   - Confirm the in-SLA one → PENDING_PAYMENT, gone from the queue, the badge shows 1.
   - Release the other with a reason → it appears in "Deleted & released"; the cabin is free in the Calendar.
   - Expire a hold with `docker compose exec app sh -c "php artisan inventory:expire-hold ANK-R-2026-0041"` (task 05). The row shows "HOLD EXPIRED — CABIN NOT HELD", the cabin is free in the Calendar, and Confirm re-claims it.
   - Waitlist: the two seeded entries on 19 Dec; add one; mark notified; remove one → the positions update.
   - As Lucía: she can act on her own requests only.

## Acceptance criteria
- [ ] The browser checks pass; both pages match the prototype in both themes (apart from "Mark notified").
- [ ] Lint, typecheck, test and build pass on a fresh clone.
- [ ] A "Task 09" section in the API's `sprint-04/REPORT.md` covering:
  - where the notice numbers come from
  - the countdown refresh rules
  - the navigation badge
  - the deviations
