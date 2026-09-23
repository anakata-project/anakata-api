# Task 09 · anakata-panel · Portal access on the agency drawer
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 13 · **Needs:** task 05 (`v0.14.0`).

## Goal
The team runs the portal from the agency drawer: who has access, who has used it, and the switch to stop it (P5, P6, P8).

## Read first
- This sprint's REPORT tasks 01 and 04
- `AgencyDrawer.vue` as Sprint 11 task 11 left it (users, preview, commissions), the existing upload patterns, `documentFetch.ts`

## Do
1. **Portal users.** The existing list gains the states from task 01: Invited (with the date the invitation was sent and when it expires), Active (with the last sign-in), Disabled. Actions with `agencies.manage`: Invite (or Re-send invitation, when one is outstanding), Disable, Enable. The line about invitations being sent at portal launch is replaced by what the API now does.
2. **Portal access.** A Suspend control (`agencies.manage`, reason required, the ReasonModal pattern) and Resume, with the state and reason shown, including who and when. The drawer explains in one line that suspending stops sign-in and ends live sessions, and changes nothing about bookings or commissions.
3. **Sales materials.** A section listing the materials for this agency plus the shared ones, with version, size and published state; upload (title, kind, file) and publish or unpublish, with the API's validation messages for type and size. Downloads reuse `documentFetch.ts`.
4. **Portal activity.** A paginated list from `GET …/portal-activity`: when, the agency user, and what (signed in, failed sign-in, request created, material downloaded), with a booking reference linking to the booking where there is one.
5. **Booking Requests.** Portal-sourced requests show their source and the agency, as task 03's payload names it.

## Don't
- Don't compute a state, an expiry or a next step in the panel.
- Don't show a portal password or a token anywhere.
- Don't offer an action the API's payload did not allow.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.14.0`.
- Browser, both themes, after `reset.sh`: invite a user (Mailpit), see Invited with its expiry, then Active after acceptance; disable and enable; suspend an agency and confirm the portal refuses sign-in and an open session ends, then resume; upload a material, publish it, and see it in the portal; the activity list shows a sign-in, a request and a download.

## Report
Append **Task 09**: the drawer sections, suspension, materials, the activity list, the Booking Requests source, and the browser pass. Git commands listed, not run.
