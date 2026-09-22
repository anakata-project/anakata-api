# Task 10 · anakata-panel · Guest Experience
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 11 · **Needs:** task 09.

## Goal
The hotel manager and operations team prepare each voyage and follow up after it from one screen.

## Read first
- This sprint's REPORT tasks 04 and 05
- `prototype/rms_index.html`: `v-gx`, `renderGX`, `prefForm`, `hmBriefHtml`, `npsPanel`, `npsForm`; screenshot `05-guest-experience.png`
- The placeholder at `/rms/operations/guest-experience`; the booking panel's Overview (where a staff NPS response is recorded)

## Do
1. **Page** `app/pages/rms/operations/guest-experience.vue` (replaces the placeholder).
   - Notice from i18n, following the prototype: medical and accessibility details are visible to the operations team only.
   - A departure picker listing departures with passengers ("date · yacht · n guests") from the API; default the next upcoming one.
   - KPIs from the API: guests on board (bookings), questionnaires answered / total (sent or scheduled date), celebrations, accessibility / medical (restricted wording without `guests.view_sensitive`).
2. **Preferences by guest.** The API's rows: guest with booking and email or "no email — sent to lead guest", cabin, status pill (ANSWERED with date and source / SENT — NO REPLY / SCHEDULED date), dietary, celebration, activity, and View or Record.
   - The modal renders the API's questions by type.
   - Restricted questions show "Restricted — operations team only" without `guests.view_sensitive`.
   - Save needs `guest_experience.manage`, with the API's validation. The version history is shown under the form.
3. **Hotel-manager brief.** "Hotel manager brief — print" opens the API's HTML in a print view. "Download PDF" uses `?format=pdf`. Nothing is stored.
4. **Post-trip survey (NPS).**
   - The NPS panel from `GET /api/rms/guest-experience/nps`: KPIs, and the responses table with score pills (below the alert threshold coral, at or above the review threshold green, from the API's values).
   - The empty state shows the first expected survey date and the rules sentence built from the API's values.
5. **Booking panel.** On a COMPLETED booking's Overview, "Record post-trip survey" (`guest_experience.manage`) opens the form: guest, call notes, the six answers from the API. It shows the API's result (alert raised, review request sent or not and why).
6. **Helpers, tested:** `prefStatusClass`, `npsScoreClass(score, alertBelow, reviewFrom)`.

## Don't
- Don't hard-code questions, thresholds or dates.
- Don't show restricted values without `guests.view_sensitive`.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.12.0`.
- Browser, both themes, after `reset.sh`:
  - the picker and KPIs;
  - record answers as Mateo, including accessibility, and confirm it is restricted for Lucía;
  - answer from the engine link (task 08) and confirm the row shows ANSWERED from the guest link;
  - print the brief as Carolina and as Lucía (no accessibility section);
  - complete a voyage (the setup helper or the voyage-status job on the right date) and record a 6 → alert and task;
  - record a 9 for a guest without marketing consent → "review request not sent";
  - the CRM contact shows the latest score.

## Report
Append **Task 10**: the page sections, the booking-panel form, permissions, helpers, and the browser pass. Git commands listed, not run.
