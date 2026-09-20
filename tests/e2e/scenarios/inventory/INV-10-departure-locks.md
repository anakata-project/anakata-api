# INV-10 · Departure locks with a block
- **Tags:** sprint-3, inventory
- **Priority:** P2
- **Users:** Carolina
- **Start:** reset

## Why
Blocks do not lock date or yacht, but any claim row — including a released one — keeps Delete off so the FK cannot 500.

## Steps
1. Sign in as Carolina. Open `http://localhost:3001/rms/booking-engine/departures`. Open DEP-003 (`14 Nov 2027` · ANAMARA).
2. Read **Embark date**, **Yacht**, and Delete.
3. Open `/rms/operations/blocks`. Release `BLK-001` (confirm `Release BLK-001`).
4. Return to Departures and open DEP-003 again.

## Expected
- [ ] E1 · Before release: date and yacht are enabled (no lock notice). Delete is disabled and reads `Delete (2 blocked)`.
- [ ] E2 · After release: date and yacht stay enabled. Delete is still disabled and reads `Delete (This departure has inventory history (released blocks or holds). Close or hide it instead.)`.

## Notes
Task 10’s “Delete is enabled after release” is wrong on the screen. Task 03 keeps `locks.delete` true for every claim row, released included (`DepartureLocks::HISTORY_DELETE`). Write the screen, not the task file.
