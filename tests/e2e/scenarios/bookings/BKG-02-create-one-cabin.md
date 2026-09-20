# BKG-02 · Create a one-cabin reservation
- **Tags:** sprint-4, bookings
- **Priority:** P1
- **Users:** Carolina
- **Start:** reset

## Why
A Suite · 2 adults quote must match the rates price-check total, freeze at sale, and occupy the calendar cell.

## Steps
1. Run `tests/e2e/bin/reset.sh`. Sign in as Carolina. Open `http://localhost:3001/rms/reservations/calendar`. Date range **Year 2027**. Confirm ANATIVA Suite 01 on `7 Nov 2027` shows `·` (Available). If it does not, **stop** — the reset did not apply.
2. Open `/rms/reservations/bookings`. Click `＋ New reservation` (toolbar) or `＋ New Reservation` (header).
3. Type **CABIN (FIT / Group)**. Guest `E2E One Cabin`, email `e2e.bkg02@anakata.test`, phone `+1 555 0202`, preferred **EMAIL**. Main channel **D2C**, origin **Hotel Booking Engine**.
4. Departure `7 Nov 2027` · ANATIVA. Adults `2`, children `0`. Cabin **Suite 01**.
5. Read the price box. Click `Create reservation`.
6. When the booking panel opens, note the reference. Open `/rms/reservations/calendar`, Year 2027, ANATIVA Suite 01 on 7 Nov.

## Expected
- [ ] E1 · After step 1 the ANATIVA Suite 01 / 7 Nov cell is `·`. Do not continue if it is not.
- [ ] E2 · Price box header `Price · from Rates & Promotions · availability checked live`. Total `USD 26,600`. Deposit line `Deposit 10% · USD 2,660 · balance at T−120`. ⚠ UNVERIFIED — i18n + `depositLineText` / `useMoney`; amounts from the fixture (Pest).
- [ ] E3 · Toast `Reservation ANK-2026-0020 created.` Panel opens on ANK-2026-0020, status `PENDING PAYMENT`, owner Carolina. ⚠ UNVERIFIED — toast from `createdToast()`; next ref from Pest.
- [ ] E4 · Calendar Year 2027: ANATIVA Suite 01 on 7 Nov shows `0020` (`PEND` if the cell uses the pending label). ⚠ UNVERIFIED — task 10 uses `PEND` for PENDING_PAYMENT, last-4 for CONFIRMED.

## Notes
Target cabin is **7 Nov 2027 ANATIVA Suite 01** (DEP-002) so this scenario does not collide with ANAMARA seed claims. Amounts: `fixtures/reference-values.md` (Suite · 2 adults).
