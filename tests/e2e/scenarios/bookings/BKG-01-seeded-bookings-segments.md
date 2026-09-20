# BKG-01 · Seeded bookings and segments
- **Tags:** sprint-4, bookings
- **Priority:** P1
- **Users:** Carolina
- **Start:** reset

## Why
The list, segment chips and Groups panel must show the seed. A missed filter or a hidden REQUESTED row makes every later booking scenario look empty.

## Steps
1. Sign in as `carolina@anakata.test` / `password`. Open `http://localhost:3001/rms/reservations/bookings`. Date range stays **All dates**.
2. Read the table and the date-range count. Click chips **D2C — direct**, then **B2B — travel trade**, then **Charter**, then **All**.
3. Scroll to the **Groups — multi-cabin reservations (OPS-008)** panel. Open GRP-007.

## Expected
- [ ] E1 · Date-range line `13 bookings · all dates`. Table includes ANK-2026-0003 … 0019 and ANK-R-2026-0041 / 0042. ⚠ UNVERIFIED — index has no default status filter (task 07 browser said 11).
- [ ] E2 · There is no Groups chip. Chips are `All` · `D2C — direct` · `B2B — travel trade` · `Charter`. ⚠ UNVERIFIED — i18n `bookings.segment*`.
- [ ] E3 · **D2C — direct** hides ANK-2026-0007 and ANK-2026-0012. **B2B — travel trade** shows only ANK-2026-0007 (M. Castellanos). **Charter** shows only ANK-2026-0012 (Vandermeer Charter).
- [ ] E4 · Status pills use spaces: `PENDING PAYMENT` (0014), `FULLY PAID` (0005), `REQUESTED` (0041 / 0042), `CONFIRMED` (the rest). ⚠ UNVERIFIED — `statusLabel()`.
- [ ] E5 · Groups panel has GRP-007 `Alvear family & friends` with a coordinator line for Lorena Alvear (or `coordinator Lorena Alvear`). Three cabins. ⚠ UNVERIFIED — i18n `bookings.groupCoordinator` / seed coord name.

## Notes
Values: `fixtures/reference-values.md` (Seeded bookings). Segment from `Booking::segment` + `ChannelSeedMap`. GRP-007 members are INBOUND → D2C.
