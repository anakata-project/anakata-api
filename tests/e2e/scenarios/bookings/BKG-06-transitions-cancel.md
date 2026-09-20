# BKG-06 · Legal transitions and cancellation
- **Tags:** sprint-4, bookings
- **Priority:** P1
- **Users:** Carolina
- **Start:** reset

## Why
A CONFIRMED booking may only move to FULLY_PAID or CANCELLED. Cancel needs a reason, frees the cabin, and writes the reason into History.

## Steps
1. Sign in as Carolina. Open `http://localhost:3001/rms/reservations/bookings`. Open ANK-2026-0003 (Harrison & Whitfield). Overview tab.
2. Read **Status transitions (only legal moves shown)** and Delete.
3. Click `→ CANCELLED`. In the modal titled `Status CONFIRMED → CANCELLED`, leave Reason empty — `Record` stays disabled. Type `E2E cancel 0003`. `Record`.
4. Open History. Open Calendar Year 2027, ANAMARA Suite 01 on `7 Nov 2027`.

## Expected
- [ ] E1 · Legal buttons only: `→ FULLY PAID` and `→ CANCELLED`. `Delete (admin only)` is enabled (Carolina). No `→ ON BOARD` / `→ COMPLETED` / `→ RELEASED`. ⚠ UNVERIFIED — i18n `bookings.transitionTo` + `statusLabel`; table from `Transitions`.
- [ ] E2 · Reason modal title `Status CONFIRMED → CANCELLED`. Label `Reason (required)`. ⚠ UNVERIFIED — `reasonModalTitle()` / i18n; task 07 browser.
- [ ] E3 · Toast `Status updated` (or the panel closes/refreshes to CANCELLED). ⚠ UNVERIFIED — i18n `bookings.transitionedToast`.
- [ ] E4 · History includes the reason `E2E cancel 0003` and a status-changed sentence `REQUESTED`/`CONFIRMED` → `CANCELLED` (seeded create line stays `Reservation created in RMS — Suite 01 · 2 AD · seeded`). ⚠ UNVERIFIED — History rendering of `booking.status_changed`.
- [ ] E5 · Calendar Year 2027: ANAMARA Suite 01 on 7 Nov is `·` (Available).

## Notes
Do not confirm cancel against a group row. ANK-2026-0003 is a single-cabin CONFIRMED booking.
